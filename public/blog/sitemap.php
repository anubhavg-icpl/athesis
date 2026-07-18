<?php
require_once '../../config/config.php';

$db = getDB();
$stmt = $db->query("
    SELECT slug, updated_at, published_at
    FROM blog_posts
    WHERE status = 'published'
    ORDER BY published_at DESC
");
$posts = $stmt->fetchAll();

header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc><?php echo htmlspecialchars(full_url('public/blog/index.php'), ENT_XML1); ?></loc>
    <changefreq>daily</changefreq>
    <priority>0.8</priority>
  </url>
  <?php foreach ($posts as $p): ?>
  <url>
    <loc><?php echo htmlspecialchars(full_url('public/blog/post.php?slug=' . urlencode($p['slug'])), ENT_XML1); ?></loc>
    <lastmod><?php echo date('Y-m-d', strtotime($p['updated_at'] ?: $p['published_at'])); ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.7</priority>
  </url>
  <?php endforeach; ?>
</urlset>
