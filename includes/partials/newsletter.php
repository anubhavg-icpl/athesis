<?php
// Newsletter capture (Phase 3)
$nl_msg = '';
$nl_err = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['form'] ?? '') === 'newsletter') {
    if (!function_exists('verify_csrf_token') || !verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $nl_err = 'Invalid token.';
    } else {
        $email = trim($_POST['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $nl_err = 'Enter a valid email.';
        } else {
            try {
                $dbn = getDB();
                $stmt = $dbn->prepare('
                    INSERT INTO blog_subscribers (email, is_active) VALUES (?, 1)
                    ON DUPLICATE KEY UPDATE is_active = 1, unsubscribed_at = NULL
                ');
                $stmt->execute([$email]);
                $nl_msg = 'subscribed · signal locked in.';
            } catch (Throwable $e) {
                $nl_err = 'Could not subscribe right now.';
            }
        }
    }
}
?>
<section class="ody-newsletter ody-section mt-4">
    <div class="ody-panel">
        <div class="ody-panel-head">newsletter</div>
        <div class="ody-panel-body">
            <p style="color:var(--text-dim);margin-bottom:1rem">sparse updates. no spam. unsubscribe anytime.</p>
            <?php if ($nl_msg): ?><div class="alert alert-success"><?php echo sanitize_input($nl_msg); ?></div><?php endif; ?>
            <?php if ($nl_err): ?><div class="alert alert-danger"><?php echo sanitize_input($nl_err); ?></div><?php endif; ?>
            <form method="POST" action="" class="row g-2 align-items-end">
                <input type="hidden" name="form" value="newsletter">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <div class="col-md-8">
                    <label class="form-label" for="nl-email">email</label>
                    <input type="email" class="form-control" id="nl-email" name="email" required placeholder="you@domain">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100">join →</button>
                </div>
            </form>
        </div>
    </div>
</section>
