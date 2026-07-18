<?php
require_once '../../config/config.php';

$slug = trim($_GET['slug'] ?? '');
if ($slug === '') {
    header('Location: ' . url('public/blog/index.php'));
    exit;
}

$db = getDB();
blog_publish_due_posts($db);
$post = blog_get_post_by_slug($db, $slug, true);

if (!$post && is_logged_in()) {
    $draft = blog_get_post_by_slug($db, $slug, false);
    if ($draft && blog_can_edit_post($draft) && (
        !empty($_GET['preview']) || in_array($draft['status'], ['draft', 'scheduled'], true)
    )) {
        $post = $draft;
    }
}

if (!$post) {
    http_response_code(404);
    $page_title = 'Not found';
    include '../../includes/header.php';
    echo '<div class="ody-empty"><div class="icon">//</div><p>post not found.</p><a class="ody-link-btn" href="' . url('public/blog/index.php') . '">← blog</a></div>';
    include '../../includes/footer.php';
    exit;
}

$is_locked = !empty($post['is_premium']) && !user_is_member() && !(blog_can_edit_post($post));

if ($post['status'] === 'published' && !$is_locked) {
    $stmt = $db->prepare('UPDATE blog_posts SET view_count = view_count + 1 WHERE id = ?');
    $stmt->execute([(int) $post['id']]);
    $post['view_count'] = (int) $post['view_count'] + 1;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($post['status'] === 'published') && !$is_locked) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        $content = trim($_POST['content'] ?? '');
        if (strlen($content) < 3) {
            $errors[] = 'Comment must be at least 3 characters.';
        } elseif (strlen($content) > 2000) {
            $errors[] = 'Comment is too long.';
        } else {
            $user_id = is_logged_in() ? (int) $_SESSION['user_id'] : null;
            $author_name = is_logged_in() ? null : mb_substr(trim($_POST['author_name'] ?? 'guest'), 0, 100);
            if (!is_logged_in() && ($author_name === null || $author_name === '')) {
                $errors[] = 'Name is required for guest comments.';
            } else {
                // Logged-in auto-approve; guests pending moderation
                $approved = is_logged_in() ? 1 : 0;
                if (is_admin_or_moderator()) {
                    $approved = 1;
                }
                $stmt = $db->prepare('INSERT INTO blog_comments (post_id, user_id, author_name, content, is_approved) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([(int) $post['id'], $user_id, $author_name, clean_content($content), $approved]);
                $_SESSION['flash_message'] = $approved
                    ? 'Comment posted.'
                    : 'Comment submitted for moderation.';
                $_SESSION['flash_type'] = 'success';
                header('Location: ' . url('public/blog/post.php?slug=' . urlencode($slug) . '#comments'));
                exit;
            }
        }
    }
}

$tags = blog_get_post_tags($db, (int) $post['id']);
$related = blog_get_related($db, $post, 3);
$series_posts = !empty($post['series_id']) ? blog_series_posts($db, (int) $post['series_id']) : [];

$stmt = $db->prepare('
    SELECT c.*, u.display_name, u.username
    FROM blog_comments c
    LEFT JOIN users u ON u.id = c.user_id
    WHERE c.post_id = ? AND c.is_approved = 1
    ORDER BY c.created_at ASC
');
$stmt->execute([(int) $post['id']]);
$comments = $stmt->fetchAll();

$page_title = $post['meta_title'] ?: $post['title'];
$page_description = $post['meta_description'] ?: ($post['excerpt'] ?: truncate_text(strip_tags($post['content']), 155));
$page_canonical = full_url('public/blog/post.php?slug=' . urlencode($post['slug']));
$page_image = $post['featured_image'] ?? '';
$page_og_type = 'article';
$share_url = $page_canonical;

[$toc_html, $body_html] = $is_locked
    ? ['', '']
    : blog_render_article_body($post['content']);

include '../../includes/header.php';
?>

<div class="ody-page-head">
    <h1>
        <?php if ($post['status'] === 'draft'): ?><span class="ody-flag">draft</span><?php endif; ?>
        <?php if ($post['status'] === 'scheduled'): ?><span class="ody-flag">scheduled</span><?php endif; ?>
        <?php if (!empty($post['is_premium'])): ?><span class="ody-flag lock">members</span><?php endif; ?>
        <?php echo sanitize_input($post['title']); ?>
    </h1>
    <a href="<?php echo url('public/blog/index.php'); ?>" class="ody-link-btn">← blog</a>
</div>

<article class="ody-post ody-blog-post">
    <div class="ody-post-head">
        <div>
            <div class="author"><?php echo sanitize_input($post['author_name']); ?></div>
            <div class="meta">
                <?php if (!empty($post['category_name'])): ?>
                    <a href="<?php echo url('public/blog/index.php?category=' . urlencode($post['category_slug'])); ?>">
                        <?php echo sanitize_input($post['category_name']); ?>
                    </a> ·
                <?php endif; ?>
                <?php echo $post['published_at'] ? format_date($post['published_at']) : format_date($post['created_at']); ?>
                · <?php echo blog_reading_time($post['content']); ?>
                · <?php echo (int) $post['view_count']; ?> views
            </div>
        </div>
        <?php if (blog_can_edit_post($post)): ?>
            <a class="ody-link-btn" href="<?php echo url('public/blog/write.php?id=' . (int) $post['id']); ?>">edit</a>
        <?php endif; ?>
    </div>

    <?php
    $cover = !empty($post['featured_image'])
        ? $post['featured_image']
        : url('public/images/brand/blog-cover.jpg');
    ?>
    <div class="ody-blog-cover">
        <img src="<?php echo sanitize_input($cover); ?>" alt="" width="1200" height="640" loading="lazy">
    </div>

    <?php if (!empty($post['excerpt'])): ?>
        <p class="ody-blog-excerpt"><?php echo sanitize_input($post['excerpt']); ?></p>
    <?php endif; ?>

    <?php if ($is_locked): ?>
        <div class="ody-post-body">
            <div class="ody-paywall">
                <div class="ody-paywall-tag">members only</div>
                <h2>locked signal</h2>
                <p>This post is for members. Log in with a member account, or ask an admin to enable membership on your profile.</p>
                <?php if (!is_logged_in()): ?>
                    <a class="btn btn-primary" href="<?php echo url('public/auth/login.php'); ?>">login →</a>
                <?php else: ?>
                    <p class="small" style="color:var(--text-mute)">your account is not marked as a member yet.</p>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <?php if ($toc_html): ?>
            <?php echo $toc_html; ?>
        <?php endif; ?>
        <div class="ody-post-body topic-content">
            <?php echo $body_html; ?>
            <?php echo render_signature($post['author_signature'] ?? '', $post['author_username'] ?? ''); ?>
        </div>
    <?php endif; ?>

    <div class="ody-post-foot ody-share-bar">
        <span class="ody-share-label">share</span>
        <button type="button" class="ody-link-btn" id="copy-link" data-url="<?php echo sanitize_input($share_url); ?>">copy link</button>
        <a class="ody-link-btn" target="_blank" rel="noopener"
           href="https://twitter.com/intent/tweet?url=<?php echo rawurlencode($share_url); ?>&text=<?php echo rawurlencode($post['title']); ?>">x / twitter</a>
        <a class="ody-link-btn" target="_blank" rel="noopener"
           href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo rawurlencode($share_url); ?>">linkedin</a>
        <a class="ody-link-btn" href="mailto:?subject=<?php echo rawurlencode($post['title']); ?>&body=<?php echo rawurlencode($share_url); ?>">email</a>
    </div>

    <?php if (!empty($tags)): ?>
        <div class="ody-post-foot ody-blog-tags">
            <?php foreach ($tags as $t): ?>
                <a class="ody-chip" href="<?php echo url('public/blog/index.php?tag=' . urlencode($t['slug'])); ?>">
                    #<?php echo sanitize_input($t['name']); ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</article>

<?php if (!empty($series_posts)): ?>
<section class="ody-section mt-4">
    <div class="ody-section-head"><h2>in this series</h2></div>
    <div class="ody-list">
        <?php foreach ($series_posts as $sp): ?>
            <a class="ody-list-item <?php echo (int)$sp['id'] === (int)$post['id'] ? 'is-current' : ''; ?>"
               href="<?php echo url('public/blog/post.php?slug=' . urlencode($sp['slug'])); ?>">
                <span class="marker"><?php echo (int)$sp['series_order'] ?: '·'; ?></span>
                <div class="body">
                    <h3 class="title"><span class="title-text"><?php echo sanitize_input($sp['title']); ?></span></h3>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($related) && !$is_locked): ?>
<section class="ody-section mt-4">
    <div class="ody-section-head"><h2>related</h2></div>
    <div class="ody-list">
        <?php foreach ($related as $r): ?>
            <a class="ody-list-item" href="<?php echo url('public/blog/post.php?slug=' . urlencode($r['slug'])); ?>">
                <span class="marker">▸</span>
                <div class="body">
                    <h3 class="title"><span class="title-text"><?php echo sanitize_input($r['title']); ?></span></h3>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if (!$is_locked && $post['status'] === 'published'): ?>
<section id="comments" class="ody-section mt-4">
    <div class="ody-section-head"><h2>comments (<?php echo count($comments); ?>)</h2></div>
    <?php if (empty($comments)): ?>
        <div class="ody-empty"><p>no comments yet.</p></div>
    <?php else: ?>
        <?php foreach ($comments as $c): ?>
            <article class="ody-post mb-3">
                <div class="ody-post-head">
                    <div>
                        <div class="author"><?php echo sanitize_input($c['display_name'] ?: ($c['author_name'] ?: 'guest')); ?></div>
                        <div class="meta"><?php echo time_ago($c['created_at']); ?></div>
                    </div>
                </div>
                <div class="ody-post-body"><?php echo nl2br(sanitize_input(strip_tags($c['content']))); ?></div>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="ody-panel mt-3">
        <div class="ody-panel-head">leave a comment</div>
        <div class="ody-panel-body">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?php echo sanitize_input($e); ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>
            <form method="POST" action="#comments">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <?php if (!is_logged_in()): ?>
                    <div class="mb-3">
                        <label class="form-label" for="author_name">name</label>
                        <input type="text" class="form-control" id="author_name" name="author_name" required maxlength="100"
                               value="<?php echo sanitize_input($_POST['author_name'] ?? ''); ?>">
                        <div class="form-text">guest comments are moderated</div>
                    </div>
                <?php endif; ?>
                <div class="mb-3">
                    <label class="form-label" for="content">comment</label>
                    <textarea class="form-control" id="content" name="content" rows="4" required minlength="3" maxlength="2000"><?php echo sanitize_input($_POST['content'] ?? ''); ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary">post comment →</button>
            </form>
        </div>
    </div>
</section>
<?php endif; ?>

<?php include '../../includes/partials/newsletter.php'; ?>

<script>
document.getElementById('copy-link')?.addEventListener('click', function () {
    var u = this.dataset.url;
    if (navigator.clipboard) navigator.clipboard.writeText(u);
    this.textContent = 'copied';
});
</script>

<?php include '../../includes/footer.php'; ?>
