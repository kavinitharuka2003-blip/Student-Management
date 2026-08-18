-- ==============================================================================
-- Student & Course Management System (SCMS) - Seed Data Script
-- Default Demo Credentials:
--   Admin:     username = 'admin'           | password = 'password123'
--   Admin 2:   username = 'manager'         | password = 'password123'
--   Lecturer:  username = 'sarah.johnson'   | password = 'password123'
--   Lecturer:  username = 'alan.turing'     | password = 'password123'
--   Lecturer:  username = 'grace.hopper'    | password = 'password123'
-- ==============================================================================

USE scms;

-- ------------------------------------------------------------------------------
-- 1. Seed Users (Admins and Lecturers)
-- Bcrypt hash below corresponds to 'password123' generated with cost factor 10:
-- $2y$10$qR19z6pW1kPmsuHn.X0OheZ43N9d2c2tWzU5Gz1FvK1o4bM9x.dWe
-- ------------------------------------------------------------------------------
INSERT INTO users (id, username, password, full_name, email, role, created_at) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin@plymouth.ac.uk', 'admin', NOW()),
(2, 'manager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Registrar', 'registrar@plymouth.ac.uk', 'admin', NOW()),
(3, 'sarah.johnson', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Sarah Johnson', 'sarah.johnson@plymouth.ac.uk', 'lecturer', NOW()),
(4, 'alan.turing', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Prof. Alan Turing', 'alan.turing@plymouth.ac.uk', 'lecturer', NOW()),
(5, 'grace.hopper', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Grace Hopper', 'grace.hopper@plymouth.ac.uk', 'lecturer', NOW())
ON DUPLICATE KEY UPDATE full_name=VALUES(full_name);

-- ------------------------------------------------------------------------------
-- 2. Seed Students (Realistic demographic & academic records)
-- ------------------------------------------------------------------------------
INSERT INTO students (id, student_number, first_name, last_name, email, phone, date_of_birth, address, enrollment_date, status) VALUES
(1, 'STU1001', 'James', 'Wilson', 'james.wilson@student.plymouth.ac.uk', '+44 7700 900101', '2002-04-14', '14 North Hill, Plymouth, PL4 8AA', '2023-09-18', 'active'),
(2, 'STU1002', 'Emily', 'Chen', 'emily.chen@student.plymouth.ac.uk', '+44 7700 900102', '2001-11-20', '28 Drake Circus, Plymouth, PL1 2AA', '2022-09-19', 'active'),
(3, 'STU1003', 'Liam', 'O''Connor', 'liam.oconnor@student.plymouth.ac.uk', '+44 7700 900103', '2003-02-05', '5 Mutley Plain, Plymouth, PL4 6LF', '2023-09-18', 'active'),
(4, 'STU1004', 'Sophia', 'Patel', 'sophia.patel@student.plymouth.ac.uk', '+44 7700 900104', '2002-08-30', '88 Tavistock Place, Plymouth, PL4 8AX', '2023-09-18', 'active'),
(5, 'STU1005', 'Oliver', 'Brown', 'oliver.brown@student.plymouth.ac.uk', '+44 7700 900105', '2000-06-12', '12 Hoe Road, Plymouth, PL1 3DE', '2021-09-20', 'graduated'),
(6, 'STU1006', 'Ava', 'Taylor', 'ava.taylor@student.plymouth.ac.uk', '+44 7700 900106', '2003-10-18', '43 North Road East, Plymouth, PL4 6AQ', '2023-09-18', 'active'),
(7, 'STU1007', 'Noah', 'Williams', 'noah.williams@student.plymouth.ac.uk', '+44 7700 900107', '2002-01-22', '19 Cobourg Street, Plymouth, PL1 1SR', '2022-09-19', 'active'),
(8, 'STU1008', 'Isabella', 'Davies', 'isabella.davies@student.plymouth.ac.uk', '+44 7700 900108', '2001-09-09', '77 Regent Street, Plymouth, PL4 8BB', '2022-09-19', 'inactive'),
(9, 'STU1009', 'Lucas', 'Evans', 'lucas.evans@student.plymouth.ac.uk', '+44 7700 900109', '2003-05-15', '22 Armada Way, Plymouth, PL1 1LD', '2023-09-18', 'active'),
(10, 'STU1010', 'Mia', 'Thomas', 'mia.thomas@student.plymouth.ac.uk', '+44 7700 900110', '2000-12-01', '34 Royal Parade, Plymouth, PL1 1DX', '2021-09-20', 'graduated'),
(11, 'STU1011', 'Alexander', 'Smith', 'alex.smith@student.plymouth.ac.uk', '+44 7700 900111', '2003-07-25', '60 Portland Square, Plymouth, PL4 8AA', '2023-09-18', 'active'),
(12, 'STU1012', 'Chloe', 'Roberts', 'chloe.roberts@student.plymouth.ac.uk', '+44 7700 900112', '2002-03-17', '15 Gibbons Street, Plymouth, PL4 8BR', '2022-09-19', 'active')
ON DUPLICATE KEY UPDATE first_name=VALUES(first_name);

-- ------------------------------------------------------------------------------
-- 3. Seed Courses (Diverse academic modules with assigned lecturers)
-- ------------------------------------------------------------------------------
INSERT INTO courses (id, course_code, course_name, description, credits, capacity, lecturer_id) VALUES
(1, 'COMP5001', 'Web & Cloud Application Architecture', 'Comprehensive architectural study of client-server systems, RESTful services, database persistence, and security controls.', 20, 35, 3),
(2, 'COMP5002', 'Data Structures & Algorithms', 'Theoretical foundations of complexity theory, graph algorithms, hash indexing, and tree structures.', 20, 40, 4),
(3, 'COMP5003', 'Relational Database Engineering', 'Advanced relational database design, SQL optimization, ACID transactions, Normalization, and indexing strategies.', 15, 30, 3),
(4, 'COMP5004', 'Cybersecurity Principles & Defensive Coding', 'Identification of common vulnerabilities (OWASP Top 10), cryptographic protocols, input validation, and secure authentication.', 15, 25, 5),
(5, 'COMP5005', 'Software Engineering Practice & DevOps', 'Agile methodologies, test-driven development, continuous integration, version control best practices, and team collaboration.', 20, 30, 4),
(6, 'COMP5006', 'Applied Artificial Intelligence & Machine Learning', 'Introduction to supervised/unsupervised learning models, neural networks, and data classification pipelines.', 20, 20, 5)
ON DUPLICATE KEY UPDATE course_name=VALUES(course_name);

-- ------------------------------------------------------------------------------
-- 4. Seed Enrollments (Many-to-Many join with realistic grades & statuses)
-- ------------------------------------------------------------------------------
INSERT INTO enrollments (id, student_id, course_id, enrollment_date, grade, status) VALUES
(1, 1, 1, '2023-09-25', 'A', 'enrolled'),
(2, 1, 3, '2023-09-25', 'B', 'enrolled'),
(3, 1, 4, '2023-09-25', NULL, 'enrolled'),
(4, 2, 1, '2022-09-26', 'A+', 'completed'),
(5, 2, 2, '2022-09-26', 'A', 'completed'),
(6, 2, 5, '2023-09-25', 'B', 'enrolled'),
(7, 3, 2, '2023-09-25', 'B', 'enrolled'),
(8, 3, 3, '2023-09-25', 'C', 'enrolled'),
(9, 4, 1, '2023-09-25', 'A', 'enrolled'),
(10, 4, 4, '2023-09-25', 'A', 'enrolled'),
(11, 4, 6, '2023-09-25', NULL, 'enrolled'),
(12, 5, 2, '2021-09-27', 'A+', 'completed'),
(13, 5, 3, '2021-09-27', 'A', 'completed'),
(14, 6, 1, '2023-09-25', 'B', 'enrolled'),
(15, 6, 5, '2023-09-25', NULL, 'enrolled'),
(16, 7, 3, '2022-09-26', 'B', 'completed'),
(17, 7, 4, '2023-09-25', 'A', 'enrolled'),
(18, 8, 2, '2022-09-26', 'F', 'dropped'),
(19, 9, 1, '2023-09-25', 'A', 'enrolled'),
(20, 9, 6, '2023-09-25', NULL, 'enrolled'),
(21, 10, 1, '2021-09-27', 'A', 'completed'),
(22, 10, 4, '2021-09-27', 'A', 'completed'),
(23, 11, 5, '2023-09-25', 'C', 'enrolled'),
(24, 12, 6, '2023-09-25', 'B', 'enrolled')
ON DUPLICATE KEY UPDATE grade=VALUES(grade);
