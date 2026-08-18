<?php
/**
 * ==============================================================================
 * Student Registration (students/add.php)
 * ------------------------------------------------------------------------------
 * Handles creation of student records with rigorous server-side validation,
 * uniqueness checks on student number and email, and CSRF protection.
 * ==============================================================================
 */

$page_title = 'Register Student';
require_once __DIR__ . '/../includes/header.php';

// Enforce Admin-only access for creating new records
require_admin();

$pdo = get_db();
$errors = [];

// Form default values
$formData = [
    'student_number'  => '',
    'first_name'      => '',
    'last_name'       => '',
    'email'           => '',
    'phone'           => '',
    'date_of_birth'   => '',
    'address'         => '',
    'enrollment_date' => date('Y-m-d'),
    'status'          => 'active',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();

    // Populate formData with submitted values
    $formData['student_number']  = strtoupper(trim($_POST['student_number'] ?? ''));
    $formData['first_name']      = trim($_POST['first_name'] ?? '');
    $formData['last_name']       = trim($_POST['last_name'] ?? '');
    $formData['email']           = strtolower(trim($_POST['email'] ?? ''));
    $formData['phone']           = trim($_POST['phone'] ?? '');
    $formData['date_of_birth']   = trim($_POST['date_of_birth'] ?? '');
    $formData['address']         = trim($_POST['address'] ?? '');
    $formData['enrollment_date'] = trim($_POST['enrollment_date'] ?? date('Y-m-d'));
    $formData['status']          = trim($_POST['status'] ?? 'active');

    // --------------------------------------------------------------------------
    // Server-Side Input Validation Rules
    // --------------------------------------------------------------------------
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
        $errors['status'] = 'Invalid student status selected.';
    }

    // --------------------------------------------------------------------------
    // Database Uniqueness Checks (Prepared Statements)
    // --------------------------------------------------------------------------
    if (empty($errors['student_number'])) {
        $stmt = $pdo->prepare("SELECT id FROM students WHERE student_number = :num LIMIT 1");
        $stmt->execute([':num' => $formData['student_number']]);
        if ($stmt->fetch()) {
            $errors['student_number'] = "A student with student ID '{$formData['student_number']}' already exists.";
        }
    }

    if (empty($errors['email'])) {
        $stmt = $pdo->prepare("SELECT id FROM students WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $formData['email']]);
        if ($stmt->fetch()) {
            $errors['email'] = "A student with email '{$formData['email']}' is already registered.";
        }
    }

    // --------------------------------------------------------------------------
    // Execute Insertion if Error Free
    // --------------------------------------------------------------------------
    if (empty($errors)) {
        try {
            $insert_sql = "
                INSERT INTO students (
                    student_number, first_name, last_name, email, phone, 
                    date_of_birth, address, enrollment_date, status
                ) VALUES (
                    :student_number, :first_name, :last_name, :email, :phone, 
                    :date_of_birth, :address, :enrollment_date, :status
                )
            ";
            
            $insert_stmt = $pdo->prepare($insert_sql);
            $insert_stmt->execute([
                ':student_number'  => $formData['student_number'],
                ':first_name'      => $formData['first_name'],
                ':last_name'       => $formData['last_name'],
                ':email'           => $formData['email'],
                ':phone'           => $formData['phone'] ?: null,
                ':date_of_birth'   => $formData['date_of_birth'] ?: null,
                ':address'         => $formData['address'] ?: null,
                ':enrollment_date' => $formData['enrollment_date'],
                ':status'          => $formData['status'],
            ]);

            $new_student_id = $pdo->lastInsertId();

            set_flash('success', "Student '{$formData['first_name']} {$formData['last_name']}' successfully registered.");
            header("Location: " . get_app_url('students/view.php?id=' . $new_student_id));
            exit;

        } catch (PDOException $e) {
            error_log("Student Insert Failure: " . $e->getMessage());
            set_flash('danger', 'A database error occurred while creating the student record.');
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-12 col-xl-9">
        
        <!-- Breadcrumb & Navigation -->
        <div class="d-flex align-items-center justify-content-between mb-4">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= e(get_app_url('students/list.php')) ?>" class="text-decoration-none">Students</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Register Student</li>
                </ol>
            </nav>
            <a href="<?= e(get_app_url('students/list.php')) ?>" class="btn btn-sm btn-light">
                <i class="bi bi-arrow-left me-1"></i> Back to Directory
            </a>
        </div>

        <!-- Registration Form Card -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="card-header-title text-dark">
                    <i class="bi bi-person-plus text-primary me-2"></i> Register New Student
                </h5>
            </div>

            <form method="POST" action="<?= e($_SERVER['PHP_SELF']) ?>" novalidate class="needs-validation">
                <?= csrf_field() ?>

                <div class="card-body p-4">
                    
                    <h6 class="text-uppercase fw-bold text-muted mb-3" style="font-size: 0.75rem; letter-spacing: 0.06em;">
                        Academic Identification
                    </h6>

                    <div class="row g-3 mb-4">
                        <!-- Student Number -->
                        <div class="col-md-6">
                            <label for="student_number" class="form-label">
                                Student ID Number <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-hash"></i></span>
                                <input type="text" 
                                       class="form-control font-monospace <?= isset($errors['Student Number']) || isset($errors['student_number']) ? 'is-invalid' : '' ?>" 
                                       id="student_number" 
                                       name="student_number" 
                                       placeholder="e.g. STU2001" 
                                       value="<?= e($formData['student_number']) ?>" 
                                       required>
                            </div>
                            <?php if (isset($errors['Student Number']) || isset($errors['student_number'])): ?>
                                <div class="invalid-feedback d-block">
                                    <?= e($errors['Student Number'] ?? $errors['student_number']) ?>
                                </div>
                            <?php else: ?>
                                <small class="text-muted">Unique institutional identifier.</small>
                            <?php endif; ?>
                        </div>

                        <!-- Status -->
                        <div class="col-md-6">
                            <label for="status" class="form-label">
                                Academic Status <span class="text-danger">*</span>
                            </label>
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
                        <!-- First Name -->
                        <div class="col-md-6">
                            <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control <?= isset($errors['First Name']) ? 'is-invalid' : '' ?>" 
                                   id="first_name" 
                                   name="first_name" 
                                   placeholder="e.g. Eleanor" 
                                   value="<?= e($formData['first_name']) ?>" 
                                   required>
                            <?php if (isset($errors['First Name'])): ?>
                                <div class="invalid-feedback d-block"><?= e($errors['First Name']) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Last Name -->
                        <div class="col-md-6">
                            <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control <?= isset($errors['Last Name']) ? 'is-invalid' : '' ?>" 
                                   id="last_name" 
                                   name="last_name" 
                                   placeholder="e.g. Vance" 
                                   value="<?= e($formData['last_name']) ?>" 
                                   required>
                            <?php if (isset($errors['Last Name'])): ?>
                                <div class="invalid-feedback d-block"><?= e($errors['Last Name']) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Email -->
                        <div class="col-md-6">
                            <label for="email" class="form-label">Institutional Email <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input type="email" 
                                       class="form-control <?= isset($errors['Email']) || isset($errors['email']) ? 'is-invalid' : '' ?>" 
                                       id="email" 
                                       name="email" 
                                       placeholder="e.g. e.vance@student.plymouth.ac.uk" 
                                       value="<?= e($formData['email']) ?>" 
                                       required>
                            </div>
                            <?php if (isset($errors['Email']) || isset($errors['email'])): ?>
                                <div class="invalid-feedback d-block"><?= e($errors['Email'] ?? $errors['email']) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Phone -->
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Contact Phone</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                                <input type="tel" 
                                       class="form-control" 
                                       id="phone" 
                                       name="phone" 
                                       placeholder="e.g. +44 7700 900555" 
                                       value="<?= e($formData['phone']) ?>">
                            </div>
                        </div>

                        <!-- Date of Birth -->
                        <div class="col-md-6">
                            <label for="date_of_birth" class="form-label">Date of Birth</label>
                            <input type="date" 
                                   class="form-control <?= isset($errors['Date of Birth']) ? 'is-invalid' : '' ?>" 
                                   id="date_of_birth" 
                                   name="date_of_birth" 
                                   value="<?= e($formData['date_of_birth']) ?>">
                            <?php if (isset($errors['Date of Birth'])): ?>
                                <div class="invalid-feedback d-block"><?= e($errors['Date of Birth']) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Enrollment Date -->
                        <div class="col-md-6">
                            <label for="enrollment_date" class="form-label">Initial Enrollment Date <span class="text-danger">*</span></label>
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

                        <!-- Address -->
                        <div class="col-12">
                            <label for="address" class="form-label">Residential Address</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="address" 
                                   name="address" 
                                   placeholder="e.g. 10 Drake Circus, Plymouth, PL1 2AA" 
                                   value="<?= e($formData['address']) ?>">
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-light border-top p-4 d-flex justify-content-end gap-2">
                    <a href="<?= e(get_app_url('students/list.php')) ?>" class="btn btn-light px-4">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4 fw-semibold">
                        <i class="bi bi-check-lg me-1"></i> Register Student
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
