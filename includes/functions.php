<?php
/**
 * ==============================================================================
 * Global Utility & Business Logic Helper Library (includes/functions.php)
 * ------------------------------------------------------------------------------
 * Provides essential security functions (XSS escaping, CSRF verification),
 * robust input validation rules, flash messaging bus, and view formatters.
 * ==============================================================================
 */

// ------------------------------------------------------------------------------
// 1. SECURITY & SANITIZATION HELPERS
// ------------------------------------------------------------------------------

/**
 * Escapes dynamic string output to defend against Cross-Site Scripting (XSS).
 *
 * @param mixed $value Dynamic string or value.
 * @return string Sanitized HTML entity-safe string.
 */
function e($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Generates or retrieves an existing cryptographically secure CSRF token for the session.
 *
 * @return string Hexadecimal CSRF token.
 */
function generate_csrf_token(): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Generates an HTML hidden input field carrying the active CSRF token.
 *
 * @return string HTML hidden input string.
 */
function csrf_field(): string {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
}

/**
 * Validates the submitted CSRF token against the stored session token.
 *
 * @param string|null $submitted_token The token sent via POST or GET request.
 * @return bool True if valid, false otherwise.
 */
function validate_csrf_token(?string $submitted_token): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($submitted_token) || empty($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $submitted_token);
}

/**
 * Enforce valid CSRF token on POST requests or terminate with 403 Forbidden.
 */
function verify_csrf_or_die(): void {
    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? null;
    if (!validate_csrf_token($token)) {
        http_response_code(403);
        die('
            <!DOCTYPE html>
            <html lang="en">
            <head><meta charset="UTF-8"><title>403 Forbidden - CSRF Error</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
            <body class="bg-light p-5">
                <div class="card max-w-md mx-auto shadow-sm p-4 text-center border-danger" style="max-width:500px;">
                    <h3 class="text-danger">403 - Invalid Security Token</h3>
                    <p class="text-muted">The security token for this form was invalid or has expired. Please refresh the page and try submitting again.</p>
                    <a href="javascript:history.back()" class="btn btn-outline-secondary">Go Back</a>
                </div>
            </body>
            </html>
        ');
    }
}

// ------------------------------------------------------------------------------
// 2. FLASH MESSAGING (Post-Redirect-Get UX)
// ------------------------------------------------------------------------------

/**
 * Store a flash notification message in the session.
 *
 * @param string $type Message type ('success', 'danger', 'warning', 'info').
 * @param string $message User notification text.
 */
function set_flash(string $type, string $message): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Normalize type
    if ($type === 'error') $type = 'danger';

    $_SESSION['flash_messages'][] = [
        'type'    => $type,
        'message' => $message,
    ];
}

/**
 * Retrieve and clear all queued flash messages from the session.
 *
 * @return array Array of flash messages.
 */
function get_flash_messages(): array {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

/**
 * Renders HTML markup for all current flash messages with dismissible alerts.
 *
 * @return string HTML alert markup.
 */
function render_flash_messages(): string {
    $messages = get_flash_messages();
    if (empty($messages)) {
        return '';
    }

    $html = '<div class="flash-messages-container mb-4">';
    foreach ($messages as $msg) {
        $type = e($msg['type']);
        $text = e($msg['message']);
        
        $icon = match($type) {
            'success' => '<i class="bi bi-check-circle-fill me-2"></i>',
            'danger'  => '<i class="bi bi-exclamation-triangle-fill me-2"></i>',
            'warning' => '<i class="bi bi-exclamation-circle-fill me-2"></i>',
            default   => '<i class="bi bi-info-circle-fill me-2"></i>',
        };

        $html .= "
            <div class=\"alert alert-{$type} alert-dismissible fade show shadow-sm d-flex align-items-center\" role=\"alert\">
                {$icon}
                <div class=\"flex-grow-1\">{$text}</div>
                <button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\" aria-label=\"Close\"></button>
            </div>
        ";
    }
    $html .= '</div>';

    return $html;
}

// ------------------------------------------------------------------------------
// 3. INPUT VALIDATION ROUTINES
// ------------------------------------------------------------------------------

/**
 * Validates a string is non-empty after trimming.
 */
function validate_required(?string $value, string $field_label, array &$errors): void {
    if ($value === null || trim($value) === '') {
        $errors[$field_label] = "The {$field_label} field is required.";
    }
}

/**
 * Validates email format.
 */
function validate_email(?string $email, string $field_label, array &$errors): void {
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[$field_label] = "Please provide a valid email address.";
    }
}

/**
 * Validates a standard date format (YYYY-MM-DD) and logical date validity.
 */
function validate_date_format(?string $date, string $field_label, array &$errors, bool $must_be_past = false): void {
    if (!empty($date)) {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date) {
            $errors[$field_label] = "The {$field_label} must be a valid date in YYYY-MM-DD format.";
            return;
        }

        if ($must_be_past) {
            $today = new DateTime('today');
            if ($d >= $today) {
                $errors[$field_label] = "The {$field_label} must be a date in the past.";
            }
        }
    }
}

/**
 * Validates integer value within a given min/max range.
 */
function validate_integer_range(?int $value, int $min, int $max, string $field_label, array &$errors): void {
    if ($value === null || $value < $min || $value > $max) {
        $errors[$field_label] = "The {$field_label} must be between {$min} and {$max}.";
    }
}

// ------------------------------------------------------------------------------
// 4. UI FORMATTING & BADGE RENDERING
// ------------------------------------------------------------------------------

/**
 * Generates a modern Bootstrap badge for student or enrollment status.
 */
function render_status_badge(string $status): string {
    $status_clean = strtolower(trim($status));
    
    $badge_map = [
        'active'    => ['bg' => 'bg-success-subtle text-success border border-success-subtle', 'label' => 'Active', 'icon' => 'bi-check-circle'],
        'inactive'  => ['bg' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle', 'label' => 'Inactive', 'icon' => 'bi-dash-circle'],
        'graduated' => ['bg' => 'bg-primary-subtle text-primary border border-primary-subtle', 'label' => 'Graduated', 'icon' => 'bi-mortarboard'],
        'enrolled'  => ['bg' => 'bg-info-subtle text-info-emphasis border border-info-subtle', 'label' => 'Enrolled', 'icon' => 'bi-person-check'],
        'completed' => ['bg' => 'bg-success-subtle text-success border border-success-subtle', 'label' => 'Completed', 'icon' => 'bi-trophy'],
        'dropped'   => ['bg' => 'bg-danger-subtle text-danger border border-danger-subtle', 'label' => 'Dropped', 'icon' => 'bi-x-circle'],
    ];

    $config = $badge_map[$status_clean] ?? ['bg' => 'bg-secondary-subtle text-secondary border border-secondary-subtle', 'label' => ucfirst($status), 'icon' => 'bi-circle'];

    return sprintf(
        '<span class="badge %s px-2.5 py-1.5 rounded-pill d-inline-flex align-items-center gap-1.5"><i class="bi %s"></i> %s</span>',
        $config['bg'],
        $config['icon'],
        e($config['label'])
    );
}

/**
 * Generates a styled badge for student grades.
 */
function render_grade_badge(?string $grade): string {
    if (empty($grade)) {
        return '<span class="badge bg-light text-secondary border px-2 py-1"><i class="bi bi-clock-history me-1"></i>Pending</span>';
    }

    $grade_clean = strtoupper(trim($grade));
    $style = match(substr($grade_clean, 0, 1)) {
        'A' => 'bg-success-subtle text-success border border-success-subtle font-monospace fw-bold',
        'B' => 'bg-primary-subtle text-primary border border-primary-subtle font-monospace fw-bold',
        'C' => 'bg-info-subtle text-info-emphasis border border-info-subtle font-monospace fw-bold',
        'D' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle font-monospace fw-bold',
        'F' => 'bg-danger-subtle text-danger border border-danger-subtle font-monospace fw-bold',
        default => 'bg-secondary-subtle text-secondary border border-secondary-subtle font-monospace',
    };

    return sprintf('<span class="badge %s px-2.5 py-1 rounded">%s</span>', $style, e($grade_clean));
}

/**
 * Calculate pagination offsets and boundaries.
 */
function paginate(int $total_items, int $per_page, int $current_page): array {
    $total_pages = max(1, (int)ceil($total_items / $per_page));
    $current_page = max(1, min($current_page, $total_pages));
    $offset = ($current_page - 1) * $per_page;

    return [
        'total_items'  => $total_items,
        'per_page'     => $per_page,
        'total_pages'  => $total_pages,
        'current_page' => $current_page,
        'offset'       => $offset,
        'has_prev'     => $current_page > 1,
        'has_next'     => $current_page < $total_pages,
    ];
}
