<?php
require_once '../../config/config.php';

require_login();

$reply_id = (int)($_GET['id'] ?? 0);
$errors = [];

if (!$reply_id) {
    header('Location: topics.php');
    exit;
}

$db = getDB();
$stmt = $db->prepare("
    SELECT r.*, t.id as topic_id, t.title as topic_title, t.is_locked,
           u.display_name
    FROM replies r
    JOIN topics t ON r.topic_id = t.id
    JOIN users u ON r.user_id = u.id
    WHERE r.id = ? AND r.is_deleted = 0
");
$stmt->execute([$reply_id]);
$reply = $stmt->fetch();

if (!$reply) {
    $_SESSION['flash_message'] = 'Reply not found.';
    $_SESSION['flash_type'] = 'error';
    header('Location: topics.php');
    exit;
}

$topic_id = (int) $reply['topic_id'];

// Only author or admin/mod can edit
if ($_SESSION['user_id'] != $reply['user_id'] && !is_admin_or_moderator()) {
    $_SESSION['flash_message'] = 'You do not have permission to edit this reply.';
    $_SESSION['flash_type'] = 'error';
    header("Location: view_topic.php?id=$topic_id#reply-$reply_id");
    exit;
}

// Locked topics: only admin/mod can edit replies
if (!empty($reply['is_locked']) && !is_admin_or_moderator()) {
    $_SESSION['flash_message'] = 'This topic is locked.';
    $_SESSION['flash_type'] = 'error';
    header("Location: view_topic.php?id=$topic_id");
    exit;
}

$page_title = 'Edit Reply';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $content = trim($_POST['content'] ?? '');

        if (empty($content)) {
            $errors[] = 'Reply content is required.';
        } elseif (strlen($content) < 5) {
            $errors[] = 'Reply content must be at least 5 characters long.';
        }

        if (empty($errors)) {
            try {
                $clean_content = clean_content($content);
                $stmt = $db->prepare("UPDATE replies SET content = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$clean_content, $reply_id]);

                $_SESSION['flash_message'] = 'Reply updated successfully.';
                $_SESSION['flash_type'] = 'success';
                header("Location: view_topic.php?id=$topic_id#reply-$reply_id");
                exit;
            } catch (PDOException $e) {
                $errors[] = 'Failed to update reply. Please try again.';
            }
        }

        $reply['content'] = $content;
    }
}

include '../../includes/header.php';
?>

<div class="ody-page-head">
    <h1><span class="prompt">$_</span>edit reply</h1>
    <a href="view_topic.php?id=<?php echo $topic_id; ?>#reply-<?php echo $reply_id; ?>" class="ody-link-btn">← back</a>
</div>

<div class="row g-4">
    <div class="col-md-8">
        <div class="ody-panel">
            <div class="ody-panel-head">compose</div>
            <div class="ody-panel-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo sanitize_input($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                    <div class="mb-3">
                        <label for="content" class="form-label">your reply</label>
                        <textarea class="form-control" id="content" name="content" rows="8"
                                  required minlength="5"><?php
                            echo sanitize_input(strip_tags($reply['content']));
                        ?></textarea>
                        <div class="form-text">min 5 characters</div>
                    </div>

                    <div class="d-flex gap-3 align-items-center">
                        <button type="submit" class="btn btn-primary">save changes</button>
                        <a href="view_topic.php?id=<?php echo $topic_id; ?>#reply-<?php echo $reply_id; ?>" class="ody-link-btn">cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4 ody-sidebar">
        <div class="ody-panel">
            <div class="ody-panel-head">context</div>
            <div class="ody-panel-body small" style="color: var(--text-mute);">
                <p class="mb-2">
                    <span style="color: var(--text-dim);">topic</span><br>
                    <a href="view_topic.php?id=<?php echo $topic_id; ?>">
                        <?php echo sanitize_input($reply['topic_title']); ?>
                    </a>
                </p>
                <p class="mb-2">
                    <span style="color: var(--text-dim);">author</span><br>
                    <?php echo sanitize_input($reply['display_name']); ?>
                </p>
                <p class="mb-0">
                    <span style="color: var(--text-dim);">posted</span><br>
                    <?php echo format_date($reply['created_at']); ?>
                </p>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('content').addEventListener('input', function () {
    this.style.height = 'auto';
    this.style.height = this.scrollHeight + 'px';
});
</script>

<?php include '../../includes/footer.php'; ?>
