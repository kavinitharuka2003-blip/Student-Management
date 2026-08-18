<?php
/**
 * ==============================================================================
 * Student Modification Portal (students/edit.php)
 * ------------------------------------------------------------------------------
 * Prefills existing student profile details and processes record updates
 * with defensive validation and uniqueness constraint checks.
 * ==============================================================================
 */

$page_title = 'Edit Student';
require_once __DIR__ . '/../includes/header.php';

// Enforce Admin permissions
require_admin();

$pdo = get_db();
$errors = [];

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($id <= 0) {
    set_flash('danger', 'Invalid student identifier specified.');
    header("Location: " . get_app_url('students/list.php'));
    exit;
}

// Fetch existing record
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$student = $stmt->fetch();

if (!$student) {
    set_flash('danger', 'The requested student record could not be found.');
    header("Location: " . get_app_url('students/list.php'));
    exit;
}

// Initialize form data with existing database attributes
$formData = $student;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();

    $formData['student_number']  = strtoupper(trim($_POST['student_number'] ?? ''));
    $formData['first_name']      = trim($_POST['first_name'] ?? '');
    $formData['last_name']       = trim($_POST['last_name'] ?? '');
    $formData['email']           = strtolower(trim($_POST['email'] ?? ''));
    $formData['phone']           = trim($_POST['phone'] ?? '');
    $formData['date_of_birth']   = trim($_POST['date_of_birth'] ?? '');
    $formData['address']         = trim($_POST['address'] ?? '');
    $formData['enrollment_date'] = trim($_POST['enrollment_date'] ?? '');
    $formData['status']          = trim($_POST['status'] ?? 'active');

    // Validation
    validate_required($formData['student_number'], 'Student Number', $errors);
    validate_required($formData['first_name'], 'First Name', $errors);
    validate_required($formData['last_name'], 'Last Name', $errors);
    validate_required($formData['email'], 'Email', $errors);
    validate_email($formData['email'], 'Email', $errors);
    validate_date_format($formData['enrollment_date'], 'Enrollment Date', $errors);
    
    if (!empty($formData['date_of_birth'])) {
        validate_date_format($formData['date_of_birth'], 'Date of Birth', $errors, true);
    }

    if (!in_array($formData['status'], ['active', 'inactive', 'graduated'])) {
        $errors['status'] = 'Invalid student status.';
    }

    // Check unique student_number excluding current student
    if (empty($errors['student_number'])) {
        $stmt_check = $pdo->prepare("SELECT id FROM students WHERE student_number = :num AND id != :id LIMIT 1");
        $stmt_check->execute([':num' => $formData['student_number'], ':id' => $id]);
        if ($stmt_check->fetch()) {
            $errors['student_number'] = "The student number '{$formData['student_number']}' is already assigned to another student.";
        }
    }

    // Check unique email excluding current student
    if (empty($errors['email'])) {
        $stmt_check = $pdo->prepare("SELECT id FROM students WHERE email = :email AND id != :id LIMIT 1");
        $stmt_check->execute([':email' => $formData['email'], ':id' => $id]);
        if ($stmt_check->fetch()) {
            $errors['email'] = "The email '{$formData['email']}' is already used by another record.";
        }
    }

    if (empty($errors)) {
        try {
            $update_sql = "
                UPDATE students SET
                    student_number = :student_number,
                    first_name = :first_name,
                    last_name = :last_name,
                    email = :email,
                    phone = :phone,
                    date_of_birth = :date_of_birth,
                    address = :address,
                    enrollment_date = :enrollment_date,
                    status = :status
                WHERE id = :id
            ";

            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([
                ':student_number'  => $formData['student_number'],
                ':first_name'      => $formData['first_name'],
                ':last_name'       => $formData['last_name'],
                ':email'           => $formData['email'],
                ':phone'           => $formData['phone'] ?: null,
                ':date_of_birth'   => $formData['date_of_birth'] ?: null,
                ':address'         => $formData['address'] ?: null,
                ':enrollment_date' => $formData['enrollment_date'],
                ':status'          => $formData['status'],
                ':id'              => $id
            ]);

            set_flash('success', "Student record for '{$formData['first_name']} {$formData['last_name']}' updated successfully.");
            header("Location: " . get_app_url('students/view.php?id=' . $id));
            exit;

        } catch (PDOException $e) {
            error_log("Student Update Error: " . $e->getMessage());
            set_flash('danger', 'An error occurred while updating the student record.');
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-12 col-xl-9">
        
        <div class="d-flex align-items-center justify-content-between mb-4">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= e(get_app_url('students/list.php')) ?>" class="text-decoration-none">Students</a></li>
                    <li class="breadcrumb-item"><a href="<?= e(get_app_url('students/view.php?id=' . $id)) ?>" class="text-decoration-none"><?= e($student['first_name'] . ' ' . $student['last_name']) ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edit</li>
                </ol>
            </nav>
            <a href="<?= e(get_app_url('students/view.php?id=' . $id)) ?>" class="btn btn-sm btn-light">
                <i class="bi bi-arrow-left me-1"></i> Back to Profile
            </a>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="card-header-title text-dark">
                    <i class="bi bi-pencil-square text-primary me-2"></i> Edit Student Record
                </h5>
            </div>

            <form method="POST" action="<?= e($_SERVER['PHP_SELF'] . '?id=' . $id) ?>" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $id ?>">

                <div class="card-body p-4">
                    
                    <h6 class="text-uppercase fw-bold text-muted mb-3" style="font-size: 0.75rem; letter-spacing: 0.06em;">
                        Academic Identification
                    </h6>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="student_number" class="form-label">Student ID Number <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control font-monospace <?= isset($errors['Student Number']) || isset($errors['student_number']) ? 'is-invalid' : '' ?>" 
                                   id="student_number" 
                                   name="student_number" 
                                   value="<?= e($formData['student_number']) ?>" 
                                   required>
                            <?php if (isset($errors['Student Number']) || isset($errors['student_number'])): ?>
                                <div class="invalid-feedback d-block"><?= e($errors['Student Number'] ?? $errors['student_number']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label for="status" class="form-label">Academic Status <span class="text-danger">*</span></label>
                            <select name="status" id="status" class="form-select <?= isset($errors['status']) ? 'is-invalid' : '' ?>" required>
                                <option value="active" <?= $formData['status'] === 'active' ? 'selected' : '' ?>>Active (Currently Enrolled)</option>
                                <option value="inactive" <?= $formData['status'] === 'inactive' ? 'selected' : '' ?>>Inactive (On Leave / Suspended)</option>
                                <option value="graduated" <?= $formData['status'] === 'graduated' ? 'selected' : '' ?>>Graduated (Alumnus)</option>
                            </select>
                            <?php if (isset($errors['status'])): ?>
                                <div class="invalid-feedback d-block"><?= e($errors['status']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <h6 class="text-uppercase fw-bold text-muted mb-3" style="font-size: 0.75rem; letter-spacing: 0.06em;">
                        Personal & Contact Details
                    </h6>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control <?= isset($errors['First Name']) ? 'is-invalid' : '' ?>" 
                                   id="first_name" 
                                   name="first_name" 
                                   value="<?= e($formData['first_name']) ?>" 
                                   required>
                            <?php if (isset($errors['First Name'])): ?>
                                <div class="invalid-feedback d-block"><?= e($errors['First Name']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control <?= isset($errors['Last Name']) ? 'is-invalid' : '' ?>" 
                                   id="last_name" 
                                   name="last_name" 
                                   value="<?= e($formData['last_name']) ?>" 
                                   required>
                            <?php if (isset($errors['Last Name'])): ?>
                                <div class="invalid-feedback d-block"><?= e($errors['Last Name']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label for="email" class="form-label">Institutional Email <span class="text-danger">*</span></label>
                            <input type="email" 
                                   class="form-control <?= isset($errors['Email']) || isset($errors['email']) ? 'is-invalid' : '' ?>" 
                                   id="email" 
                                   name="email" 
                                   value="<?= e($formData['email']) ?>" 
                                   required>
                            <?php if (isset($errors['Email']) || isset($errors['email'])): ?>
                                <div class="invalid-feedback d-block"><?= e($errors['Email'] ?? $errors['email']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label for="phone" class="form-label">Contact Phone</label>
                            <input type="tel" 
                                   class="form-control" 
                                   id="phone" 
                                   name="phone" 
                                   value="<?= e($formData['phone']) ?>">
                        </div>

                        <div class="col-md-6">
                            <label for="date_of_birth" class="form-label">Date of Birth</label>
                            <input type="date" 
                                   class="form-control <?= isset($errors['Date of Birth']) ? 'is-invalid' : '' ?>" 
                                   id="date_of_birth" 
                                   name="date_of_birth" 
                                   value="<?= e($formData['date_of_birth']) ?>">
                        </div>

                        <div class="col-md-6">
                            <label for="enrollment_date" class="form-label">Enrollment Date <span class="text-danger">*</span></label>
                            <input type="date" 
                                   class="form-control <?= isset($errors['Enrollment Date']) ? 'is-invalid' : '' ?>" 
                                   id="enrollment_date" 
                                   name="enrollment_date" 
                                   value="<?= e($formData['enrollment_date']) ?>" 
                                   required>
                        </div>

                        <div class="col-12">
                            <label for="address" class="form-label">Residential Address</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="address" 
                                   name="address" 
                                   value="<?= e($formData['address']) ?>">
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-light border-top p-4 d-flex justify-content-end gap-2">
                    <a href="<?= e(get_app_url('students/view.php?id=' . $id)) ?>" class="btn btn-light px-4">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4 fw-semibold">
                        <i class="bi bi-save me-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
