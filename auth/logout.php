<?php
/**
 * ==============================================================================
 * Session Termination Handler (auth/logout.php)
 * ------------------------------------------------------------------------------
 * Cleanses user session data, invalidates session identifiers, and redirects
 * to the login portal with an informative flash notice.
 * ==============================================================================
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Terminate active session
logout_user();

// Start clean session for flash notification
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
set_flash('info', 'You have been successfully signed out.');

// Redirect to login page
header("Location: " . get_app_url('auth/login.php'));
exit;
