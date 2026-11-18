-- School LMS Database Schema
-- University of Skills Training and Entrepreneurial Development
-- ICT243 Web Programming Assignment 2026

CREATE DATABASE IF NOT EXISTS school_lms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE school_lms;

-- Users table (login accounts for admin, headteacher, teachers)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    password_plain VARCHAR(255) DEFAULT NULL,
    role ENUM('admin', 'headteacher', 'teacher') NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    security_question VARCHAR(255),
    security_answer VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Teachers detailed profile
CREATE TABLE teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    staff_id VARCHAR(50) NOT NULL UNIQUE,
    full_name VARCHAR(150) NOT NULL,
    date_of_birth DATE,
    sex ENUM('Male', 'Female') NOT NULL,
    marital_status ENUM('Single', 'Married', 'Divorced', 'Widowed') DEFAULT 'Single',
    current_rank VARCHAR(100),
    license_number VARCHAR(50),
    gps VARCHAR(100),
    ssnit_no VARCHAR(50),
    emis_code VARCHAR(50),
    bank_name VARCHAR(100),
    bank_account_number VARCHAR(50),
    bank_branch VARCHAR(100),
    email VARCHAR(100),
    phone VARCHAR(20),
    passport_photo VARCHAR(255),
    class_taught VARCHAR(50),
    is_approved TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Classes
CREATE TABLE classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255),
    academic_year VARCHAR(20) DEFAULT '2025/2026',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Teacher-Class assignment (many-to-many: multiple teachers can share a class)
CREATE TABLE class_teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    teacher_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_assignment (class_id, teacher_id)
);

-- Learners / Pupils
CREATE TABLE learners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admission_no VARCHAR(50) NOT NULL UNIQUE,
    full_name VARCHAR(150) NOT NULL,
    date_of_birth DATE NOT NULL,
    age INT,
    sex ENUM('Male', 'Female') NOT NULL,
    nhis_no VARCHAR(50),
    ghana_card_no VARCHAR(50),
    class_id INT,
    previous_school VARCHAR(150),
    mother_name VARCHAR(150),
    mother_occupation VARCHAR(100),
    mother_contact VARCHAR(20),
    father_name VARCHAR(150),
    father_occupation VARCHAR(100),
    father_contact VARCHAR(20),
    parents_resident VARCHAR(200),
    guardian_name VARCHAR(150),
    guardian_occupation VARCHAR(100),
    guardian_contact VARCHAR(20),
    passport_photo VARCHAR(255),
    enrollment_date DATE DEFAULT (CURRENT_DATE),
    academic_year VARCHAR(20) DEFAULT '2025/2026',
    is_active TINYINT(1) DEFAULT 1,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Weekly enrollment tracking (headteacher dashboard)
CREATE TABLE weekly_enrollment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    term_name VARCHAR(50) NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    week_date DATE NOT NULL,
    week_no INT NOT NULL,
    class_id INT NOT NULL,
    active_boys INT DEFAULT 0,
    active_girls INT DEFAULT 0,
    dropout_boys INT DEFAULT 0,
    dropout_girls INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
);

-- Staff daily attendance (teachers + headteacher, tracked on headteacher dashboard)
CREATE TABLE staff_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    term_name VARCHAR(50) NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    week_no INT NOT NULL,
    week_start_date DATE NOT NULL,
    staff_id VARCHAR(50) NOT NULL,
    staff_name VARCHAR(150) NOT NULL,
    attendance_date DATE NOT NULL,
    day_name ENUM('Monday','Tuesday','Wednesday','Thursday','Friday') NOT NULL,
    present TINYINT(1) DEFAULT 0,
    arrival_time TIME NULL,
    departure_time TIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_daily (term_name, academic_year, week_no, staff_id, attendance_date)
);

-- Matron / Cook daily feeding records (monitored on headteacher dashboard)
CREATE TABLE matron_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    record_date DATE NOT NULL,
    day_name VARCHAR(20) NOT NULL,
    matron_name VARCHAR(150) NOT NULL,
    meal_type VARCHAR(50) NOT NULL DEFAULT 'Lunch',
    food_type VARCHAR(255) NOT NULL,
    portions INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Academic terms
CREATE TABLE terms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    term_name VARCHAR(50) NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    term_ends DATE,
    next_term_begins DATE,
    is_current TINYINT(1) DEFAULT 0
);

-- Subjects
CREATE TABLE subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_name VARCHAR(100) NOT NULL UNIQUE,
    is_core TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0
);

-- Scores / Results
CREATE TABLE scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    learner_id INT NOT NULL,
    subject_id INT NOT NULL,
    term_id INT NOT NULL,
    class_score DECIMAL(5,2) DEFAULT 0,
    exam_score DECIMAL(5,2) DEFAULT 0,
    total_score
...