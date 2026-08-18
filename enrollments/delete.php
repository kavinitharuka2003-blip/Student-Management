<?php
/**
 * ==============================================================================
 * Enrollment Removal / Drop Endpoint (enrollments/delete.php)
 * ------------------------------------------------------------------------------
 * POST-only endpoint protected by CSRF verification and Admin role guards.
 * Removes a student from a course enrollment.
 * ==============================================================================
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    set_flash('danger', 'Method Not Allowed. Enrollment drop must be sent via POST.');
    header("Location: " . get_app_url('enrollments/list.php'));
    exit;
}

verify_csrf_or_die();

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    set_flash('danger', 'Invalid enrollment ID specified.');
    header("Location: " . get_app_url('enrollments/list.php'));
    exit;
}

try {
    $pdo = get_db();

    $stmt_fetch = $pdo->prepare("
        SELECT 
            s.first_name, 
            s.last_name, 
            c.course_code 
        FROM enrollments e
        INNER JOIN students s ON e.student_id = s.id
        INNER JOIN courses c ON e.course_id = c.id
        WHERE e.id = :id
        LIMIT 1
    ");
    $stmt_fetch->execute([':id' => $id]);
    $record = $stmt_fetch->fetch();

    if (!$record) {
        set_flash('warning', 'The specified enrollment record has already been removed.');
        header("Location: " . get_app_url('enrollments/list.php'));
        exit;
    }

    $stmt_del = $pdo->prepare("DELETE FROM enrollments WHERE id = :id");
    $stmt_del->execute([':id' => $id]);

    set_flash('success', "Enrollment for '{$record['first_name']} {$record['last_name']}' in module '{$record['course_code']}' was removed.");

} catch (PDOException $e) {
    error_log("Enrollment Deletion Error: " . $e->getMessage());
    set_flash('danger', 'A database error occurred while removing the enrollment.');
}

header("Location: " . get_app_url('enrollments/list.php'));
exit;
