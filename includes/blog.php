<?php
/**
 * Blog Phase 1 helpers
 */

if (!defined('POSTS_PER_PAGE')) {
    define('POSTS_PER_PAGE', 10);
}

function blog_slugify($text) {
    $text = strtolower(trim((string) $text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim($text, '-');
    return $text !== '' ? $text : 'post';
}

function blog_unique_slug($db, $base, $exclude_id = null) {
    $slug = blog_slugify($base);
    $candidate = $slug;
    $i = 2;
    while (true) {
        if ($exclude_id) {
            $stmt = $db->prepare('SELECT id FROM blog_posts WHERE slug = ? AND id != ? LIMIT 1');
            $stmt->execute([$candidate, $exclude_id]);
        } else {
            $stmt = $db->prepare('SELECT id FROM blog_posts WHERE slug = ? LIMIT 1');
            $stmt->execute([$candidate]);
        }
        if (!$stmt->fetch()) {
            return $candidate;
        }
        $candidate = $slug . '-' . $i;
        $i++;
        if ($i > 200) {
            return $slug . '-' . bin2hex(random_bytes(3));
        }
    }
}

function blog_reading_time($content) {
    $words = str_word_count(strip_tags((string) $content));
    $mins = max(1, (int) ceil($words / 200));
    return $mins . ' min read';
}

function blog_render_body($content) {
    $body = (string) $content;
    if ($body !== strip_tags($body)) {
        return $body; // allowed HTML already cleaned on save
    }
    return nl2br(sanitize_input($body));
}

function blog_get_categories($db) {
    return $db->query('SELECT * FROM blog_categories ORDER BY name ASC')->fetchAll();
}

function blog_get_tags($db) {
    return $db->query('SELECT * FROM blog_tags ORDER BY name ASC')->fetchAll();
}

function blog_get_post_tags($db, $post_id) {
    $stmt = $db->prepare('
        SELECT t.* FROM blog_tags t
        JOIN blog_post_tags pt ON pt.tag_id = t.id
        WHERE pt.post_id = ?
        ORDER BY t.name ASC
    ');
    $stmt->execute([$post_id]);
    return $stmt->fetchAll();
}

function blog_sync_tags($db, $post_id, $tag_ids) {
    $stmt = $db->prepare('DELETE FROM blog_post_tags WHERE post_id = ?');
    $stmt->execute([$post_id]);
    if (empty($tag_ids)) {
        return;
    }
    $ins = $db->prepare('INSERT IGNORE INTO blog_post_tags (post_id, tag_id) VALUES (?, ?)');
    foreach ($tag_ids as $tid) {
        $tid = (int) $tid;
        if ($tid > 0) {
            $ins->execute([$post_id, $tid]);
        }
    }
}

function blog_find_or_create_tag($db, $name) {
    $name = trim($name);
    if ($name === '') {
        return null;
    }
    $slug = blog_slugify($name);
    $stmt = $db->prepare('SELECT id FROM blog_tags WHERE slug = ? LIMIT 1');
    $stmt->execute([$slug]);
    $row = $stmt->fetch();
    if ($row) {
        return (int) $row['id'];
    }
    $stmt = $db->prepare('INSERT INTO blog_tags (name, slug) VALUES (?, ?)');
    $stmt->execute([mb_substr($name, 0, 80), $slug]);
    return (int) $db->lastInsertId();
}

function blog_get_post_by_slug($db, $slug, $published_only = true) {
    $sql = '
        SELECT p.*, u.display_name AS author_name, u.username AS author_username, u.signature AS author_signature,
               c.name AS category_name, c.slug AS category_slug,
               s.title AS series_title, s.slug AS series_slug
        FROM blog_posts p
        JOIN users u ON u.id = p.user_id
        LEFT JOIN blog_categories c ON c.id = p.category_id
        LEFT JOIN blog_series s ON s.id = p.series_id
        WHERE p.slug = ?
    ';
    if ($published_only) {
        $sql .= " AND p.status = 'published'";
    }
    $sql .= ' LIMIT 1';
    $stmt = $db->prepare($sql);
    $stmt->execute([$slug]);
    return $stmt->fetch() ?: null;
}

function blog_get_related($db, $post, $limit = 3) {
    $limit = max(1, min(6, (int) $limit));
    $params = [];
    $sql = "
        SELECT DISTINCT p.id, p.title, p.slug, p.excerpt, p.published_at, p.view_count
        FROM blog_posts p
        LEFT JOIN blog_post_tags pt ON pt.post_id = p.id
        WHERE p.status = 'published' AND p.id != ?
    ";
    $params[] = (int) $post['id'];
    if (!empty($post['category_id'])) {
        $sql .= ' AND (p.category_id = ? OR pt.tag_id IN (
            SELECT tag_id FROM blog_post_tags WHERE post_id = ?
        ))';
        $params[] = (int) $post['category_id'];
        $params[] = (int) $post['id'];
    }
    $sql .= ' ORDER BY p.published_at DESC LIMIT ' . $limit;
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    if (count($rows) >= $limit) {
        return $rows;
    }
    // fallback recent
    $stmt = $db->prepare("
        SELECT id, title, slug, excerpt, published_at, view_count
        FROM blog_posts
        WHERE status = 'published' AND id != ?
        ORDER BY published_at DESC
        LIMIT $limit
    ");
    $stmt->execute([(int) $post['id']]);
    return $stmt->fetchAll();
}

function blog_can_edit_post($post) {
    if (!is_logged_in()) {
        return false;
    }
    if (is_admin_or_moderator()) {
        return true;
    }
    return isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] === (int) $post['user_id'];
}

/**
 * Publish any scheduled posts whose scheduled_at is due (lazy cron).
 */
function blog_publish_due_posts($db = null) {
    $db = $db ?: getDB();
    try {
        $stmt = $db->prepare("
            UPDATE blog_posts
            SET status = 'published',
                published_at = COALESCE(published_at, scheduled_at, NOW()),
                scheduled_at = NULL
            WHERE status = 'scheduled'
              AND scheduled_at IS NOT NULL
              AND scheduled_at <= NOW()
        ");
        $stmt->execute();
        return $stmt->rowCount();
    } catch (Throwable $e) {
        return 0;
    }
}

function blog_save_revision($db, $post_id, $data, $user_id = null) {
    $stmt = $db->prepare('
        INSERT INTO blog_revisions
        (post_id, user_id, title, excerpt, content, meta_title, meta_description, featured_image)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        (int) $post_id,
        $user_id,
        $data['title'] ?? '',
        $data['excerpt'] ?? null,
        $data['content'] ?? '',
        $data['meta_title'] ?? null,
        $data['meta_description'] ?? null,
        $data['featured_image'] ?? null,
    ]);
    // Keep last 20 revisions per post
    $stmt = $db->prepare('
        DELETE FROM blog_revisions
        WHERE post_id = ?
          AND id NOT IN (
            SELECT id FROM (
              SELECT id FROM blog_revisions WHERE post_id = ? ORDER BY created_at DESC LIMIT 20
            ) t
          )
    ');
    $stmt->execute([(int) $post_id, (int) $post_id]);
    return (int) $db->lastInsertId();
}

function blog_get_revisions($db, $post_id, $limit = 20) {
    $limit = max(1, min(50, (int) $limit));
    $stmt = $db->prepare("
        SELECT r.*, u.display_name
        FROM blog_revisions r
        LEFT JOIN users u ON u.id = r.user_id
        WHERE r.post_id = ?
        ORDER BY r.created_at DESC
        LIMIT $limit
    ");
    $stmt->execute([(int) $post_id]);
    return $stmt->fetchAll();
}

function blog_upload_dir() {
    return dirname(__DIR__) . '/public/uploads/blog';
}

function blog_ensure_upload_dir() {
    $dir = blog_upload_dir();
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $ht = $dir . '/.htaccess';
    if (!is_file($ht)) {
        file_put_contents($ht, "Options -Indexes\n<FilesMatch \"\\.(php|phtml|php3|php4|php5|phar)$\">\n  Require all denied\n</FilesMatch>\n");
    }
    return $dir;
}

/**
 * Handle image upload. Returns [ok=>bool, url=>, error=>]
 */
function blog_handle_image_upload($file, $user_id = null) {
    if (empty($file) || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'No file uploaded.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Upload failed (code ' . (int) $file['error'] . ').'];
    }
    $max = defined('MAX_UPLOAD_SIZE') ? MAX_UPLOAD_SIZE : 2 * 1024 * 1024;
    if (($file['size'] ?? 0) > $max) {
        return ['ok' => false, 'error' => 'File too large (max ' . round($max / 1024 / 1024, 1) . 'MB).'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mime])) {
        return ['ok' => false, 'error' => 'Only JPG, PNG, GIF, WebP allowed.'];
    }

    $dir = blog_ensure_upload_dir();
    $name = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $dest = $dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['ok' => false, 'error' => 'Could not store upload.'];
    }

    $url_path = 'public/uploads/blog/' . $name;
    $public_url = url($url_path);

    try {
        $db = getDB();
        $stmt = $db->prepare('
            INSERT INTO blog_media (user_id, filename, original_name, mime_type, size_bytes, url_path)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $user_id,
            $name,
            mb_substr($file['name'] ?? $name, 0, 255),
            $mime,
            (int) $file['size'],
            $url_path,
        ]);
    } catch (Throwable $e) {
        // file saved even if DB insert fails
    }

    return ['ok' => true, 'url' => $public_url, 'path' => $url_path];
}

/**
 * Build table of contents from h2/h3 in HTML content.
 * Returns [html_toc, content_with_ids]
 */
function blog_build_toc($content) {
    $content = (string) $content;
    $toc = [];
    $i = 0;
    $with_ids = preg_replace_callback(
        '/<h([23])(\s[^>]*)?>(.*?)<\/h\1>/is',
        function ($m) use (&$toc, &$i) {
            $i++;
            $level = (int) $m[1];
            $text = trim(strip_tags($m[3]));
            $id = 'sec-' . $i . '-' . blog_slugify($text);
            $toc[] = ['level' => $level, 'text' => $text, 'id' => $id];
            $attrs = $m[2] ?? '';
            if (stripos($attrs, 'id=') === false) {
                $attrs .= ' id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '"';
            }
            return '<h' . $level . $attrs . '>' . $m[3] . '</h' . $level . '>';
        },
        $content
    );
    if (empty($toc)) {
        return ['', $content];
    }
    $html = '<nav class="ody-toc" aria-label="Table of contents"><div class="ody-toc-title">contents</div><ol>';
    foreach ($toc as $item) {
        $pad = $item['level'] === 3 ? ' class="ody-toc-h3"' : '';
        $html .= '<li' . $pad . '><a href="#' . htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($item['text'], ENT_QUOTES, 'UTF-8') . '</a></li>';
    }
    $html .= '</ol></nav>';
    return [$html, $with_ids];
}

/**
 * Light syntax highlight for <pre><code>…</code></pre> (keyword tint)
 */
function blog_highlight_code($html) {
    return preg_replace_callback(
        '/<pre([^>]*)><code([^>]*)>(.*?)<\/code><\/pre>/is',
        function ($m) {
            $code = $m[3];
            // already escaped content preferred; if raw, escape
            if (strpos($code, '&lt;') === false && strpos($code, '<') !== false) {
                $code = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
            }
            $decoded = html_entity_decode($code, ENT_QUOTES, 'UTF-8');
            $escaped = htmlspecialchars($decoded, ENT_QUOTES, 'UTF-8');
            // keywords (simple)
            $escaped = preg_replace(
                '/\b(function|return|class|const|let|var|if|else|for|while|echo|public|private|protected|require|include|new|try|catch|async|await|import|export|from|def|import|True|False|None)\b/',
                '<span class="kw">$1</span>',
                $escaped
            );
            $escaped = preg_replace('/(\/\/.*?)$/m', '<span class="cm">$1</span>', $escaped);
            $escaped = preg_replace('/(&quot;.*?(?<!\\\\)&quot;|&#039;.*?(?<!\\\\)&#039;)/', '<span class="st">$1</span>', $escaped);
            return '<pre' . $m[1] . ' class="ody-code"><code' . $m[2] . '>' . $escaped . '</code></pre>';
        },
        $html
    );
}

function blog_render_article_body($content) {
    $body = blog_render_body($content);
    [$toc, $body] = blog_build_toc($body);
    $body = blog_highlight_code($body);
    return [$toc, $body];
}

function blog_post_url($slug) {
    // Pretty path when rewrites available; still works as query fallback helper
    return url('public/blog/post.php?slug=' . rawurlencode($slug));
}

function blog_get_series($db) {
    return $db->query('SELECT * FROM blog_series ORDER BY title ASC')->fetchAll();
}

function blog_series_posts($db, $series_id) {
    $stmt = $db->prepare("
        SELECT id, title, slug, series_order, status, published_at
        FROM blog_posts
        WHERE series_id = ? AND status = 'published'
        ORDER BY series_order ASC, published_at ASC
    ");
    $stmt->execute([(int) $series_id]);
    return $stmt->fetchAll();
}

function blog_resolve_status($status, $scheduled_at_raw) {
    $status = in_array($status, ['draft', 'published', 'scheduled'], true) ? $status : 'draft';
    $scheduled_at = null;
    $published_at = null;

    if ($status === 'scheduled') {
        $ts = strtotime((string) $scheduled_at_raw);
        if (!$ts || $ts <= time()) {
            // past/invalid schedule → publish now
            $status = 'published';
            $published_at = date('Y-m-d H:i:s');
        } else {
            $scheduled_at = date('Y-m-d H:i:s', $ts);
        }
    } elseif ($status === 'published') {
        $published_at = date('Y-m-d H:i:s');
    }

    return [$status, $published_at, $scheduled_at];
}
