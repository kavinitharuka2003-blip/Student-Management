<?php
/**
 * ==============================================================================
 * Course Deletion Endpoint (courses/delete.php)
 * ------------------------------------------------------------------------------
 * POST-only administrative deletion handler protected by CSRF verification.
 * Automatically cleans up child enrollment records via database cascade.
 * ==============================================================================
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    set_flash('danger', 'Method Not Allowed. Course deletion must be sent via POST.');
    header("Location: " . get_app_url('courses/list.php'));
    exit;
}

verify_csrf_or_die();

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    set_flash('danger', 'Invalid course ID specified.');
    header("Location: " . get_app_url('courses/list.php'));
    exit;
}

try {
    $pdo = get_db();

    $stmt_fetch = $pdo->prepare("SELECT course_code, course_name FROM courses WHERE id = :id LIMIT 1");
    $stmt_fetch->execute([':id' => $id]);
    $course = $stmt_fetch->fetch();

    if (!$course) {
        set_flash('warning', 'The specified course has already been removed.');
        header("Location: " . get_app_url('courses/list.php'));
        exit;
    }

    $stmt_del = $pdo->prepare("DELETE FROM courses WHERE id = :id");
    $stmt_del->execute([':id' => $id]);

    set_flash('success', "Course '{$course['course_code']} - {$course['course_name']}' was successfully deleted.");

} catch (PDOException $e) {
    error_log("Course Deletion Error: " . $e->getMessage());
    set_flash('danger', 'A database error occurred while deleting the course offering.');
}

header("Location: " . get_app_url('courses/list.php'));
exit;
