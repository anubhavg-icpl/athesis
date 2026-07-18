<?php
require_once '../../config/config.php';

$page_title = 'Forum Topics';
$page = max(1, (int)($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');
$sort = $_GET['sort'] ?? 'latest';

// Build search query
$where_clause = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $where_clause .= " AND (t.title LIKE ? OR t.content LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
}

// Build sort clause
$sort_clause = match($sort) {
    'oldest' => 'ORDER BY t.is_pinned DESC, t.created_at ASC',
    'replies' => 'ORDER BY t.is_pinned DESC, t.reply_count DESC, t.created_at DESC',
    'views' => 'ORDER BY t.is_pinned DESC, t.view_count DESC, t.created_at DESC',
    default => 'ORDER BY t.is_pinned DESC, t.last_reply_at DESC, t.created_at DESC'
};

// Get total count for pagination
$db = getDB();
$count_sql = "SELECT COUNT(*) FROM topics t $where_clause";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_topics = $stmt->fetchColumn();

$pagination = get_pagination($total_topics, TOPICS_PER_PAGE, $page);

// Get topics
$offset = $pagination['offset'];
$sql = "
    SELECT t.*, 
           u.username, u.display_name as author_name,
           lr_u.display_name as last_reply_author
    FROM topics t 
    JOIN users u ON t.user_id = u.id 
    LEFT JOIN users lr_u ON t.last_reply_user_id = lr_u.id
    $where_clause 
    $sort_clause 
    LIMIT ? OFFSET ?
";

$params[] = TOPICS_PER_PAGE;
$params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$topics = $stmt->fetchAll();

include '../../includes/header.php';
?>

<div class="ody-page-head">
    <h1><span class="prompt">$_</span>topics</h1>
    <?php if (is_logged_in()): ?>
        <a href="create_topic.php" class="btn btn-primary btn-sm">new topic</a>
    <?php endif; ?>
</div>

<div class="ody-panel mb-4">
    <div class="ody-panel-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-6">
                <label for="search" class="form-label">search</label>
                <input type="text" class="form-control" id="search" name="search"
                       value="<?php echo sanitize_input($search); ?>"
                       placeholder="title or content...">
            </div>
            <div class="col-md-4">
                <label for="sort" class="form-label">sort</label>
                <select class="form-select" id="sort" name="sort">
                    <option value="latest" <?php echo $sort === 'latest' ? 'selected' : ''; ?>>latest activity</option>
                    <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>oldest first</option>
                    <option value="replies" <?php echo $sort === 'replies' ? 'selected' : ''; ?>>most replies</option>
                    <option value="views" <?php echo $sort === 'views' ? 'selected' : ''; ?>>most views</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-outline-primary w-100">search</button>
            </div>
        </form>

        <?php if (!empty($search)): ?>
            <div class="mt-3">
                <small class="text-muted">
                    results for: <strong style="color: var(--text-dim);"><?php echo sanitize_input($search); ?></strong>
                    · <a href="topics.php">clear</a>
                </small>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($topics)): ?>
    <div class="ody-empty ody-empty-art">
        <img class="ody-empty-img" src="<?php echo url('public/images/brand/empty-void.jpg'); ?>" alt="" width="180" height="240" loading="lazy">
        <div class="icon">//</div>
        <?php if (!empty($search)): ?>
            <p>no topics match your search.</p>
        <?php else: ?>
            <p>no topics. crickets, but make it monochrome.
            <?php if (is_logged_in()): ?>
                <a href="create_topic.php">start the first one</a>
            <?php else: ?>
                <a href="../auth/login.php">login</a> if you insist on existing here
            <?php endif; ?>
            </p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="ody-list">
        <?php foreach ($topics as $topic): ?>
            <a class="ody-list-item" href="<?php echo url('public/forum/view_topic.php?id=' . (int) $topic['id']); ?>">
                <span class="marker" aria-hidden="true">▸</span>
                <div class="body">
                    <h3 class="title">
                        <?php if (!empty($topic['is_pinned'])): ?>
                            <span class="ody-flag" title="pinned">pin</span>
                        <?php endif; ?>
                        <?php if (!empty($topic['is_locked'])): ?>
                            <span class="ody-flag lock" title="locked">lock</span>
                        <?php endif; ?>
                        <span class="title-text"><?php echo sanitize_input($topic['title']); ?></span>
                    </h3>
                    <div class="meta">
                        <span>by <strong><?php echo sanitize_input($topic['author_name']); ?></strong></span>
                        <span><?php echo time_ago($topic['created_at']); ?></span>
                        <?php if (!empty($topic['last_reply_at'])): ?>
                            <span>last: <?php echo time_ago($topic['last_reply_at']); ?>
                                <?php if (!empty($topic['last_reply_author'])): ?>
                                    by <?php echo sanitize_input($topic['last_reply_author']); ?>
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="stats">
                    <span class="open-hint">open →</span>
                    <span class="d-none d-md-block"><?php echo (int) $topic['reply_count']; ?> rpl</span>
                    <span class="d-none d-md-block"><?php echo (int) $topic['view_count']; ?> views</span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($pagination['total_pages'] > 1): ?>
        <nav aria-label="Topics pagination" class="mt-4">
            <ul class="pagination justify-content-center">
                <?php if ($pagination['has_prev']): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $pagination['current_page'] - 1; ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo sanitize_input($sort); ?>">prev</a>
                    </li>
                <?php endif; ?>

                <?php for ($i = max(1, $pagination['current_page'] - 2); $i <= min($pagination['total_pages'], $pagination['current_page'] + 2); $i++): ?>
                    <li class="page-item <?php echo $i == $pagination['current_page'] ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo sanitize_input($sort); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>

                <?php if ($pagination['has_next']): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $pagination['current_page'] + 1; ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo sanitize_input($sort); ?>">next</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>

        <div class="text-center text-muted small mt-2">
            showing <?php echo $pagination['offset'] + 1; ?>–<?php echo min($pagination['offset'] + TOPICS_PER_PAGE, $total_topics); ?>
            of <?php echo (int) $total_topics; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php
$stmt = $db->query("SELECT COUNT(*) FROM topics");
$total_topics_count = $stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM replies WHERE is_deleted = 0");
$total_replies_count = $stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM users WHERE is_active = 1");
$total_users_count = $stmt->fetchColumn();

$stmt = $db->query("SELECT display_name FROM users WHERE is_active = 1 ORDER BY join_date DESC LIMIT 1");
$newest_user = $stmt->fetchColumn();
?>

<div class="ody-stats cols-4 mt-4">
    <div class="ody-stat">
        <span class="num"><?php echo (int) $total_topics_count; ?></span>
        <span class="lbl">topics</span>
    </div>
    <div class="ody-stat">
        <span class="num"><?php echo (int) $total_replies_count; ?></span>
        <span class="lbl">replies</span>
    </div>
    <div class="ody-stat">
        <span class="num"><?php echo (int) $total_users_count; ?></span>
        <span class="lbl">users</span>
    </div>
    <div class="ody-stat">
        <span class="num" style="font-size: 0.85rem;"><?php echo sanitize_input($newest_user ?: '—'); ?></span>
        <span class="lbl">newest</span>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
