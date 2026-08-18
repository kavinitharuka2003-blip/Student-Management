<?php
/**
 * ==============================================================================
 * Student Data CSV Exporter (students/export.php)
 * ------------------------------------------------------------------------------
 * Generates and downloads a clean, structured CSV report of student records
 * applying any active filters for academic audits and reporting.
 * ==============================================================================
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

$pdo = get_db();

// Read query parameters
$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');

$where_clauses = [];
$params = [];

if ($search !== '') {
    $where_clauses[] = "(s.first_name LIKE :search_first OR s.last_name LIKE :search_last OR s.student_number LIKE :search_num OR s.email LIKE :search_email)";
    $search_param = "%{$search}%";
    $params[':search_first'] = $search_param;
    $params[':search_last']  = $search_param;
    $params[':search_num']   = $search_param;
    $params[':search_email'] = $search_param;
}

if ($status_filter !== '' && in_array($status_filter, ['active', 'inactive', 'graduated'])) {
    $where_clauses[] = "s.status = :status_filter";
    $params[':status_filter'] = $status_filter;
}

$where_sql = !empty($where_clauses) ? ' WHERE ' . implode(' AND ', $where_clauses) : '';

$sql = "
    SELECT 
        s.student_number,
        s.first_name,
        s.last_name,
        s.email,
        s.phone,
        s.date_of_birth,
        s.address,
        s.enrollment_date,
        s.status,
        COUNT(e.id) AS total_enrolled_courses
    FROM students s
    LEFT JOIN enrollments e ON s.id = e.student_id
    {$where_sql}
    GROUP BY s.id, s.student_number, s.first_name, s.last_name, s.email, s.phone, s.date_of_birth, s.address, s.enrollment_date, s.status
    ORDER BY s.last_name ASC, s.first_name ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

// Set HTTP download headers
$filename = 'students_export_' . date('Y-m-d_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Open output stream
$output = fopen('php://output', 'w');

// UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Header row
fputcsv($output, [
    'Student Number',
    'First Name',
    'Last Name',
    'Institutional Email',
    'Contact Phone',
    'Date of Birth',
    'Residential Address',
    'Enrollment Date',
    'Academic Status',
    'Enrolled Modules Count'
]);

// Data rows
foreach ($records as $row) {
    fputcsv($output, [
        $row['student_number'],
        $row['first_name'],
        $row['last_name'],
        $row['email'],
        $row['phone'] ?? 'N/A',
        $row['date_of_birth'] ?? 'N/A',
        $row['address'] ?? 'N/A',
        $row['enrollment_date'],
        ucfirst($row['status']),
        $row['total_enrolled_courses']
    ]);
}

fclose($output);
exit;
