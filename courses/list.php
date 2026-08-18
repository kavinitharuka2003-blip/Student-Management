<?php
/**
 * ==============================================================================
 * Course Catalog & Offerings (courses/list.php)
 * ------------------------------------------------------------------------------
 * Lists all active course modules with lecturer assignments, credit weightings,
 * enrollment capacity gauges, and search/filtering controls.
 * ==============================================================================
 */

$page_title = 'Course Catalog';
require_once __DIR__ . '/../includes/header.php';

$pdo = get_db();

// ------------------------------------------------------------------------------
// Search, Filter & Sorting Parameters
// ------------------------------------------------------------------------------
$search = trim($_GET['search'] ?? '');
$lecturer_filter = (int)($_GET['lecturer'] ?? 0);
$sort_by = trim($_GET['sort'] ?? 'course_code');
$sort_order = strtoupper(trim($_GET['order'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
$current_page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;

// Whitelist sort fields
$allowed_sorts = [
    'course_code' => 'c.course_code',
    'course_name' => 'c.course_name',
    'credits'     => 'c.credits',
    'capacity'    => 'c.capacity',
    'lecturer'    => 'u.full_name',
];

$order_clause = $allowed_sorts[$sort_by] ?? 'c.course_code';

// ------------------------------------------------------------------------------
// Build Query with Prepared Statements
// ------------------------------------------------------------------------------
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

// 1. Get Count
$count_query = "
    SELECT COUNT(*) 
    FROM courses c 
    LEFT JOIN users u ON c.lecturer_id = u.id
    {$where_sql}
";
$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($params);
$total_courses = (int)$count_stmt->fetchColumn();

$pagination = paginate($total_courses, $per_page, $current_page);

// 2. Fetch Courses with Enrollment Counts
$data_query = "
    SELECT 
        c.*,
        u.full_name AS lecturer_name,
        u.email AS lecturer_email,
        COUNT(e.id) AS total_enrolled
    FROM courses c
    LEFT JOIN users u ON c.lecturer_id = u.id
    LEFT JOIN enrollments e ON c.id = e.course_id AND e.status = 'enrolled'
    {$where_sql}
    GROUP BY c.id, u.id
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
$courses = $stmt->fetchAll();

// 3. Fetch Lecturers for Filter Dropdown
$lecturers = $pdo->query("SELECT id, full_name FROM users WHERE role = 'lecturer' ORDER BY full_name ASC")->fetchAll();

function course_sort_url(string $col, string $current_col, string $current_order): string {
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
        <h3 class="font-heading fw-bold text-dark mb-1">Course Catalog</h3>
        <p class="text-muted small mb-0">Browse academic curriculum, module allocations, and live capacity.</p>
    </div>
    
    <div class="d-flex flex-wrap gap-2">
        <?php if (is_lecturer()): ?>
            <?php $my_id = (int)($_SESSION['user_id'] ?? 0); ?>
            <a href="<?= e(get_app_url('courses/list.php?lecturer=' . $my_id)) ?>" class="btn btn-outline-primary d-inline-flex align-items-center gap-2 <?= $lecturer_filter === $my_id ? 'active' : '' ?>">
                <i class="bi bi-person-check-fill"></i> My Assigned Courses
            </a>
        <?php endif; ?>
        <a href="<?= e(get_app_url('courses/export.php?' . http_build_query($_GET))) ?>" class="btn btn-light border d-inline-flex align-items-center gap-2" title="Export courses to CSV">
            <i class="bi bi-file-earmark-spreadsheet text-success"></i> Export CSV
        </a>
        <?php if (is_admin()): ?>
        <a href="<?= e(get_app_url('courses/add.php')) ?>" class="btn btn-primary d-inline-flex align-items-center gap-2">
            <i class="bi bi-plus-lg"></i> Create New Course
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Filters Card -->
<div class="card mb-4">
    <div class="card-body p-3 p-md-4">
        <form method="GET" action="<?= e($_SERVER['PHP_SELF']) ?>" class="row g-3 align-items-end">
            <!-- Search Keyword -->
            <div class="col-12 col-md-5">
                <label for="search" class="form-label small text-muted">Search Courses</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" 
                           class="form-control" 
                           id="search" 
                           name="search" 
                           placeholder="Search by course code, title, or keywords..." 
                           value="<?= e($search) ?>">
                </div>
            </div>

            <!-- Lecturer Filter -->
            <div class="col-12 col-sm-6 col-md-4">
                <label for="lecturer" class="form-label small text-muted">Assigned Lecturer</label>
                <select name="lecturer" id="lecturer" class="form-select">
                    <option value="0">All Faculty / Lecturers</option>
                    <?php foreach ($lecturers as $lec): ?>
                        <option value="<?= $lec['id'] ?>" <?= $lecturer_filter === (int)$lec['id'] ? 'selected' : '' ?>>
                            <?= e($lec['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Action Buttons -->
            <div class="col-12 col-sm-6 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-funnel-fill me-1"></i> Filter
                </button>
                <?php if ($search !== '' || $lecturer_filter > 0 || $sort_by !== 'course_code'): ?>
                    <a href="<?= e(get_app_url('courses/list.php')) ?>" class="btn btn-light" title="Reset Filters">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Courses Data Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="card-header-title text-dark">
            <i class="bi bi-journal-text me-1 text-primary"></i> 
            Active Course Offerings <span class="badge bg-secondary-subtle text-secondary ms-2"><?= $total_courses ?> Total</span>
        </h6>
        <span class="text-muted small">Page <?= $pagination['current_page'] ?> of <?= $pagination['total_pages'] ?></span>
    </div>

    <div class="table-responsive">
        <table class="table custom-table">
            <thead>
                <tr>
                    <th>
                        <a href="<?= e(course_sort_url('course_code', $sort_by, $sort_order)) ?>">
                            Code 
                            <?php if ($sort_by === 'course_code'): ?>
                                <i class="bi bi-arrow-<?= $sort_order === 'ASC' ? 'up' : 'down' ?> text-primary"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th>
                        <a href="<?= e(course_sort_url('course_name', $sort_by, $sort_order)) ?>">
                            Course Name
                            <?php if ($sort_by === 'course_name'): ?>
                                <i class="bi bi-arrow-<?= $sort_order === 'ASC' ? 'up' : 'down' ?> text-primary"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th>
                        <a href="<?= e(course_sort_url('credits', $sort_by, $sort_order)) ?>">
                            Credits
                            <?php if ($sort_by === 'credits'): ?>
                                <i class="bi bi-arrow-<?= $sort_order === 'ASC' ? 'up' : 'down' ?> text-primary"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th>
                        <a href="<?= e(course_sort_url('lecturer', $sort_by, $sort_order)) ?>">
                            Assigned Lecturer
                            <?php if ($sort_by === 'lecturer'): ?>
                                <i class="bi bi-arrow-<?= $sort_order === 'ASC' ? 'up' : 'down' ?> text-primary"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th style="min-width: 180px;">Capacity & Enrollment</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($courses)): ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="bi bi-journal-x"></i>
                                </div>
                                <h6 class="empty-state-title">No Courses Found</h6>
                                <p class="empty-state-desc">No course offerings matched your filter criteria.</p>
                                <?php if (is_admin()): ?>
                                    <a href="<?= e(get_app_url('courses/add.php')) ?>" class="btn btn-sm btn-primary">
                                        <i class="bi bi-plus-lg me-1"></i> Add Course
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($courses as $course): ?>
                        <?php 
                        $cap = max(1, (int)$course['capacity']);
                        $enrolled = (int)$course['total_enrolled'];
                        $percent = min(100, (int)round(($enrolled / $cap) * 100));
                        $bar_color = $percent >= 90 ? 'bg-danger' : ($percent >= 70 ? 'bg-warning' : 'bg-primary');
                        ?>
                        <tr>
                            <td>
                                <a href="<?= e(get_app_url('courses/view.php?id=' . $course['id'])) ?>" class="fw-bold text-primary font-monospace text-decoration-none">
                                    <?= e($course['course_code']) ?>
                                </a>
                            </td>
                            <td>
                                <span class="fw-semibold text-dark"><?= e($course['course_name']) ?></span>
                                <?php if (!empty($course['description'])): ?>
                                    <div class="text-muted small text-truncate" style="max-width: 320px;">
                                        <?= e($course['description']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border font-monospace">
                                    <?= e($course['credits']) ?> Credits
                                </span>
                            </td>
                            <td>
                                <?php if ($course['lecturer_name']): ?>
                                    <div class="d-flex align-items-center gap-1.5">
                                        <i class="bi bi-person-badge text-primary"></i>
                                        <span class="small fw-medium text-dark"><?= e($course['lecturer_name']) ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                                        Unassigned
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex justify-content-between align-items-center small mb-1">
                                    <span class="fw-semibold text-dark"><?= $enrolled ?> / <?= $cap ?></span>
                                    <span class="text-muted font-monospace"><?= $percent ?>%</span>
                                </div>
                                <div class="capacity-progress-container">
                                    <div class="capacity-progress-bar <?= $bar_color ?>" style="width: <?= $percent ?>%;"></div>
                                </div>
                            </td>
                            <td class="text-end">
                                <div class="btn-group" role="group">
                                    <!-- View Course -->
                                    <a href="<?= e(get_app_url('courses/view.php?id=' . $course['id'])) ?>" 
                                       class="btn btn-sm btn-light btn-action text-primary" 
                                       title="View Course Details & Roster">
                                        <i class="bi bi-eye"></i>
                                    </a>

                                    <?php if (is_admin()): ?>
                                    <!-- Edit Course -->
                                    <a href="<?= e(get_app_url('courses/edit.php?id=' . $course['id'])) ?>" 
                                       class="btn btn-sm btn-light btn-action text-secondary" 
                                       title="Edit Course">
                                        <i class="bi bi-pencil"></i>
                                    </a>

                                    <!-- Delete Course -->
                                    <button type="button" 
                                            class="btn btn-sm btn-light btn-action text-danger delete-btn" 
                                            data-id="<?= e($course['id']) ?>" 
                                            data-name="<?= e($course['course_code'] . ' - ' . $course['course_name']) ?>" 
                                            data-action="<?= e(get_app_url('courses/delete.php')) ?>" 
                                            title="Delete Course">
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
                <span class="fw-semibold"><?= min($pagination['offset'] + $pagination['per_page'], $total_courses) ?></span> of 
                <span class="fw-semibold"><?= $total_courses ?></span> courses
            </div>
            
            <nav aria-label="Courses pagination">
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
