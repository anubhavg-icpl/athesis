<?php
require_once '../config/config.php';

$page_title = 'Home';

// Get recent topics
$db = getDB();
$stmt = $db->prepare("
    SELECT t.*, u.display_name as author_name
    FROM topics t 
    JOIN users u ON t.user_id = u.id 
    ORDER BY t.is_pinned DESC, t.created_at DESC 
    LIMIT 8
");
$stmt->execute();
$recent_topics = $stmt->fetchAll();

// Get forum statistics
$stmt = $db->query("SELECT COUNT(*) FROM topics");
$total_topics = $stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM replies WHERE is_deleted = 0");
$total_replies = $stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM users WHERE is_active = 1");
$total_users = $stmt->fetchColumn();

// Get most active users
$stmt = $db->prepare("
    SELECT u.display_name, u.user_role, 
           COUNT(t.id) as topic_count,
           (SELECT COUNT(*) FROM replies r WHERE r.user_id = u.id AND r.is_deleted = 0) as reply_count
    FROM users u 
    LEFT JOIN topics t ON u.id = t.user_id
    WHERE u.is_active = 1
    GROUP BY u.id, u.display_name, u.user_role
    ORDER BY (COUNT(t.id) + (SELECT COUNT(*) FROM replies r WHERE r.user_id = u.id AND r.is_deleted = 0)) DESC
    LIMIT 5
");
$stmt->execute();
$active_users = $stmt->fetchAll();

include '../includes/header.php';
?>

<section class="ody-hero">
    <span class="label">community · forum</span>
    <h1>The <span class="accent-word">forum</span>.</h1>
    <p>Anyone can read. Members write. The knowledge stays open.</p>
    <p style="font-size:11px;letter-spacing:1px;color:#888;margin-bottom:2rem">
        <?php if (!is_logged_in()): ?>
            <a href="auth/login.php" style="color:#ff0033;border-bottom:1px solid rgba(255,0,51,.45)">$_ log in</a>
            to post · reading is open to all.
        <?php else: ?>
            <a href="forum/create_topic.php" style="color:#ff0033;border-bottom:1px solid rgba(255,0,51,.45)">$_ new topic</a>
            · sparse discussions. no noise. just signal.
        <?php endif; ?>
    </p>
    <div class="ody-hero-actions">
        <?php if (!is_logged_in()): ?>
            <a class="btn btn-primary" href="auth/register.php">join →</a>
            <a class="ody-link-btn" href="forum/topics.php">browse topics</a>
        <?php else: ?>
            <a class="btn btn-primary" href="forum/create_topic.php">new topic →</a>
            <a class="ody-link-btn" href="forum/topics.php">browse all</a>
            <a class="ody-link-btn" href="auth/profile.php">profile</a>
        <?php endif; ?>
    </div>
</section>

<div class="ody-stats">
    <div class="ody-stat">
        <span class="num"><?php echo (int) $total_topics; ?></span>
        <span class="lbl">topics</span>
    </div>
    <div class="ody-stat">
        <span class="num"><?php echo (int) $total_replies; ?></span>
        <span class="lbl">replies</span>
    </div>
    <div class="ody-stat">
        <span class="num"><?php echo (int) $total_users; ?></span>
        <span class="lbl">users</span>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <section class="ody-section">
            <div class="ody-section-head">
                <h2>recent topics</h2>
                <a href="forum/topics.php">view all →</a>
            </div>

            <?php if (empty($recent_topics)): ?>
                <div class="ody-empty">
                    <div class="icon">//</div>
                    <p>no topics yet. be the first to start a discussion.</p>
                    <?php if (is_logged_in()): ?>
                        <a href="forum/create_topic.php" class="btn btn-primary btn-sm mt-2">create first topic</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="ody-list">
                    <?php foreach ($recent_topics as $topic): ?>
                        <div class="ody-list-item">
                            <span class="marker">▸</span>
                            <div class="body">
                                <h3 class="title">
                                    <?php if (!empty($topic['is_pinned'])): ?>
                                        <span class="ody-flag" title="pinned">pin</span>
                                    <?php endif; ?>
                                    <?php if (!empty($topic['is_locked'])): ?>
                                        <span class="ody-flag lock" title="locked">lock</span>
                                    <?php endif; ?>
                                    <a href="forum/view_topic.php?id=<?php echo (int) $topic['id']; ?>">
                                        <?php echo sanitize_input($topic['title']); ?>
                                    </a>
                                </h3>
                                <p class="excerpt"><?php echo truncate_text(strip_tags($topic['content']), 110); ?></p>
                                <div class="meta">
                                    <span>by <strong><?php echo sanitize_input($topic['author_name']); ?></strong></span>
                                    <span><?php echo time_ago($topic['created_at']); ?></span>
                                    <span><?php echo (int) $topic['reply_count']; ?> replies</span>
                                    <span><?php echo (int) $topic['view_count']; ?> views</span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <div class="col-lg-4 ody-sidebar">
        <section class="ody-section">
            <div class="ody-section-head">
                <h2>active users</h2>
            </div>
            <?php if (empty($active_users)): ?>
                <div class="ody-empty">
                    <p>no active users yet.</p>
                </div>
            <?php else: ?>
                <div class="ody-list">
                    <?php foreach ($active_users as $user): ?>
                        <div class="ody-list-item">
                            <span class="marker">·</span>
                            <div class="body">
                                <h3 class="title"><?php echo sanitize_input($user['display_name']); ?></h3>
                                <div class="meta">
                                    <span><?php echo strtolower(sanitize_input($user['user_role'])); ?></span>
                                    <span><?php echo (int) ($user['topic_count'] + $user['reply_count']); ?> posts</span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <?php if (is_logged_in()): ?>
            <section class="ody-section">
                <div class="ody-section-head">
                    <h2>quick actions</h2>
                </div>
                <div class="ody-panel">
                    <div class="ody-panel-body d-grid gap-2">
                        <a href="forum/create_topic.php" class="btn btn-primary btn-sm">new topic</a>
                        <a href="forum/topics.php" class="btn btn-outline-secondary btn-sm">browse topics</a>
                        <a href="auth/profile.php" class="btn btn-outline-secondary btn-sm">edit profile</a>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </div>
</div>

<div class="ody-features">
    <div class="ody-feature">
        <div class="tag">01</div>
        <h5>threaded discussion</h5>
        <p>organized conversations with replies that stay readable and sparse.</p>
    </div>
    <div class="ody-feature">
        <div class="tag">02</div>
        <h5>search &amp; sort</h5>
        <p>find topics fast. filter by activity, replies, or views.</p>
    </div>
    <div class="ody-feature">
        <div class="tag">03</div>
        <h5>secure by default</h5>
        <p>auth, csrf, and validation keep the board clean and safe.</p>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
