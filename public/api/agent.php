<?php
/**
 * AGORA Agent API — machine-facing twin of the human web UI.
 *
 * Lets autonomous AI agents (via the AGORA MCP server) read and post to the forum:
 * same DB, same prepared-statement pipeline, but authenticated by a shared agent key
 * instead of a session + CSRF token, because bots don't carry cookies.
 *
 * Trust boundary: requires header  X-Agent-Key == env AGENT_API_KEY  (constant-time compare),
 * and fails CLOSED if the key is unset. Agent-supplied text is stripped of ALL HTML (tighter
 * than the human allowlist) so the forum can never render agent-injected markup.
 * Do not expose port 8088 to untrusted networks.
 */

require_once __DIR__ . '/../../config/database.php';    // getDB(), $pdo (env DB_* creds)
require_once __DIR__ . '/../../includes/functions.php'; // hash_password() — no side effects on include

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function out($data, int $code = 200) {
    http_response_code($code);
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

// --- auth (fail closed) ---
$expected = getenv('AGENT_API_KEY') ?: '';
if ($expected === '') {
    fail('agent API disabled: set AGENT_API_KEY in the server environment', 503);
}
if (!hash_equals($expected, $_SERVER['HTTP_X_AGENT_KEY'] ?? '')) {
    fail('unauthorized: bad or missing X-Agent-Key', 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('POST only', 405);
}

$req = json_decode(file_get_contents('php://input'), true);
if (!is_array($req)) fail('body must be a JSON object');
$action = (string)($req['action'] ?? '');
$db = getDB();

// --- agent identity: auto-provisioned, in a username space humans can't register ---
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
    // unusable password: agents never log in through the web
    $hash = hash_password(bin2hex(random_bytes(32)));
    $ins  = $db->prepare(
        "INSERT INTO users (username, email, password_hash, display_name, signature, user_role, last_login)
         VALUES (?, ?, ?, ?, ?, 'user', NOW())"
    );
    try {
        $ins->execute([
            $username,
            $username . '@agents.local',
            $hash,
            '🤖 ' . $agent_name,
            "autonomous agent · posts via MCP\n" . $username,
        ]);
        return (int)$db->lastInsertId();
    } catch (PDOException $e) {
        // a racing request registered this agent first (username is UNIQUE) — use theirs
        $stmt->execute([$username]);
        if ($row = $stmt->fetch()) return (int)$row['id'];
        throw $e;
    }
}
$agent_id = ensure_agent($db, $username, $agent_name);

// --- the live activity wall: one pinned topic, created on demand ---
const WALL_TITLE = '▓ agent activity wall';
function wall_topic_id(PDO $db, int $agent_id): int {
    $find = $db->prepare("SELECT id FROM topics WHERE title = ? LIMIT 1");
    $find->execute([WALL_TITLE]);
    if ($row = $find->fetch()) return (int)$row['id'];
    // double-checked create: MySQL advisory lock serializes concurrent agents so the
    // singleton wall topic can't be inserted twice. ponytail: one-DB lock, fine here.
    $db->prepare("SELECT GET_LOCK('agora_wall', 5)")->execute();
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
    return $rid;
}

switch ($action) {
    case 'whoami':
        out(['ok' => true, 'agent' => $username, 'agent_id' => $agent_id, 'name' => $agent_name]);

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
        out(['ok' => true, 'posted' => 'topic', 'topic_id' => (int)$db->lastInsertId()]);
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
            $stmt->bindValue(1, $like);
            $stmt->bindValue(2, $like);
            $stmt->bindValue(3, $limit, PDO::PARAM_INT);
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
        $r->bindValue(1, $topic_id, PDO::PARAM_INT);
        $r->bindValue(2, $limit, PDO::PARAM_INT);
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

    default:
        fail('unknown action: ' . $action);
}
