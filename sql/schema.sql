    -- Nyumba Connect Database Schema
    -- Drop existing tables if they exist (in reverse order of dependencies)
    DROP TABLE IF EXISTS messages;
    DROP TABLE IF EXISTS mentorships;
    DROP TABLE IF EXISTS mentorship_requests;
    DROP TABLE IF EXISTS applications;
    DROP TABLE IF EXISTS opportunities;
    DROP TABLE IF EXISTS resources;
    DROP TABLE IF EXISTS accounts;

    -- Create accounts table
    CREATE TABLE accounts (
        account_id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        role ENUM('student', 'alumni', 'admin') NOT NULL,
        year_left YEAR NULL,
        education TEXT NULL,
        skills TEXT NULL,
        location VARCHAR(255) NULL,
        bio TEXT NULL,
        cv_path VARCHAR(500) NULL,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_email (email),
        INDEX idx_role (role),
        INDEX idx_active (is_active)
    );

    -- Create opportunities table
    CREATE TABLE opportunities (
        opportunity_id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        category VARCHAR(100) NOT NULL,
        deadline DATE NULL,
        link VARCHAR(500) NULL,
        posted_by INT NOT NULL,
        status ENUM('pending', 'approved', 'rejected', 'closed') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (posted_by) REFERENCES accounts(account_id) ON DELETE CASCADE,
        INDEX idx_status (status),
        INDEX idx_deadline (deadline),
        INDEX idx_category (category)
    );

    -- Create applications table
    CREATE TABLE applications (
        application_id INT AUTO_INCREMENT PRIMARY KEY,
        opportunity_id INT NOT NULL,
        applicant_id INT NOT NULL,
        cv_path VARCHAR(500) NOT NULL,
        cover_letter TEXT NOT NULL,
        status ENUM('applied', 'shortlisted', 'rejected', 'hired') DEFAULT 'applied',
        applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (opportunity_id) REFERENCES opportunities(opportunity_id) ON DELETE CASCADE,
        FOREIGN KEY (applicant_id) REFERENCES accounts(account_id) ON DELETE CASCADE,
        UNIQUE KEY unique_application (opportunity_id, applicant_id),
        INDEX idx_applicant (applicant_id),
        INDEX idx_status (status)
    );

    -- Create mentorship_requests table
    CREATE TABLE mentorship_requests (
        request_id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        alumni_id INT NOT NULL,
        message TEXT NOT NULL,
        status ENUM('pending', 'accepted', 'declined') DEFAULT 'pending',
        requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        responded_at TIMESTAMP NULL,
        FOREIGN KEY (student_id) REFERENCES accounts(account_id) ON DELETE CASCADE,
        FOREIGN KEY (alumni_id) REFERENCES accounts(account_id) ON DELETE CASCADE,
        UNIQUE KEY unique_request (student_id, alumni_id),
        INDEX idx_student (student_id),
        INDEX idx_alumni (alumni_id),
        INDEX idx_status (status)
    );

    -- Create mentorships table
    CREATE TABLE mentorships (
        mentorship_id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        alumni_id INT NOT NULL,
        started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        active BOOLEAN DEFAULT TRUE,
        FOREIGN KEY (student_id) REFERENCES accounts(account_id) ON DELETE CASCADE,
        FOREIGN KEY (alumni_id) REFERENCES accounts(account_id) ON DELETE CASCADE,
        UNIQUE KEY unique_mentorship (student_id, alumni_id),
        INDEX idx_student (student_id),
        INDEX idx_alumni (alumni_id),
        INDEX idx_active (active)
    );

    -- Create messages table
    CREATE TABLE messages (
        message_id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        mentorship_id INT NOT NULL,
        message_text TEXT NOT NULL,
        sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_read BOOLEAN DEFAULT FALSE,
        FOREIGN KEY (sender_id) REFERENCES accounts(account_id) ON DELETE CASCADE,
        FOREIGN KEY (receiver_id) REFERENCES accounts(account_id) ON DELETE CASCADE,
        FOREIGN KEY (mentorship_id) REFERENCES mentorships(mentorship_id) ON DELETE CASCADE,
        INDEX idx_mentorship (mentorship_id),
        INDEX idx_sender (sender_id),
        INDEX idx_receiver (receiver_id),
        INDEX idx_read_status (is_read),
        INDEX idx_sent_at (sent_at)
    );

    -- Create resources table
    CREATE TABLE resources (
        resource_id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        uploaded_by INT NOT NULL,
        download_count INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (uploaded_by) REFERENCES accounts(account_id) ON DELETE CASCADE,
        INDEX idx_uploaded_by (uploaded_by),
        INDEX idx_created_at (created_at)
    );