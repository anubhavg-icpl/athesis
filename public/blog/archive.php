<?php
require_once '../../config/config.php';

$db = getDB();
blog_publish_due_posts($db);

$year = isset($_GET['y']) ? (int) $_GET['y'] : 0;
$month = isset($_GET['m']) ? (int) $_GET['m'] : 0;

$page_title = 'Blog archive';
$page_description = 'Posts by month and year.';

// Month buckets
$months = $db->query("
    SELECT YEAR(published_at) y, MONTH(published_at) m, COUNT(*) c
    FROM blog_posts
    WHERE status = 'published' AND published_at IS NOT NULL
    GROUP BY YEAR(published_at), MONTH(published_at)
    ORDER BY y DESC, m DESC
")->fetchAll();

$posts = [];
if ($year > 2000 && $month >= 1 && $month <= 12) {
    $stmt = $db->prepare("
        SELECT p.title, p.slug, p.excerpt, p.published_at, u.display_name AS author_name
        FROM blog_posts p
        JOIN users u ON u.id = p.user_id
        WHERE p.status = 'published'
          AND YEAR(p.published_at) = ?
          AND MONTH(p.published_at) = ?
        ORDER BY p.published_at DESC
    ");
    $stmt->execute([$year, $month]);
    $posts = $stmt->fetchAll();
    $page_title = date('F Y', mktime(0, 0, 0, $month, 1, $year)) . ' · archive';
}

include '../../includes/header.php';
?>

<div class="ody-page-head">
    <h1><span class="prompt">$_</span>archive</h1>
    <a class="ody-link-btn" href="<?php echo url('public/blog/index.php'); ?>">← blog</a>
</div>

<div class="row g-4">
    <div class="col-md-4">
        <div class="ody-panel">
            <div class="ody-panel-head">months</div>
            <div class="ody-panel-body">
                <?php if (empty($months)): ?>
                    <p class="mb-0" style="color:var(--text-mute)">no published posts yet.</p>
                <?php else: ?>
                    <ul class="ody-archive-list">
                        <?php foreach ($months as $row): ?>
                            <li>
                                <a href="?y=<?php echo (int)$row['y']; ?>&m=<?php echo (int)$row['m']; ?>">
                                    <?php echo date('M Y', mktime(0, 0, 0, (int)$row['m'], 1, (int)$row['y'])); ?>
                                    <span>(<?php echo (int)$row['c']; ?>)</span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <?php if ($year && $month): ?>
            <?php if (empty($posts)): ?>
                <div class="ody-empty"><p>no posts this month.</p></div>
            <?php else: ?>
                <div class="ody-list">
                    <?php foreach ($posts as $p): ?>
                        <a class="ody-list-item" href="<?php echo url('public/blog/post.php?slug=' . urlencode($p['slug'])); ?>">
                            <span class="marker">▸</span>
                            <div class="body">
                                <h3 class="title"><span class="title-text"><?php echo sanitize_input($p['title']); ?></span></h3>
                                <div class="meta">
                                    <span><?php echo sanitize_input($p['author_name']); ?></span>
                                    <span><?php echo format_date($p['published_at']); ?></span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="ody-empty"><p>pick a month →</p></div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
