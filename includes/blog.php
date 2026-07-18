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
               c.name AS category_name, c.slug AS category_slug
        FROM blog_posts p
        JOIN users u ON u.id = p.user_id
        LEFT JOIN blog_categories c ON c.id = p.category_id
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
