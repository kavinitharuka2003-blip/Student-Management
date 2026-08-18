<?php
/**
 * ==============================================================================
 * Course Enrollment Creation (enrollments/add.php)
 * ------------------------------------------------------------------------------
 * Registers a student into a chosen course module. Enforces rigorous business
 * logic including duplicate enrollment rejection and course capacity verification.
 * ==============================================================================
 */

$page_title = 'Enroll Student in Course';
require_once __DIR__ . '/../includes/header.php';

// Enforce Admin access
require_admin();

$pdo = get_db();
$errors = [];

// Pre-fill parameters if arriving from Student Profile or Course Details view
$prefill_student_id = (int)($_GET['student_id'] ?? 0);
$prefill_course_id = (int)($_GET['course_id'] ?? 0);

// Fetch all students for dropdown
$students = $pdo->query("SELECT id, student_number, first_name, last_name, email, status FROM students ORDER BY last_name ASC, first_name ASC")->fetchAll();

// Fetch all courses with current enrollment count and capacity
$courses = $pdo->query("
    SELECT 
        c.id, 
        c.course_code, 
        c.course_name, 
        c.capacity,
        COUNT(e.id) AS active_enrolled
    FROM courses c
    LEFT JOIN enrollments e ON c.id = e.course_id AND e.status = 'enrolled'
    GROUP BY c.id
    ORDER BY c.course_code ASC
")->fetchAll();

$formData = [
    'student_id'      => $prefill_student_id,
    'course_id'       => $prefill_course_id,
    'enrollment_date' => date('Y-m-d'),
    'grade'           => '',
    'status'          => 'enrolled',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();

    $formData['student_id']      = (int)($_POST['student_id'] ?? 0);
    $formData['course_id']       = (int)($_POST['course_id'] ?? 0);
    $formData['enrollment_date'] = trim($_POST['enrollment_date'] ?? date('Y-m-d'));
    $formData['grade']           = strtoupper(trim($_POST['grade'] ?? ''));
    $formData['status']          = trim($_POST['status'] ?? 'enrolled');

    // 1. Basic Validation
    if ($formData['student_id'] <= 0) {
        $errors['student_id'] = 'Please select a valid student.';
    }

    if ($formData['course_id'] <= 0) {
        $errors['course_id'] = 'Please select a course to enroll in.';
    }

    validate_date_format($formData['enrollment_date'], 'Enrollment Date', $errors);

    if (!in_array($formData['status'], ['enrolled', 'completed', 'dropped'])) {
        $errors['status'] = 'Invalid enrollment status selected.';
    }

    if (!empty($formData['grade']) && !in_array($formData['grade'], ['A+', 'A', 'B+', 'B', 'C+', 'C', 'D', 'F'])) {
        $errors['grade'] = 'Please select a standard grade (A+, A, B, C, D, F) or leave blank if pending.';
    }

    // 2. Duplicate Enrollment Prevention (Business Logic Guard)
    if (empty($errors)) {
        $stmt_check = $pdo->prepare("SELECT id, status FROM enrollments WHERE student_id = :sid AND course_id = :cid LIMIT 1");
        $stmt_check->execute([
            ':sid' => $formData['student_id'],
            ':cid' => $formData['course_id']
        ]);
        $existing = $stmt_check->fetch();

        if ($existing) {
            $errors['duplicate'] = "This student is already enrolled in the selected course (Status: " . ucfirst($existing['status']) . "). Duplicate enrollments are not permitted.";
        }
    }

    // 3. Course Capacity Verification
    if (empty($errors)) {
        $stmt_cap = $pdo->prepare("
            SELECT 
                c.capacity, 
                c.course_code,
                c.course_name,
                COUNT(e.id) AS current_enrolled
            FROM courses c
            LEFT JOIN enrollments e ON c.id = e.course_id AND e.status = 'enrolled'
            WHERE c.id = :cid
            GROUP BY c.id
            LIMIT 1
        ");
        $stmt_cap->execute([':cid' => $formData['course_id']]);
        $course_info = $stmt_cap->fetch();

        if ($course_info) {
            if ((int)$course_info['current_enrolled'] >= (int)$course_info['capacity']) {
                $errors['capacity'] = "The course '{$course_info['course_code']}' has reached its maximum enrollment capacity of {$course_info['capacity']} students.";
            }
        } else {
            $errors['course_id'] = 'Selected course does not exist in curriculum.';
        }
    }

    // 4. Execute Insertion
    if (empty($errors)) {
        try {
            $insert_sql = "
                INSERT INTO enrollments (student_id, course_id, enrollment_date, grade, status)
                VALUES (:student_id, :course_id, :enrollment_date, :grade, :status)
            ";

            $stmt_insert = $pdo->prepare($insert_sql);
            $stmt_insert->execute([
                ':student_id'      => $formData['student_id'],
                ':course_id'       => $formData['course_id'],
                ':enrollment_date' => $formData['enrollment_date'],
                ':grade'           => !empty($formData['grade']) ? $formData['grade'] : null,
                ':status'          => $formData['status'],
            ]);

            set_flash('success', 'Student successfully enrolled in course module.');
            header("Location: " . get_app_url('enrollments/list.php'));
            exit;

        } catch (PDOException $e) {
            // Handle race condition or unique constraint defensive fallback
            if ($e->getCode() == 23000) {
                $errors['duplicate'] = "Duplicate enrollment detected by database integrity constraint.";
            } else {
                error_log("Enrollment Insert Error: " . $e->getMessage());
                set_flash('danger', 'A database error occurred while registering the enrollment.');
            }
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-12 col-xl-8">
        
        <!-- Breadcrumb Navigation -->
        <div class="d-flex align-items-center justify-content-between mb-4">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= e(get_app_url('enrollments/list.php')) ?>" class="text-decoration-none">Enrollments</a></li>
                    <li class="breadcrumb-item active" aria-current="page">New Enrollment</li>
                </ol>
            </nav>
            <a href="<?= e(get_app_url('enrollments/list.php')) ?>" class="btn btn-sm btn-light">
                <i class="bi bi-arrow-left me-1"></i> Back to Ledger
            </a>
        </div>

        <!-- System Alerts for Business Logic Errors -->
        <?php if (isset($errors['duplicate'])): ?>
            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
                <i class="bi bi-exclamation-octagon-fill me-2 fs-5"></i>
                <div><strong>Enrollment Blocked:</strong> <?= e($errors['duplicate']) ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($errors['capacity'])): ?>
            <div class="alert alert-warning alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
                <i class="bi bi-person-x-fill me-2 fs-5"></i>
                <div><strong>Capacity Limit Exceeded:</strong> <?= e($errors['capacity']) ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="card-header-title text-dark">
                    <i class="bi bi-person-plus text-primary me-2"></i> Enroll Student into Course
                </h5>
            </div>

            <form method="POST" action="<?= e($_SERVER['PHP_SELF']) ?>" novalidate>
                <?= csrf_field() ?>

                <div class="card-body p-4">
                    <div class="row g-3 mb-4">
                        <!-- Student Selector -->
                        <div class="col-md-12">
                            <label for="student_id" class="form-label">Select Student <span class="text-danger">*</span></label>
                            <select name="student_id" id="student_id" class="form-select <?= isset($errors['student_id']) ? 'is-invalid' : '' ?>" required>
                                <option value="">-- Choose a Student --</option>
                                <?php foreach ($students as $s): ?>
                                    <option value="<?= $s['id'] ?>" <?= (int)$formData['student_id'] === (int)$s['id'] ? 'selected' : '' ?>>
                                        <?= e($s['last_name']) ?>, <?= e($s['first_name']) ?> (ID: <?= e($s['student_number']) ?> &bull; <?= ucfirst($s['status']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['student_id'])): ?>
                                <div class="invalid-feedback d-block"><?= e($errors['student_id']) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Course Selector -->
                        <div class="col-md-12">
                            <label for="course_id" class="form-label">Select Course Offering <span class="text-danger">*</span></label>
                            <select name="course_id" id="course_id" class="form-select <?= isset($errors['course_id']) ? 'is-invalid' : '' ?>" required>
                                <option value="">-- Choose a Course Module --</option>
                                <?php foreach ($courses as $c): ?>
                                    <?php 
                                    $is_full = (int)$c['active_enrolled'] >= (int)$c['capacity'];
                                    $label = e($c['course_code']) . ' - ' . e($c['course_name']) . ' (' . (int)$c['active_enrolled'] . '/' . (int)$c['capacity'] . ' enrolled' . ($is_full ? ' - FULL' : '') . ')';
                                    ?>
                                    <option value="<?= $c['id'] ?>" 
                                            <?= (int)$formData['course_id'] === (int)$c['id'] ? 'selected' : '' ?>
                                            <?= $is_full ? 'class="text-danger"' : '' ?>>
                                        <?= $label ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['course_id'])): ?>
                                <div class="invalid-feedback d-block"><?= e($errors['course_id']) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Enrollment Date -->
                        <div class="col-md-4">
                            <label for="enrollment_date" class="form-label">Enrollment Date <span class="text-danger">*</span></label>
                            <input type="date" 
                                   class="form-control <?= isset($errors['Enrollment Date']) ? 'is-invalid' : '' ?>" 
                                   id="enrollment_date" 
                                   name="enrollment_date" 
                                   value="<?= e($formData['enrollment_date']) ?>" 
                                   required>
                            <?php if (isset($errors['Enrollment Date'])): ?>
                                <div class="invalid-feedback d-block"><?= e($errors['Enrollment Date']) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Status -->
                        <div class="col-md-4">
                            <label for="status" class="form-label">Enrollment Status <span class="text-danger">*</span></label>
                            <select name="status" id="status" class="form-select <?= isset($errors['status']) ? 'is-invalid' : '' ?>" required>
                                <option value="enrolled" <?= $formData['status'] === 'enrolled' ? 'selected' : '' ?>>Enrolled (Active)</option>
                                <option value="completed" <?= $formData['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="dropped" <?= $formData['status'] === 'dropped' ? 'selected' : '' ?>>Dropped</option>
                            </select>
                        </div>

                        <!-- Optional Initial Grade -->
                        <div class="col-md-4">
                            <label for="grade" class="form-label">Academic Grade (Optional)</label>
                            <select name="grade" id="grade" class="form-select <?= isset($errors['grade']) ? 'is-invalid' : '' ?>">
                                <option value="">-- Pending / Ungraded --</option>
                                <option value="A+" <?= $formData['grade'] === 'A+' ? 'selected' : '' ?>>A+ (Distinction)</option>
                                <option value="A"  <?= $formData['grade'] === 'A' ? 'selected' : '' ?>>A (Excellent)</option>
                                <option value="B+" <?= $formData['grade'] === 'B+' ? 'selected' : '' ?>>B+ (Very Good)</option>
                                <option value="B"  <?= $formData['grade'] === 'B' ? 'selected' : '' ?>>B (Good)</option>
                                <option value="C+" <?= $formData['grade'] === 'C+' ? 'selected' : '' ?>>C+ (Satisfactory)</option>
                                <option value="C"  <?= $formData['grade'] === 'C' ? 'selected' : '' ?>>C (Pass)</option>
                                <option value="D"  <?= $formData['grade'] === 'D' ? 'selected' : '' ?>>D (Marginal Pass)</option>
                                <option value="F"  <?= $formData['grade'] === 'F' ? 'selected' : '' ?>>F (Fail)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-light border-top p-4 d-flex justify-content-end gap-2">
                    <a href="<?= e(get_app_url('enrollments/list.php')) ?>" class="btn btn-light px-4">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4 fw-semibold">
                        <i class="bi bi-check-lg me-1"></i> Complete Enrollment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
