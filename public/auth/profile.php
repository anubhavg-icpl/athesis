<?php
require_once '../../config/config.php';

$page_title = 'Profile';
require_login();

$current_user = get_current_user_data();
$errors = [];
$success = false;

// Process profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $display_name = trim($_POST['display_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validate display name
        if (empty($display_name)) {
            $errors[] = 'Display name is required.';
        } elseif (strlen($display_name) < 2 || strlen($display_name) > 50) {
            $errors[] = 'Display name must be 2-50 characters long.';
        }
        
        // Validate email
        if (empty($email)) {
            $errors[] = 'Email is required.';
        } elseif (!validate_email($email)) {
            $errors[] = 'Please enter a valid email address.';
        }
        
        $db = getDB();

        // Check if email is already taken by another user
        if (empty($errors) && $email !== $current_user['email']) {
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $current_user['id']]);
            if ($stmt->fetch()) {
                $errors[] = 'Email address is already in use.';
            }
        }
        
        // Validate password change if requested
        $update_password = false;
        if (!empty($new_password)) {
            if (empty($current_password)) {
                $errors[] = 'Current password is required to change password.';
            } else {
                // Verify current password
                $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
                $stmt->execute([$current_user['id']]);
                $user_data = $stmt->fetch();
                
                if (!$user_data || !verify_password($current_password, $user_data['password_hash'])) {
                    $errors[] = 'Current password is incorrect.';
                } elseif (!validate_password($new_password)) {
                    $errors[] = 'New password must be at least 6 characters long.';
                } elseif ($new_password !== $confirm_password) {
                    $errors[] = 'New passwords do not match.';
                } else {
                    $update_password = true;
                }
            }
        }
        
        // Update profile if no errors
        if (empty($errors)) {
            try {
                if ($update_password) {
                    $password_hash = hash_password($new_password);
                    $stmt = $db->prepare("UPDATE users SET display_name = ?, email = ?, password_hash = ? WHERE id = ?");
                    $stmt->execute([$display_name, $email, $password_hash, $current_user['id']]);
                } else {
                    $stmt = $db->prepare("UPDATE users SET display_name = ?, email = ? WHERE id = ?");
                    $stmt->execute([$display_name, $email, $current_user['id']]);
                }
                
                // Update session data
                $_SESSION['display_name'] = $display_name;
                
                $success = true;
                $_SESSION['flash_message'] = 'Profile updated successfully!';
                $_SESSION['flash_type'] = 'success';
                header('Location: profile.php');
                exit;
            } catch (PDOException $e) {
                $errors[] = 'Profile update failed. Please try again.';
            }
        }
    }
}

// Get user statistics
$db = getDB();
$stmt = $db->prepare("SELECT COUNT(*) as topic_count FROM topics WHERE user_id = ?");
$stmt->execute([$current_user['id']]);
$topic_count = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) as reply_count FROM replies WHERE user_id = ? AND is_deleted = 0");
$stmt->execute([$current_user['id']]);
$reply_count = $stmt->fetchColumn();

include '../../includes/header.php';
?>

<div class="ody-page-head">
    <h1><span class="prompt">$_</span>profile</h1>
</div>

<div class="row g-4">
    <div class="col-md-8">
        <div class="ody-panel">
            <div class="ody-panel-head">edit profile</div>
            <div class="ody-panel-body">
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
                        <input type="text" class="form-control" id="username"
                               value="<?php echo sanitize_input($current_user['username']); ?>"
                               disabled>
                        <div class="form-text">cannot be changed</div>
                    </div>

                    <div class="mb-3">
                        <label for="display_name" class="form-label">display name</label>
                        <input type="text" class="form-control" id="display_name" name="display_name"
                               value="<?php echo sanitize_input($_POST['display_name'] ?? $current_user['display_name']); ?>"
                               required maxlength="50">
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">email</label>
                        <input type="email" class="form-control" id="email" name="email"
                               value="<?php echo sanitize_input($_POST['email'] ?? $current_user['email']); ?>"
                               required maxlength="100">
                    </div>

                    <hr>
                    <p class="form-label mb-3" style="display:block;">change password · optional</p>

                    <div class="mb-3">
                        <label for="current_password" class="form-label">current password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password"
                               autocomplete="current-password">
                    </div>

                    <div class="mb-3">
                        <label for="new_password" class="form-label">new password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password"
                               minlength="6" autocomplete="new-password">
                    </div>

                    <div class="mb-4">
                        <label for="confirm_password" class="form-label">confirm new password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                               minlength="6" autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn btn-primary">update profile</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4 ody-sidebar">
        <div class="ody-stats" style="grid-template-columns: 1fr 1fr;">
            <div class="ody-stat">
                <span class="num"><?php echo (int) $topic_count; ?></span>
                <span class="lbl">topics</span>
            </div>
            <div class="ody-stat">
                <span class="num"><?php echo (int) $reply_count; ?></span>
                <span class="lbl">replies</span>
            </div>
        </div>

        <div class="ody-panel">
            <div class="ody-panel-head">account</div>
            <div class="ody-panel-body small" style="color: var(--text-mute);">
                <p class="mb-2">
                    <span style="color: var(--text-dim);">member since</span><br>
                    <?php echo format_date($current_user['join_date']); ?>
                </p>
                <p class="mb-0">
                    <span style="color: var(--text-dim);">role</span><br>
                    <?php echo strtolower(sanitize_input($current_user['user_role'])); ?>
                </p>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
