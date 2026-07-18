<?php
require_once '../../config/config.php';
require_login();

$db = getDB();
$errors = [];
$success_url = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        $result = blog_handle_image_upload($_FILES['image'] ?? [], (int) $_SESSION['user_id']);
        if (!$result['ok']) {
            $errors[] = $result['error'];
        } else {
            $success_url = $result['url'];
            $_SESSION['flash_message'] = 'Image uploaded.';
            $_SESSION['flash_type'] = 'success';
            header('Location: ' . url('public/blog/media.php?picked=' . urlencode($success_url)));
            exit;
        }
    }
}

$where = '1=1';
$params = [];
if (!is_admin_or_moderator()) {
    $where = 'user_id = ?';
    $params[] = (int) $_SESSION['user_id'];
}
$stmt = $db->prepare("SELECT * FROM blog_media WHERE $where ORDER BY created_at DESC LIMIT 60");
$stmt->execute($params);
$media = $stmt->fetchAll();

$picked = trim($_GET['picked'] ?? '');

$page_title = 'Media library';
include '../../includes/header.php';
?>

<div class="ody-page-head">
    <h1><span class="prompt">$_</span>media</h1>
    <div class="d-flex gap-3">
        <a class="ody-link-btn" href="<?php echo url('public/blog/admin.php'); ?>">admin</a>
        <a class="ody-link-btn" href="<?php echo url('public/blog/write.php'); ?>">write</a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?php echo sanitize_input($e); ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<?php if ($picked !== ''): ?>
    <div class="alert alert-success">
        uploaded · copy URL:
        <code class="ody-copy-url" id="picked-url"><?php echo sanitize_input($picked); ?></code>
        <button type="button" class="ody-link-btn ms-2" id="copy-picked">copy</button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-md-5">
        <div class="ody-panel">
            <div class="ody-panel-head">upload image</div>
            <div class="ody-panel-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <div class="mb-3">
                        <label class="form-label" for="image">file</label>
                        <input type="file" class="form-control" id="image" name="image" accept="image/jpeg,image/png,image/gif,image/webp" required>
                        <div class="form-text">jpg/png/gif/webp · max <?php echo round(MAX_UPLOAD_SIZE / 1024 / 1024, 1); ?>MB</div>
                    </div>
                    <button type="submit" class="btn btn-primary">upload →</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <div class="ody-panel">
            <div class="ody-panel-head">library</div>
            <div class="ody-panel-body">
                <?php if (empty($media)): ?>
                    <p class="mb-0" style="color:var(--text-mute)">no uploads yet.</p>
                <?php else: ?>
                    <div class="ody-media-grid">
                        <?php foreach ($media as $m): ?>
                            <?php $u = url($m['url_path']); ?>
                            <div class="ody-media-card">
                                <img src="<?php echo sanitize_input($u); ?>" alt="">
                                <button type="button" class="ody-link-btn copy-media" data-url="<?php echo sanitize_input($u); ?>">copy url</button>
                                <div class="small" style="color:var(--text-mute)"><?php echo sanitize_input($m['original_name']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function copyText(t) {
    if (navigator.clipboard) return navigator.clipboard.writeText(t);
    var i = document.createElement('input'); i.value = t; document.body.appendChild(i); i.select(); document.execCommand('copy'); i.remove();
}
document.getElementById('copy-picked')?.addEventListener('click', function () {
    var el = document.getElementById('picked-url');
    if (el) copyText(el.textContent.trim());
});
document.querySelectorAll('.copy-media').forEach(function (btn) {
    btn.addEventListener('click', function () { copyText(this.dataset.url); this.textContent = 'copied'; });
});
</script>

<?php include '../../includes/footer.php'; ?>
