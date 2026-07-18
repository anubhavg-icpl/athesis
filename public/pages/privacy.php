<?php
require_once '../../config/config.php';
$page_title = 'Privacy';
$page_description = 'Privacy policy';
include '../../includes/header.php';
?>
<div class="ody-page-head">
    <h1><span class="prompt">$_</span>privacy</h1>
</div>
<div class="ody-panel">
    <div class="ody-panel-body" style="color:var(--text-body);line-height:1.8">
        <p>We store account data you provide (username, email, password hash) and content you publish.</p>
        <p>Newsletter emails are used only for updates you opted into. Unsubscribe by contacting us.</p>
        <p>We do not sell personal data. Sessions use cookies required for login.</p>
        <p class="mb-0">Contact: use the <a href="<?php echo url('public/pages/contact.php'); ?>">contact</a> form.</p>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>
