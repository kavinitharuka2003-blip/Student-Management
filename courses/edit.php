<?php
/**
 * ==============================================================================
 * Course Editor (courses/edit.php)
 * ------------------------------------------------------------------------------
 * Modifies course specifications, instructor assignments, credit values,
 * and capacity limits with active enrollment threshold safeguards.
 * ==============================================================================
 */

$page_title = 'Edit Course';
require_once __DIR__ . '/../includes/header.php';

// Enforce Admin access
require_admin();

$pdo = get_db();
$errors = [];

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($id <= 0) {
    set_flash('danger', 'Invalid course identifier specified.');
    header("Location: " . get_app_url('courses/list.php'));
    exit;
}

// Fetch existing course record
$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$course = $stmt->fetch();

if (!$course) {
    set_flash('danger', 'The specified course could not be found.');
    header("Location: " . get_app_url('courses/list.php'));
    exit;
}

// Count current active enrollments to prevent setting capacity below active student count
$enr_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE course_id = :id AND status = 'enrolled'");
$enr_count_stmt->execute([':id' => $id]);
$current_active_enrolled = (int)$enr_count_stmt->fetchColumn();

// Fetch available lecturers
$lecturers = $pdo->query("SELECT id, full_name, email FROM users WHERE role = 'lecturer' ORDER BY full_name ASC")->fetchAll();

$formData = $course;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();

    $formData['course_code'] = strtoupper(trim($_POST['course_code'] ?? ''));
    $formData['course_name'] = trim($_POST['course_name'] ?? '');
    $formData['description'] = trim($_POST['description'] ?? '');
    $formData['credits']     = (int)($_POST['credits'] ?? 0);
    $formData['capacity']    = (int)($_POST['capacity'] ?? 0);
    $formData['lecturer_id'] = !empty($_POST['lecturer_id']) ? (int)$_POST['lecturer_id'] : null;

    // Validation
    validate_required($formData['course_code'], 'Course Code', $errors);
    validate_required($formData['course_name'], 'Course Name', $errors);
    validate_integer_range($formData['credits'], 1, 120, 'Credits', $errors);
    validate_integer_range($formData['capacity'], 1, 500, 'Capacity', $errors);

    // Ensure new capacity is not less than currently active enrolled students
    if ($formData['capacity'] < $current_active_enrolled) {
        $errors['capacity'] = "Capacity cannot be reduced below {$current_active_enrolled} (the number of currently active enrolled students).";
    }

    // Check code uniqueness excluding current course
    if (empty($errors['Course Code'])) {
        $stmt_check = $pdo->prepare("SELECT id FROM courses WHERE course_code = :code AND id != :id LIMIT 1");
        $stmt_check->execute([':code' => $formData['course_code'], ':id' => $id]);
        if ($stmt_check->fetch()) {
            $errors['course_code'] = "Another course already uses the code '{$formData['course_code']}'.";
        }
    }

    if (empty($errors)) {
        try {
            $update_sql = "
                UPDATE courses SET
                    course_code = :course_code,
                    course_name = :course_name,
                    description = :description,
                    credits     = :credits,
                    capacity    = :capacity,
                    lecturer_id = :lecturer_id
                WHERE id = :id
            ";

            $stmt_update = $pdo->prepare($update_sql);
            $stmt_update->execute([
                ':course_code' => $formData['course_code'],
                ':course_name' => $formData['course_name'],
                ':description' => $formData['description'] ?: null,
                ':credits'     => $formData['credits'],
                ':capacity'    => $formData['capacity'],
                ':lecturer_id' => $formData['lecturer_id'],
                ':id'          => $id
            ]);

            set_flash('success', "Course '{$formData['course_code']}' updated successfully.");
            header("Location: " . get_app_url('courses/view.php?id=' . $id));
            exit;

        } catch (PDOException $e) {
            error_log("Course Update Error: " . $e->getMessage());
            set_flash('danger', 'An error occurred while saving the course updates.');
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-12 col-xl-8">
        
        <div class="d-flex align-items-center justify-content-between mb-4">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= e(get_app_url('courses/list.php')) ?>" class="text-decoration-none">Courses</a></li>
                    <li class="breadcrumb-item"><a href="<?= e(get_app_url('courses/view.php?id=' . $id)) ?>" class="text-decoration-none"><?= e($course['course_code']) ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edit</li>
                </ol>
            </nav>
            <a href="<?= e(get_app_url('courses/view.php?id=' . $id)) ?>" class="btn btn-sm btn-light">
                <i class="bi bi-arrow-left me-1"></i> Back to Course
            </a>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="card-header-title text-dark">
                    <i class="bi bi-pencil-square text-primary me-2"></i> Edit Course Offering
                </h5>
            </div>

            <form method="POST" action="<?= e($_SERVER['PHP_SELF'] . '?id=' . $id) ?>" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $id ?>">

                <div class="card-body p-4">
                    <div class="row g-3 mb-4">
                        <div class="col-md-5">
                            <label for="course_code" class="form-label">Course Code <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control font-monospace <?= isset($errors['Course Code']) || isset($errors['course_code']) ? 'is-invalid' : '' ?>" 
                                   id="course_code" 
                                   name="course_code" 
                                   value="<?= e($formData['course_code']) ?>" 
                                   required>
                            <?php if (isset($errors['Course Code']) || isset($errors['course_code'])): ?>
                                <div class="invalid-feedback d-block"><?= e($errors['Course Code'] ?? $errors['course_code']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-7">
                            <label for="course_name" class="form-label">Course Title <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control <?= isset($errors['Course Name']) ? 'is-invalid' : '' ?>" 
                                   id="course_name" 
                                   name="course_name" 
                                   value="<?= e($formData['course_name']) ?>" 
                                   required>
                            <?php if (isset($errors['Course Name'])): ?>
                                <div class="invalid-feedback d-block"><?= e($errors['Course Name']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-12">
                            <label for="lecturer_id" class="form-label">Assigned Lecturer</label>
                            <select name="lecturer_id" id="lecturer_id" class="form-select">
                                <option value="">-- Unassigned --</option>
                                <?php foreach ($lecturers as $lec): ?>
                                    <option value="<?= $lec['id'] ?>" <?= (string)$formData['lecturer_id'] === (string)$lec['id'] ? 'selected' : '' ?>>
                                        <?= e($lec['full_name']) ?> (<?= e($lec['email']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="credits" class="form-label">Credits <span class="text-danger">*</span></label>
                            <input type="number" 
                                   class="form-control <?= isset($errors['Credits']) ? 'is-invalid' : '' ?>" 
                                   id="credits" 
                                   name="credits" 
                                   min="1" 
                                   max="120" 
                                   value="<?= e($formData['credits']) ?>" 
                                   required>
                            <?php if (isset($errors['Credits'])): ?>
                                <div class="invalid-feedback d-block"><?= e($errors['Credits']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label for="capacity" class="form-label">Capacity <span class="text-danger">*</span></label>
                            <input type="number" 
                                   class="form-control <?= isset($errors['Capacity']) || isset($errors['capacity']) ? 'is-invalid' : '' ?>" 
                                   id="capacity" 
                                   name="capacity" 
                                   min="1" 
                                   max="500" 
                                   value="<?= e($formData['capacity']) ?>" 
                                   required>
                            <?php if (isset($errors['Capacity']) || isset($errors['capacity'])): ?>
                                <div class="invalid-feedback d-block"><?= e($errors['Capacity'] ?? $errors['capacity']) ?></div>
                            <?php else: ?>
                                <small class="text-muted">Currently active enrollments: <strong><?= $current_active_enrolled ?></strong></small>
                            <?php endif; ?>
                        </div>

                        <div class="col-12">
                            <label for="description" class="form-label">Course Description</label>
                            <textarea class="form-control" 
                                      id="description" 
                                      name="description" 
                                      rows="4"><?= e($formData['description']) ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-light border-top p-4 d-flex justify-content-end gap-2">
                    <a href="<?= e(get_app_url('courses/view.php?id=' . $id)) ?>" class="btn btn-light px-4">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4 fw-semibold">
                        <i class="bi bi-save me-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
