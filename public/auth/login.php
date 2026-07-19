<?php
require_once '../../config/config.php';

$page_title = 'Login';
$errors = [];

// Redirect if already logged in
if (is_logged_in()) {
    header('Location: ../index.php');
    exit;
}

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember_me = isset($_POST['remember_me']);
        
        // Validate input
        if (empty($username)) {
            $errors[] = 'Username or email is required.';
        }
        
        if (empty($password)) {
            $errors[] = 'Password is required.';
        }
        
        // Authenticate user
        if (empty($errors)) {
            $db = getDB();
            $stmt = $db->prepare("SELECT id, username, password_hash, display_name, user_role FROM users WHERE (username = ? OR email = ?) AND is_active = 1");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
            
            if ($user && verify_password($password, $user['password_hash'])) {
                // Login successful
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['display_name'] = $user['display_name'];
                $_SESSION['user_role'] = $user['user_role'];
                
                // Update last login
                $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $stmt->execute([$user['id']]);
                
                // Create session record
                $session_id = session_id();
                $expires_at = date('Y-m-d H:i:s', time() + SESSION_TIMEOUT);
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                
                $stmt = $db->prepare("INSERT INTO user_sessions (id, user_id, expires_at, ip_address, user_agent) VALUES (?, ?, ?, ?, ?) ON CONFLICT (id) DO UPDATE SET expires_at = EXCLUDED.expires_at");
                $stmt->execute([$session_id, $user['id'], $expires_at, $ip_address, $user_agent]);
                
                // Set remember me cookie if requested
                if ($remember_me) {
                    $cookie_token = bin2hex(random_bytes(32));
                    setcookie('remember_token', $cookie_token, time() + (30 * 24 * 60 * 60), '/', '', false, true); // 30 days
                }
                
                // Redirect to intended page or home
                $redirect_url = $_SESSION['redirect_after_login'] ?? '../index.php';
                unset($_SESSION['redirect_after_login']);
                
                $_SESSION['flash_message'] = 'Welcome back, ' . $user['display_name'] . '!';
                $_SESSION['flash_type'] = 'success';
                header("Location: $redirect_url");
                exit;
            } else {
                $errors[] = 'Invalid username/email or password.';
            }
        }
    }
}

include '../../includes/header.php';
?>

<div class="ody-auth">
    <section class="ody-hero" style="border:0;margin-bottom:0;padding-bottom:0">
        <span class="label">forum · sign in</span>
        <h1>Welcome <span class="accent-word">back</span>.</h1>
    </section>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo sanitize_input($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

        <div class="mb-3">
            <label for="username" class="form-label">username or email</label>
            <input type="text" class="form-control" id="username" name="username"
                   value="<?php echo sanitize_input($_POST['username'] ?? ''); ?>"
                   required autocomplete="username">
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">password</label>
            <input type="password" class="form-control" id="password" name="password"
                   required autocomplete="current-password">
        </div>

        <div class="mb-4 form-check">
            <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me">
            <label class="form-check-label" for="remember_me">remember me · 30 days</label>
        </div>

        <button type="submit" class="btn btn-primary">$_ sign in →</button>
    </form>

    <p class="mt-4 mb-0 small" style="color: var(--text-dim); letter-spacing: 1px;">
        not a member yet? <a href="register.php" style="color:#ff0033;border-bottom:1px solid rgba(255,0,51,.45)">join the journey →</a>
    </p>
</div>

<?php include '../../includes/footer.php'; ?>
