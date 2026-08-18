<?php
/**
 * ==============================================================================
 * Grade & Enrollment Status Modifier (enrollments/edit.php)
 * ------------------------------------------------------------------------------
 * Allows academic staff to award module grades (A+, A, B, C, D, F) and update
 * progression statuses (enrolled, completed, dropped).
 * ==============================================================================
 */

$page_title = 'Update Grade & Status';
require_once __DIR__ . '/../includes/header.php';

$pdo = get_db();
$errors = [];

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($id <= 0) {
    set_flash('danger', 'Invalid enrollment record ID.');
    header("Location: " . get_app_url('enrollments/list.php'));
    exit;
}

// Fetch enrollment with Student & Course associations
$stmt = $pdo->prepare("
    SELECT 
        e.*,
        s.student_number,
        s.first_name,
        s.last_name,
        s.email AS student_email,
        c.course_code,
        c.course_name,
        c.credits,
        c.lecturer_id,
        u.full_name AS lecturer_name
    FROM enrollments e
    INNER JOIN students s ON e.student_id = s.id
    INNER JOIN courses c ON e.course_id = c.id
    LEFT JOIN users u ON c.lecturer_id = u.id
    WHERE e.id = :id
    LIMIT 1
");
$stmt->execute([':id' => $id]);
$enrollment = $stmt->fetch();

if (!$enrollment) {
    set_flash('danger', 'The specified enrollment record was not found.');
    header("Location: " . get_app_url('enrollments/list.php'));
    exit;
}

// RBAC Check: Lecturers can only edit enrollments for their assigned courses
if (is_lecturer()) {
    $current_user_id = (int)($_SESSION['user_id'] ?? 0);
    if ((int)$enrollment['lecturer_id'] !== $current_user_id) {
        set_flash('danger', 'Access Denied: You are not the assigned instructor for this course offering.');
        header("Location: " . get_app_url('enrollments/list.php'));
        exit;
    }
}

$formData = [
    'grade'           => $enrollment['grade'] ?? '',
    'status'          => $enrollment['status'],
    'enrollment_date' => $enrollment['enrollment_date'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();

    $formData['grade']           = strtoupper(trim($_POST['grade'] ?? ''));
    $formData['status']          = trim($_POST['status'] ?? 'enrolled');
    $formData['enrollment_date'] = trim($_POST['enrollment_date'] ?? $enrollment['enrollment_date']);

    // Validation
    if (!in_array($formData['status'], ['enrolled', 'completed', 'dropped'])) {
        $errors['status'] = 'Invalid status selected.';
    }

    if (!empty($formData['grade']) && !in_array($formData['grade'], ['A+', 'A', 'B+', 'B', 'C+', 'C', 'D', 'F'])) {
        $errors['grade'] = 'Please select a valid academic grade.';
    }

    validate_date_format($formData['enrollment_date'], 'Enrollment Date', $errors);

    if (empty($errors)) {
        try {
            $update_sql = "
                UPDATE enrollments SET
                    grade = :grade,
                    status = :status,
                    enrollment_date = :enrollment_date
                WHERE id = :id
            ";

            $stmt_upd = $pdo->prepare($update_sql);
            $stmt_upd->execute([
                ':grade'           => !empty($formData['grade']) ? $formData['grade'] : null,
                ':status'          => $formData['status'],
                ':enrollment_date' => $formData['enrollment_date'],
                ':id'              => $id
            ]);

            set_flash('success', "Enrollment record for '{$enrollment['first_name']} {$enrollment['last_name']}' in '{$enrollment['course_code']}' updated successfully.");
            header("Location: " . get_app_url('enrollments/list.php'));
            exit;

        } catch (PDOException $e) {
            error_log("Enrollment Update Error: " . $e->getMessage());
            set_flash('danger', 'A database error occurred while updating the enrollment record.');
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-12 col-xl-8">
        
        <div class="d-flex align-items-center justify-content-between mb-4">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= e(get_app_url('enrollments/list.php')) ?>" class="text-decoration-none">Enrollments</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Update Record</li>
                </ol>
            </nav>
            <a href="<?= e(get_app_url('enrollments/list.php')) ?>" class="btn btn-sm btn-light">
                <i class="bi bi-arrow-left me-1"></i> Back to Ledger
            </a>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="card-header-title text-dark">
                    <i class="bi bi-award text-primary me-2"></i> Update Grade & Academic Standing
                </h5>
            </div>

            <!-- Read-Only Context Summary -->
            <div class="p-4 bg-light border-bottom">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-muted small">Student Name & ID</div>
                        <div class="fw-bold text-dark fs-6">
                            <?= e($enrollment['first_name'] . ' ' . $enrollment['last_name']) ?>
                            <span class="badge bg-white text-primary border font-monospace ms-1"><?= e($enrollment['student_number']) ?></span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Course Module</div>
                        <div class="fw-bold text-dark fs-6">
                            <span class="font-monospace text-primary"><?= e($enrollment['course_code']) ?></span> - <?= e($enrollment['course_name']) ?>
                        </div>
                    </div>
                </div>
            </div>

            <form method="POST" action="<?= e($_SERVER['PHP_SELF'] . '?id=' . $id) ?>" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $id ?>">

                <div class="card-body p-4">
                    <div class="row g-3 mb-4">
                        <!-- Grade Award Selector -->
                        <div class="col-md-6">
                            <label for="grade" class="form-label">Award Grade</label>
                            <select name="grade" id="grade" class="form-select <?= isset($errors['grade']) ? 'is-invalid' : '' ?>">
                                <option value="" <?= empty($formData['grade']) ? 'selected' : '' ?>>-- Pending / In Progress --</option>
                                <option value="A+" <?= $formData['grade'] === 'A+' ? 'selected' : '' ?>>A+ (Distinction - 85%+)</option>
                                <option value="A"  <?= $formData['grade'] === 'A'  ? 'selected' : '' ?>>A (Excellent - 70-84%)</option>
                                <option value="B+" <?= $formData['grade'] === 'B+' ? 'selected' : '' ?>>B+ (Very Good - 65-69%)</option>
                                <option value="B"  <?= $formData['grade'] === 'B'  ? 'selected' : '' ?>>B (Good - 60-64%)</option>
                                <option value="C+" <?= $formData['grade'] === 'C+' ? 'selected' : '' ?>>C+ (Satisfactory - 55-59%)</option>
                                <option value="C"  <?= $formData['grade'] === 'C'  ? 'selected' : '' ?>>C (Pass - 50-54%)</option>
                                <option value="D"  <?= $formData['grade'] === 'D'  ? 'selected' : '' ?>>D (Marginal Pass - 40-49%)</option>
                                <option value="F"  <?= $formData['grade'] === 'F'  ? 'selected' : '' ?>>F (Fail - Below 40%)</option>
                            </select>
                            <?php if (isset($errors['grade'])): ?>
                                <div class="invalid-feedback d-block"><?= e($errors['grade']) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Status Selector -->
                        <div class="col-md-6">
                            <label for="status" class="form-label">Module Status <span class="text-danger">*</span></label>
                            <select name="status" id="status" class="form-select <?= isset($errors['status']) ? 'is-invalid' : '' ?>" required>
                                <option value="enrolled" <?= $formData['status'] === 'enrolled' ? 'selected' : '' ?>>Enrolled (Active Coursework)</option>
                                <option value="completed" <?= $formData['status'] === 'completed' ? 'selected' : '' ?>>Completed (Module Finished)</option>
                                <option value="dropped" <?= $formData['status'] === 'dropped' ? 'selected' : '' ?>>Dropped (Withdrawn)</option>
                            </select>
                            <?php if (isset($errors['status'])): ?>
                                <div class="invalid-feedback d-block"><?= e($errors['status']) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Enrollment Date -->
                        <div class="col-md-6">
                            <label for="enrollment_date" class="form-label">Enrollment Date</label>
                            <input type="date" 
                                   class="form-control <?= isset($errors['Enrollment Date']) ? 'is-invalid' : '' ?>" 
                                   id="enrollment_date" 
                                   name="enrollment_date" 
                                   value="<?= e($formData['enrollment_date']) ?>" 
                                   <?= !is_admin() ? 'readonly' : '' ?> 
                                   required>
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-light border-top p-4 d-flex justify-content-end gap-2">
                    <a href="<?= e(get_app_url('enrollments/list.php')) ?>" class="btn btn-light px-4">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4 fw-semibold">
                        <i class="bi bi-check-lg me-1"></i> Save Grade & Status
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
