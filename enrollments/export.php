<?php
/**
 * ==============================================================================
 * Enrollment & Grade Ledger CSV Exporter (enrollments/export.php)
 * ------------------------------------------------------------------------------
 * Generates and downloads a CSV spreadsheet of student course enrollments,
 * academic grades awarded, and status classifications.
 * ==============================================================================
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

$pdo = get_db();

$search = trim($_GET['search'] ?? '');
$course_filter = (int)($_GET['course_id'] ?? 0);
$status_filter = trim($_GET['status'] ?? '');
$grade_filter = trim($_GET['grade'] ?? '');

$where_clauses = [];
$params = [];

if ($search !== '') {
    $where_clauses[] = "(s.first_name LIKE :search_first OR s.last_name LIKE :search_last OR s.student_number LIKE :search_num OR c.course_code LIKE :search_code OR c.course_name LIKE :search_cname)";
    $search_param = "%{$search}%";
    $params[':search_first'] = $search_param;
    $params[':search_last']  = $search_param;
    $params[':search_num']   = $search_param;
    $params[':search_code']  = $search_param;
    $params[':search_cname'] = $search_param;
}

if ($course_filter > 0) {
    $where_clauses[] = "e.course_id = :course_filter";
    $params[':course_filter'] = $course_filter;
}

if ($status_filter !== '' && in_array($status_filter, ['enrolled', 'completed', 'dropped'])) {
    $where_clauses[] = "e.status = :status_filter";
    $params[':status_filter'] = $status_filter;
}

if ($grade_filter !== '') {
    if ($grade_filter === 'pending') {
        $where_clauses[] = "(e.grade IS NULL OR e.grade = '')";
    } else {
        $where_clauses[] = "e.grade = :grade_filter";
        $params[':grade_filter'] = $grade_filter;
    }
}

$where_sql = !empty($where_clauses) ? ' WHERE ' . implode(' AND ', $where_clauses) : '';

$sql = "
    SELECT 
        s.student_number,
        s.first_name,
        s.last_name,
        s.email AS student_email,
        c.course_code,
        c.course_name,
        c.credits,
        u.full_name AS lecturer_name,
        e.enrollment_date,
        e.grade,
        e.status AS enrollment_status
    FROM enrollments e
    INNER JOIN students s ON e.student_id = s.id
    INNER JOIN courses c ON e.course_id = c.id
    LEFT JOIN users u ON c.lecturer_id = u.id
    {$where_sql}
    ORDER BY c.course_code ASC, s.last_name ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

$filename = 'enrollments_ledger_' . date('Y-m-d_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

fputcsv($output, [
    'Student ID',
    'Student Name',
    'Student Email',
    'Course Code',
    'Course Name',
    'Credits',
    'Assigned Faculty',
    'Enrollment Date',
    'Awarded Grade',
    'Enrollment Status'
]);

foreach ($records as $row) {
    fputcsv($output, [
        $row['student_number'],
        $row['first_name'] . ' ' . $row['last_name'],
        $row['student_email'],
        $row['course_code'],
        $row['course_name'],
        $row['credits'],
        $row['lecturer_name'] ?? 'Unassigned',
        $row['enrollment_date'],
        $row['grade'] ?? 'Pending',
        ucfirst($row['enrollment_status'])
    ]);
}

fclose($output);
exit;
