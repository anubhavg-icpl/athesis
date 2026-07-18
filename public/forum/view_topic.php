<?php
require_once '../../config/config.php';

$topic_id = (int)($_GET['id'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));

if (!$topic_id) {
    header('Location: topics.php');
    exit;
}

$errors = [];
$reply_success = false;

// Get topic details
$db = getDB();
$stmt = $db->prepare("
    SELECT t.*, u.username, u.display_name, u.user_role, u.join_date
    FROM topics t 
    JOIN users u ON t.user_id = u.id 
    WHERE t.id = ?
");
$stmt->execute([$topic_id]);
$topic = $stmt->fetch();

if (!$topic) {
    header('Location: topics.php');
    exit;
}

$page_title = $topic['title'];

// Update view count
$stmt = $db->prepare("UPDATE topics SET view_count = view_count + 1 WHERE id = ?");
$stmt->execute([$topic_id]);

// Process reply submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_logged_in()) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $content = trim($_POST['content'] ?? '');
        $parent_reply_id = !empty($_POST['parent_reply_id']) ? (int)$_POST['parent_reply_id'] : null;
        
        // Validate input
        if (empty($content)) {
            $errors[] = 'Reply content is required.';
        } elseif (strlen($content) < 5) {
            $errors[] = 'Reply content must be at least 5 characters long.';
        }
        
        // Check if topic is locked
        if ($topic['is_locked'] && !is_admin_or_moderator()) {
            $errors[] = 'This topic is locked and cannot receive new replies.';
        }
        
        // Create reply if no errors
        if (empty($errors)) {
            try {
                $clean_content = clean_content($content);
                
                $stmt = $db->prepare("INSERT INTO replies (topic_id, user_id, content, parent_reply_id) VALUES (?, ?, ?, ?)");
                $stmt->execute([$topic_id, $_SESSION['user_id'], $clean_content, $parent_reply_id]);
                
                // Update topic reply count and last reply info
                $stmt = $db->prepare("UPDATE topics SET reply_count = reply_count + 1, last_reply_at = NOW(), last_reply_user_id = ? WHERE id = ?");
                $stmt->execute([$_SESSION['user_id'], $topic_id]);
                
                $reply_success = true;
                $_SESSION['flash_message'] = 'Reply posted successfully!';
                $_SESSION['flash_type'] = 'success';
                header("Location: view_topic.php?id=$topic_id&page=$page#replies");
                exit;
            } catch (PDOException $e) {
                $errors[] = 'Failed to post reply. Please try again.';
            }
        }
    }
}

// Get replies with pagination
$offset = ($page - 1) * REPLIES_PER_PAGE;
$stmt = $db->prepare("
    SELECT r.*, u.username, u.display_name, u.user_role, u.join_date,
           parent_u.display_name as parent_author
    FROM replies r 
    JOIN users u ON r.user_id = u.id 
    LEFT JOIN replies parent_r ON r.parent_reply_id = parent_r.id
    LEFT JOIN users parent_u ON parent_r.user_id = parent_u.id
    WHERE r.topic_id = ? AND r.is_deleted = 0
    ORDER BY r.created_at ASC 
    LIMIT ? OFFSET ?
");
$stmt->execute([$topic_id, REPLIES_PER_PAGE, $offset]);
$replies = $stmt->fetchAll();

// Get total reply count for pagination
$stmt = $db->prepare("SELECT COUNT(*) FROM replies WHERE topic_id = ? AND is_deleted = 0");
$stmt->execute([$topic_id]);
$total_replies = $stmt->fetchColumn();

$pagination = get_pagination($total_replies, REPLIES_PER_PAGE, $page);

include '../../includes/header.php';
?>

<div class="ody-page-head">
    <h1>
        <?php if (!empty($topic['is_pinned'])): ?>
            <span class="ody-flag">pin</span>
        <?php endif; ?>
        <?php if (!empty($topic['is_locked'])): ?>
            <span class="ody-flag lock">lock</span>
        <?php endif; ?>
        <?php echo sanitize_input($topic['title']); ?>
    </h1>
    <a href="topics.php" class="ody-link-btn">← topics</a>
</div>

<article class="ody-post">
    <div class="ody-post-head">
        <div>
            <div class="author"><?php echo sanitize_input($topic['display_name']); ?></div>
            <div class="meta">
                <?php echo strtolower(sanitize_input($topic['user_role'])); ?>
                · <?php echo time_ago($topic['created_at']); ?>
                · <?php echo (int) $topic['view_count']; ?> views
                · <?php echo (int) $topic['reply_count']; ?> replies
            </div>
        </div>
        <div>
            <?php if (!empty($topic['is_pinned'])): ?>
                <span class="badge bg-warning">pinned</span>
            <?php endif; ?>
            <?php if (!empty($topic['is_locked'])): ?>
                <span class="badge bg-danger">locked</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="ody-post-body topic-content">
        <?php echo $topic['content']; ?>
    </div>
    <div class="ody-post-foot">
        <span>member since <?php echo format_date($topic['join_date']); ?></span>
        <div class="d-flex gap-3">
            <?php if (is_logged_in() && ($_SESSION['user_id'] == $topic['user_id'] || is_admin_or_moderator())): ?>
                <a href="edit_topic.php?id=<?php echo $topic_id; ?>" class="ody-link-btn">edit</a>
            <?php endif; ?>
        </div>
    </div>
</article>

<section id="replies" class="ody-section mt-4">
    <div class="ody-section-head">
        <h2>replies (<?php echo (int) $total_replies; ?>)</h2>
    </div>

    <?php if (empty($replies)): ?>
        <div class="ody-empty">
            <div class="icon">//</div>
            <p>no replies yet. be the first.</p>
        </div>
    <?php else: ?>
        <?php foreach ($replies as $reply): ?>
            <article class="ody-post" id="reply-<?php echo (int) $reply['id']; ?>">
                <div class="ody-post-head">
                    <div>
                        <div class="author"><?php echo sanitize_input($reply['display_name']); ?></div>
                        <div class="meta">
                            <?php echo strtolower(sanitize_input($reply['user_role'])); ?>
                            · <?php echo time_ago($reply['created_at']); ?>
                            <?php if (!empty($reply['parent_reply_id'])): ?>
                                · replying to <?php echo sanitize_input($reply['parent_author']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="ody-post-body reply-content">
                    <?php echo $reply['content']; ?>
                </div>
                <div class="ody-post-foot">
                    <span></span>
                    <div class="d-flex gap-3">
                        <?php if (is_logged_in() && !$topic['is_locked']): ?>
                            <button type="button" class="ody-link-btn reply-btn"
                                    data-reply-id="<?php echo (int) $reply['id']; ?>"
                                    data-author="<?php echo sanitize_input($reply['display_name']); ?>">
                                reply
                            </button>
                        <?php endif; ?>
                        <?php if (is_logged_in() && ($_SESSION['user_id'] == $reply['user_id'] || is_admin_or_moderator())): ?>
                            <a href="edit_reply.php?id=<?php echo (int) $reply['id']; ?>" class="ody-link-btn">edit</a>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>

        <?php if ($pagination['total_pages'] > 1): ?>
            <nav aria-label="Replies pagination" class="mt-3">
                <ul class="pagination justify-content-center">
                    <?php if ($pagination['has_prev']): ?>
                        <li class="page-item">
                            <a class="page-link" href="?id=<?php echo $topic_id; ?>&page=<?php echo $pagination['current_page'] - 1; ?>#replies">prev</a>
                        </li>
                    <?php endif; ?>

                    <?php for ($i = max(1, $pagination['current_page'] - 2); $i <= min($pagination['total_pages'], $pagination['current_page'] + 2); $i++): ?>
                        <li class="page-item <?php echo $i == $pagination['current_page'] ? 'active' : ''; ?>">
                            <a class="page-link" href="?id=<?php echo $topic_id; ?>&page=<?php echo $i; ?>#replies"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($pagination['has_next']): ?>
                        <li class="page-item">
                            <a class="page-link" href="?id=<?php echo $topic_id; ?>&page=<?php echo $pagination['current_page'] + 1; ?>#replies">next</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php if (is_logged_in() && !$topic['is_locked']): ?>
    <div class="ody-panel mt-4">
        <div class="ody-panel-head">post a reply</div>
        <div class="ody-panel-body">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo sanitize_input($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="reply-form">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="parent_reply_id" id="parent_reply_id" value="">

                <div id="reply-context" class="alert alert-info" style="display: none;">
                    replying to <strong id="reply-author"></strong>
                    <button type="button" class="btn-close float-end" id="cancel-reply" aria-label="Cancel"></button>
                </div>

                <div class="mb-3">
                    <label for="content" class="form-label">your reply</label>
                    <textarea class="form-control" id="content" name="content" rows="5"
                              required minlength="5"
                              placeholder="write your reply..."><?php echo sanitize_input($_POST['content'] ?? ''); ?></textarea>
                </div>

                <div class="d-flex gap-3 align-items-center">
                    <button type="submit" class="btn btn-primary">post reply</button>
                    <button type="button" class="ody-link-btn" id="cancel-reply-btn" style="display: none;">cancel</button>
                </div>
            </form>
        </div>
    </div>
<?php elseif (!is_logged_in()): ?>
    <div class="alert alert-info mt-4">
        <a href="../auth/login.php">login</a> or <a href="../auth/register.php">register</a> to reply.
    </div>
<?php elseif ($topic['is_locked']): ?>
    <div class="alert alert-warning mt-4">
        this topic is locked.
    </div>
<?php endif; ?>

<script>
document.querySelectorAll('.reply-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var replyId = this.dataset.replyId;
        var author = this.dataset.author;
        document.getElementById('parent_reply_id').value = replyId;
        document.getElementById('reply-author').textContent = author;
        document.getElementById('reply-context').style.display = 'block';
        document.getElementById('cancel-reply-btn').style.display = 'inline-block';
        document.getElementById('reply-form').scrollIntoView({ behavior: 'smooth' });
        document.getElementById('content').focus();
    });
});

function cancelReply() {
    document.getElementById('parent_reply_id').value = '';
    document.getElementById('reply-context').style.display = 'none';
    document.getElementById('cancel-reply-btn').style.display = 'none';
}

var cancelBtn = document.getElementById('cancel-reply');
var cancelReplyBtn = document.getElementById('cancel-reply-btn');
if (cancelBtn) cancelBtn.addEventListener('click', cancelReply);
if (cancelReplyBtn) cancelReplyBtn.addEventListener('click', cancelReply);

var contentEl = document.getElementById('content');
if (contentEl) {
    contentEl.addEventListener('input', function () {
        this.style.height = 'auto';
        this.style.height = this.scrollHeight + 'px';
    });
}
</script>

<?php include '../../includes/footer.php'; ?>
