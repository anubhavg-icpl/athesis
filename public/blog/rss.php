<?php
require_once '../../config/config.php';

$db = getDB();
$stmt = $db->query("
    SELECT p.title, p.slug, p.excerpt, p.content, p.published_at, u.display_name
    FROM blog_posts p
    JOIN users u ON u.id = p.user_id
    WHERE p.status = 'published'
    ORDER BY p.published_at DESC
    LIMIT 30
");
$posts = $stmt->fetchAll();

header('Content-Type: application/rss+xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0">
  <channel>
    <title><?php echo htmlspecialchars(SITE_NAME . ' Blog', ENT_XML1); ?></title>
    <link><?php echo htmlspecialchars(full_url('public/blog/index.php'), ENT_XML1); ?></link>
    <description><?php echo htmlspecialchars(SITE_DESCRIPTION, ENT_XML1); ?></description>
    <language>en</language>
    <?php foreach ($posts as $p): ?>
    <item>
      <title><?php echo htmlspecialchars($p['title'], ENT_XML1); ?></title>
      <link><?php echo htmlspecialchars(full_url('public/blog/post.php?slug=' . urlencode($p['slug'])), ENT_XML1); ?></link>
      <guid><?php echo htmlspecialchars(full_url('public/blog/post.php?slug=' . urlencode($p['slug'])), ENT_XML1); ?></guid>
      <pubDate><?php echo date(DATE_RSS, strtotime($p['published_at'] ?: 'now')); ?></pubDate>
      <author><?php echo htmlspecialchars($p['display_name'], ENT_XML1); ?></author>
      <description><![CDATA[<?php echo $p['excerpt'] ?: truncate_text(strip_tags($p['content']), 280); ?>]]></description>
    </item>
    <?php endforeach; ?>
  </channel>
</rss>
