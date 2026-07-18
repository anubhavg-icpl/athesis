<?php
require_once '../../config/config.php';
require_login();

if (!is_admin_or_moderator() && !is_logged_in()) {
    header('Location: ' . url('public/blog/index.php'));
    exit;
}

$db = getDB();
blog_publish_due_posts($db);

$errors = [];
$status_filter = $_GET['status'] ?? 'all';
$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));

// Bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        $action = $_POST['bulk_action'] ?? '';
        $ids = array_map('intval', $_POST['ids'] ?? []);
        $ids = array_values(array_filter($ids, fn($id) => $id > 0));

        if (empty($ids)) {
            $errors[] = 'Select at least one post.';
        } elseif (!in_array($action, ['publish', 'unpublish', 'delete'], true)) {
            $errors[] = 'Unknown bulk action.';
        } else {
            // Scope: admin/mod all; author only own
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $params = $ids;
            $owner_sql = '';
            if (!is_admin_or_moderator()) {
                $owner_sql = ' AND user_id = ?';
                $params[] = (int) $_SESSION['user_id'];
            }

            if ($action === 'delete') {
                $stmt = $db->prepare("DELETE FROM blog_posts WHERE id IN ($placeholders) $owner_sql");
                $stmt->execute($params);
                $_SESSION['flash_message'] = 'Deleted ' . $stmt->rowCount() . ' post(s).';
            } elseif ($action === 'publish') {
                $stmt = $db->prepare("
                    UPDATE blog_posts
                    SET status = 'published',
                        published_at = COALESCE(published_at, NOW()),
                        scheduled_at = NULL
                    WHERE id IN ($placeholders) $owner_sql
                ");
                $stmt->execute($params);
                $_SESSION['flash_message'] = 'Published ' . $stmt->rowCount() . ' post(s).';
            } else { // unpublish
                $stmt = $db->prepare("
                    UPDATE blog_posts
                    SET status = 'draft', scheduled_at = NULL
                    WHERE id IN ($placeholders) $owner_sql
                ");
                $stmt->execute($params);
                $_SESSION['flash_message'] = 'Unpublished ' . $stmt->rowCount() . ' post(s).';
            }
            $_SESSION['flash_type'] = 'success';
            header('Location: ' . url('public/blog/admin.php?status=' . urlencode($status_filter) . '&q=' . urlencode($q)));
            exit;
        }
    }
}

$where = ['1=1'];
$params = [];
if (!is_admin_or_moderator()) {
    $where[] = 'p.user_id = ?';
    $params[] = (int) $_SESSION['user_id'];
}
if (in_array($status_filter, ['draft', 'published', 'scheduled'], true)) {
    $where[] = 'p.status = ?';
    $params[] = $status_filter;
}
if ($q !== '') {
    $where[] = '(p.title LIKE ? OR p.slug LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
$where_sql = implode(' AND ', $where);

$stmt = $db->prepare("SELECT COUNT(*) FROM blog_posts p WHERE $where_sql");
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();
$pagination = get_pagination(max(1, $total) ? $total : 0, 20, $page);
// fix empty total
if ($total === 0) {
    $pagination = [
        'total_items' => 0, 'total_pages' => 0, 'current_page' => 1,
        'items_per_page' => 20, 'offset' => 0, 'has_prev' => false, 'has_next' => false,
    ];
}

$sql = "
    SELECT p.*, u.display_name AS author_name
    FROM blog_posts p
    JOIN users u ON u.id = p.user_id
    WHERE $where_sql
    ORDER BY p.updated_at DESC
    LIMIT ? OFFSET ?
";
$params[] = 20;
$params[] = $pagination['offset'];
$stmt = $db->prepare($sql);
$stmt->execute($params);
$posts = $stmt->fetchAll();

// stats
$stats = ['all' => 0, 'draft' => 0, 'published' => 0, 'scheduled' => 0];
$stat_sql = 'SELECT status, COUNT(*) c FROM blog_posts';
$stat_params = [];
if (!is_admin_or_moderator()) {
    $stat_sql .= ' WHERE user_id = ?';
    $stat_params[] = (int) $_SESSION['user_id'];
}
$stat_sql .= ' GROUP BY status';
$st = $db->prepare($stat_sql);
$st->execute($stat_params);
foreach ($st->fetchAll() as $row) {
    $stats[$row['status']] = (int) $row['c'];
    $stats['all'] += (int) $row['c'];
}

$page_title = 'Blog admin';
include '../../includes/header.php';
?>

<div class="ody-page-head">
    <h1><span class="prompt">$_</span>blog admin</h1>
    <div class="d-flex gap-3">
        <a class="btn btn-primary btn-sm" href="<?php echo url('public/blog/write.php'); ?>">write →</a>
        <a class="ody-link-btn" href="<?php echo url('public/blog/media.php'); ?>">media</a>
        <?php if (is_admin_or_moderator()): ?>
        <a class="ody-link-btn" href="<?php echo url('public/blog/moderate.php'); ?>">moderate</a>
        <a class="ody-link-btn" href="<?php echo url('public/blog/subscribers.php'); ?>">subscribers</a>
        <?php endif; ?>
        <a class="ody-link-btn" href="<?php echo url('public/blog/index.php'); ?>">← public blog</a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?php echo sanitize_input($e); ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="ody-stats cols-4 mb-4">
    <div class="ody-stat"><span class="num"><?php echo $stats['all']; ?></span><span class="lbl">all</span></div>
    <div class="ody-stat"><span class="num"><?php echo $stats['published']; ?></span><span class="lbl">published</span></div>
    <div class="ody-stat"><span class="num"><?php echo $stats['draft']; ?></span><span class="lbl">draft</span></div>
    <div class="ody-stat"><span class="num"><?php echo $stats['scheduled']; ?></span><span class="lbl">scheduled</span></div>
</div>

<form method="GET" class="ody-panel mb-3">
    <div class="ody-panel-body row g-3">
        <div class="col-md-5">
            <label class="form-label" for="q">search</label>
            <input class="form-control" type="text" name="q" id="q" value="<?php echo sanitize_input($q); ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="status">status</label>
            <select class="form-select" name="status" id="status">
                <?php foreach (['all','published','draft','scheduled'] as $s): ?>
                    <option value="<?php echo $s; ?>" <?php echo $status_filter === $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <button class="btn btn-outline-primary w-100" type="submit">filter</button>
        </div>
    </div>
</form>

<form method="POST" action="">
    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

    <div class="ody-panel mb-3">
        <div class="ody-panel-body d-flex flex-wrap gap-2 align-items-center">
            <select name="bulk_action" class="form-select" style="max-width:12rem" required>
                <option value="">bulk action…</option>
                <option value="publish">publish</option>
                <option value="unpublish">unpublish → draft</option>
                <option value="delete">delete</option>
            </select>
            <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Apply bulk action to selected posts?');">apply</button>
            <span class="small" style="color:var(--text-mute)">select rows below</span>
        </div>
    </div>

    <?php if (empty($posts)): ?>
        <div class="ody-empty"><p>no posts match.</p></div>
    <?php else: ?>
        <div class="table-responsive ody-admin-table">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width:2rem"><input type="checkbox" id="check-all" aria-label="Select all"></th>
                        <th>title</th>
                        <th>status</th>
                        <th class="d-none d-md-table-cell">author</th>
                        <th class="d-none d-md-table-cell">updated</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($posts as $p): ?>
                        <tr>
                            <td><input type="checkbox" name="ids[]" value="<?php echo (int) $p['id']; ?>" class="row-check"></td>
                            <td>
                                <strong><?php echo sanitize_input($p['title']); ?></strong>
                                <div class="small" style="color:var(--text-mute)"><?php echo sanitize_input($p['slug']); ?></div>
                            </td>
                            <td>
                                <span class="badge <?php
                                    echo $p['status'] === 'published' ? 'bg-success' : ($p['status'] === 'scheduled' ? 'bg-warning' : 'bg-secondary');
                                ?>"><?php echo sanitize_input($p['status']); ?></span>
                                <?php if ($p['status'] === 'scheduled' && !empty($p['scheduled_at'])): ?>
                                    <div class="small" style="color:var(--text-dim)"><?php echo format_date($p['scheduled_at']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="d-none d-md-table-cell"><?php echo sanitize_input($p['author_name']); ?></td>
                            <td class="d-none d-md-table-cell small"><?php echo format_date($p['updated_at']); ?></td>
                            <td class="text-end">
                                <a class="ody-link-btn" href="<?php echo url('public/blog/write.php?id=' . (int) $p['id']); ?>">edit</a>
                                <?php if ($p['status'] === 'published'): ?>
                                    · <a class="ody-link-btn" href="<?php echo url('public/blog/post.php?slug=' . urlencode($p['slug'])); ?>">view</a>
                                <?php else: ?>
                                    · <a class="ody-link-btn" href="<?php echo url('public/blog/post.php?slug=' . urlencode($p['slug']) . '&preview=1'); ?>">preview</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</form>

<?php if ($pagination['total_pages'] > 1): ?>
<nav class="mt-4">
    <ul class="pagination justify-content-center">
        <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
            <li class="page-item <?php echo $i == $pagination['current_page'] ? 'active' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status_filter); ?>&q=<?php echo urlencode($q); ?>"><?php echo $i; ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<script>
document.getElementById('check-all')?.addEventListener('change', function () {
    document.querySelectorAll('.row-check').forEach(function (c) { c.checked = this.checked; }.bind(this));
});
</script>

<?php include '../../includes/footer.php'; ?>
