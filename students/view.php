<?php
/**
 * ==============================================================================
 * Student Profile & Academic Transcript (students/view.php)
 * ------------------------------------------------------------------------------
 * Presents a comprehensive student profile overview including personal data,
 * complete course enrollment history, academic progress, and grade records.
 * ==============================================================================
 */

$page_title = 'Student Profile';
require_once __DIR__ . '/../includes/header.php';

$pdo = get_db();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    set_flash('danger', 'Invalid student identifier provided.');
    header("Location: " . get_app_url('students/list.php'));
    exit;
}

// 1. Fetch Student Details
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$student = $stmt->fetch();

if (!$student) {
    set_flash('danger', 'The specified student record does not exist.');
    header("Location: " . get_app_url('students/list.php'));
    exit;
}

// 2. Fetch All Course Enrollments with Course & Lecturer Information
$enrollments_sql = "
    SELECT 
        e.id AS enrollment_id,
        e.enrollment_date,
        e.grade,
        e.status AS enrollment_status,
        c.id AS course_id,
        c.course_code,
        c.course_name,
        c.credits,
        u.full_name AS lecturer_name
    FROM enrollments e
    INNER JOIN courses c ON e.course_id = c.id
    LEFT JOIN users u ON c.lecturer_id = u.id
    WHERE e.student_id = :student_id
    ORDER BY e.created_at DESC
";
$enroll_stmt = $pdo->prepare($enrollments_sql);
$enroll_stmt->execute([':student_id' => $id]);
$enrollments = $enroll_stmt->fetchAll();

// 3. Compute Summary Statistics
$total_enrolled = count($enrollments);
$total_credits = 0;
$completed_courses = 0;

foreach ($enrollments as $enr) {
    if ($enr['enrollment_status'] === 'completed') {
        $completed_courses++;
        $total_credits += (int)$enr['credits'];
    }
}
?>

<!-- Breadcrumb & Actions -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= e(get_app_url('students/list.php')) ?>" class="text-decoration-none">Students</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= e($student['first_name'] . ' ' . $student['last_name']) ?></li>
        </ol>
    </nav>
    
    <div class="d-flex gap-2">
        <a href="<?= e(get_app_url('students/list.php')) ?>" class="btn btn-light">
            <i class="bi bi-arrow-left me-1"></i> Back to Directory
        </a>
        <?php if (is_admin()): ?>
            <a href="<?= e(get_app_url('students/edit.php?id=' . $id)) ?>" class="btn btn-primary">
                <i class="bi bi-pencil me-1"></i> Edit Profile
            </a>
            <button type="button" 
                    class="btn btn-outline-danger delete-btn" 
                    data-id="<?= e($student['id']) ?>" 
                    data-name="<?= e($student['first_name'] . ' ' . $student['last_name'] . ' (' . $student['student_number'] . ')') ?>" 
                    data-action="<?= e(get_app_url('students/delete.php')) ?>">
                <i class="bi bi-trash me-1"></i> Delete
            </button>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <!-- Student Overview Card -->
    <div class="col-12 col-lg-4">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4 text-center">
                <div class="user-avatar mx-auto mb-3" style="width: 80px; height: 80px; font-size: 2rem; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: #fff; box-shadow: 0 8px 20px rgba(79, 70, 229, 0.25);">
                    <?= strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)) ?>
                </div>
                <h4 class="font-heading fw-bold text-dark mb-1">
                    <?= e($student['first_name'] . ' ' . $student['last_name']) ?>
                </h4>
                <div class="font-monospace text-primary fw-semibold mb-2">
                    <?= e($student['student_number']) ?>
                </div>
                <div class="mb-3">
                    <?= render_status_badge($student['status']) ?>
                </div>
            </div>

            <div class="border-top px-4 py-3 bg-light">
                <h6 class="text-uppercase fw-bold text-muted mb-3" style="font-size: 0.72rem; letter-spacing: 0.06em;">
                    Contact & Biographical Info
                </h6>
                <ul class="list-unstyled mb-0 d-flex flex-column gap-2.5 small">
                    <li class="d-flex align-items-center gap-2">
                        <i class="bi bi-envelope text-primary"></i>
                        <span class="text-dark"><?= e($student['email']) ?></span>
                    </li>
                    <li class="d-flex align-items-center gap-2">
                        <i class="bi bi-telephone text-secondary"></i>
                        <span class="text-muted"><?= e($student['phone'] ?: 'Not Provided') ?></span>
                    </li>
                    <li class="d-flex align-items-center gap-2">
                        <i class="bi bi-cake2 text-secondary"></i>
                        <span class="text-muted">DOB: <?= $student['date_of_birth'] ? date('M d, Y', strtotime($student['date_of_birth'])) : 'Not Provided' ?></span>
                    </li>
                    <li class="d-flex align-items-center gap-2">
                        <i class="bi bi-geo-alt text-secondary"></i>
                        <span class="text-muted"><?= e($student['address'] ?: 'No address registered') ?></span>
                    </li>
                    <li class="d-flex align-items-center gap-2">
                        <i class="bi bi-calendar-check text-secondary"></i>
                        <span class="text-muted">Enrolled: <?= date('M d, Y', strtotime($student['enrollment_date'])) ?></span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Academic Summary Metrics -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h6 class="card-header-title text-dark">
                    <i class="bi bi-bar-chart-line text-primary me-2"></i> Academic Summary
                </h6>
            </div>
            <div class="card-body p-3">
                <div class="row g-2 text-center">
                    <div class="col-6">
                        <div class="p-3 bg-light rounded-3 border">
                            <div class="fs-4 fw-bold text-dark font-heading"><?= $total_enrolled ?></div>
                            <div class="text-muted small">Total Modules</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 bg-light rounded-3 border">
                            <div class="fs-4 fw-bold text-success font-heading"><?= $total_credits ?></div>
                            <div class="text-muted small">Credits Earned</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Course History & Enrollments -->
    <div class="col-12 col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="card-header-title text-dark mb-0">
                    <i class="bi bi-journal-check text-primary me-2"></i> Course Enrollments
                </h5>
                <?php if (is_admin()): ?>
                    <a href="<?= e(get_app_url('enrollments/add.php?student_id=' . $id)) ?>" class="btn btn-sm btn-primary">
                        <i class="bi bi-plus-circle me-1"></i> Enroll in Course
                    </a>
                <?php endif; ?>
            </div>

            <div class="table-responsive">
                <table class="table custom-table">
                    <thead>
                        <tr>
                            <th>Module Code</th>
                            <th>Module Name</th>
                            <th>Lecturer</th>
                            <th>Credits</th>
                            <th>Grade</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($enrollments)): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state py-4">
                                        <div class="empty-state-icon" style="width: 48px; height: 48px; font-size: 1.5rem;">
                                            <i class="bi bi-journal-x"></i>
                                        </div>
                                        <h6 class="empty-state-title">No Enrolled Courses</h6>
                                        <p class="empty-state-desc small mb-3">This student is not currently enrolled in any academic modules.</p>
                                        <?php if (is_admin()): ?>
                                            <a href="<?= e(get_app_url('enrollments/add.php?student_id=' . $id)) ?>" class="btn btn-sm btn-primary">
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
                                        <a href="<?= e(get_app_url('courses/view.php?id=' . $enr['course_id'])) ?>" class="font-monospace text-primary fw-bold text-decoration-none">
                                            <?= e($enr['course_code']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-dark"><?= e($enr['course_name']) ?></span>
                                    </td>
                                    <td>
                                        <span class="text-muted small"><?= e($enr['lecturer_name'] ?: 'TBA') ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?= e($enr['credits']) ?> cr</span>
                                    </td>
                                    <td>
                                        <?= render_grade_badge($enr['grade']) ?>
                                    </td>
                                    <td>
                                        <?= render_status_badge($enr['enrollment_status']) ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group" role="group">
                                            <!-- Edit Grade/Status -->
                                            <a href="<?= e(get_app_url('enrollments/edit.php?id=' . $enr['enrollment_id'])) ?>" 
                                               class="btn btn-sm btn-light btn-action text-secondary" 
                                               title="Update Grade or Status">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            
                                            <?php if (is_admin()): ?>
                                            <!-- Delete Enrollment -->
                                            <button type="button" 
                                                    class="btn btn-sm btn-light btn-action text-danger delete-btn" 
                                                    data-id="<?= e($enr['enrollment_id']) ?>" 
                                                    data-name="<?= e($enr['course_code'] . ' for ' . $student['first_name']) ?>" 
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
