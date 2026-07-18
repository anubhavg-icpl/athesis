<?php
require_once '../../config/config.php';

require_login();

$topic_id = (int)($_GET['id'] ?? 0);
$errors = [];

if (!$topic_id) {
    header('Location: topics.php');
    exit;
}

$db = getDB();
$stmt = $db->prepare("
    SELECT t.*, u.display_name
    FROM topics t
    JOIN users u ON t.user_id = u.id
    WHERE t.id = ?
");
$stmt->execute([$topic_id]);
$topic = $stmt->fetch();

if (!$topic) {
    $_SESSION['flash_message'] = 'Topic not found.';
    $_SESSION['flash_type'] = 'error';
    header('Location: topics.php');
    exit;
}

// Only author or admin/mod can edit
if ($_SESSION['user_id'] != $topic['user_id'] && !is_admin_or_moderator()) {
    $_SESSION['flash_message'] = 'You do not have permission to edit this topic.';
    $_SESSION['flash_type'] = 'error';
    header("Location: view_topic.php?id=$topic_id");
    exit;
}

// Locked topics: only admin/mod can edit
if (!empty($topic['is_locked']) && !is_admin_or_moderator()) {
    $_SESSION['flash_message'] = 'This topic is locked.';
    $_SESSION['flash_type'] = 'error';
    header("Location: view_topic.php?id=$topic_id");
    exit;
}

$page_title = 'Edit Topic';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');

        if (empty($title)) {
            $errors[] = 'Topic title is required.';
        } elseif (strlen($title) < 5 || strlen($title) > 255) {
            $errors[] = 'Topic title must be between 5 and 255 characters.';
        }

        if (empty($content)) {
            $errors[] = 'Topic content is required.';
        } elseif (strlen($content) < 10) {
            $errors[] = 'Topic content must be at least 10 characters long.';
        }

        if (empty($errors)) {
            try {
                $clean_content = clean_content($content);
                $stmt = $db->prepare("UPDATE topics SET title = ?, content = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$title, $clean_content, $topic_id]);

                $_SESSION['flash_message'] = 'Topic updated successfully.';
                $_SESSION['flash_type'] = 'success';
                header("Location: view_topic.php?id=$topic_id");
                exit;
            } catch (PDOException $e) {
                $errors[] = 'Failed to update topic. Please try again.';
            }
        }

        // Keep posted values on error
        $topic['title'] = $title;
        $topic['content'] = $content;
    }
}

include '../../includes/header.php';
?>

<div class="ody-page-head">
    <h1><span class="prompt">$_</span>edit topic</h1>
    <a href="view_topic.php?id=<?php echo $topic_id; ?>" class="ody-link-btn">← back</a>
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
                        <label for="title" class="form-label">title</label>
                        <input type="text" class="form-control" id="title" name="title"
                               value="<?php echo sanitize_input($topic['title']); ?>"
                               required maxlength="255" minlength="5">
                    </div>

                    <div class="mb-3">
                        <label for="content" class="form-label">content</label>
                        <textarea class="form-control" id="content" name="content" rows="10"
                                  required minlength="10"><?php
                            // Content may contain allowed HTML — strip for edit field safety
                            echo sanitize_input(strip_tags($topic['content']));
                        ?></textarea>
                        <div class="form-text">min 10 chars · allowed: p, br, strong, em, u, ol, ul, li, blockquote</div>
                    </div>

                    <div class="d-flex gap-3 align-items-center">
                        <button type="submit" class="btn btn-primary">save changes</button>
                        <a href="view_topic.php?id=<?php echo $topic_id; ?>" class="ody-link-btn">cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4 ody-sidebar">
        <div class="ody-panel">
            <div class="ody-panel-head">meta</div>
            <div class="ody-panel-body small" style="color: var(--text-mute);">
                <p class="mb-2">
                    <span style="color: var(--text-dim);">author</span><br>
                    <?php echo sanitize_input($topic['display_name']); ?>
                </p>
                <p class="mb-2">
                    <span style="color: var(--text-dim);">created</span><br>
                    <?php echo format_date($topic['created_at']); ?>
                </p>
                <p class="mb-0">
                    <span style="color: var(--text-dim);">id</span><br>
                    #<?php echo (int) $topic_id; ?>
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
