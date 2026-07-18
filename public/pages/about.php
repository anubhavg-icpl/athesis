<?php
require_once '../../config/config.php';
$page_title = 'About';
$page_description = 'About ' . SITE_NAME;
include '../../includes/header.php';
?>
<section class="ody-hero ody-hero-with-art">
    <div class="ody-hero-visual ody-hero-visual-mark" aria-hidden="true">
        <img src="<?php echo url('public/images/brand/mark-red.jpg'); ?>" alt="" width="320" height="320" loading="eager">
        <div class="ody-hero-visual-fade"></div>
    </div>
    <div class="ody-hero-copy">
        <span class="label">site · about · still shipping</span>
        <h1>About <span class="accent-word"><?php echo sanitize_input(strtolower(SITE_NAME)); ?></span>.</h1>
        <p><strong>Athesis</strong> — not a generic “php forum” label. This is the repo. This is the product. Forum + blog. Black mono. Chatak red.</p>
    </div>
</section>
<div class="ody-panel">
    <div class="ody-panel-body" style="color:var(--text-body);line-height:1.8">
        <p>Long-form on the <a href="<?php echo url('public/blog/index.php'); ?>">blog</a>. Discussion in the <a href="<?php echo url('public/forum/topics.php'); ?>">forum</a>. Signatures for people who still sign posts like it’s an underground board.</p>
        <p>Anyone can read. Members write. Guests can comment — and wait in moderation like civilized chaos agents.</p>
        <p>Can’t stop. Won’t stop. Still called <strong>Athesis</strong>, not “PHP Forum.”</p>
        <p class="mb-0" style="color:var(--text-mute)">— athesis · built for clarity · allergic to feature freeze</p>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>
