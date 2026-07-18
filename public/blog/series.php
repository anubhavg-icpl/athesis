<?php
require_once '../../config/config.php';

$db = getDB();
blog_publish_due_posts($db);
$slug = trim($_GET['slug'] ?? '');

$page_title = 'Series';

if ($slug !== '') {
    $stmt = $db->prepare('SELECT * FROM blog_series WHERE slug = ? LIMIT 1');
    $stmt->execute([$slug]);
    $series = $stmt->fetch();
    if (!$series) {
        http_response_code(404);
        $page_title = 'Series not found';
        include '../../includes/header.php';
        echo '<div class="ody-empty"><p>series not found.</p></div>';
        include '../../includes/footer.php';
        exit;
    }
    $page_title = $series['title'];
    $posts = blog_series_posts($db, (int) $series['id']);
    include '../../includes/header.php';
    ?>
    <div class="ody-page-head">
        <h1><span class="prompt">$_</span><?php echo sanitize_input($series['title']); ?></h1>
        <a class="ody-link-btn" href="<?php echo url('public/blog/series.php'); ?>">← all series</a>
    </div>
    <?php if (!empty($series['description'])): ?>
        <p style="color:var(--text-dim)"><?php echo sanitize_input($series['description']); ?></p>
    <?php endif; ?>
    <div class="ody-list">
        <?php foreach ($posts as $i => $p): ?>
            <a class="ody-list-item" href="<?php echo url('public/blog/post.php?slug=' . urlencode($p['slug'])); ?>">
                <span class="marker"><?php echo (int)($p['series_order'] ?: ($i + 1)); ?></span>
                <div class="body">
                    <h3 class="title"><span class="title-text"><?php echo sanitize_input($p['title']); ?></span></h3>
                </div>
                <div class="stats"><span class="open-hint">read →</span></div>
            </a>
        <?php endforeach; ?>
        <?php if (empty($posts)): ?>
            <div class="ody-empty"><p>no published parts yet.</p></div>
        <?php endif; ?>
    </div>
    <?php
    include '../../includes/footer.php';
    exit;
}

$all = $db->query('
    SELECT s.*, COUNT(p.id) AS post_count
    FROM blog_series s
    LEFT JOIN blog_posts p ON p.series_id = s.id AND p.status = "published"
    GROUP BY s.id
    ORDER BY s.title ASC
')->fetchAll();

include '../../includes/header.php';
?>
<div class="ody-page-head">
    <h1><span class="prompt">$_</span>series</h1>
    <a class="ody-link-btn" href="<?php echo url('public/blog/index.php'); ?>">← blog</a>
</div>
<div class="ody-list">
    <?php foreach ($all as $s): ?>
        <a class="ody-list-item" href="<?php echo url('public/blog/series.php?slug=' . urlencode($s['slug'])); ?>">
            <span class="marker">▸</span>
            <div class="body">
                <h3 class="title"><span class="title-text"><?php echo sanitize_input($s['title']); ?></span></h3>
                <div class="meta"><span><?php echo (int)$s['post_count']; ?> parts</span></div>
            </div>
        </a>
    <?php endforeach; ?>
</div>
<?php include '../../includes/footer.php'; ?>
