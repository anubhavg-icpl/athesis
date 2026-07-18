<?php
require_once '../../config/config.php';
require_login();

if (!is_admin_or_moderator()) {
    header('Location: ' . url('public/blog/admin.php'));
    exit;
}

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($id && $action === 'approve') {
        $db->prepare('UPDATE blog_comments SET is_approved = 1 WHERE id = ?')->execute([$id]);
        $_SESSION['flash_message'] = 'Comment approved.';
    } elseif ($id && $action === 'delete') {
        $db->prepare('DELETE FROM blog_comments WHERE id = ?')->execute([$id]);
        $_SESSION['flash_message'] = 'Comment deleted.';
    }
    $_SESSION['flash_type'] = 'success';
    header('Location: ' . url('public/blog/moderate.php'));
    exit;
}

$pending = $db->query('
    SELECT c.*, p.title AS post_title, p.slug AS post_slug, u.display_name
    FROM blog_comments c
    JOIN blog_posts p ON p.id = c.post_id
    LEFT JOIN users u ON u.id = c.user_id
    WHERE c.is_approved = 0
    ORDER BY c.created_at ASC
')->fetchAll();

$page_title = 'Moderate comments';
include '../../includes/header.php';
?>
<div class="ody-page-head">
    <h1><span class="prompt">$_</span>moderate</h1>
    <a class="ody-link-btn" href="<?php echo url('public/blog/admin.php'); ?>">← admin</a>
</div>

<?php if (empty($pending)): ?>
    <div class="ody-empty"><p>queue clear. no pending comments.</p></div>
<?php else: ?>
    <?php foreach ($pending as $c): ?>
        <article class="ody-post mb-3">
            <div class="ody-post-head">
                <div>
                    <div class="author"><?php echo sanitize_input($c['display_name'] ?: ($c['author_name'] ?: 'guest')); ?></div>
                    <div class="meta">
                        on <a href="<?php echo url('public/blog/post.php?slug=' . urlencode($c['post_slug'])); ?>"><?php echo sanitize_input($c['post_title']); ?></a>
                        · <?php echo time_ago($c['created_at']); ?>
                    </div>
                </div>
            </div>
            <div class="ody-post-body"><?php echo nl2br(sanitize_input(strip_tags($c['content']))); ?></div>
            <div class="ody-post-foot d-flex gap-3">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                    <input type="hidden" name="action" value="approve">
                    <button class="btn btn-primary btn-sm" type="submit">approve</button>
                </form>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                    <input type="hidden" name="action" value="delete">
                    <button class="btn btn-outline-secondary btn-sm" type="submit">delete</button>
                </form>
            </div>
        </article>
    <?php endforeach; ?>
<?php endif; ?>
<?php include '../../includes/footer.php'; ?>
