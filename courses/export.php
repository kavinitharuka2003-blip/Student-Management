<?php
/**
 * ==============================================================================
 * Course Curriculum CSV Exporter (courses/export.php)
 * ------------------------------------------------------------------------------
 * Generates and downloads a CSV spreadsheet of all academic course offerings,
 * credit allocations, capacity limits, and assigned faculty instructors.
 * ==============================================================================
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

$pdo = get_db();

$search = trim($_GET['search'] ?? '');
$lecturer_filter = (int)($_GET['lecturer'] ?? 0);

$where_clauses = [];
$params = [];

if ($search !== '') {
    $where_clauses[] = "(c.course_code LIKE :search_code OR c.course_name LIKE :search_name OR c.description LIKE :search_desc)";
    $search_param = "%{$search}%";
    $params[':search_code'] = $search_param;
    $params[':search_name'] = $search_param;
    $params[':search_desc'] = $search_param;
}

if ($lecturer_filter > 0) {
    $where_clauses[] = "c.lecturer_id = :lecturer_filter";
    $params[':lecturer_filter'] = $lecturer_filter;
}

$where_sql = !empty($where_clauses) ? ' WHERE ' . implode(' AND ', $where_clauses) : '';

$sql = "
    SELECT 
        c.course_code,
        c.course_name,
        c.description,
        c.credits,
        c.capacity,
        u.full_name AS lecturer_name,
        u.email AS lecturer_email,
        COUNT(e.id) AS active_enrolled
    FROM courses c
    LEFT JOIN users u ON c.lecturer_id = u.id
    LEFT JOIN enrollments e ON c.id = e.course_id AND e.status = 'enrolled'
    {$where_sql}
    GROUP BY c.id, c.course_code, c.course_name, c.description, c.credits, c.capacity, u.id, u.full_name, u.email
    ORDER BY c.course_code ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

$filename = 'courses_export_' . date('Y-m-d_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

fputcsv($output, [
    'Course Code',
    'Course Title',
    'Description',
    'Credits',
    'Max Capacity',
    'Active Enrollments',
    'Available Seats',
    'Assigned Lecturer',
    'Lecturer Email'
]);

foreach ($records as $row) {
    $available = max(0, (int)$row['capacity'] - (int)$row['active_enrolled']);
    fputcsv($output, [
        $row['course_code'],
        $row['course_name'],
        $row['description'] ?? 'N/A',
        $row['credits'],
        $row['capacity'],
        $row['active_enrolled'],
        $available,
        $row['lecturer_name'] ?? 'Unassigned',
        $row['lecturer_email'] ?? 'N/A'
    ]);
}

fclose($output);
exit;
