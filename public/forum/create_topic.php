<?php
require_once '../../config/config.php';

$page_title = 'Create New Topic';
require_login();

$errors = [];
$success = false;

// Process topic creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        
        // Validate input
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
        
        // Create topic if no errors
        if (empty($errors)) {
            try {
                $db = getDB();
                $clean_content = clean_content($content);
                
                $stmt = $db->prepare("INSERT INTO topics (title, content, user_id) VALUES (?, ?, ?)");
                $stmt->execute([$title, $clean_content, $_SESSION['user_id']]);
                
                $topic_id = $db->lastInsertId();
                
                $_SESSION['flash_message'] = 'Topic created successfully!';
                $_SESSION['flash_type'] = 'success';
                header("Location: view_topic.php?id=$topic_id");
                exit;
            } catch (PDOException $e) {
                $errors[] = 'Failed to create topic. Please try again.';
            }
        }
    }
}

include '../../includes/header.php';
?>

<div class="ody-page-head">
    <h1><span class="prompt">$_</span>new topic</h1>
    <a href="topics.php" class="ody-link-btn">← topics</a>
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
                               value="<?php echo sanitize_input($_POST['title'] ?? ''); ?>"
                               required maxlength="255" minlength="5"
                               placeholder="clear, descriptive title">
                        <div class="form-text">5–255 characters</div>
                    </div>

                    <div class="mb-3">
                        <label for="content" class="form-label">content</label>
                        <textarea class="form-control" id="content" name="content" rows="10"
                                  required minlength="10"
                                  placeholder="write your topic..."><?php echo sanitize_input($_POST['content'] ?? ''); ?></textarea>
                        <div class="form-text">
                            min 10 chars · allowed: p, br, strong, em, u, ol, ul, li, blockquote
                        </div>
                    </div>

                    <div class="d-flex gap-3 align-items-center">
                        <button type="submit" class="btn btn-primary">create topic</button>
                        <a href="topics.php" class="ody-link-btn">cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4 ody-sidebar">
        <div class="ody-panel">
            <div class="ody-panel-head">guidelines</div>
            <div class="ody-panel-body">
                <ul class="small mb-0" style="padding-left: 1.1rem; color: var(--text-dim);">
                    <li class="mb-2">clear, descriptive title</li>
                    <li class="mb-2">enough detail in the body</li>
                    <li class="mb-2">be respectful</li>
                    <li class="mb-2">search before posting</li>
                    <li>format for readability</li>
                </ul>
            </div>
        </div>

        <div class="ody-panel">
            <div class="ody-panel-head">formatting</div>
            <div class="ody-panel-body small" style="color: var(--text-mute);">
                <p class="mb-2"><span style="color: var(--text-dim);">bold</span> &lt;strong&gt;</p>
                <p class="mb-2"><span style="color: var(--text-dim);">italic</span> &lt;em&gt;</p>
                <p class="mb-2"><span style="color: var(--text-dim);">break</span> &lt;br&gt;</p>
                <p class="mb-0"><span style="color: var(--text-dim);">quote</span> &lt;blockquote&gt;</p>
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
