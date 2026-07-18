<?php
if (!defined('SITE_NAME')) {
    require_once __DIR__ . '/../config/config.php';
}

// Detect current path for active nav
$script = $_SERVER['SCRIPT_NAME'] ?? '';
$is_home = (bool) preg_match('#/public/index\.php$#', $script) || (bool) preg_match('#/public/?$#', $script);
$is_topics = (bool) preg_match('#/forum/(topics|view_topic|create_topic|edit_topic|edit_reply)\.php#', $script);
$is_blog = (bool) preg_match('#/blog/#', $script);
$is_login = (bool) preg_match('#/auth/login\.php#', $script);
$is_register = (bool) preg_match('#/auth/register\.php#', $script);
$is_profile = (bool) preg_match('#/auth/profile\.php#', $script);

$meta_description = $page_description ?? SITE_DESCRIPTION;
$meta_title = isset($page_title) ? sanitize_input($page_title) . ' — ' . SITE_NAME : SITE_NAME;
$og_url = $page_canonical ?? (SITE_URL . ($_SERVER['REQUEST_URI'] ?? ''));
$og_image = $page_image ?? '';
$og_type = $page_og_type ?? 'website';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $meta_title; ?></title>
    <meta name="description" content="<?php echo sanitize_input($meta_description); ?>">
    <?php if (!empty($page_canonical)): ?>
    <link rel="canonical" href="<?php echo sanitize_input($page_canonical); ?>">
    <?php endif; ?>
    <meta property="og:title" content="<?php echo $meta_title; ?>">
    <meta property="og:description" content="<?php echo sanitize_input($meta_description); ?>">
    <meta property="og:type" content="<?php echo sanitize_input($og_type); ?>">
    <meta property="og:url" content="<?php echo sanitize_input($og_url); ?>">
    <?php if ($og_image !== ''): ?>
    <meta property="og:image" content="<?php echo sanitize_input($og_image); ?>">
    <?php endif; ?>
    <meta name="twitter:card" content="summary_large_image">
    <meta name="theme-color" content="#000000">
    <link rel="alternate" type="application/rss+xml" title="<?php echo sanitize_input(SITE_NAME); ?> Blog" href="<?php echo url('public/blog/rss.php'); ?>">

    <!-- JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- Bootstrap (grid + utilities only; fully restyled) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom Odyssey-inspired theme -->
    <link href="<?php echo url('public/css/style.css'); ?>?v=<?php echo filemtime(__DIR__ . '/../public/css/style.css'); ?>?v2" rel="stylesheet">
    <?php if (defined('PLAUSIBLE_DOMAIN') && PLAUSIBLE_DOMAIN !== ''): ?>
    <script defer data-domain="<?php echo sanitize_input(PLAUSIBLE_DOMAIN); ?>" src="https://plausible.io/js/script.js"></script>
    <?php endif; ?>
    <?php if (defined('GA_MEASUREMENT_ID') && GA_MEASUREMENT_ID !== ''): ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo sanitize_input(GA_MEASUREMENT_ID); ?>"></script>
    <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config',<?php echo json_encode(GA_MEASUREMENT_ID); ?>);</script>
    <?php endif; ?>
</head>
<body>
    <header class="ody-nav">
        <div class="ody-nav-inner">
            <a class="ody-brand" href="<?php echo url('public/index.php'); ?>">
                <span class="prompt">$_</span><?php echo strtolower(SITE_NAME); ?>
            </a>

            <button type="button" class="ody-nav-toggle" id="ody-nav-toggle" aria-label="Menu" aria-expanded="false">
                menu
            </button>

            <ul class="ody-nav-links" id="ody-nav-links">
                <li>
                    <a href="<?php echo url('public/index.php'); ?>" class="<?php echo $is_home ? 'active' : ''; ?>">home</a>
                </li>
                <li>
                    <a href="<?php echo url('public/blog/index.php'); ?>" class="<?php echo $is_blog ? 'active' : ''; ?>">blog</a>
                </li>
                <li>
                    <a href="<?php echo url('public/forum/topics.php'); ?>" class="<?php echo $is_topics ? 'active' : ''; ?>">topics</a>
                </li>
                <li>
                    <a href="<?php echo url('public/pages/about.php'); ?>">about</a>
                </li>
                <?php if (is_logged_in()): ?>
                    <li>
                        <a href="<?php echo url('public/blog/write.php'); ?>" class="accent">write</a>
                    </li>
                    <li>
                        <a href="<?php echo url('public/blog/admin.php'); ?>">admin</a>
                    </li>
                    <li>
                        <a href="<?php echo url('public/forum/create_topic.php'); ?>">new topic</a>
                    </li>
                    <li>
                        <a href="<?php echo url('public/auth/profile.php'); ?>" class="<?php echo $is_profile ? 'active' : ''; ?>">
                            <?php
                            $u = get_current_user_data();
                            echo sanitize_input(strtolower($u['display_name'] ?? $u['username'] ?? 'profile'));
                            ?>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo url('public/auth/logout.php'); ?>">logout</a>
                    </li>
                <?php else: ?>
                    <li>
                        <a href="<?php echo url('public/auth/login.php'); ?>" class="<?php echo $is_login ? 'active' : ''; ?>">login</a>
                    </li>
                    <li>
                        <a href="<?php echo url('public/auth/register.php'); ?>" class="accent <?php echo $is_register ? 'active' : ''; ?>">join</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </header>

    <main class="ody-main">
        <?php display_flash_message(); ?>
