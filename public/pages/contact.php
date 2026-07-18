<?php
require_once '../../config/config.php';
$page_title = 'Contact';
$errors = [];
$ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid token.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $body = trim($_POST['body'] ?? '');
        if (strlen($name) < 2) $errors[] = 'Name required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';
        if (strlen($subject) < 3) $errors[] = 'Subject required.';
        if (strlen($body) < 10) $errors[] = 'Message too short.';
        if (empty($errors)) {
            try {
                $db = getDB();
                $stmt = $db->prepare('INSERT INTO site_messages (name, email, subject, body) VALUES (?, ?, ?, ?)');
                $stmt->execute([
                    mb_substr($name, 0, 100),
                    mb_substr($email, 0, 190),
                    mb_substr($subject, 0, 200),
                    mb_substr($body, 0, 5000),
                ]);
                $ok = true;
            } catch (Throwable $e) {
                $errors[] = 'Could not send message.';
            }
        }
    }
}

include '../../includes/header.php';
?>
<div class="ody-page-head">
    <h1><span class="prompt">$_</span>contact</h1>
</div>
<?php if ($ok): ?>
    <div class="alert alert-success">message received. we will get back when we can.</div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?php echo sanitize_input($e); ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<div class="ody-panel">
    <div class="ody-panel-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <div class="mb-3">
                <label class="form-label" for="name">name</label>
                <input class="form-control" id="name" name="name" required value="<?php echo sanitize_input($_POST['name'] ?? ''); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label" for="email">email</label>
                <input type="email" class="form-control" id="email" name="email" required value="<?php echo sanitize_input($_POST['email'] ?? ''); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label" for="subject">subject</label>
                <input class="form-control" id="subject" name="subject" required value="<?php echo sanitize_input($_POST['subject'] ?? ''); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label" for="body">message</label>
                <textarea class="form-control" id="body" name="body" rows="5" required><?php echo sanitize_input($_POST['body'] ?? ''); ?></textarea>
            </div>
            <button class="btn btn-primary" type="submit">send →</button>
        </form>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>
