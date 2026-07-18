<?php
require_once '../../config/config.php';
require_login();
if (!is_admin_or_moderator()) {
    header('Location: ' . url('public/blog/admin.php'));
    exit;
}
$db = getDB();
$rows = $db->query('SELECT * FROM blog_subscribers ORDER BY created_at DESC LIMIT 200')->fetchAll();
$page_title = 'Subscribers';
include '../../includes/header.php';
?>
<div class="ody-page-head">
    <h1><span class="prompt">$_</span>subscribers</h1>
    <a class="ody-link-btn" href="<?php echo url('public/blog/admin.php'); ?>">← admin</a>
</div>
<div class="table-responsive ody-admin-table">
    <table class="table mb-0">
        <thead><tr><th>email</th><th>status</th><th>joined</th></tr></thead>
        <tbody>
        <?php if (empty($rows)): ?>
            <tr><td colspan="3" style="color:var(--text-mute)">none yet</td></tr>
        <?php else: foreach ($rows as $r): ?>
            <tr>
                <td><?php echo sanitize_input($r['email']); ?></td>
                <td><?php echo !empty($r['is_active']) ? 'active' : 'off'; ?></td>
                <td class="small"><?php echo format_date($r['created_at']); ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
<?php include '../../includes/footer.php'; ?>
