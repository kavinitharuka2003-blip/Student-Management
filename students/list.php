<?php
/**
 * ==============================================================================
 * Students Directory & Management (students/list.php)
 * ------------------------------------------------------------------------------
 * Displays paginated list of student records with dynamic multi-field search,
 * status filtering, column sorting, and responsive action controls.
 * ==============================================================================
 */

$page_title = 'Students Directory';
require_once __DIR__ . '/../includes/header.php';

$pdo = get_db();

// ------------------------------------------------------------------------------
// Filter, Search, Sort & Pagination Parameters
// ------------------------------------------------------------------------------
$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$sort_by = trim($_GET['sort'] ?? 'created_at');
$sort_order = strtoupper(trim($_GET['order'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
$current_page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;

// Whitelist permitted sorting columns to prevent SQL injection
$allowed_sort_columns = [
    'student_number'  => 's.student_number',
    'name'            => 's.first_name',
    'email'           => 's.email',
    'enrollment_date' => 's.enrollment_date',
    'status'          => 's.status',
    'created_at'      => 's.created_at'
];

$order_clause = $allowed_sort_columns[$sort_by] ?? 's.created_at';

// ------------------------------------------------------------------------------
// Build Dynamic SQL Query with Prepared Statements
// ------------------------------------------------------------------------------
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

// 1. Get Total Count for Pagination
$count_query = "SELECT COUNT(*) FROM students s" . $where_sql;
$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($params);
$total_students = (int)$count_stmt->fetchColumn();

// Compute pagination math
$pagination = paginate($total_students, $per_page, $current_page);

// 2. Fetch Paginated Records with Enrollment Count
$data_query = "
    SELECT s.*, COUNT(e.id) AS total_enrolled_courses
    FROM students s
    LEFT JOIN enrollments e ON s.id = e.student_id
    {$where_sql}
    GROUP BY s.id, s.student_number, s.first_name, s.last_name, s.email, s.phone, s.date_of_birth, s.address, s.enrollment_date, s.status, s.created_at, s.updated_at
    ORDER BY {$order_clause} {$sort_order}
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($data_query);

// Bind filter params
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
// Bind integer limit and offset strictly
$stmt->bindValue(':limit', $pagination['per_page'], PDO::PARAM_INT);
$stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
$stmt->execute();
$students = $stmt->fetchAll();

// Helper to generate sort link with current filters preserved
function sort_url(string $col, string $current_col, string $current_order): string {
    $params = $_GET;
    $params['sort'] = $col;
    $params['order'] = ($current_col === $col && $current_order === 'ASC') ? 'DESC' : 'ASC';
    $params['page'] = 1; // Reset to first page
    return '?' . http_build_query($params);
}
?>

<!-- Page Header & Action Bar -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h3 class="font-heading fw-bold text-dark mb-1">Student Directory</h3>
        <p class="text-muted small mb-0">Manage registered students, academic status, and enrollments.</p>
    </div>
    
    <div class="d-flex gap-2">
        <a href="<?= e(get_app_url('students/export.php?' . http_build_query($_GET))) ?>" class="btn btn-light border d-inline-flex align-items-center gap-2" title="Export current results as CSV">
            <i class="bi bi-file-earmark-spreadsheet text-success"></i> Export CSV
        </a>
        <?php if (is_admin()): ?>
        <a href="<?= e(get_app_url('students/add.php')) ?>" class="btn btn-primary d-inline-flex align-items-center gap-2">
            <i class="bi bi-person-plus-fill"></i> Add New Student
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Search, Filter & Quick Stats Card -->
<div class="card mb-4">
    <div class="card-body p-3 p-md-4">
        <form method="GET" action="<?= e($_SERVER['PHP_SELF']) ?>" class="row g-3 align-items-end">
            <!-- Search Query -->
            <div class="col-12 col-md-5">
                <label for="search" class="form-label small text-muted">Search Student</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" 
                           class="form-control" 
                           id="search" 
                           name="search" 
                           placeholder="Search by name, student ID, or email..." 
                           value="<?= e($search) ?>">
                </div>
            </div>

            <!-- Status Filter -->
            <div class="col-12 col-sm-6 col-md-3">
                <label for="status" class="form-label small text-muted">Status</label>
                <select name="status" id="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    <option value="graduated" <?= $status_filter === 'graduated' ? 'selected' : '' ?>>Graduated</option>
                </select>
            </div>

            <!-- Action Buttons -->
            <div class="col-12 col-sm-6 col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-funnel-fill me-1"></i> Filter
                </button>
                <?php if ($search !== '' || $status_filter !== '' || $sort_by !== 'created_at'): ?>
                    <a href="<?= e(get_app_url('students/list.php')) ?>" class="btn btn-light" title="Reset Filters">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Students Data Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="card-header-title text-dark">
            <i class="bi bi-people me-1 text-primary"></i> 
            Student Records <span class="badge bg-secondary-subtle text-secondary ms-2"><?= $total_students ?> Total</span>
        </h6>
        <span class="text-muted small">Page <?= $pagination['current_page'] ?> of <?= $pagination['total_pages'] ?></span>
    </div>

    <div class="table-responsive">
        <table class="table custom-table">
            <thead>
                <tr>
                    <th>
                        <a href="<?= e(sort_url('student_number', $sort_by, $sort_order)) ?>">
                            Student ID 
                            <?php if ($sort_by === 'student_number'): ?>
                                <i class="bi bi-arrow-<?= $sort_order === 'ASC' ? 'up' : 'down' ?> text-primary"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th>
                        <a href="<?= e(sort_url('name', $sort_by, $sort_order)) ?>">
                            Full Name 
                            <?php if ($sort_by === 'name'): ?>
                                <i class="bi bi-arrow-<?= $sort_order === 'ASC' ? 'up' : 'down' ?> text-primary"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th>
                        <a href="<?= e(sort_url('email', $sort_by, $sort_order)) ?>">
                            Email Address
                            <?php if ($sort_by === 'email'): ?>
                                <i class="bi bi-arrow-<?= $sort_order === 'ASC' ? 'up' : 'down' ?> text-primary"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th>Courses</th>
                    <th>
                        <a href="<?= e(sort_url('status', $sort_by, $sort_order)) ?>">
                            Status
                            <?php if ($sort_by === 'status'): ?>
                                <i class="bi bi-arrow-<?= $sort_order === 'ASC' ? 'up' : 'down' ?> text-primary"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th>
                        <a href="<?= e(sort_url('enrollment_date', $sort_by, $sort_order)) ?>">
                            Enrolled Date
                            <?php if ($sort_by === 'enrollment_date'): ?>
                                <i class="bi bi-arrow-<?= $sort_order === 'ASC' ? 'up' : 'down' ?> text-primary"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="bi bi-search"></i>
                                </div>
                                <h6 class="empty-state-title">No Students Found</h6>
                                <p class="empty-state-desc">No student records matched your search criteria. Try modifying your filter settings or register a new student.</p>
                                <?php if (is_admin()): ?>
                                    <a href="<?= e(get_app_url('students/add.php')) ?>" class="btn btn-sm btn-primary">
                                        <i class="bi bi-person-plus-fill me-1"></i> Register Student
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td>
                                <a href="<?= e(get_app_url('students/view.php?id=' . $student['id'])) ?>" class="fw-bold text-primary font-monospace text-decoration-none">
                                    <?= e($student['student_number']) ?>
                                </a>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="user-avatar" style="width: 32px; height: 32px; font-size: 0.75rem; background-color: #e0e7ff; color: #4338ca;">
                                        <?= strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <span class="fw-semibold text-dark"><?= e($student['first_name'] . ' ' . $student['last_name']) ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="text-muted small"><?= e($student['email']) ?></span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <i class="bi bi-journal-check text-primary me-1"></i>
                                    <?= (int)$student['total_enrolled_courses'] ?>
                                </span>
                            </td>
                            <td>
                                <?= render_status_badge($student['status']) ?>
                            </td>
                            <td>
                                <span class="text-muted small"><?= date('M d, Y', strtotime($student['enrollment_date'])) ?></span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group" role="group">
                                    <!-- View Details Button -->
                                    <a href="<?= e(get_app_url('students/view.php?id=' . $student['id'])) ?>" 
                                       class="btn btn-sm btn-light btn-action text-primary" 
                                       title="View Student Profile">
                                        <i class="bi bi-eye"></i>
                                    </a>

                                    <?php if (is_admin()): ?>
                                    <!-- Edit Record Button -->
                                    <a href="<?= e(get_app_url('students/edit.php?id=' . $student['id'])) ?>" 
                                       class="btn btn-sm btn-light btn-action text-secondary" 
                                       title="Edit Record">
                                        <i class="bi bi-pencil"></i>
                                    </a>

                                    <!-- Delete Record (Triggers Reusable Modal) -->
                                    <button type="button" 
                                            class="btn btn-sm btn-light btn-action text-danger delete-btn" 
                                            data-id="<?= e($student['id']) ?>" 
                                            data-name="<?= e($student['first_name'] . ' ' . $student['last_name'] . ' (' . $student['student_number'] . ')') ?>" 
                                            data-action="<?= e(get_app_url('students/delete.php')) ?>" 
                                            title="Delete Student">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Footer -->
    <?php if ($pagination['total_pages'] > 1): ?>
        <div class="card-footer bg-white border-top d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2 py-3">
            <div class="small text-muted">
                Showing <span class="fw-semibold"><?= $pagination['offset'] + 1 ?></span> to 
                <span class="fw-semibold"><?= min($pagination['offset'] + $pagination['per_page'], $total_students) ?></span> of 
                <span class="fw-semibold"><?= $total_students ?></span> students
            </div>
            
            <nav aria-label="Students pagination">
                <ul class="pagination pagination-sm mb-0">
                    <!-- Prev Page -->
                    <li class="page-item <?= !$pagination['has_prev'] ? 'disabled' : '' ?>">
                        <?php 
                        $prev_params = $_GET; 
                        $prev_params['page'] = $pagination['current_page'] - 1; 
                        ?>
                        <a class="page-link" href="?<?= http_build_query($prev_params) ?>" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>

                    <!-- Page Numbers -->
                    <?php for ($p = 1; $p <= $pagination['total_pages']; $p++): ?>
                        <?php 
                        $page_params = $_GET; 
                        $page_params['page'] = $p; 
                        ?>
                        <li class="page-item <?= $p === $pagination['current_page'] ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query($page_params) ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>

                    <!-- Next Page -->
                    <li class="page-item <?= !$pagination['has_next'] ? 'disabled' : '' ?>">
                        <?php 
                        $next_params = $_GET; 
                        $next_params['page'] = $pagination['current_page'] + 1; 
                        ?>
                        <a class="page-link" href="?<?= http_build_query($next_params) ?>" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
