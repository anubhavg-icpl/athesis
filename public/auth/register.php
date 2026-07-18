<?php
require_once '../../config/config.php';

$page_title = 'Register';
$errors = [];
$success = false;

// Redirect if already logged in
if (is_logged_in()) {
    header('Location: ../index.php');
    exit;
}

// Process registration form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $display_name = trim($_POST['display_name'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validate input
        if (empty($username)) {
            $errors[] = 'Username is required.';
        } elseif (!validate_username($username)) {
            $errors[] = 'Username must be 3-20 characters long and contain only letters, numbers, and underscores.';
        }
        
        if (empty($email)) {
            $errors[] = 'Email is required.';
        } elseif (!validate_email($email)) {
            $errors[] = 'Please enter a valid email address.';
        }
        
        if (empty($display_name)) {
            $errors[] = 'Display name is required.';
        } elseif (strlen($display_name) < 2 || strlen($display_name) > 50) {
            $errors[] = 'Display name must be 2-50 characters long.';
        }
        
        if (empty($password)) {
            $errors[] = 'Password is required.';
        } elseif (!validate_password($password)) {
            $errors[] = 'Password must be at least 6 characters long.';
        }
        
        if ($password !== $confirm_password) {
            $errors[] = 'Passwords do not match.';
        }
        
        // Check if username or email already exists
        if (empty($errors)) {
            $db = getDB();
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $errors[] = 'Username or email already exists.';
            }
        }
        
        // Create user if no errors
        if (empty($errors)) {
            try {
                $password_hash = hash_password($password);
                $stmt = $db->prepare("INSERT INTO users (username, email, display_name, password_hash) VALUES (?, ?, ?, ?)");
                $stmt->execute([$username, $email, $display_name, $password_hash]);
                
                $success = true;
                $_SESSION['flash_message'] = 'Registration successful! You can now log in.';
                $_SESSION['flash_type'] = 'success';
                header('Location: login.php');
                exit;
            } catch (PDOException $e) {
                $errors[] = 'Registration failed. Please try again.';
            }
        }
    }
}

include '../../includes/header.php';
?>

<div class="ody-auth">
    <section class="ody-hero" style="border:0;margin-bottom:0;padding-bottom:0">
        <span class="label">forum · join</span>
        <h1>Start the <span class="accent-word">journey</span>.</h1>
        <p>create an account to post and reply.</p>
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
            <label for="username" class="form-label">username</label>
            <input type="text" class="form-control" id="username" name="username"
                   value="<?php echo sanitize_input($_POST['username'] ?? ''); ?>"
                   required maxlength="20" pattern="[a-zA-Z0-9_]{3,20}"
                   autocomplete="username">
            <div class="form-text">3–20 chars · letters, numbers, _</div>
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">email</label>
            <input type="email" class="form-control" id="email" name="email"
                   value="<?php echo sanitize_input($_POST['email'] ?? ''); ?>"
                   required maxlength="100" autocomplete="email">
        </div>

        <div class="mb-3">
            <label for="display_name" class="form-label">display name</label>
            <input type="text" class="form-control" id="display_name" name="display_name"
                   value="<?php echo sanitize_input($_POST['display_name'] ?? ''); ?>"
                   required maxlength="50" autocomplete="nickname">
            <div class="form-text">shown on posts</div>
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">password</label>
            <input type="password" class="form-control" id="password" name="password"
                   required minlength="6" autocomplete="new-password">
            <div class="form-text">min 6 characters</div>
        </div>

        <div class="mb-4">
            <label for="confirm_password" class="form-label">confirm password</label>
            <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                   required minlength="6" autocomplete="new-password">
        </div>

        <button type="submit" class="btn btn-primary">$_ register →</button>
    </form>

    <p class="mt-4 mb-0 small" style="color: var(--text-dim); letter-spacing: 1px;">
        already here? <a href="login.php" style="color:#ff0033;border-bottom:1px solid rgba(255,0,51,.45)">login →</a>
    </p>
</div>

<?php include '../../includes/footer.php'; ?>
