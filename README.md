# Student & Course Management System (SCMS)

A robust, full-stack, database-backed web application built for academic institutions to manage students, course curricula, and module enrollments with role-based access control (RBAC).

Developed with **PHP 8.x (PDO)**, **MySQL/MariaDB**, **Bootstrap 5.3**, and **Vanilla JavaScript/Chart.js**.

---

## 🌟 Key Features & Architectural Highlights

- **Complete Multi-Entity CRUD**: Full lifecycle management for **Students**, **Courses**, and **Enrollments** (Many-to-Many join table with grade/status tracking).
- **Role-Based Access Control (RBAC)**:
  - **Admin**: Complete system authority across all entities, lecturer assignments, and global analytics.
  - **Lecturer**: Scoped view tailored to assigned courses, roster inspection, and grade updates.
- **Enterprise-Grade Security**:
  - **100% Prepared Statements (PDO)** with bound parameters across all database interactions (immunity to SQL Injection).
  - **CSRF Token Verification** on all state-altering POST/DELETE requests.
  - **Secure Password Hashing** using PHP `password_hash()` (Bcrypt).
  - **Output Escaping** with `htmlspecialchars()` to prevent Cross-Site Scripting (XSS).
  - **Post-Redirect-Get (PRG)** pattern to prevent accidental duplicate form submissions.
- **Interactive Analytics & Dashboard**:
  - Real-time KPI summaries (Total Students, Active Courses, Active Enrollments, Registered Lecturers).
  - Dynamic **Chart.js** data visualizations (Enrollments per Course & Enrollment Status Distribution).
- **Advanced Data Presentation**:
  - Real-time search, multi-criteria filtering, column sorting, and pagination (10 items per page).
  - Live capacity progress bars on course views with enrollment caps.
  - Duplicate enrollment prevention at both UI and database constraint levels.
- **Modern Responsive UI**:
  - Built with Bootstrap 5.3, Google Fonts (*Inter*), custom glassmorphic components, status badges, and interactive delete confirmation modals.

---

## 🗂️ Project Directory Structure

```text
manage/
├── config/
│   └── db.php                 # Database connection with PDO & error handling
├── includes/
│   ├── auth.php                # Authentication guards & RBAC helpers
│   ├── functions.php           # Validation, CSRF, flash messaging & utility functions
│   ├── header.php              # Global navigation, sidebar, and breadcrumbs
│   └── footer.php              # Global footer, modals, and script tags
├── auth/
│   ├── login.php               # Secure authentication portal
│   ├── logout.php              # Session termination
│   └── profile.php             # User profile & password management
├── students/
│   ├── list.php                # Search, filter, sort & pagination for students
│   ├── add.php                 # New student registration with validation
│   ├── edit.php                # Student record modification
│   ├── view.php                # Comprehensive student profile & course history
│   ├── delete.php              # Protected student deletion endpoint
│   └── export.php              # CSV data exporter for students
├── courses/
│   ├── list.php                # Course catalog with capacity indicators & faculty filter
│   ├── add.php                 # Course creation & lecturer assignment
│   ├── edit.php                # Course details & capacity configuration
│   ├── view.php                # Course overview, capacity meter & enrolled roster
│   ├── delete.php              # Protected course deletion endpoint
│   └── export.php              # CSV data exporter for course catalog
├── enrollments/
│   ├── list.php                # Enrollment ledger with multi-filter support
│   ├── add.php                 # Student course enrollment with capacity validation
│   ├── edit.php                # Grade recording & status updates
│   ├── delete.php              # Protected enrollment drop/deletion endpoint
│   └── export.php              # CSV data exporter for enrollment ledger
├── dashboard.php               # Executive KPI overview & Chart.js visual charts
├── index.php                   # Intelligent landing router
├── 404.php                     # Branded 404 Not Found error screen
├── assets/
│   ├── css/
│   │   └── style.css           # Custom styles, modern variables & animations
│   └── js/
│       ├── main.js             # Delete modal controller & toast handlers
│       └── validation.js       # Live client-side form validation UX
├── sql/
│   ├── schema.sql              # Relational database table definitions & FK constraints
│   └── seed.sql                # Demonstration dataset with sample users & records
├── .gitignore                  # Clean repository configuration
├── REPORT_SKELETON.md          # 1st-Class coursework reflective report draft
└── README.md                   # System documentation & setup guide
```

---

## 🚀 Step-by-Step Local Setup Guide (XAMPP / WAMP)

### 1. Prerequisites
- **Apache Web Server** & **PHP 8.0+**
- **MySQL 5.7+** or **MariaDB 10.3+**
- (Recommended: **XAMPP**, **WAMP**, or **MAMP**)

### 2. Installation Steps

1. **Deploy to Web Root**:
   - Copy this project folder (`manage` or `student-course-management`) into your local server root:
     - **XAMPP**: `C:\xampp\htdocs\manage`
     - **WAMP**: `C:\wamp64\www\manage`

2. **Database Import**:
   - Open your browser and navigate to **phpMyAdmin** (`http://localhost/phpmyadmin`).
   - Create a new database named `scms` with collation `utf8mb4_unicode_ci` (or execute the scripts directly).
   - In the **Import** tab:
     1. Import `sql/schema.sql` (Creates tables, foreign keys, and indexes).
     2. Import `sql/seed.sql` (Populates sample administrators, lecturers, students, and courses).

3. **Configure Database Credentials**:
   - Open `config/db.php` in any text editor.
   - Adjust the database constants if your local MySQL configuration differs:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_PORT', '3306');
     define('DB_NAME', 'scms');
     define('DB_USER', 'root');      // Default XAMPP/WAMP username
     define('DB_PASS', '');          // Default XAMPP password is empty (WAMP might be 'root' or empty)
     ```

4. **Launch Application**:
   - Open your web browser and navigate to:
     ```text
     http://localhost/manage/
     ```

---

## 🔑 Default Demonstration Accounts

All pre-seeded demo accounts share the password: `password123` (or `admin123`):

| Role | Username | Password | Access Capabilities |
| :--- | :--- | :--- | :--- |
| **System Administrator** | `admin` | `password123` | Full access across all Students, Courses, Enrollments, Lecturers, & Metrics. |
| **Academic Registrar** | `manager` | `password123` | Full administrative & management access. |
| **Lecturer (Dr. Johnson)**| `sarah.johnson`| `password123` | View assigned courses (COMP5001, COMP5003), view enrolled students, enter grades. |
| **Lecturer (Prof. Turing)**| `alan.turing` | `password123` | View assigned courses (COMP5002, COMP5005), view enrolled students, enter grades. |
| **Lecturer (Dr. Hopper)** | `grace.hopper` | `password123` | View assigned courses (COMP5004, COMP5006), view enrolled students, enter grades. |

*(Quick-fill buttons are provided on the login page for rapid viva & coursework demonstration).*

---

## 🛡️ Security & Defensive Coding Measures

1. **SQL Injection Prevention**: All queries execute through PDO prepared statements (`$stmt->prepare()` and `$stmt->execute($params)`). Zero dynamic string concatenation in SQL.
2. **Cross-Site Scripting (XSS)**: All user-supplied output rendered in HTML is sanitized via `htmlspecialchars($str, ENT_QUOTES, 'UTF-8')` through the central `e()` helper function.
3. **Cross-Site Request Forgery (CSRF)**: All state-modifying actions (add, edit, delete) generate and validate cryptographically secure tokens (`bin2hex(random_bytes(32))`).
4. **Session Security & RBAC**: Session IDs are regenerated upon authentication. Role-based guards verify permissions at the top of every restricted script.
5. **Data Integrity Constraints**: Foreign keys enforce referential integrity (`ON DELETE CASCADE` on student/course deletion, `ON DELETE SET NULL` on lecturer reassignment) and composite unique index (`student_id`, `course_id`) prevents duplicate enrollments.

---

## 🧪 Testing Checklist & Verification

Refer to **Phase 7** in the technical brief for comprehensive test cases covering positive flows, edge cases (duplicate emails, boundary capacity limits, date sanitization), and security exploits (SQL injection payload simulation, CSRF tampering).
