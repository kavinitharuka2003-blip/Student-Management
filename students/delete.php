<?php
/**
 * ==============================================================================
 * Student Deletion Endpoint (students/delete.php)
 * ------------------------------------------------------------------------------
 * POST-only endpoint protected by CSRF verification and Admin role guards.
 * Deletes student records with database-level cascading enrollment removal.
 * ==============================================================================
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Enforce Admin-only access
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    set_flash('danger', 'Method Not Allowed. Deletion must be submitted via POST.');
    header("Location: " . get_app_url('students/list.php'));
    exit;
}

verify_csrf_or_die();

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    set_flash('danger', 'Invalid student ID specified for deletion.');
    header("Location: " . get_app_url('students/list.php'));
    exit;
}

try {
    $pdo = get_db();

    // Fetch student name for flash notification before deletion
    $stmt_fetch = $pdo->prepare("SELECT first_name, last_name, student_number FROM students WHERE id = :id LIMIT 1");
    $stmt_fetch->execute([':id' => $id]);
    $student = $stmt_fetch->fetch();

    if (!$student) {
        set_flash('warning', 'The student record has already been removed or does not exist.');
        header("Location: " . get_app_url('students/list.php'));
        exit;
    }

    // Execute deletion (Database cascade handles related enrollments)
    $stmt_del = $pdo->prepare("DELETE FROM students WHERE id = :id");
    $stmt_del->execute([':id' => $id]);

    set_flash('success', "Student '{$student['first_name']} {$student['last_name']} ({$student['student_number']})' was successfully removed.");

} catch (PDOException $e) {
    error_log("Student Deletion Error: " . $e->getMessage());
    set_flash('danger', 'A database error occurred while deleting the student record.');
}

header("Location: " . get_app_url('students/list.php'));
exit;
