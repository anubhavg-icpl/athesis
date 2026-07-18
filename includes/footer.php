    </main>

    <footer class="ody-footer">
        <div class="ody-footer-inner">
            <div>
                <span class="prompt" style="color: var(--accent);">$_</span>
                <?php echo strtolower(SITE_NAME); ?>
                <span> · <?php echo date('Y'); ?></span>
            </div>
            <div class="ody-footer-links">
                <a href="<?php echo url('public/index.php'); ?>">home</a>
                <a href="<?php echo url('public/blog/index.php'); ?>">blog</a>
                <a href="<?php echo url('public/blog/archive.php'); ?>">archive</a>
                <a href="<?php echo url('public/blog/series.php'); ?>">series</a>
                <a href="<?php echo url('public/forum/topics.php'); ?>">topics</a>
                <a href="<?php echo url('public/blog/rss.php'); ?>">rss</a>
                <a href="<?php echo url('public/pages/about.php'); ?>">about</a>
                <a href="<?php echo url('public/pages/privacy.php'); ?>">privacy</a>
                <a href="<?php echo url('public/pages/contact.php'); ?>">contact</a>
                <?php if (is_logged_in()): ?>
                    <a href="<?php echo url('public/blog/write.php'); ?>">write</a>
                    <a href="<?php echo url('public/blog/admin.php'); ?>">admin</a>
                    <?php if (is_admin_or_moderator()): ?>
                    <a href="<?php echo url('public/blog/moderate.php'); ?>">moderate</a>
                    <?php endif; ?>
                    <a href="<?php echo url('public/auth/profile.php'); ?>">profile</a>
                <?php else: ?>
                    <a href="<?php echo url('public/auth/login.php'); ?>">login</a>
                    <a href="<?php echo url('public/auth/register.php'); ?>">join</a>
                <?php endif; ?>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo url('public/js/script.js'); ?>"></script>
    <script>
    (function () {
        var toggle = document.getElementById('ody-nav-toggle');
        var links = document.getElementById('ody-nav-links');
        if (toggle && links) {
            toggle.addEventListener('click', function () {
                var open = links.classList.toggle('open');
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        }
    })();
    </script>
</body>
</html>
