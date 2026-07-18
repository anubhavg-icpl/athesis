<?php
require_once '../../config/config.php';

// Verify CSRF token for logout
if (!isset($_GET['token']) || !verify_csrf_token($_GET['token'])) {
    // If no token or invalid token, show confirmation page
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
        // Process logout
        perform_logout();
    } else {
        // Show logout confirmation
        $page_title = 'Logout';
        include '../../includes/header.php';
        ?>
        <div class="ody-auth">
            <div class="ody-page-head">
                <h1><span class="prompt">$_</span>logout</h1>
            </div>

            <div class="ody-panel">
                <div class="ody-panel-head">confirm</div>
                <div class="ody-panel-body">
                    <p style="color: var(--text-dim); margin-bottom: 1.25rem;">
                        end this session?
                    </p>
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <div class="d-flex gap-3 align-items-center">
                            <button type="submit" class="btn btn-primary">yes, logout</button>
                            <a href="../index.php" class="ody-link-btn">cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
        include '../../includes/footer.php';
        exit;
    }
} else {
    // Direct logout with valid token
    perform_logout();
}

function perform_logout() {
    // Remove session from database
    if (isset($_SESSION['user_id'])) {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM user_sessions WHERE id = ?");
        $stmt->execute([session_id()]);
    }
    
    // Clear session
    $_SESSION = [];
    
    // Destroy session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Clear remember me cookie
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/', '', false, true);
    }
    
    // Destroy session
    session_destroy();
    
    // Redirect with message
    session_start();
    $_SESSION['flash_message'] = 'You have been logged out successfully.';
    $_SESSION['flash_type'] = 'info';
    header('Location: ../index.php');
    exit;
}
?>
