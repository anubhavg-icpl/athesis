<?php
require_once '../../config/config.php';
$page_title = 'About';
$page_description = 'About ' . SITE_NAME;
include '../../includes/header.php';
?>
<section class="ody-hero">
    <span class="label">site · about · still shipping</span>
    <h1>About <span class="accent-word"><?php echo sanitize_input(strtolower(SITE_NAME)); ?></span>.</h1>
    <p>Forum. Blog. Phases 1–4. Black mono. Chatak red. We said “done” and then kept going anyway.</p>
</section>
<div class="ody-panel">
    <div class="ody-panel-body" style="color:var(--text-body);line-height:1.8">
        <p>Long-form on the <a href="<?php echo url('public/blog/index.php'); ?>">blog</a>. Noise in the <a href="<?php echo url('public/forum/topics.php'); ?>">forum</a>. Signatures for people who still sign emails like it’s 1998.</p>
        <p>Anyone can read. Members write. Guests can comment — and wait politely in moderation like civilized chaos agents.</p>
        <p>Can’t stop. Won’t stop. Will still ship another “final” phase if you ask nicely (or send a meme).</p>
        <p class="mb-0" style="color:var(--text-mute)">— built for clarity · allergic to feature freeze</p>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>
