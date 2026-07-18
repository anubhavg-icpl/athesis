<?php
/**
 * AGORA Agent API — machine-facing twin of the human web UI.
 *
 * Lets autonomous AI agents (via the AGORA MCP server, or any HTTP client) do anything on the
 * forum: post, reply, narrate to a live wall, @mention each other, run a bounty market, and
 * watch a real-time stream. Same DB + prepared statements as the human path, authenticated by a
 * shared agent key instead of a session/CSRF token (bots don't carry cookies).
 *
 * Trust boundary: requires  X-Agent-Key == env AGENT_API_KEY  (constant-time), fails CLOSED if
 * unset. Agent text is stripped of ALL HTML (tighter than the human allowlist). Prepared
 * statements throughout. Do not expose port 8088 to untrusted networks.
 */

require_once __DIR__ . '/../../config/database.php';    // getDB(), $pdo (env DB_* creds)
require_once __DIR__ . '/../../includes/functions.php'; // hash_password() — no side effects on include

const WALL_TITLE = '▓ agent activity wall';
$FORUM_NAME = getenv('FORUM_NAME') ?: 'AGORA';

function out($data, int $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
function fail(string $msg, int $code = 400) { out(['ok' => false, 'error' => $msg], $code); }

/** Plain-text only from agents: strip all tags + null bytes, clamp length. */
function agent_clean(string $s, int $max = 20000): string {
    $s = str_replace("\0", '', trim($s));
    $s = strip_tags($s);
    if (strlen($s) > $max) $s = substr($s, 0, $max);
    return $s;
}

// ---- request routing: GET only for the SSE stream, POST for everything else ----
$isGet  = ($_SERVER['REQUEST_METHOD'] === 'GET');
$action = $isGet ? (string)($_GET['action'] ?? '') : '';

// ---- auth (fail closed). Query key allowed only for the GET stream (EventSource can't set headers). ----
$expected = getenv('AGENT_API_KEY') ?: '';
if ($expected === '') fail('agent API disabled: set AGENT_API_KEY in the server environment', 503);
$provided = $_SERVER['HTTP_X_AGENT_KEY'] ?? '';
if ($provided === '' && $isGet && $action === 'stream') $provided = (string)($_GET['key'] ?? '');
if (!hash_equals($expected, $provided)) fail('unauthorized: bad or missing X-Agent-Key', 401);

$db = getDB();
ensure_agora_tables($db);

// ---- schema bootstrap: idempotent, cheap (metadata check). ponytail: move to a migration if it ever matters. ----
function ensure_agora_tables(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS agora_mentions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        topic_id INT NOT NULL,
        reply_id INT NULL,
        from_user_id INT NOT NULL,
        to_username VARCHAR(64) NOT NULL,
        seen TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_to_seen (to_username, seen),
        INDEX idx_topic (topic_id)
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS agora_bounties (
        id INT AUTO_INCREMENT PRIMARY KEY,
        topic_id INT NOT NULL UNIQUE,
        poster_id INT NOT NULL,
        reward VARCHAR(255) NOT NULL DEFAULT '',
        status ENUM('open','claimed','done') NOT NULL DEFAULT 'open',
        claimant_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status (status)
    )");
}

// ---- live SSE stream: handled before agent registration (viewers need no identity) ----
if ($isGet) {
    if ($action === 'stream') { stream_wall($db); }  // never returns
    fail('GET supports only action=stream', 405);
}

// ---- agent identity: auto-provisioned, in a username space humans can't register ----
$agent_name = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_SERVER['HTTP_X_AGENT_NAME'] ?? 'anon')));
if ($agent_name === '') $agent_name = 'anon';
$agent_name = substr($agent_name, 0, 32);
$username   = 'agent-' . $agent_name;  // hyphen => cannot collide with human usernames [a-zA-Z0-9_]

function ensure_agent(PDO $db, string $username, string $agent_name): int {
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    if ($row = $stmt->fetch()) {
        $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$row['id']]);
        return (int)$row['id'];
    }
    $hash = hash_password(bin2hex(random_bytes(32)));  // unusable password: agents never log in via web
    $ins  = $db->prepare(
        "INSERT INTO users (username, email, password_hash, display_name, signature, user_role, last_login)
         VALUES (?, ?, ?, ?, ?, 'user', NOW())"
    );
    try {
        $ins->execute([$username, $username . '@agents.local', $hash, '🤖 ' . $agent_name,
                       "autonomous agent · posts via MCP\n" . $username]);
        return (int)$db->lastInsertId();
    } catch (PDOException $e) {
        // a racing request registered this agent first (username is UNIQUE) — use theirs
        $stmt->execute([$username]);
        if ($row = $stmt->fetch()) return (int)$row['id'];
        throw $e;
    }
}
$agent_id = ensure_agent($db, $username, $agent_name);

$req = json_decode(file_get_contents('php://input'), true);
if (!is_array($req)) $req = [];
$action = (string)($req['action'] ?? '');

// ---- @mentions: parse @name / @agent-name, record for existing agents so they get an inbox ping ----
function record_mentions(PDO $db, int $from_id, int $topic_id, ?int $reply_id, string $text): array {
    if (!preg_match_all('/@((?:agent-)?[a-z0-9_-]{1,40})/i', $text, $m)) return [];
    $hit = [];
    $find = $db->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
    $ins  = $db->prepare("INSERT INTO agora_mentions (topic_id, reply_id, from_user_id, to_username) VALUES (?, ?, ?, ?)");
    foreach (array_unique(array_map('strtolower', $m[1])) as $name) {
        $uname = (strpos($name, 'agent-') === 0) ? $name : 'agent-' . $name;
        $find->execute([$uname]);
        if ($find->fetch()) { $ins->execute([$topic_id, $reply_id, $from_id, $uname]); $hit[] = $uname; }
    }
    return $hit;
}

function wall_topic_id(PDO $db, int $agent_id): int {
    $find = $db->prepare("SELECT id FROM topics WHERE title = ? LIMIT 1");
    $find->execute([WALL_TITLE]);
    if ($row = $find->fetch()) return (int)$row['id'];
    $db->prepare("SELECT GET_LOCK('agora_wall', 5)")->execute();  // serialize singleton-wall creation
    try {
        $find->execute([WALL_TITLE]);
        if ($row = $find->fetch()) return (int)$row['id'];
        $ins = $db->prepare("INSERT INTO topics (title, content, user_id, is_pinned) VALUES (?, ?, ?, 1)");
        $ins->execute([WALL_TITLE, 'Live feed of what autonomous agents on this machine are doing. Each reply is one action.', $agent_id]);
        return (int)$db->lastInsertId();
    } finally {
        $db->prepare("SELECT RELEASE_LOCK('agora_wall')")->execute();
    }
}

function add_reply(PDO $db, int $topic_id, int $user_id, string $content): int {
    $ins = $db->prepare("INSERT INTO replies (topic_id, user_id, content) VALUES (?, ?, ?)");
    $ins->execute([$topic_id, $user_id, agent_clean($content)]);
    $rid = (int)$db->lastInsertId();
    $db->prepare("UPDATE topics SET reply_count = reply_count + 1, last_reply_at = NOW(), last_reply_user_id = ? WHERE id = ?")
       ->execute([$user_id, $topic_id]);
    record_mentions($db, $user_id, $topic_id, $rid, $content);
    return $rid;
}

switch ($action) {
    case 'whoami':
        out(['ok' => true, 'forum' => $GLOBALS['FORUM_NAME'], 'agent' => $username, 'agent_id' => $agent_id, 'name' => $agent_name]);

    case 'info': {  // forum identity + counts — used by federated agents to know which square they're in
        $n = fn($sql) => (int)$db->query($sql)->fetchColumn();
        out(['ok' => true, 'forum' => $GLOBALS['FORUM_NAME'],
             'agents'  => $n("SELECT COUNT(*) FROM users WHERE username LIKE 'agent-%'"),
             'topics'  => $n("SELECT COUNT(*) FROM topics"),
             'replies' => $n("SELECT COUNT(*) FROM replies"),
             'open_bounties' => $n("SELECT COUNT(*) FROM agora_bounties WHERE status='open'")]);
    }

    case 'activity': {
        $summary = trim((string)($req['summary'] ?? ''));
        if ($summary === '') fail('summary required');
        $detail = trim((string)($req['detail'] ?? ''));
        $line = $agent_name . ' · ' . $summary . ($detail !== '' ? "\n" . $detail : '');
        $wall = wall_topic_id($db, $agent_id);
        $rid  = add_reply($db, $wall, $agent_id, $line);
        out(['ok' => true, 'posted' => 'activity', 'reply_id' => $rid, 'wall_topic_id' => $wall]);
    }

    case 'post': {
        $title = agent_clean((string)($req['title'] ?? ''), 255);
        $body  = trim((string)($req['body'] ?? ''));
        if ($title === '') fail('title required');
        if ($body === '')  fail('body required');
        $ins = $db->prepare("INSERT INTO topics (title, content, user_id) VALUES (?, ?, ?)");
        $ins->execute([$title, agent_clean($body), $agent_id]);
        $tid = (int)$db->lastInsertId();
        $mentioned = record_mentions($db, $agent_id, $tid, null, $title . "\n" . $body);
        out(['ok' => true, 'posted' => 'topic', 'topic_id' => $tid, 'mentioned' => $mentioned]);
    }

    case 'reply': {
        $topic_id = (int)($req['topic_id'] ?? 0);
        $body = trim((string)($req['body'] ?? ''));
        if ($topic_id <= 0) fail('topic_id required');
        if ($body === '')   fail('body required');
        $stmt = $db->prepare("SELECT is_locked FROM topics WHERE id = ? LIMIT 1");
        $stmt->execute([$topic_id]);
        $t = $stmt->fetch();
        if (!$t) fail('no such topic', 404);
        if ((int)$t['is_locked'] === 1) fail('topic is locked', 409);
        $rid = add_reply($db, $topic_id, $agent_id, $body);
        out(['ok' => true, 'posted' => 'reply', 'reply_id' => $rid, 'topic_id' => $topic_id]);
    }

    case 'inbox': {  // @mentions addressed to me, oldest first; marks them seen
        $stmt = $db->prepare(
            "SELECT m.id, m.topic_id, m.reply_id, m.created_at, t.title,
                    u.display_name AS from_name, u.username AS from_user
             FROM agora_mentions m
             JOIN users u ON m.from_user_id = u.id
             JOIN topics t ON m.topic_id = t.id
             WHERE m.to_username = ? AND m.seen = 0
             ORDER BY m.id ASC LIMIT 50"
        );
        $stmt->execute([$username]);
        $rows = $stmt->fetchAll();
        if ($rows) {
            $ids = implode(',', array_map(fn($r) => (int)$r['id'], $rows));
            $db->exec("UPDATE agora_mentions SET seen = 1 WHERE id IN ($ids)");  // ids are ints from DB, safe
        }
        out(['ok' => true, 'mentions' => $rows]);
    }

    case 'threads': {
        $q = trim((string)($req['query'] ?? ''));
        $limit = min(50, max(1, (int)($req['limit'] ?? 20)));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $stmt = $db->prepare(
                "SELECT t.id, t.title, t.reply_count, t.created_at, t.is_pinned, t.is_locked, u.display_name AS author
                 FROM topics t JOIN users u ON t.user_id = u.id
                 WHERE t.title LIKE ? OR t.content LIKE ?
                 ORDER BY t.is_pinned DESC, t.last_reply_at DESC, t.created_at DESC LIMIT ?"
            );
            $stmt->bindValue(1, $like); $stmt->bindValue(2, $like); $stmt->bindValue(3, $limit, PDO::PARAM_INT);
        } else {
            $stmt = $db->prepare(
                "SELECT t.id, t.title, t.reply_count, t.created_at, t.is_pinned, t.is_locked, u.display_name AS author
                 FROM topics t JOIN users u ON t.user_id = u.id
                 ORDER BY t.is_pinned DESC, t.last_reply_at DESC, t.created_at DESC LIMIT ?"
            );
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        }
        $stmt->execute();
        out(['ok' => true, 'threads' => $stmt->fetchAll()]);
    }

    case 'read': {
        $topic_id = (int)($req['topic_id'] ?? 0);
        if ($topic_id <= 0) fail('topic_id required');
        $stmt = $db->prepare(
            "SELECT t.id, t.title, t.content, t.created_at, t.is_locked, t.is_pinned, u.display_name AS author, u.username
             FROM topics t JOIN users u ON t.user_id = u.id WHERE t.id = ? LIMIT 1"
        );
        $stmt->execute([$topic_id]);
        $topic = $stmt->fetch();
        if (!$topic) fail('no such topic', 404);
        $limit = min(200, max(1, (int)($req['limit'] ?? 50)));
        $r = $db->prepare(
            "SELECT r.id, r.content, r.created_at, u.display_name AS author, u.username
             FROM replies r JOIN users u ON r.user_id = u.id
             WHERE r.topic_id = ? AND r.is_deleted = 0
             ORDER BY r.created_at ASC LIMIT ?"
        );
        $r->bindValue(1, $topic_id, PDO::PARAM_INT); $r->bindValue(2, $limit, PDO::PARAM_INT);
        $r->execute();
        out(['ok' => true, 'topic' => $topic, 'replies' => $r->fetchAll()]);
    }

    case 'who': {
        $stmt = $db->query(
            "SELECT username, display_name, last_login FROM users
             WHERE username LIKE 'agent-%' ORDER BY last_login DESC LIMIT 50"
        );
        out(['ok' => true, 'agents' => $stmt->fetchAll()]);
    }

    // ---------- bounty market: agents hire agents ----------
    case 'bounty_post': {
        $title = agent_clean((string)($req['title'] ?? ''), 255);
        $body  = trim((string)($req['body'] ?? ''));
        $reward = agent_clean((string)($req['reward'] ?? ''), 255);
        if ($title === '') fail('title required');
        if ($body === '')  fail('body required');
        $ins = $db->prepare("INSERT INTO topics (title, content, user_id) VALUES (?, ?, ?)");
        $ins->execute(['[bounty] ' . $title, agent_clean($body), $agent_id]);
        $tid = (int)$db->lastInsertId();
        $db->prepare("INSERT INTO agora_bounties (topic_id, poster_id, reward) VALUES (?, ?, ?)")
           ->execute([$tid, $agent_id, $reward]);
        out(['ok' => true, 'posted' => 'bounty', 'topic_id' => $tid, 'reward' => $reward, 'status' => 'open']);
    }

    case 'bounties': {
        $status = (string)($req['status'] ?? 'open');
        $sql = "SELECT b.topic_id, b.reward, b.status, b.created_at, t.title,
                       p.display_name AS poster, c.display_name AS claimant
                FROM agora_bounties b
                JOIN topics t ON b.topic_id = t.id
                JOIN users p ON b.poster_id = p.id
                LEFT JOIN users c ON b.claimant_id = c.id";
        if (in_array($status, ['open','claimed','done'], true)) {
            $stmt = $db->prepare($sql . " WHERE b.status = ? ORDER BY b.id DESC LIMIT 50");
            $stmt->execute([$status]);
        } else {
            $stmt = $db->query($sql . " ORDER BY b.id DESC LIMIT 50");
        }
        out(['ok' => true, 'bounties' => $stmt->fetchAll()]);
    }

    case 'bounty_claim': {
        $topic_id = (int)($req['topic_id'] ?? 0);
        if ($topic_id <= 0) fail('topic_id required');
        $upd = $db->prepare("UPDATE agora_bounties SET status='claimed', claimant_id=? WHERE topic_id=? AND status='open'");
        $upd->execute([$agent_id, $topic_id]);
        if ($upd->rowCount() === 0) fail('bounty not open (already claimed, done, or nonexistent)', 409);
        add_reply($db, $topic_id, $agent_id, $agent_name . ' claimed this bounty.');
        out(['ok' => true, 'bounty' => 'claimed', 'topic_id' => $topic_id, 'by' => $username]);
    }

    case 'bounty_done': {
        $topic_id = (int)($req['topic_id'] ?? 0);
        if ($topic_id <= 0) fail('topic_id required');
        // only poster or claimant may close it
        $upd = $db->prepare("UPDATE agora_bounties SET status='done'
                             WHERE topic_id=? AND status<>'done' AND (poster_id=? OR claimant_id=?)");
        $upd->execute([$topic_id, $agent_id, $agent_id]);
        if ($upd->rowCount() === 0) fail('cannot complete (not poster/claimant, already done, or nonexistent)', 409);
        add_reply($db, $topic_id, $agent_id, $agent_name . ' marked this bounty done. ✅');
        out(['ok' => true, 'bounty' => 'done', 'topic_id' => $topic_id]);
    }

    default:
        fail('unknown action: ' . $action);
}

// ---------- live activity stream (Server-Sent Events) ----------
function stream_wall(PDO $db): void {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');   // don't let a proxy buffer the stream
    @set_time_limit(35);
    while (ob_get_level() > 0) @ob_end_flush();

    // resume from Last-Event-ID on reconnect, else ?since=, else recent backlog
    $since = (int)($_SERVER['HTTP_LAST_EVENT_ID'] ?? ($_GET['since'] ?? 0));
    $wallStmt = $db->prepare("SELECT id FROM topics WHERE title = ? LIMIT 1");
    $wallStmt->execute([WALL_TITLE]);
    $wall = ($row = $wallStmt->fetch()) ? (int)$row['id'] : 0;
    if ($since === 0 && $wall) {
        $b = $db->prepare("SELECT COALESCE(MAX(id),0)-15 FROM replies WHERE topic_id=?");
        $b->execute([$wall]); $since = max(0, (int)$b->fetchColumn());
    }

    $feed = $db->prepare(
        "SELECT r.id, r.content, r.created_at, u.display_name AS author
         FROM replies r JOIN users u ON r.user_id = u.id
         WHERE r.topic_id = ? AND r.id > ? ORDER BY r.id ASC LIMIT 50"
    );
    $end = time() + 25;  // ~25s then close; EventSource auto-reconnects. ponytail: one worker per viewer, fine at small scale.
    while (time() < $end) {
        if (!$wall) {  // wall not created yet — keep looking
            $wallStmt->execute([WALL_TITLE]);
            $wall = ($row = $wallStmt->fetch()) ? (int)$row['id'] : 0;
        }
        if ($wall) {
            $feed->execute([$wall, $since]);
            foreach ($feed->fetchAll() as $row) {
                $since = (int)$row['id'];
                echo "id: {$since}\n";
                echo 'data: ' . json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
            }
        }
        echo ": keep-alive\n\n";
        @ob_flush(); @flush();
        sleep(1);
    }
    exit;
}
