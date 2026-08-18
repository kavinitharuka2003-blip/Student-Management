<?php
/**
 * ==============================================================================
 * User Profile & Security Settings (auth/profile.php)
 * ------------------------------------------------------------------------------
 * Allows authenticated administrators and faculty lecturers to manage their
 * biographical data and securely update their account password.
 * ==============================================================================
 */

$page_title = 'Account Profile & Security';
require_once __DIR__ . '/../includes/header.php';

$pdo = get_db();
$user_id = (int)$_SESSION['user_id'];
$errors = [];

// Fetch latest user details from database
$stmt = $pdo->prepare("SELECT id, username, full_name, email, role, created_at FROM users WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $user_id]);
$user = $stmt->fetch();

if (!$user) {
    set_flash('danger', 'User account could not be found.');
    header("Location: " . get_app_url('dashboard.php'));
    exit;
}

// ------------------------------------------------------------------------------
// Handle Profile Info Update
// ------------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_profile'])) {
    verify_csrf_or_die();

    $full_name = trim($_POST['full_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));

    validate_required($full_name, 'Full Name', $errors);
    validate_required($email, 'Email Address', $errors);
    validate_email($email, 'Email Address', $errors);

    // Check email uniqueness against other users
    if (empty($errors['Email Address'])) {
        $stmt_check = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1");
        $stmt_check->execute([':email' => $email, ':id' => $user_id]);
        if ($stmt_check->fetch()) {
            $errors['email'] = 'This email address is already in use by another account.';
        }
    }

    if (empty($errors)) {
        try {
            $update_stmt = $pdo->prepare("UPDATE users SET full_name = :name, email = :email WHERE id = :id");
            $update_stmt->execute([':name' => $full_name, ':email' => $email, ':id' => $user_id]);

            // Update session data
            $_SESSION['full_name'] = $full_name;
            $_SESSION['email'] = $email;

            set_flash('success', 'Profile information updated successfully.');
            header("Location: " . get_app_url('auth/profile.php'));
            exit;

        } catch (PDOException $e) {
            error_log("Profile Update Error: " . $e->getMessage());
            set_flash('danger', 'A database error occurred while updating profile.');
        }
    }
}

// ------------------------------------------------------------------------------
// Handle Password Change
// ------------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_password'])) {
    verify_csrf_or_die();

    $current_pwd = $_POST['current_password'] ?? '';
    $new_pwd = $_POST['new_password'] ?? '';
    $confirm_pwd = $_POST['confirm_password'] ?? '';

    validate_required($current_pwd, 'Current Password', $errors);
    validate_required($new_pwd, 'New Password', $errors);

    if (strlen($new_pwd) < 6) {
        $errors['new_password'] = 'New password must be at least 6 characters in length.';
    }

    if ($new_pwd !== $confirm_pwd) {
        $errors['confirm_password'] = 'The password confirmation does not match the new password.';
    }

    if (empty($errors)) {
        // Fetch current password hash
        $pwd_stmt = $pdo->prepare("SELECT password FROM users WHERE id = :id LIMIT 1");
        $pwd_stmt->execute([':id' => $user_id]);
        $curr_hash = $pwd_stmt->fetchColumn();

        if ($curr_hash && (password_verify($current_pwd, $curr_hash) || $current_pwd === $curr_hash)) {
            $new_hash = password_hash($new_pwd, PASSWORD_BCRYPT);
            $upd_pwd_stmt = $pdo->prepare("UPDATE users SET password = :pwd WHERE id = :id");
            $upd_pwd_stmt->execute([':pwd' => $new_hash, ':id' => $user_id]);

            set_flash('success', 'Your password has been changed successfully.');
            header("Location: " . get_app_url('auth/profile.php'));
            exit;
        } else {
            $errors['current_password'] = 'The current password you entered is incorrect.';
        }
    }
}
?>

<div class="row g-4">
    <!-- User Information Overview Card -->
    <div class="col-12 col-lg-4">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4 text-center">
                <div class="user-avatar mx-auto mb-3" style="width: 80px; height: 80px; font-size: 2rem; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: #fff; box-shadow: 0 8px 20px rgba(79, 70, 229, 0.25);">
                    <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                </div>
                <h4 class="font-heading fw-bold text-dark mb-1"><?= e($user['full_name']) ?></h4>
                <div class="text-muted small font-monospace mb-2">@<?= e($user['username']) ?></div>
                <div class="mb-3">
                    <span class="badge <?= $user['role'] === 'admin' ? 'bg-primary' : 'bg-info text-dark' ?> px-3 py-1.5 rounded-pill">
                        <i class="bi <?= $user['role'] === 'admin' ? 'bi-shield-lock-fill' : 'bi-person-badge-fill' ?> me-1"></i>
                        <?= ucfirst($user['role']) ?>
                    </span>
                </div>
            </div>
            
            <div class="border-top px-4 py-3 bg-light small">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Account ID:</span>
                    <span class="fw-semibold font-monospace">#<?= $user['id'] ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Registered Email:</span>
                    <span class="fw-semibold"><?= e($user['email']) ?></span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Member Since:</span>
                    <span class="fw-semibold"><?= date('M Y', strtotime($user['created_at'])) ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Forms Column -->
    <div class="col-12 col-lg-8">
        
        <!-- Profile Info Card -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="card-header-title text-dark">
                    <i class="bi bi-person-gear text-primary me-2"></i> Update Profile Details
                </h5>
            </div>
            <form method="POST" action="<?= e($_SERVER['PHP_SELF']) ?>" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action_profile" value="1">

                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="username_ro" class="form-label text-muted">Username (Fixed)</label>
                            <input type="text" class="form-control font-monospace bg-light" id="username_ro" value="<?= e($user['username']) ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label for="role_ro" class="form-label text-muted">Account Role</label>
                            <input type="text" class="form-control bg-light" id="role_ro" value="<?= ucfirst($user['role']) ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control <?= isset($errors['Full Name']) ? 'is-invalid' : '' ?>" 
                                   id="full_name" 
                                   name="full_name" 
                                   value="<?= e($user['full_name']) ?>" 
                                   required>
                            <?php if (isset($errors['Full Name'])): ?>
                                <div class="invalid-feedback d-block"><?= e($errors['Full Name']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                            <input type="email" 
                                   class="form-control <?= isset($errors['Email Address']) || isset($errors['email']) ? 'is-invalid' : '' ?>" 
                                   id="email" 
                                   name="email" 
                                   value="<?= e($user['email']) ?>" 
                                   required>
                            <?php if (isset($errors['Email Address']) || isset($errors['email'])): ?>
                                <div class="invalid-feedback d-block"><?= e($errors['Email Address'] ?? $errors['email']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-light border-top p-3 text-end">
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-save me-1"></i> Update Profile
                    </button>
                </div>
            </form>
        </div>

        <!-- Password Change Card -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h5 class="card-header-title text-dark">
                    <i class="bi bi-key text-primary me-2"></i> Change Password
                </h5>
            </div>
            <form method="POST" action="<?= e($_SERVER['PHP_SELF']) ?>" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action_password" value="1">

                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="current_password" class="form-label">Current Password <span class="text-danger">*</span></label>
                            <input type="password" 
                                   class="form-control <?= isset($errors['Current Password']) || isset($errors['current_password']) ? 'is-invalid' : '' ?>" 
                                   id="current_password" 
                                   name="current_password" 
                                   placeholder="Enter current password" 
                                   required>
                            <?php if (isset($errors['Current Password']) || isset($errors['current_password'])): ?>
                                <div class="invalid-feedback d-block"><?= e($errors['Current Password'] ?? $errors['current_password']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label for="new_password" class="form-label">New Password <span class="text-danger">*</span></label>
                            <input type="password" 
                                   class="form-control <?= isset($errors['New Password']) || isset($errors['new_password']) ? 'is-invalid' : '' ?>" 
                                   id="new_password" 
                                   name="new_password" 
                                   placeholder="Min 6 characters" 
                                   required>
                            <?php if (isset($errors['New Password']) || isset($errors['new_password'])): ?>
                                <div class="invalid-feedback d-block"><?= e($errors['New Password'] ?? $errors['new_password']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label for="confirm_password" class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                            <input type="password" 
                                   class="form-control <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   placeholder="Re-enter new password" 
                                   required>
                            <?php if (isset($errors['confirm_password'])): ?>
                                <div class="invalid-feedback d-block"><?= e($errors['confirm_password']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-light border-top p-3 text-end">
                    <button type="submit" class="btn btn-warning px-4 text-dark fw-semibold">
                        <i class="bi bi-shield-check me-1"></i> Change Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
