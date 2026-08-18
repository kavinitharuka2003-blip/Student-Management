<?php
/**
 * ==============================================================================
 * Application Entry Router (index.php)
 * ------------------------------------------------------------------------------
 * Inspects authentication state and routes incoming requests to the
 * Executive Dashboard or the Authentication Portal.
 * ==============================================================================
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    header("Location: " . get_app_url('dashboard.php'));
    exit;
} else {
    header("Location: " . get_app_url('auth/login.php'));
    exit;
}
