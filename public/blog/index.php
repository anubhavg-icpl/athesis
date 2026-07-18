<?php
require_once '../../config/config.php';

$page_title = 'Blog';
$page_description = 'Published articles, guides, and notes.';
$page = max(1, (int)($_GET['page'] ?? 1));
$search = trim($_GET['q'] ?? '');
$category = trim($_GET['category'] ?? '');
$tag = trim($_GET['tag'] ?? '');

$db = getDB();
blog_publish_due_posts($db);
$where = ["p.status = 'published'"];
$params = [];

if ($search !== '') {
    $where[] = '(p.title LIKE ? OR p.excerpt LIKE ? OR p.content LIKE ?)';
    $term = '%' . $search . '%';
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}
if ($category !== '') {
    $where[] = 'c.slug = ?';
    $params[] = $category;
}
if ($tag !== '') {
    $where[] = 'EXISTS (
        SELECT 1 FROM blog_post_tags pt
        JOIN blog_tags tg ON tg.id = pt.tag_id
        WHERE pt.post_id = p.id AND tg.slug = ?
    )';
    $params[] = $tag;
}

$where_sql = implode(' AND ', $where);

$count_sql = "
    SELECT COUNT(*) FROM blog_posts p
    LEFT JOIN blog_categories c ON c.id = p.category_id
    WHERE $where_sql
";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();
$pagination = get_pagination($total, POSTS_PER_PAGE, $page);

$sql = "
    SELECT p.*, u.display_name AS author_name, c.name AS category_name, c.slug AS category_slug
    FROM blog_posts p
    JOIN users u ON u.id = p.user_id
    LEFT JOIN blog_categories c ON c.id = p.category_id
    WHERE $where_sql
    ORDER BY p.published_at DESC, p.id DESC
    LIMIT ? OFFSET ?
";
$params[] = POSTS_PER_PAGE;
$params[] = $pagination['offset'];
$stmt = $db->prepare($sql);
$stmt->execute($params);
$posts = $stmt->fetchAll();

$categories = blog_get_categories($db);
$tags = blog_get_tags($db);

include '../../includes/header.php';
?>

<section class="ody-hero">
    <span class="label">blog · publish</span>
    <h1>The <span class="accent-word">blog</span>.</h1>
    <p>Guides, notes, and long-form. Sparse signal. No noise.</p>
    <div class="ody-hero-actions">
        <?php if (is_logged_in()): ?>
            <a class="btn btn-primary" href="<?php echo url('public/blog/write.php'); ?>">write →</a>
        <?php endif; ?>
        <a class="ody-link-btn" href="<?php echo url('public/blog/archive.php'); ?>">archive</a>
        <a class="ody-link-btn" href="<?php echo url('public/blog/series.php'); ?>">series</a>
        <a class="ody-link-btn" href="<?php echo url('public/blog/rss.php'); ?>">rss</a>
        <a class="ody-link-btn" href="<?php echo url('public/blog/sitemap.php'); ?>">sitemap</a>
    </div>
</section>

<form method="GET" action="" class="ody-panel mb-4">
    <div class="ody-panel-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="q">search</label>
                <input type="text" class="form-control" id="q" name="q" value="<?php echo sanitize_input($search); ?>" placeholder="title or content…">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="category">category</label>
                <select class="form-select" id="category" name="category">
                    <option value="">all</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo sanitize_input($cat['slug']); ?>" <?php echo $category === $cat['slug'] ? 'selected' : ''; ?>>
                            <?php echo sanitize_input($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-outline-primary w-100">filter</button>
            </div>
        </div>
        <?php if ($tag !== ''): ?>
            <input type="hidden" name="tag" value="<?php echo sanitize_input($tag); ?>">
            <div class="mt-3 small" style="color:var(--text-dim)">
                tag: <strong><?php echo sanitize_input($tag); ?></strong>
                · <a href="<?php echo url('public/blog/index.php'); ?>">clear</a>
            </div>
        <?php endif; ?>
    </div>
</form>

<?php if (!empty($tags)): ?>
<div class="ody-blog-tags mb-4">
    <?php foreach ($tags as $t): ?>
        <a class="ody-chip <?php echo $tag === $t['slug'] ? 'active' : ''; ?>"
           href="<?php echo url('public/blog/index.php?tag=' . urlencode($t['slug'])); ?>">
            #<?php echo sanitize_input($t['name']); ?>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (empty($posts)): ?>
    <div class="ody-empty">
        <div class="icon">//</div>
        <p>no published posts yet.</p>
        <?php if (is_logged_in()): ?>
            <a class="btn btn-primary btn-sm mt-2" href="<?php echo url('public/blog/write.php'); ?>">write the first one</a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="ody-list">
        <?php foreach ($posts as $post): ?>
            <a class="ody-list-item" href="<?php echo url('public/blog/post.php?slug=' . urlencode($post['slug'])); ?>">
                <span class="marker" aria-hidden="true">▸</span>
                <div class="body">
                    <h3 class="title">
                        <span class="title-text"><?php echo sanitize_input($post['title']); ?></span>
                    </h3>
                    <?php if (!empty($post['excerpt'])): ?>
                        <p class="excerpt"><?php echo sanitize_input(truncate_text($post['excerpt'], 160)); ?></p>
                    <?php endif; ?>
                    <div class="meta">
                        <span>by <strong><?php echo sanitize_input($post['author_name']); ?></strong></span>
                        <span><?php echo $post['published_at'] ? format_date($post['published_at']) : ''; ?></span>
                        <span><?php echo blog_reading_time($post['content']); ?></span>
                        <?php if (!empty($post['category_name'])): ?>
                            <span><?php echo sanitize_input($post['category_name']); ?></span>
                        <?php endif; ?>
                        <span><?php echo (int) $post['view_count']; ?> views</span>
                    </div>
                </div>
                <div class="stats">
                    <span class="open-hint">read →</span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($pagination['total_pages'] > 1): ?>
        <nav class="mt-4" aria-label="Blog pagination">
            <ul class="pagination justify-content-center">
                <?php if ($pagination['has_prev']): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $pagination['current_page'] - 1; ?>&q=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&tag=<?php echo urlencode($tag); ?>">prev</a>
                    </li>
                <?php endif; ?>
                <?php for ($i = max(1, $pagination['current_page'] - 2); $i <= min($pagination['total_pages'], $pagination['current_page'] + 2); $i++): ?>
                    <li class="page-item <?php echo $i == $pagination['current_page'] ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&q=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&tag=<?php echo urlencode($tag); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <?php if ($pagination['has_next']): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $pagination['current_page'] + 1; ?>&q=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&tag=<?php echo urlencode($tag); ?>">next</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<?php include '../../includes/partials/newsletter.php'; ?>
<?php include '../../includes/footer.php'; ?>
