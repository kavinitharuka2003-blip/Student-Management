<?php
/**
 * ==============================================================================
 * Executive Analytical Dashboard (dashboard.php)
 * ------------------------------------------------------------------------------
 * Aggregates institutional KPIs, visual analytics via Chart.js, recent student
 * registrations, and quick navigation shortcuts with role-aware metrics.
 * ==============================================================================
 */

$page_title = 'Executive Dashboard';
require_once __DIR__ . '/includes/header.php';

$pdo = get_db();

// ------------------------------------------------------------------------------
// 1. Fetch Executive KPI Metrics
// ------------------------------------------------------------------------------
// Total Students
$total_students = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$active_students = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE status = 'active'")->fetchColumn();

// Total Courses
$total_courses = (int)$pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();

// Total Enrollments
$total_enrollments = (int)$pdo->query("SELECT COUNT(*) FROM enrollments")->fetchColumn();
$active_enrollments = (int)$pdo->query("SELECT COUNT(*) FROM enrollments WHERE status = 'enrolled'")->fetchColumn();

// Total Faculty Lecturers
$total_lecturers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'lecturer'")->fetchColumn();

// ------------------------------------------------------------------------------
// 2. Fetch Chart Analytics Data
// ------------------------------------------------------------------------------
// A. Course Enrollments Data (Bar Chart)
$course_chart_sql = "
    SELECT 
        c.course_code, 
        c.capacity,
        COUNT(e.id) AS enrolled_count
    FROM courses c
    LEFT JOIN enrollments e ON c.id = e.course_id AND e.status = 'enrolled'
    GROUP BY c.id, c.course_code, c.capacity
    ORDER BY c.course_code ASC
";
$course_chart_data = $pdo->query($course_chart_sql)->fetchAll();

$chart_labels = [];
$chart_enrolled = [];
$chart_capacities = [];

foreach ($course_chart_data as $row) {
    $chart_labels[] = $row['course_code'];
    $chart_enrolled[] = (int)$row['enrolled_count'];
    $chart_capacities[] = (int)$row['capacity'];
}

// B. Enrollment Status Breakdown (Doughnut Chart)
$status_sql = "
    SELECT status, COUNT(*) AS count
    FROM enrollments
    GROUP BY status
";
$status_data = $pdo->query($status_sql)->fetchAll();
$status_counts = ['enrolled' => 0, 'completed' => 0, 'dropped' => 0];

foreach ($status_data as $s) {
    $status_counts[$s['status']] = (int)$s['count'];
}

// ------------------------------------------------------------------------------
// 3. Fetch Recently Added Students (Recent 5)
// ------------------------------------------------------------------------------
$recent_students_sql = "
    SELECT id, student_number, first_name, last_name, email, enrollment_date, status
    FROM students
    ORDER BY created_at DESC
    LIMIT 5
";
$recent_students = $pdo->query($recent_students_sql)->fetchAll();
?>

<!-- Welcome Banner -->
<div class="card bg-white border-0 shadow-sm mb-4 p-4" style="background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h4 class="font-heading fw-bold text-dark mb-0">Good Day, <?= e($current_user['full_name']) ?>!</h4>
                <span class="badge <?= is_admin() ? 'bg-primary-subtle text-primary border border-primary-subtle' : 'bg-info-subtle text-info-emphasis border border-info-subtle' ?>">
                    <?= is_admin() ? 'Administrator Portal' : 'Faculty Instructor' ?>
                </span>
            </div>
            <p class="text-muted small mb-0">
                Institutional status overview for academic year <?= date('Y') ?>/<?= date('y', strtotime('+1 year')) ?>. All systems operating normally.
            </p>
        </div>
        
        <?php if (is_admin()): ?>
        <div class="d-flex gap-2">
            <a href="<?= e(get_app_url('students/add.php')) ?>" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1.5">
                <i class="bi bi-person-plus-fill"></i> Register Student
            </a>
            <a href="<?= e(get_app_url('enrollments/add.php')) ?>" class="btn btn-light btn-sm d-inline-flex align-items-center gap-1.5 border">
                <i class="bi bi-card-plus"></i> New Enrollment
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- KPI Metric Cards Grid -->
<div class="row g-3 mb-4">
    <!-- Stat 1: Total Students -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card shadow-sm">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-value"><?= $total_students ?></div>
                    <div class="stat-label">Registered Students</div>
                </div>
                <div class="stat-icon-wrapper bg-primary-subtle text-primary">
                    <i class="bi bi-people-fill"></i>
                </div>
            </div>
            <div class="mt-3 pt-2 border-top d-flex align-items-center justify-content-between small text-muted">
                <span>Active Roster</span>
                <span class="fw-semibold text-success font-monospace"><?= $active_students ?> Active</span>
            </div>
        </div>
    </div>

    <!-- Stat 2: Active Courses -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card shadow-sm">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-value"><?= $total_courses ?></div>
                    <div class="stat-label">Active Course Modules</div>
                </div>
                <div class="stat-icon-wrapper bg-success-subtle text-success">
                    <i class="bi bi-journal-bookmark-fill"></i>
                </div>
            </div>
            <div class="mt-3 pt-2 border-top d-flex align-items-center justify-content-between small text-muted">
                <span>Curriculum</span>
                <a href="<?= e(get_app_url('courses/list.php')) ?>" class="text-primary text-decoration-none fw-medium">View Catalog &rarr;</a>
            </div>
        </div>
    </div>

    <!-- Stat 3: Total Enrollments -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card shadow-sm">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-value"><?= $total_enrollments ?></div>
                    <div class="stat-label">Total Module Enrollments</div>
                </div>
                <div class="stat-icon-wrapper bg-warning-subtle text-warning">
                    <i class="bi bi-card-checklist"></i>
                </div>
            </div>
            <div class="mt-3 pt-2 border-top d-flex align-items-center justify-content-between small text-muted">
                <span>Current Active</span>
                <span class="fw-semibold text-primary font-monospace"><?= $active_enrollments ?> Enrolled</span>
            </div>
        </div>
    </div>

    <!-- Stat 4: Faculty Lecturers -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card shadow-sm">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-value"><?= $total_lecturers ?></div>
                    <div class="stat-label">Academic Faculty</div>
                </div>
                <div class="stat-icon-wrapper bg-info-subtle text-info">
                    <i class="bi bi-person-badge-fill"></i>
                </div>
            </div>
            <div class="mt-3 pt-2 border-top d-flex align-items-center justify-content-between small text-muted">
                <span>Teaching Staff</span>
                <span class="fw-semibold text-secondary font-monospace"><?= $total_lecturers ?> Lecturers</span>
            </div>
        </div>
    </div>
</div>

<!-- Analytical Charts Row -->
<div class="row g-4 mb-4">
    <!-- Chart 1: Course Enrollment Distribution -->
    <div class="col-12 col-lg-8">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="card-header-title text-dark">
                    <i class="bi bi-bar-chart-fill text-primary me-2"></i> Student Enrollment per Course
                </h6>
                <span class="badge bg-light text-secondary border">Active Students</span>
            </div>
            <div class="card-body p-4">
                <div style="position: relative; height: 280px; width: 100%;">
                    <canvas id="courseEnrollmentChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart 2: Status Breakdown -->
    <div class="col-12 col-lg-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3">
                <h6 class="card-header-title text-dark">
                    <i class="bi bi-pie-chart-fill text-primary me-2"></i> Enrollment Status Breakdown
                </h6>
            </div>
            <div class="card-body p-4 d-flex flex-column align-items-center justify-content-center">
                <div style="position: relative; height: 200px; width: 200px;">
                    <canvas id="statusDistributionChart"></canvas>
                </div>
                <div class="d-flex justify-content-center gap-3 mt-4 text-center small">
                    <div>
                        <div class="fw-bold text-dark"><?= $status_counts['enrolled'] ?></div>
                        <div class="text-muted"><span class="badge bg-primary p-1 me-1"></span>Enrolled</div>
                    </div>
                    <div>
                        <div class="fw-bold text-dark"><?= $status_counts['completed'] ?></div>
                        <div class="text-muted"><span class="badge bg-success p-1 me-1"></span>Completed</div>
                    </div>
                    <div>
                        <div class="fw-bold text-dark"><?= $status_counts['dropped'] ?></div>
                        <div class="text-muted"><span class="badge bg-danger p-1 me-1"></span>Dropped</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recently Registered Students Table -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="card-header-title text-dark">
            <i class="bi bi-clock-history text-primary me-2"></i> Recently Registered Students
        </h6>
        <a href="<?= e(get_app_url('students/list.php')) ?>" class="btn btn-sm btn-light border">
            View All Students &rarr;
        </a>
    </div>

    <div class="table-responsive">
        <table class="table custom-table">
            <thead>
                <tr>
                    <th>Student ID</th>
                    <th>Student Name</th>
                    <th>Email Address</th>
                    <th>Enrolled Date</th>
                    <th>Status</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recent_students)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">No students recorded yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recent_students as $st): ?>
                        <tr>
                            <td>
                                <a href="<?= e(get_app_url('students/view.php?id=' . $st['id'])) ?>" class="fw-bold text-primary font-monospace text-decoration-none">
                                    <?= e($st['student_number']) ?>
                                </a>
                            </td>
                            <td>
                                <span class="fw-semibold text-dark"><?= e($st['first_name'] . ' ' . $st['last_name']) ?></span>
                            </td>
                            <td>
                                <span class="text-muted small"><?= e($st['email']) ?></span>
                            </td>
                            <td>
                                <span class="text-muted small"><?= date('M d, Y', strtotime($st['enrollment_date'])) ?></span>
                            </td>
                            <td>
                                <?= render_status_badge($st['status']) ?>
                            </td>
                            <td class="text-end">
                                <a href="<?= e(get_app_url('students/view.php?id=' . $st['id'])) ?>" class="btn btn-sm btn-light btn-action text-primary" title="View Profile">
                                    <i class="bi bi-arrow-right"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Chart.js Initialization Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Bar Chart: Course Enrollments
    const ctxBar = document.getElementById('courseEnrollmentChart');
    if (ctxBar) {
        new Chart(ctxBar, {
            type: 'bar',
            data: {
                labels: <?= json_encode($chart_labels) ?>,
                datasets: [
                    {
                        label: 'Active Enrollments',
                        data: <?= json_encode($chart_enrolled) ?>,
                        backgroundColor: '#4f46e5',
                        borderRadius: 6,
                        barThickness: 24,
                    },
                    {
                        label: 'Max Capacity',
                        data: <?= json_encode($chart_capacities) ?>,
                        backgroundColor: '#e2e8f0',
                        borderRadius: 6,
                        barThickness: 24,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            font: { family: 'Inter', size: 12 },
                            usePointStyle: true,
                            boxWidth: 8
                        }
                    },
                    tooltip: {
                        padding: 10,
                        cornerRadius: 8,
                        titleFont: { family: 'Inter', weight: 'bold' },
                        bodyFont: { family: 'Inter' }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: 'Inter', size: 11 } }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9' },
                        ticks: { stepSize: 5, font: { family: 'Inter', size: 11 } }
                    }
                }
            }
        });
    }

    // 2. Doughnut Chart: Status Breakdown
    const ctxPie = document.getElementById('statusDistributionChart');
    if (ctxPie) {
        new Chart(ctxPie, {
            type: 'doughnut',
            data: {
                labels: ['Enrolled', 'Completed', 'Dropped'],
                datasets: [{
                    data: [
                        <?= $status_counts['enrolled'] ?>,
                        <?= $status_counts['completed'] ?>,
                        <?= $status_counts['dropped'] ?>
                    ],
                    backgroundColor: ['#4f46e5', '#10b981', '#ef4444'],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        padding: 10,
                        cornerRadius: 8
                    }
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
