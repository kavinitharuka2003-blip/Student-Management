<?php
/**
 * ==============================================================================
 * Authentication & Role-Based Access Control (RBAC) Module (includes/auth.php)
 * ------------------------------------------------------------------------------
 * Manages user session state, authentication verification, and access guard
 * assertions for Administrator vs Lecturer authorization tiers.
 * ==============================================================================
 */

// Start secure session if not already initialized
if (session_status() === PHP_SESSION_NONE) {
    // Configure secure session cookie settings
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

/**
 * Check if a user is currently logged in.
 *
 * @return bool True if authenticated, false otherwise.
 */
function is_logged_in(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Retrieve current logged-in user information array.
 *
 * @return array|null User session data or null if not logged in.
 */
function get_current_user_data(): ?array {
    if (!is_logged_in()) {
        return null;
    }

    return [
        'id'        => $_SESSION['user_id'],
        'username'  => $_SESSION['username'] ?? '',
        'full_name' => $_SESSION['full_name'] ?? '',
        'email'     => $_SESSION['email'] ?? '',
        'role'      => $_SESSION['role'] ?? 'lecturer',
    ];
}

/**
 * Check if the currently logged-in user has the 'admin' role.
 *
 * @return bool True if admin, false otherwise.
 */
function is_admin(): bool {
    return is_logged_in() && (($_SESSION['role'] ?? '') === 'admin');
}

/**
 * Check if the currently logged-in user has the 'lecturer' role.
 *
 * @return bool True if lecturer, false otherwise.
 */
function is_lecturer(): bool {
    return is_logged_in() && (($_SESSION['role'] ?? '') === 'lecturer');
}

/**
 * Get dynamic application base URL or relative root path.
 *
 * @return string Relative or absolute base URL prefix.
 */
/**
 * Get dynamic application base URL or relative root path.
 *
 * @param string $path Target relative path to append.
 * @return string Computed application URL.
 */
function get_app_url(string $path = ''): string {
    $script_name = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $script_dir = dirname($script_name);
    
    // Normalize root directory
    if ($script_dir === '/' || $script_dir === '\\' || $script_dir === '.') {
        $script_dir = '';
    }

    // If currently in a known subdirectory, climb up to project root
    $subdirs = ['/students', '/courses', '/enrollments', '/auth', '/config', '/includes'];
    foreach ($subdirs as $dir) {
        if (str_ends_with($script_dir, $dir)) {
            $script_dir = substr($script_dir, 0, -strlen($dir));
            break;
        }
    }
    
    $base = rtrim($script_dir, '/');
    return ($base === '' ? '' : $base) . '/' . ltrim($path, '/');
}

/**
 * Enforce that the user must be logged in to view the page.
 * Redirects to the login screen with a flash message if unauthenticated.
 */
function require_login(): void {
    if (!is_logged_in()) {
        // Store intended destination URL for post-login redirection
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        
        // Use relative redirect
        $login_url = get_app_url('auth/login.php');
        header("Location: " . $login_url);
        exit;
    }
}

/**
 * Enforce that the user must possess the 'admin' role.
 * Shows an access denied screen if permission is insufficient.
 */
function require_admin(): void {
    require_login();
    
    if (!is_admin()) {
        http_response_code(403);
        
        // If helper functions are available, display flash message, else die gracefully
        if (function_exists('set_flash')) {
            set_flash('error', 'Access Denied: You do not have permission to access that administrative resource.');
            header("Location: " . get_app_url('dashboard.php'));
            exit;
        }
        
        die("403 Forbidden: Administrator permissions required.");
    }
}

/**
 * Log in a user by populating session variables and regenerating the session ID
 * to guard against session fixation attacks.
 *
 * @param array $user User database record array.
 */
function login_user(array $user): void {
    session_regenerate_id(true); // Session fixation countermeasure
    
    $_SESSION['user_id']   = (int)$user['id'];
    $_SESSION['username']  = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email']     = $user['email'];
    $_SESSION['role']      = $user['role'];
    $_SESSION['logged_in_at'] = time();
}

/**
 * Securely terminate user session and clear auth cookies.
 */
function logout_user(): void {
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();
}
