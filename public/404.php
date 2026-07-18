<?php
http_response_code(404);
if (!defined('SITE_NAME')) {
    require_once __DIR__ . '/../config/config.php';
}
$page_title = '404';
include __DIR__ . '/../includes/header.php';
?>
<section class="ody-hero">
    <span class="label">error · 404</span>
    <h1>signal <span class="accent-word">lost</span>.</h1>
    <p>that path does not exist on this node.</p>
    <div class="ody-hero-actions">
        <a class="btn btn-primary" href="<?php echo url('public/index.php'); ?>">home →</a>
        <a class="ody-link-btn" href="<?php echo url('public/blog/index.php'); ?>">blog</a>
        <a class="ody-link-btn" href="<?php echo url('public/forum/topics.php'); ?>">topics</a>
    </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
