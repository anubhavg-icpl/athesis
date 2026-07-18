<?php
require_once '../../config/config.php';
require_login();

$db = getDB();
$post_id = (int)($_GET['id'] ?? 0);
$errors = [];
$post = null;

if ($post_id) {
    $stmt = $db->prepare('SELECT * FROM blog_posts WHERE id = ?');
    $stmt->execute([$post_id]);
    $post = $stmt->fetch();
    if (!$post || !blog_can_edit_post($post)) {
        $_SESSION['flash_message'] = 'Post not found or no permission.';
        $_SESSION['flash_type'] = 'error';
        header('Location: ' . url('public/blog/index.php'));
        exit;
    }
}

$page_title = $post ? 'Edit post' : 'Write post';
$categories = blog_get_categories($db);
$all_tags = blog_get_tags($db);
$selected_tags = $post ? array_column(blog_get_post_tags($db, (int) $post['id']), 'id') : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $excerpt = trim($_POST['excerpt'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $status = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
        $category_id = !empty($_POST['category_id']) ? (int) $_POST['category_id'] : null;
        $meta_title = trim($_POST['meta_title'] ?? '');
        $meta_description = trim($_POST['meta_description'] ?? '');
        $featured_image = trim($_POST['featured_image'] ?? '');
        $tag_ids = array_map('intval', $_POST['tag_ids'] ?? []);
        $new_tags = trim($_POST['new_tags'] ?? '');

        if (strlen($title) < 3 || strlen($title) > 255) {
            $errors[] = 'Title must be 3–255 characters.';
        }
        if (strlen($content) < 20) {
            $errors[] = 'Content must be at least 20 characters.';
        }
        if ($featured_image !== '' && !filter_var($featured_image, FILTER_VALIDATE_URL)) {
            $errors[] = 'Featured image must be a valid URL (or empty).';
        }

        if (empty($errors)) {
            $clean = clean_content($content);
            $slug_source = $post['slug'] ?? $title;
            // keep existing slug on edit unless title changed drastically and user is new post
            if ($post) {
                $slug = blog_unique_slug($db, $post['slug'], (int) $post['id']);
            } else {
                $slug = blog_unique_slug($db, $title);
            }

            $published_at = null;
            if ($status === 'published') {
                if ($post && $post['status'] === 'published' && !empty($post['published_at'])) {
                    $published_at = $post['published_at'];
                } else {
                    $published_at = date('Y-m-d H:i:s');
                }
            }

            try {
                if ($post) {
                    $stmt = $db->prepare('
                        UPDATE blog_posts SET
                            category_id = ?, title = ?, excerpt = ?, content = ?, status = ?,
                            meta_title = ?, meta_description = ?, featured_image = ?, published_at = ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ');
                    $stmt->execute([
                        $category_id,
                        $title,
                        $excerpt !== '' ? $excerpt : null,
                        $clean,
                        $status,
                        $meta_title !== '' ? $meta_title : null,
                        $meta_description !== '' ? $meta_description : null,
                        $featured_image !== '' ? $featured_image : null,
                        $published_at,
                        (int) $post['id'],
                    ]);
                    $id = (int) $post['id'];
                    $final_slug = $post['slug'];
                } else {
                    $stmt = $db->prepare('
                        INSERT INTO blog_posts
                        (user_id, category_id, title, slug, excerpt, content, status, meta_title, meta_description, featured_image, published_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ');
                    $stmt->execute([
                        (int) $_SESSION['user_id'],
                        $category_id,
                        $title,
                        $slug,
                        $excerpt !== '' ? $excerpt : null,
                        $clean,
                        $status,
                        $meta_title !== '' ? $meta_title : null,
                        $meta_description !== '' ? $meta_description : null,
                        $featured_image !== '' ? $featured_image : null,
                        $published_at,
                    ]);
                    $id = (int) $db->lastInsertId();
                    $final_slug = $slug;
                }

                // new tags from comma list
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

                $_SESSION['flash_message'] = $status === 'published' ? 'Post published.' : 'Draft saved.';
                $_SESSION['flash_type'] = 'success';
                if ($status === 'published') {
                    header('Location: ' . url('public/blog/post.php?slug=' . urlencode($final_slug)));
                } else {
                    header('Location: ' . url('public/blog/post.php?slug=' . urlencode($final_slug) . '&preview=1'));
                }
                exit;
            } catch (PDOException $e) {
                $errors[] = 'Could not save post. Try a different title.';
            }
        }

        // repopulate
        $post = array_merge($post ?: [], [
            'title' => $title,
            'excerpt' => $excerpt,
            'content' => $content,
            'status' => $status,
            'category_id' => $category_id,
            'meta_title' => $meta_title,
            'meta_description' => $meta_description,
            'featured_image' => $featured_image,
        ]);
        $selected_tags = $tag_ids;
    }
}

include '../../includes/header.php';
?>

<div class="ody-page-head">
    <h1><span class="prompt">$_</span><?php echo $post_id ? 'edit post' : 'write'; ?></h1>
    <a href="<?php echo url('public/blog/index.php'); ?>" class="ody-link-btn">← blog</a>
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

<form method="POST" action="" class="row g-4">
    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

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
            </div>
        </div>
    </div>

    <div class="col-md-4 ody-sidebar">
        <div class="ody-panel">
            <div class="ody-panel-head">publish</div>
            <div class="ody-panel-body">
                <div class="mb-3">
                    <label class="form-label" for="status">status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="draft" <?php echo (($post['status'] ?? 'draft') === 'draft') ? 'selected' : ''; ?>>draft</option>
                        <option value="published" <?php echo (($post['status'] ?? '') === 'published') ? 'selected' : ''; ?>>published</option>
                    </select>
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
                    <input type="text" class="form-control" id="new_tags" name="new_tags"
                           placeholder="comma,separated">
                </div>
                <button type="submit" class="btn btn-primary w-100">save →</button>
            </div>
        </div>

        <div class="ody-panel">
            <div class="ody-panel-head">seo</div>
            <div class="ody-panel-body">
                <div class="mb-3">
                    <label class="form-label" for="meta_title">meta title</label>
                    <input type="text" class="form-control" id="meta_title" name="meta_title" maxlength="255"
                           value="<?php echo sanitize_input($post['meta_title'] ?? ''); ?>"
                           placeholder="defaults to post title">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="meta_description">meta description</label>
                    <textarea class="form-control" id="meta_description" name="meta_description" rows="3" maxlength="320"
                              placeholder="~155 chars for search snippets"><?php echo sanitize_input($post['meta_description'] ?? ''); ?></textarea>
                </div>
                <div class="mb-0">
                    <label class="form-label" for="featured_image">featured image url</label>
                    <input type="url" class="form-control" id="featured_image" name="featured_image"
                           value="<?php echo sanitize_input($post['featured_image'] ?? ''); ?>"
                           placeholder="https://…">
                </div>
            </div>
        </div>
    </div>
</form>

<?php include '../../includes/footer.php'; ?>
