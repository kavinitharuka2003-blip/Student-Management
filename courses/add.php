<?php
/**
 * ==============================================================================
 * Course Creation Form (courses/add.php)
 * ------------------------------------------------------------------------------
 * Administrative interface to register new course offerings, assign faculty
 * instructors, and configure enrollment capacity limits.
 * ==============================================================================
 */

$page_title = 'Create New Course';
require_once __DIR__ . '/../includes/header.php';

// Enforce Admin access
require_admin();

$pdo = get_db();
$errors = [];

// Fetch available lecturers for dropdown assignment
$lecturers = $pdo->query("SELECT id, full_name, email FROM users WHERE role = 'lecturer' ORDER BY full_name ASC")->fetchAll();

$formData = [
    'course_code' => '',
    'course_name' => '',
    'description' => '',
    'credits'     => 15,
    'capacity'    => 30,
    'lecturer_id' => '',
];

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

    // Uniqueness of Course Code
    if (empty($errors['Course Code'])) {
        $stmt_check = $pdo->prepare("SELECT id FROM courses WHERE course_code = :code LIMIT 1");
        $stmt_check->execute([':code' => $formData['course_code']]);
        if ($stmt_check->fetch()) {
            $errors['course_code'] = "A course with code '{$formData['course_code']}' already exists.";
        }
    }

    // Verify lecturer validity if provided
    if ($formData['lecturer_id'] !== null) {
        $stmt_lec = $pdo->prepare("SELECT id FROM users WHERE id = :id AND role = 'lecturer' LIMIT 1");
        $stmt_lec->execute([':id' => $formData['lecturer_id']]);
        if (!$stmt_lec->fetch()) {
            $errors['lecturer_id'] = 'The selected lecturer is invalid or inactive.';
        }
    }

    if (empty($errors)) {
        try {
            $insert_sql = "
                INSERT INTO courses (course_code, course_name, description, credits, capacity, lecturer_id)
                VALUES (:course_code, :course_name, :description, :credits, :capacity, :lecturer_id)
            ";

            $stmt_insert = $pdo->prepare($insert_sql);
            $stmt_insert->execute([
                ':course_code' => $formData['course_code'],
                ':course_name' => $formData['course_name'],
                ':description' => $formData['description'] ?: null,
                ':credits'     => $formData['credits'],
                ':capacity'    => $formData['capacity'],
                ':lecturer_id' => $formData['lecturer_id'],
            ]);

            $new_course_id = $pdo->lastInsertId();

            set_flash('success', "Course '{$formData['course_code']} - {$formData['course_name']}' created successfully.");
            header("Location: " . get_app_url('courses/view.php?id=' . $new_course_id));
            exit;

        } catch (PDOException $e) {
            error_log("Course Insert Error: " . $e->getMessage());
            set_flash('danger', 'A database error occurred while creating the course.');
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-12 col-xl-8">
        
        <!-- Navigation Header -->
        <div class="d-flex align-items-center justify-content-between mb-4">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= e(get_app_url('courses/list.php')) ?>" class="text-decoration-none">Courses</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Create Course</li>
                </ol>
            </nav>
            <a href="<?= e(get_app_url('courses/list.php')) ?>" class="btn btn-sm btn-light">
                <i class="bi bi-arrow-left me-1"></i> Back to Catalog
            </a>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="card-header-title text-dark">
                    <i class="bi bi-journal-plus text-primary me-2"></i> Add Course Offering
                </h5>
            </div>

            <form method="POST" action="<?= e($_SERVER['PHP_SELF']) ?>" novalidate>
                <?= csrf_field() ?>

                <div class="card-body p-4">
                    <div class="row g-3 mb-4">
                        <!-- Course Code -->
                        <div class="col-md-5">
                            <label for="course_code" class="form-label">Course Code <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control font-monospace <?= isset($errors['Course Code']) || isset($errors['course_code']) ? 'is-invalid' : '' ?>" 
                                   id="course_code" 
                                   name="course_code" 
                                   placeholder="e.g. COMP5007" 
                                   value="<?= e($formData['course_code']) ?>" 
                                   required>
                            <?php if (isset($errors['Course Code']) || isset($errors['course_code'])): ?>
                                <div class="invalid-feedback d-block"><?= e($errors['Course Code'] ?? $errors['course_code']) ?></div>
                            <?php else: ?>
                                <small class="text-muted">Unique module code identifier.</small>
                            <?php endif; ?>
                        </div>

                        <!-- Course Name -->
                        <div class="col-md-7">
                            <label for="course_name" class="form-label">Course / Module Title <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control <?= isset($errors['Course Name']) ? 'is-invalid' : '' ?>" 
                                   id="course_name" 
                                   name="course_name" 
                                   placeholder="e.g. Advanced Distributed Systems" 
                                   value="<?= e($formData['course_name']) ?>" 
                                   required>
                            <?php if (isset($errors['Course Name'])): ?>
                                <div class="invalid-feedback d-block"><?= e($errors['Course Name']) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Assigned Lecturer -->
                        <div class="col-md-12">
                            <label for="lecturer_id" class="form-label">Assigned Lecturer / Faculty Lead</label>
                            <select name="lecturer_id" id="lecturer_id" class="form-select <?= isset($errors['lecturer_id']) ? 'is-invalid' : '' ?>">
                                <option value="">-- Unassigned (Assign Later) --</option>
                                <?php foreach ($lecturers as $lec): ?>
                                    <option value="<?= $lec['id'] ?>" <?= (string)$formData['lecturer_id'] === (string)$lec['id'] ? 'selected' : '' ?>>
                                        <?= e($lec['full_name']) ?> (<?= e($lec['email']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['lecturer_id'])): ?>
                                <div class="invalid-feedback d-block"><?= e($errors['lecturer_id']) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Academic Credits -->
                        <div class="col-md-6">
                            <label for="credits" class="form-label">Academic Credits <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" 
                                       class="form-control <?= isset($errors['Credits']) ? 'is-invalid' : '' ?>" 
                                       id="credits" 
                                       name="credits" 
                                       min="1" 
                                       max="120" 
                                       value="<?= e($formData['credits']) ?>" 
                                       required>
                                <span class="input-group-text">Credits</span>
                            </div>
                            <?php if (isset($errors['Credits'])): ?>
                                <div class="invalid-feedback d-block"><?= e($errors['Credits']) ?></div>
                            <?php else: ?>
                                <small class="text-muted">Standard module weighting (e.g. 15 or 20 credits).</small>
                            <?php endif; ?>
                        </div>

                        <!-- Max Capacity -->
                        <div class="col-md-6">
                            <label for="capacity" class="form-label">Max Student Capacity <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" 
                                       class="form-control <?= isset($errors['Capacity']) ? 'is-invalid' : '' ?>" 
                                       id="capacity" 
                                       name="capacity" 
                                       min="1" 
                                       max="500" 
                                       value="<?= e($formData['capacity']) ?>" 
                                       required>
                                <span class="input-group-text"><i class="bi bi-people"></i></span>
                            </div>
                            <?php if (isset($errors['Capacity'])): ?>
                                <div class="invalid-feedback d-block"><?= e($errors['Capacity']) ?></div>
                            <?php else: ?>
                                <small class="text-muted">Maximum allowable enrollments.</small>
                            <?php endif; ?>
                        </div>

                        <!-- Syllabus Description -->
                        <div class="col-12">
                            <label for="description" class="form-label">Course Description / Syllabus Overview</label>
                            <textarea class="form-control" 
                                      id="description" 
                                      name="description" 
                                      rows="4" 
                                      placeholder="Provide an overview of module objectives, prerequisites, and learning outcomes..."><?= e($formData['description']) ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-light border-top p-4 d-flex justify-content-end gap-2">
                    <a href="<?= e(get_app_url('courses/list.php')) ?>" class="btn btn-light px-4">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4 fw-semibold">
                        <i class="bi bi-check-lg me-1"></i> Save Course
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
