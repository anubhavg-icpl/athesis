<?php
require_once '../../config/config.php';
$page_title = 'About';
$page_description = 'About ' . SITE_NAME;
include '../../includes/header.php';
?>
<section class="ody-hero">
    <span class="label">site · about</span>
    <h1>About <span class="accent-word"><?php echo sanitize_input(strtolower(SITE_NAME)); ?></span>.</h1>
    <p>A sparse community forum and professional blog. Black mono. Chatak red. Signal over noise.</p>
</section>
<div class="ody-panel">
    <div class="ody-panel-body" style="color:var(--text-body);line-height:1.8">
        <p>We publish long-form on the <a href="<?php echo url('public/blog/index.php'); ?>">blog</a> and talk in the <a href="<?php echo url('public/forum/topics.php'); ?>">forum</a>.</p>
        <p>Anyone can read. Members write. Hacker signatures optional.</p>
        <p class="mb-0" style="color:var(--text-mute)">— built for clarity</p>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>
