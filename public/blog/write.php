<?php
require_once '../../config/config.php';
require_login();

$db = getDB();
blog_publish_due_posts($db);

$post_id = (int)($_GET['id'] ?? 0);
$restore_id = (int)($_GET['restore'] ?? 0);
$errors = [];
$post = null;

if ($post_id) {
    $stmt = $db->prepare('SELECT * FROM blog_posts WHERE id = ?');
    $stmt->execute([$post_id]);
    $post = $stmt->fetch();
    if (!$post || !blog_can_edit_post($post)) {
        $_SESSION['flash_message'] = 'Post not found or no permission.';
        $_SESSION['flash_type'] = 'error';
        header('Location: ' . url('public/blog/admin.php'));
        exit;
    }
}

// Restore revision into form fields (not saved until user saves)
if ($post && $restore_id) {
    $stmt = $db->prepare('SELECT * FROM blog_revisions WHERE id = ? AND post_id = ?');
    $stmt->execute([$restore_id, (int) $post['id']]);
    $rev = $stmt->fetch();
    if ($rev) {
        $post = array_merge($post, [
            'title' => $rev['title'],
            'excerpt' => $rev['excerpt'],
            'content' => $rev['content'],
            'meta_title' => $rev['meta_title'],
            'meta_description' => $rev['meta_description'],
            'featured_image' => $rev['featured_image'],
        ]);
        $_SESSION['flash_message'] = 'Revision loaded into editor — save to apply.';
        $_SESSION['flash_type'] = 'info';
    }
}

$page_title = $post ? 'Edit post' : 'Write post';
$categories = blog_get_categories($db);
$series_list = blog_get_series($db);
$all_tags = blog_get_tags($db);
$selected_tags = $post ? array_column(blog_get_post_tags($db, (int) $post['id']), 'id') : [];
$revisions = $post ? blog_get_revisions($db, (int) $post['id']) : [];

// Recent media for picker
$media_stmt = $db->prepare('SELECT * FROM blog_media WHERE user_id = ? OR ? = 1 ORDER BY created_at DESC LIMIT 12');
$media_stmt->execute([(int) $_SESSION['user_id'], is_admin_or_moderator() ? 1 : 0]);
$recent_media = $media_stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $excerpt = trim($_POST['excerpt'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $status_in = $_POST['status'] ?? 'draft';
        $scheduled_raw = trim($_POST['scheduled_at'] ?? '');
        $category_id = !empty($_POST['category_id']) ? (int) $_POST['category_id'] : null;
        $series_id = !empty($_POST['series_id']) ? (int) $_POST['series_id'] : null;
        $series_order = (int) ($_POST['series_order'] ?? 0);
        $is_premium = !empty($_POST['is_premium']) ? 1 : 0;
        $meta_title = trim($_POST['meta_title'] ?? '');
        $meta_description = trim($_POST['meta_description'] ?? '');
        $featured_image = trim($_POST['featured_image'] ?? '');
        $tag_ids = array_map('intval', $_POST['tag_ids'] ?? []);
        $new_tags = trim($_POST['new_tags'] ?? '');
        $intent = $_POST['intent'] ?? 'save'; // save | preview

        // Optional direct upload on save
        if (!empty($_FILES['featured_upload']['name'])) {
            $up = blog_handle_image_upload($_FILES['featured_upload'], (int) $_SESSION['user_id']);
            if ($up['ok']) {
                $featured_image = $up['url'];
            } else {
                $errors[] = $up['error'];
            }
        }

        if (strlen($title) < 3 || strlen($title) > 255) {
            $errors[] = 'Title must be 3–255 characters.';
        }
        if (strlen($content) < 20) {
            $errors[] = 'Content must be at least 20 characters.';
        }
        if ($featured_image !== '' && !preg_match('#^(https?://|/public/)#i', $featured_image)) {
            $errors[] = 'Featured image must be a full URL or site path starting with /public/.';
        }
        if ($status_in === 'scheduled' && $scheduled_raw === '') {
            $errors[] = 'Pick a schedule date/time for scheduled posts.';
        }

        if (empty($errors)) {
            $clean = clean_content($content);
            if ($post) {
                $slug = $post['slug'];
            } else {
                $slug = blog_unique_slug($db, $title);
            }

            [$status, $published_at_new, $scheduled_at] = blog_resolve_status($status_in, $scheduled_raw);

            // Preserve original published_at if already live
            $published_at = null;
            if ($status === 'published') {
                if ($post && $post['status'] === 'published' && !empty($post['published_at'])) {
                    $published_at = $post['published_at'];
                } else {
                    $published_at = $published_at_new ?: date('Y-m-d H:i:s');
                }
            } elseif ($status === 'scheduled') {
                $published_at = null;
            } else {
                $published_at = $post['published_at'] ?? null; // keep history if unpublishing
            }

            try {
                // Snapshot revision before overwrite
                if ($post) {
                    blog_save_revision($db, (int) $post['id'], [
                        'title' => $post['title'],
                        'excerpt' => $post['excerpt'],
                        'content' => $post['content'],
                        'meta_title' => $post['meta_title'],
                        'meta_description' => $post['meta_description'],
                        'featured_image' => $post['featured_image'],
                    ], (int) $_SESSION['user_id']);
                }

                if ($post) {
                    $stmt = $db->prepare('
                        UPDATE blog_posts SET
                            category_id = ?, series_id = ?, series_order = ?, title = ?, excerpt = ?, content = ?, status = ?,
                            meta_title = ?, meta_description = ?, featured_image = ?, is_premium = ?,
                            published_at = ?, scheduled_at = ?, updated_at = NOW()
                        WHERE id = ?
                    ');
                    $stmt->execute([
                        $category_id,
                        $series_id,
                        $series_order,
                        $title,
                        $excerpt !== '' ? $excerpt : null,
                        $clean,
                        $status,
                        $meta_title !== '' ? $meta_title : null,
                        $meta_description !== '' ? $meta_description : null,
                        $featured_image !== '' ? $featured_image : null,
                        $is_premium,
                        $published_at,
                        $scheduled_at,
                        (int) $post['id'],
                    ]);
                    $id = (int) $post['id'];
                    $final_slug = $post['slug'];
                } else {
                    $stmt = $db->prepare('
                        INSERT INTO blog_posts
                        (user_id, category_id, series_id, series_order, title, slug, excerpt, content, status, meta_title, meta_description, featured_image, is_premium, published_at, scheduled_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ');
                    $stmt->execute([
                        (int) $_SESSION['user_id'],
                        $category_id,
                        $series_id,
                        $series_order,
                        $title,
                        $slug,
                        $excerpt !== '' ? $excerpt : null,
                        $clean,
                        $status,
                        $meta_title !== '' ? $meta_title : null,
                        $meta_description !== '' ? $meta_description : null,
                        $featured_image !== '' ? $featured_image : null,
                        $is_premium,
                        $published_at,
                        $scheduled_at,
                    ]);
                    $id = (int) $db->lastInsertId();
                    $final_slug = $slug;
                    // initial revision
                    blog_save_revision($db, $id, [
                        'title' => $title,
                        'excerpt' => $excerpt,
                        'content' => $clean,
                        'meta_title' => $meta_title,
                        'meta_description' => $meta_description,
                        'featured_image' => $featured_image,
                    ], (int) $_SESSION['user_id']);
                }

                if ($new_tags !== '') {
                    foreach (explode(',', $new_tags) as $nt) {
                        $tid = blog_find_or_create_tag($db, $nt);
                        if ($tid) {
                            $tag_ids[] = $tid;
                        }
                    }
                }
                $tag_ids = array_unique(array_filter($tag_ids));
                blog_sync_tags($db, $id, $tag_ids);

                $msg = match ($status) {
                    'published' => 'Post published.',
                    'scheduled' => 'Post scheduled.',
                    default => 'Draft saved.',
                };
                $_SESSION['flash_message'] = $msg;
                $_SESSION['flash_type'] = 'success';

                if ($intent === 'preview' || $status !== 'published') {
                    header('Location: ' . url('public/blog/post.php?slug=' . urlencode($final_slug) . '&preview=1'));
                } else {
                    header('Location: ' . url('public/blog/post.php?slug=' . urlencode($final_slug)));
                }
                exit;
            } catch (PDOException $e) {
                $errors[] = 'Could not save post. Try a different title.';
            }
        }

        $post = array_merge($post ?: [], [
            'title' => $title,
            'excerpt' => $excerpt,
            'content' => $content,
            'status' => $status_in,
            'category_id' => $category_id,
            'meta_title' => $meta_title,
            'meta_description' => $meta_description,
            'featured_image' => $featured_image,
            'scheduled_at' => $scheduled_raw,
        ]);
        $selected_tags = $tag_ids;
    }
}

// datetime-local value
$sched_value = '';
if (!empty($post['scheduled_at'])) {
    $ts = strtotime($post['scheduled_at']);
    if ($ts) {
        $sched_value = date('Y-m-d\TH:i', $ts);
    }
}

include '../../includes/header.php';
?>

<div class="ody-page-head">
    <h1><span class="prompt">$_</span><?php echo $post_id ? 'edit post' : 'write'; ?></h1>
    <div class="d-flex gap-3 flex-wrap">
        <a href="<?php echo url('public/blog/admin.php'); ?>" class="ody-link-btn">admin</a>
        <a href="<?php echo url('public/blog/media.php'); ?>" class="ody-link-btn">media</a>
        <a href="<?php echo url('public/blog/index.php'); ?>" class="ody-link-btn">← blog</a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $e): ?>
                <li><?php echo sanitize_input($e); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST" action="" enctype="multipart/form-data" class="row g-4" id="write-form">
    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
    <input type="hidden" name="intent" id="intent" value="save">

    <div class="col-md-8">
        <div class="ody-panel">
            <div class="ody-panel-head">compose</div>
            <div class="ody-panel-body">
                <div class="mb-3">
                    <label class="form-label" for="title">title</label>
                    <input type="text" class="form-control" id="title" name="title" required minlength="3" maxlength="255"
                           value="<?php echo sanitize_input($post['title'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="excerpt">excerpt</label>
                    <textarea class="form-control" id="excerpt" name="excerpt" rows="2" maxlength="500"
                              placeholder="short summary for lists & SEO"><?php echo sanitize_input($post['excerpt'] ?? ''); ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="content">content</label>
                    <textarea class="form-control" id="content" name="content" rows="14" required minlength="20"
                              placeholder="write the post… basic HTML allowed"><?php echo sanitize_input($post['content'] ?? ''); ?></textarea>
                    <div class="form-text">min 20 chars · p, br, strong, em, lists, blockquote</div>
                </div>

                <div class="ody-preview-box d-none" id="live-preview">
                    <div class="ody-panel-head" style="padding:0 0 .75rem;border:0">live preview</div>
                    <h2 id="preview-title" class="h4"></h2>
                    <p id="preview-excerpt" class="ody-blog-excerpt" style="margin-left:0"></p>
                    <div id="preview-body" class="topic-content"></div>
                </div>
                <button type="button" class="ody-link-btn mt-2" id="toggle-preview">toggle live preview</button>
            </div>
        </div>

        <?php if (!empty($revisions)): ?>
        <div class="ody-panel mt-3">
            <div class="ody-panel-head">revisions (<?php echo count($revisions); ?>)</div>
            <div class="ody-panel-body">
                <div class="ody-list">
                    <?php foreach ($revisions as $r): ?>
                        <div class="ody-list-item" style="cursor:default">
                            <span class="marker">·</span>
                            <div class="body">
                                <h3 class="title"><span class="title-text"><?php echo sanitize_input($r['title']); ?></span></h3>
                                <div class="meta">
                                    <span><?php echo format_date($r['created_at']); ?></span>
                                    <span><?php echo sanitize_input($r['display_name'] ?? 'system'); ?></span>
                                </div>
                            </div>
                            <div class="stats">
                                <a class="open-hint" href="<?php echo url('public/blog/write.php?id=' . (int) $post_id . '&restore=' . (int) $r['id']); ?>">restore →</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-md-4 ody-sidebar">
        <div class="ody-panel">
            <div class="ody-panel-head">publish</div>
            <div class="ody-panel-body">
                <div class="mb-3">
                    <label class="form-label" for="status">status</label>
                    <select class="form-select" id="status" name="status">
                        <?php
                        $st = $post['status'] ?? 'draft';
                        foreach (['draft' => 'draft', 'published' => 'published', 'scheduled' => 'scheduled'] as $val => $label):
                        ?>
                            <option value="<?php echo $val; ?>" <?php echo $st === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3" id="schedule-wrap">
                    <label class="form-label" for="scheduled_at">schedule (local)</label>
                    <input type="datetime-local" class="form-control" id="scheduled_at" name="scheduled_at"
                           value="<?php echo sanitize_input($sched_value); ?>">
                    <div class="form-text">used when status = scheduled</div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="category_id">category</label>
                    <select class="form-select" id="category_id" name="category_id">
                        <option value="">none</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo (int) $cat['id']; ?>"
                                <?php echo ((int)($post['category_id'] ?? 0) === (int)$cat['id']) ? 'selected' : ''; ?>>
                                <?php echo sanitize_input($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="series_id">series</label>
                    <select class="form-select" id="series_id" name="series_id">
                        <option value="">none</option>
                        <?php foreach ($series_list as $s): ?>
                            <option value="<?php echo (int) $s['id']; ?>"
                                <?php echo ((int)($post['series_id'] ?? 0) === (int)$s['id']) ? 'selected' : ''; ?>>
                                <?php echo sanitize_input($s['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="series_order">series order</label>
                    <input type="number" class="form-control" id="series_order" name="series_order" min="0" max="999"
                           value="<?php echo (int)($post['series_order'] ?? 0); ?>">
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="is_premium" name="is_premium" value="1"
                        <?php echo !empty($post['is_premium']) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="is_premium">members only (paywall)</label>
                </div>
                <div class="mb-3">
                    <label class="form-label">tags</label>
                    <div class="ody-tag-checks">
                        <?php foreach ($all_tags as $t): ?>
                            <label class="ody-check">
                                <input type="checkbox" name="tag_ids[]" value="<?php echo (int) $t['id']; ?>"
                                    <?php echo in_array((int)$t['id'], array_map('intval', $selected_tags), true) ? 'checked' : ''; ?>>
                                #<?php echo sanitize_input($t['name']); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="new_tags">new tags</label>
                    <input type="text" class="form-control" id="new_tags" name="new_tags" placeholder="comma,separated">
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary" onclick="document.getElementById('intent').value='save'">save →</button>
                    <button type="submit" class="btn btn-outline-secondary" onclick="document.getElementById('intent').value='preview'">save &amp; preview</button>
                </div>
            </div>
        </div>

        <div class="ody-panel">
            <div class="ody-panel-head">seo + cover</div>
            <div class="ody-panel-body">
                <div class="mb-3">
                    <label class="form-label" for="meta_title">meta title</label>
                    <input type="text" class="form-control" id="meta_title" name="meta_title" maxlength="255"
                           value="<?php echo sanitize_input($post['meta_title'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="meta_description">meta description</label>
                    <textarea class="form-control" id="meta_description" name="meta_description" rows="3" maxlength="320"><?php echo sanitize_input($post['meta_description'] ?? ''); ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="featured_image">featured image url / path</label>
                    <input type="text" class="form-control" id="featured_image" name="featured_image"
                           value="<?php echo sanitize_input($post['featured_image'] ?? ''); ?>"
                           placeholder="https://… or /public/uploads/…">
                </div>
                <div class="mb-0">
                    <label class="form-label" for="featured_upload">or upload cover</label>
                    <input type="file" class="form-control" id="featured_upload" name="featured_upload" accept="image/*">
                </div>
            </div>
        </div>

        <?php if (!empty($recent_media)): ?>
        <div class="ody-panel">
            <div class="ody-panel-head">recent media</div>
            <div class="ody-panel-body ody-media-grid ody-media-grid-sm">
                <?php foreach ($recent_media as $m): ?>
                    <?php $u = url($m['url_path']); ?>
                    <button type="button" class="ody-media-pick" data-url="<?php echo sanitize_input($u); ?>" title="use as cover">
                        <img src="<?php echo sanitize_input($u); ?>" alt="">
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</form>

<script>
(function () {
    var status = document.getElementById('status');
    var wrap = document.getElementById('schedule-wrap');
    function syncSched() {
        if (!status || !wrap) return;
        wrap.style.opacity = status.value === 'scheduled' ? '1' : '0.45';
    }
    status?.addEventListener('change', syncSched);
    syncSched();

    document.querySelectorAll('.ody-media-pick').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var input = document.getElementById('featured_image');
            if (input) input.value = this.dataset.url;
        });
    });

    var box = document.getElementById('live-preview');
    var toggle = document.getElementById('toggle-preview');
    function esc(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]);
        });
    }
    function refreshPreview() {
        document.getElementById('preview-title').textContent = document.getElementById('title').value || 'untitled';
        var ex = document.getElementById('excerpt').value;
        var pex = document.getElementById('preview-excerpt');
        pex.textContent = ex;
        pex.style.display = ex ? 'block' : 'none';
        var raw = document.getElementById('content').value || '';
        // simple safe preview: escape then allow basic tags we support
        var html = esc(raw).replace(/\n/g, '<br>');
        document.getElementById('preview-body').innerHTML = html;
    }
    toggle?.addEventListener('click', function () {
        box.classList.toggle('d-none');
        if (!box.classList.contains('d-none')) refreshPreview();
    });
    ['title','excerpt','content'].forEach(function (id) {
        document.getElementById(id)?.addEventListener('input', function () {
            if (box && !box.classList.contains('d-none')) refreshPreview();
        });
    });
})();
</script>

<?php include '../../includes/footer.php'; ?>
