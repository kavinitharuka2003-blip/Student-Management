<?php
/**
 * ==============================================================================
 * Enrollment Ledger & Grade Book (enrollments/list.php)
 * ------------------------------------------------------------------------------
 * Comprehensive record of all student-course associations with multi-faceted
 * filtering (by course, status, grade), search, sorting, and pagination.
 * ==============================================================================
 */

$page_title = 'Enrollment Ledger';
require_once __DIR__ . '/../includes/header.php';

$pdo = get_db();

// ------------------------------------------------------------------------------
// Search, Filters & Sorting Parameters
// ------------------------------------------------------------------------------
$search = trim($_GET['search'] ?? '');
$course_filter = (int)($_GET['course_id'] ?? 0);
$status_filter = trim($_GET['status'] ?? '');
$grade_filter = trim($_GET['grade'] ?? '');
$sort_by = trim($_GET['sort'] ?? 'enrolled_date');
$sort_order = strtoupper(trim($_GET['order'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
$current_page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;

// Whitelist sort fields
$allowed_sorts = [
    'student'       => 's.last_name',
    'student_num'   => 's.student_number',
    'course'        => 'c.course_code',
    'enrolled_date' => 'e.enrollment_date',
    'status'        => 'e.status',
    'grade'         => 'e.grade',
    'created_at'    => 'e.created_at'
];

$order_clause = $allowed_sorts[$sort_by] ?? 'e.created_at';

// ------------------------------------------------------------------------------
// Query Construction
// ------------------------------------------------------------------------------
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

// 1. Get Count
$count_query = "
    SELECT COUNT(*)
    FROM enrollments e
    INNER JOIN students s ON e.student_id = s.id
    INNER JOIN courses c ON e.course_id = c.id
    {$where_sql}
";
$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($params);
$total_enrollments = (int)$count_stmt->fetchColumn();

$pagination = paginate($total_enrollments, $per_page, $current_page);

// 2. Fetch Data
$data_query = "
    SELECT 
        e.*,
        s.student_number,
        s.first_name,
        s.last_name,
        s.email AS student_email,
        c.course_code,
        c.course_name,
        c.credits,
        u.full_name AS lecturer_name
    FROM enrollments e
    INNER JOIN students s ON e.student_id = s.id
    INNER JOIN courses c ON e.course_id = c.id
    LEFT JOIN users u ON c.lecturer_id = u.id
    {$where_sql}
    ORDER BY {$order_clause} {$sort_order}
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($data_query);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $pagination['per_page'], PDO::PARAM_INT);
$stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
$stmt->execute();
$enrollments = $stmt->fetchAll();

// Fetch Courses for Filter Dropdown
$all_courses = $pdo->query("SELECT id, course_code, course_name FROM courses ORDER BY course_code ASC")->fetchAll();

function enr_sort_url(string $col, string $current_col, string $current_order): string {
    $params = $_GET;
    $params['sort'] = $col;
    $params['order'] = ($current_col === $col && $current_order === 'ASC') ? 'DESC' : 'ASC';
    $params['page'] = 1;
    return '?' . http_build_query($params);
}
?>

<!-- Action Bar -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h3 class="font-heading fw-bold text-dark mb-1">Enrollment Ledger</h3>
        <p class="text-muted small mb-0">Track student course allocations, academic standings, and module grades.</p>
    </div>
    
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= e(get_app_url('enrollments/export.php?' . http_build_query($_GET))) ?>" class="btn btn-light border d-inline-flex align-items-center gap-2" title="Export ledger to CSV">
            <i class="bi bi-file-earmark-spreadsheet text-success"></i> Export CSV
        </a>
        <?php if (is_admin()): ?>
        <a href="<?= e(get_app_url('enrollments/add.php')) ?>" class="btn btn-primary d-inline-flex align-items-center gap-2">
            <i class="bi bi-plus-circle"></i> New Enrollment
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Filters Card -->
<div class="card mb-4">
    <div class="card-body p-3 p-md-4">
        <form method="GET" action="<?= e($_SERVER['PHP_SELF']) ?>" class="row g-3 align-items-end">
            <!-- Search Keyword -->
            <div class="col-12 col-md-4">
                <label for="search" class="form-label small text-muted">Search Records</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" 
                           class="form-control" 
                           id="search" 
                           name="search" 
                           placeholder="Student name, ID, or course code..." 
                           value="<?= e($search) ?>">
                </div>
            </div>

            <!-- Course Filter -->
            <div class="col-12 col-sm-6 col-md-3">
                <label for="course_id" class="form-label small text-muted">Course Filter</label>
                <select name="course_id" id="course_id" class="form-select">
                    <option value="0">All Courses</option>
                    <?php foreach ($all_courses as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $course_filter === (int)$c['id'] ? 'selected' : '' ?>>
                            <?= e($c['course_code']) ?> - <?= e($c['course_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Status Filter -->
            <div class="col-12 col-sm-6 col-md-2">
                <label for="status" class="form-label small text-muted">Status</label>
                <select name="status" id="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="enrolled" <?= $status_filter === 'enrolled' ? 'selected' : '' ?>>Enrolled</option>
                    <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="dropped" <?= $status_filter === 'dropped' ? 'selected' : '' ?>>Dropped</option>
                </select>
            </div>

            <!-- Grade Filter -->
            <div class="col-12 col-sm-6 col-md-1">
                <label for="grade" class="form-label small text-muted">Grade</label>
                <select name="grade" id="grade" class="form-select">
                    <option value="">All</option>
                    <option value="A+" <?= $grade_filter === 'A+' ? 'selected' : '' ?>>A+</option>
                    <option value="A" <?= $grade_filter === 'A' ? 'selected' : '' ?>>A</option>
                    <option value="B" <?= $grade_filter === 'B' ? 'selected' : '' ?>>B</option>
                    <option value="C" <?= $grade_filter === 'C' ? 'selected' : '' ?>>C</option>
                    <option value="D" <?= $grade_filter === 'D' ? 'selected' : '' ?>>D</option>
                    <option value="F" <?= $grade_filter === 'F' ? 'selected' : '' ?>>F</option>
                    <option value="pending" <?= $grade_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                </select>
            </div>

            <!-- Action Buttons -->
            <div class="col-12 col-sm-6 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-funnel-fill me-1"></i> Filter
                </button>
                <?php if ($search !== '' || $course_filter > 0 || $status_filter !== '' || $grade_filter !== '' || $sort_by !== 'enrolled_date'): ?>
                    <a href="<?= e(get_app_url('enrollments/list.php')) ?>" class="btn btn-light" title="Reset Filters">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Enrollments Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="card-header-title text-dark">
            <i class="bi bi-card-checklist me-1 text-primary"></i> 
            Enrollment Records <span class="badge bg-secondary-subtle text-secondary ms-2"><?= $total_enrollments ?> Total</span>
        </h6>
        <span class="text-muted small">Page <?= $pagination['current_page'] ?> of <?= $pagination['total_pages'] ?></span>
    </div>

    <div class="table-responsive">
        <table class="table custom-table">
            <thead>
                <tr>
                    <th>
                        <a href="<?= e(enr_sort_url('student', $sort_by, $sort_order)) ?>">
                            Student 
                            <?php if ($sort_by === 'student'): ?>
                                <i class="bi bi-arrow-<?= $sort_order === 'ASC' ? 'up' : 'down' ?> text-primary"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th>
                        <a href="<?= e(enr_sort_url('course', $sort_by, $sort_order)) ?>">
                            Course Module
                            <?php if ($sort_by === 'course'): ?>
                                <i class="bi bi-arrow-<?= $sort_order === 'ASC' ? 'up' : 'down' ?> text-primary"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th>
                        <a href="<?= e(enr_sort_url('enrolled_date', $sort_by, $sort_order)) ?>">
                            Enrolled Date
                            <?php if ($sort_by === 'enrolled_date'): ?>
                                <i class="bi bi-arrow-<?= $sort_order === 'ASC' ? 'up' : 'down' ?> text-primary"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th>
                        <a href="<?= e(enr_sort_url('status', $sort_by, $sort_order)) ?>">
                            Status
                            <?php if ($sort_by === 'status'): ?>
                                <i class="bi bi-arrow-<?= $sort_order === 'ASC' ? 'up' : 'down' ?> text-primary"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th>
                        <a href="<?= e(enr_sort_url('grade', $sort_by, $sort_order)) ?>">
                            Grade
                            <?php if ($sort_by === 'grade'): ?>
                                <i class="bi bi-arrow-<?= $sort_order === 'ASC' ? 'up' : 'down' ?> text-primary"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($enrollments)): ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="bi bi-card-heading"></i>
                                </div>
                                <h6 class="empty-state-title">No Enrollments Found</h6>
                                <p class="empty-state-desc">No enrollment records matched the selected query parameters.</p>
                                <?php if (is_admin()): ?>
                                    <a href="<?= e(get_app_url('enrollments/add.php')) ?>" class="btn btn-sm btn-primary">
                                        <i class="bi bi-plus-circle me-1"></i> Enroll Student
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($enrollments as $enr): ?>
                        <tr>
                            <td>
                                <div>
                                    <a href="<?= e(get_app_url('students/view.php?id=' . $enr['student_id'])) ?>" class="fw-bold text-dark text-decoration-none">
                                        <?= e($enr['first_name'] . ' ' . $enr['last_name']) ?>
                                    </a>
                                </div>
                                <div class="small text-muted font-monospace">
                                    <?= e($enr['student_number']) ?>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <a href="<?= e(get_app_url('courses/view.php?id=' . $enr['course_id'])) ?>" class="fw-bold text-primary font-monospace text-decoration-none">
                                        <?= e($enr['course_code']) ?>
                                    </a>
                                    <span class="text-dark small ms-1"><?= e($enr['course_name']) ?></span>
                                </div>
                                <div class="small text-muted">
                                    Lecturer: <?= e($enr['lecturer_name'] ?: 'Unassigned') ?> &bull; <?= e($enr['credits']) ?> cr
                                </div>
                            </td>
                            <td>
                                <span class="text-muted small"><?= date('M d, Y', strtotime($enr['enrollment_date'])) ?></span>
                            </td>
                            <td>
                                <?= render_status_badge($enr['status']) ?>
                            </td>
                            <td>
                                <?= render_grade_badge($enr['grade']) ?>
                            </td>
                            <td class="text-end">
                                <div class="btn-group" role="group">
                                    <!-- Edit Grade / Status -->
                                    <a href="<?= e(get_app_url('enrollments/edit.php?id=' . $enr['id'])) ?>" 
                                       class="btn btn-sm btn-light btn-action text-secondary" 
                                       title="Edit Grade & Status">
                                        <i class="bi bi-pencil"></i>
                                    </a>

                                    <?php if (is_admin()): ?>
                                    <!-- Delete Enrollment -->
                                    <button type="button" 
                                            class="btn btn-sm btn-light btn-action text-danger delete-btn" 
                                            data-id="<?= e($enr['id']) ?>" 
                                            data-name="<?= e($enr['first_name'] . ' ' . $enr['last_name'] . ' from ' . $enr['course_code']) ?>" 
                                            data-action="<?= e(get_app_url('enrollments/delete.php')) ?>" 
                                            title="Drop Enrollment">
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
                <span class="fw-semibold"><?= min($pagination['offset'] + $pagination['per_page'], $total_enrollments) ?></span> of 
                <span class="fw-semibold"><?= $total_enrollments ?></span> enrollments
            </div>
            
            <nav aria-label="Enrollments pagination">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= !$pagination['has_prev'] ? 'disabled' : '' ?>">
                        <?php $prev_p = $_GET; $prev_p['page'] = $pagination['current_page'] - 1; ?>
                        <a class="page-link" href="?<?= http_build_query($prev_p) ?>" aria-label="Previous">&laquo;</a>
                    </li>
                    <?php for ($p = 1; $p <= $pagination['total_pages']; $p++): ?>
                        <?php $p_params = $_GET; $p_params['page'] = $p; ?>
                        <li class="page-item <?= $p === $pagination['current_page'] ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query($p_params) ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= !$pagination['has_next'] ? 'disabled' : '' ?>">
                        <?php $next_p = $_GET; $next_p['page'] = $pagination['current_page'] + 1; ?>
                        <a class="page-link" href="?<?= http_build_query($next_p) ?>" aria-label="Next">&raquo;</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
