<?php
/**
 * ==============================================================================
 * Course Details & Class Roster (courses/view.php)
 * ------------------------------------------------------------------------------
 * Detailed course specification overview with instructor contact card, live
 * capacity meter, and full class roster with grade management capabilities.
 * ==============================================================================
 */

$page_title = 'Course Details';
require_once __DIR__ . '/../includes/header.php';

$pdo = get_db();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    set_flash('danger', 'Invalid course identifier specified.');
    header("Location: " . get_app_url('courses/list.php'));
    exit;
}

// 1. Fetch Course & Lecturer Details
$stmt = $pdo->prepare("
    SELECT 
        c.*,
        u.full_name AS lecturer_name,
        u.email AS lecturer_email
    FROM courses c
    LEFT JOIN users u ON c.lecturer_id = u.id
    WHERE c.id = :id
    LIMIT 1
");
$stmt->execute([':id' => $id]);
$course = $stmt->fetch();

if (!$course) {
    set_flash('danger', 'The specified course could not be found.');
    header("Location: " . get_app_url('courses/list.php'));
    exit;
}

// 2. Fetch Enrolled Students Roster
$roster_stmt = $pdo->prepare("
    SELECT 
        e.id AS enrollment_id,
        e.enrollment_date,
        e.grade,
        e.status AS enrollment_status,
        s.id AS student_id,
        s.student_number,
        s.first_name,
        s.last_name,
        s.email AS student_email,
        s.status AS student_academic_status
    FROM enrollments e
    INNER JOIN students s ON e.student_id = s.id
    WHERE e.course_id = :course_id
    ORDER BY s.last_name ASC, s.first_name ASC
");
$roster_stmt->execute([':course_id' => $id]);
$roster = $roster_stmt->fetchAll();

// 3. Compute Metrics
$total_enrolled_all = count($roster);
$active_enrolled = 0;
$grade_counts = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'F' => 0, 'Pending' => 0];

foreach ($roster as $r) {
    if ($r['enrollment_status'] === 'enrolled') {
        $active_enrolled++;
    }
    if (!empty($r['grade'])) {
        $first_char = strtoupper(substr(trim($r['grade']), 0, 1));
        if (isset($grade_counts[$first_char])) {
            $grade_counts[$first_char]++;
        }
    } else {
        $grade_counts['Pending']++;
    }
}

$cap = max(1, (int)$course['capacity']);
$percent_filled = min(100, (int)round(($active_enrolled / $cap) * 100));
$bar_color = $percent_filled >= 90 ? 'bg-danger' : ($percent_filled >= 70 ? 'bg-warning' : 'bg-primary');
?>

<!-- Breadcrumb & Top Actions -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= e(get_app_url('courses/list.php')) ?>" class="text-decoration-none">Courses</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= e($course['course_code']) ?></li>
        </ol>
    </nav>
    
    <div class="d-flex gap-2">
        <a href="<?= e(get_app_url('courses/list.php')) ?>" class="btn btn-light">
            <i class="bi bi-arrow-left me-1"></i> Back to Catalog
        </a>
        <?php if (is_admin()): ?>
            <a href="<?= e(get_app_url('courses/edit.php?id=' . $id)) ?>" class="btn btn-primary">
                <i class="bi bi-pencil me-1"></i> Edit Course
            </a>
            <button type="button" 
                    class="btn btn-outline-danger delete-btn" 
                    data-id="<?= e($course['id']) ?>" 
                    data-name="<?= e($course['course_code'] . ' - ' . $course['course_name']) ?>" 
                    data-action="<?= e(get_app_url('courses/delete.php')) ?>">
                <i class="bi bi-trash me-1"></i> Delete
            </button>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <!-- Course Specification Overview -->
    <div class="col-12 col-lg-4">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace px-3 py-1.5 fs-6">
                        <?= e($course['course_code']) ?>
                    </span>
                    <span class="badge bg-light text-dark border">
                        <?= e($course['credits']) ?> Credits
                    </span>
                </div>

                <h4 class="font-heading fw-bold text-dark mb-2"><?= e($course['course_name']) ?></h4>
                <p class="text-muted small mb-4"><?= e($course['description'] ?: 'No module description provided.') ?></p>

                <!-- Capacity Gauge -->
                <div class="p-3 bg-light rounded-3 border mb-4">
                    <div class="d-flex justify-content-between align-items-center small mb-1">
                        <span class="fw-semibold text-dark">Enrollment Capacity</span>
                        <span class="font-monospace fw-bold text-dark"><?= $active_enrolled ?> / <?= $cap ?> (<?= $percent_filled ?>%)</span>
                    </div>
                    <div class="capacity-progress-container" style="height: 10px;">
                        <div class="capacity-progress-bar <?= $bar_color ?>" style="width: <?= $percent_filled ?>%;"></div>
                    </div>
                    <div class="small text-muted mt-2">
                        <?= max(0, $cap - $active_enrolled) ?> seats currently remaining
                    </div>
                </div>

                <!-- Lecturer Contact Card -->
                <h6 class="text-uppercase fw-bold text-muted mb-3" style="font-size: 0.72rem; letter-spacing: 0.06em;">
                    Assigned Instructor
                </h6>
                <div class="d-flex align-items-center gap-3 p-2 border rounded-3 bg-white">
                    <div class="user-avatar" style="background: #e0e7ff; color: #4338ca;">
                        <i class="bi bi-person-badge"></i>
                    </div>
                    <div class="overflow-hidden">
                        <div class="fw-semibold text-dark text-truncate small"><?= e($course['lecturer_name'] ?: 'Unassigned') ?></div>
                        <div class="text-muted small text-truncate" style="font-size: 0.78rem;"><?= e($course['lecturer_email'] ?: 'No email on file') ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Grade Distribution Mini Widget -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h6 class="card-header-title text-dark">
                    <i class="bi bi-pie-chart text-primary me-2"></i> Grade Distribution
                </h6>
            </div>
            <div class="card-body p-3">
                <div class="d-flex flex-wrap gap-2 justify-content-between text-center">
                    <?php foreach (['A', 'B', 'C', 'D', 'F', 'Pending'] as $g): ?>
                        <div class="p-2 border rounded bg-light flex-grow-1" style="min-width: 45px;">
                            <div class="fw-bold font-monospace text-dark"><?= $grade_counts[$g] ?></div>
                            <div class="text-muted" style="font-size: 0.72rem;"><?= $g ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Enrolled Students Class Roster -->
    <div class="col-12 col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="card-header-title text-dark mb-0">
                    <i class="bi bi-people text-primary me-2"></i> Class Roster 
                    <span class="badge bg-secondary-subtle text-secondary ms-2"><?= $total_enrolled_all ?> Enrolled</span>
                </h5>
                <?php if (is_admin() && $active_enrolled < $cap): ?>
                    <a href="<?= e(get_app_url('enrollments/add.php?course_id=' . $id)) ?>" class="btn btn-sm btn-primary">
                        <i class="bi bi-person-plus-fill me-1"></i> Enroll Student
                    </a>
                <?php endif; ?>
            </div>

            <div class="table-responsive">
                <table class="table custom-table">
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Student Name</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Grade</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($roster)): ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state py-4">
                                        <div class="empty-state-icon" style="width: 48px; height: 48px; font-size: 1.5rem;">
                                            <i class="bi bi-people"></i>
                                        </div>
                                        <h6 class="empty-state-title">No Students Enrolled</h6>
                                        <p class="empty-state-desc small mb-3">No students are currently registered in this course module.</p>
                                        <?php if (is_admin()): ?>
                                            <a href="<?= e(get_app_url('enrollments/add.php?course_id=' . $id)) ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-person-plus-fill me-1"></i> Enroll Student
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($roster as $r): ?>
                                <tr>
                                    <td>
                                        <a href="<?= e(get_app_url('students/view.php?id=' . $r['student_id'])) ?>" class="font-monospace fw-bold text-primary text-decoration-none">
                                            <?= e($r['student_number']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-dark"><?= e($r['first_name'] . ' ' . $r['last_name']) ?></span>
                                    </td>
                                    <td>
                                        <span class="text-muted small"><?= e($r['student_email']) ?></span>
                                    </td>
                                    <td>
                                        <?= render_status_badge($r['enrollment_status']) ?>
                                    </td>
                                    <td>
                                        <?= render_grade_badge($r['grade']) ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group" role="group">
                                            <!-- Edit Grade/Status -->
                                            <a href="<?= e(get_app_url('enrollments/edit.php?id=' . $r['enrollment_id'])) ?>" 
                                               class="btn btn-sm btn-light btn-action text-secondary" 
                                               title="Update Grade / Status">
                                                <i class="bi bi-pencil"></i>
                                            </a>

                                            <?php if (is_admin()): ?>
                                            <!-- Drop Enrollment -->
                                            <button type="button" 
                                                    class="btn btn-sm btn-light btn-action text-danger delete-btn" 
                                                    data-id="<?= e($r['enrollment_id']) ?>" 
                                                    data-name="<?= e($r['first_name'] . ' ' . $r['last_name'] . ' from ' . $course['course_code']) ?>" 
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
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
