-- MentorBridge Database Schema
-- Run this complete SQL file to set up the database

CREATE DATABASE IF NOT EXISTS mentorbridge;
USE mentorbridge;

-- Drop tables if they exist (for fresh install)
DROP TABLE IF EXISTS feedback;
DROP TABLE IF EXISTS sessions;
DROP TABLE IF EXISTS mentor_availability;
DROP TABLE IF EXISTS time_slots;
DROP TABLE IF EXISTS mentor_categories;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS mentee_profiles;
DROP TABLE IF EXISTS mentor_profiles;
DROP TABLE IF EXISTS users;

-- Users table (authentication and roles)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('mentor', 'mentee', 'admin') NOT NULL,
    status ENUM('pending', 'active', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mentor profiles
CREATE TABLE mentor_profiles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    bio TEXT,
    skills TEXT,
    experience TEXT,
    profile_image VARCHAR(255),
    hourly_rate DECIMAL(10,2) DEFAULT 50.00,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    average_rating DECIMAL(3,2) DEFAULT 0.00,
    total_reviews INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_rating (average_rating),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mentee profiles
CREATE TABLE mentee_profiles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    interests TEXT,
    profile_image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categories (Programming, School, University, etc.)
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default categories
INSERT INTO categories (name, description, icon) VALUES
('Programming', 'Software development, coding, web development, mobile apps', '💻'),
('School', 'K-12 education, homework help, exam preparation', '🎒'),
('University', 'College-level courses, research, academic projects', '🎓'),
('Biology', 'Life sciences, anatomy, ecology, microbiology', '🧬'),
('Mathematics', 'Algebra, calculus, statistics, geometry', '📐'),
('Business', 'Entrepreneurship, marketing, management, finance', '💼'),
('Languages', 'English, Spanish, French, language learning', '🌍'),
('Arts', 'Music, painting, design, creative arts', '🎨'),
('Science', 'Physics, chemistry, earth sciences', '🔬'),
('Engineering', 'Mechanical, electrical, civil engineering', '⚙️');

-- Mentor-Category relationship (many-to-many)
CREATE TABLE mentor_categories (
    mentor_id INT NOT NULL,
    category_id INT NOT NULL,
    PRIMARY KEY (mentor_id, category_id),
    FOREIGN KEY (mentor_id) REFERENCES mentor_profiles(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    INDEX idx_mentor (mentor_id),
    INDEX idx_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Time slots for mentor availability (deprecated - use mentor_availability instead)
CREATE TABLE time_slots (
    id INT PRIMARY KEY AUTO_INCREMENT,
    mentor_id INT NOT NULL,
    day_of_week TINYINT NOT NULL COMMENT '0=Sunday, 1=Monday, ..., 6=Saturday',
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_available BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (mentor_id) REFERENCES mentor_profiles(id) ON DELETE CASCADE,
    INDEX idx_mentor (mentor_id),
    INDEX idx_day (day_of_week),
    INDEX idx_available (is_available)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mentor availability slots (new system)
CREATE TABLE mentor_availability (
    id INT PRIMARY KEY AUTO_INCREMENT,
    mentor_id INT NOT NULL,
    day_of_week VARCHAR(20) NOT NULL,
    time_slot TIME NOT NULL,
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (mentor_id) REFERENCES mentor_profiles(id) ON DELETE CASCADE,
    UNIQUE KEY unique_slot (mentor_id, day_of_week, time_slot),
    INDEX idx_mentor (mentor_id),
    INDEX idx_available (is_available)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mentorship sessions
CREATE TABLE sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    mentor_id INT NOT NULL,
    mentee_id INT NOT NULL,
    scheduled_at DATETIME NOT NULL,
    duration INT DEFAULT 60 COMMENT 'Duration in minutes',
    status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
    payment_status ENUM('pending', 'paid', 'refunded') DEFAULT 'pending',
    amount DECIMAL(10,2),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (mentor_id) REFERENCES mentor_profiles(id) ON DELETE CASCADE,
    FOREIGN KEY (mentee_id) REFERENCES mentee_profiles(id) ON DELETE CASCADE,
    INDEX idx_mentor (mentor_id),
    INDEX idx_mentee (mentee_id),
    INDEX idx_status (status),
    INDEX idx_scheduled (scheduled_at),
    INDEX idx_payment (payment_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Feedback and ratings
CREATE TABLE feedback (
    id INT PRIMARY KEY AUTO_INCREMENT,
    session_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
    INDEX idx_session (session_id),
    INDEX idx_rating (rating)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create an admin user (password: admin)
INSERT INTO users (email, password, role, status) VALUES 
('admin@mentorbridge.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active');

-- Sample mentor data (for testing)
INSERT INTO users (email, password, role, status) VALUES 
('john.mentor@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mentor', 'active');

INSERT INTO mentor_profiles (user_id, full_name, bio, skills, experience, hourly_rate, status, average_rating, total_reviews) VALUES 
(2, 'John Smith', 'Experienced software developer with 10+ years in web development. I specialize in teaching beginners and helping them build real-world projects.', 'JavaScript, Python, React, Node.js, SQL', '10 years as Senior Developer at Tech Corp. Built and mentored teams of 5-10 developers.', 75.00, 'approved', 4.8, 25);

INSERT INTO mentor_categories (mentor_id, category_id) VALUES 
(1, 1); -- Programming category

-- Sample mentee data (for testing)
INSERT INTO users (email, password, role, status) VALUES 
('jane.student@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mentee', 'active');

INSERT INTO mentee_profiles (user_id, full_name, interests) VALUES 
(3, 'Jane Doe', 'Learning web development, interested in building full-stack applications');

-- Sample session (for testing)
INSERT INTO sessions (mentor_id, mentee_id, scheduled_at, amount, status, payment_status) VALUES 
(1, 1, '2024-12-01 14:00:00', 75.00, 'completed', 'paid');

-- Sample feedback (for testing)
INSERT INTO feedback (session_id, rating, comment) VALUES 
(1, 5, 'John is an amazing mentor! Very patient and explains concepts clearly. Highly recommend!');

-- Trigger to automatically create default availability slots when mentor is approved
DELIMITER $$

CREATE TRIGGER create_default_availability AFTER UPDATE ON mentor_profiles
FOR EACH ROW
BEGIN
    IF NEW.status = 'approved' AND OLD.status != 'approved' THEN
        -- Insert default 9:00 AM slots for Monday through Friday
        INSERT IGNORE INTO mentor_availability (mentor_id, day_of_week, time_slot, is_available) VALUES
        (NEW.id, 'Monday', '09:00:00', 1),
        (NEW.id, 'Tuesday', '09:00:00', 1),
        (NEW.id, 'Wednesday', '09:00:00', 1),
        (NEW.id, 'Thursday', '09:00:00', 1),
        (NEW.id, 'Friday', '09:00:00', 1);
    END IF;
END$$

DELIMITER ;

-- Trigger to update mentor rating when feedback is added
DELIMITER $$

CREATE TRIGGER update_mentor_rating AFTER INSERT ON feedback
FOR EACH ROW
BEGIN
    DECLARE mentor_id_var INT;
    DECLARE avg_rating DECIMAL(3,2);
    DECLARE review_count INT;
    
    -- Get mentor_id from session
    SELECT s.mentor_id INTO mentor_id_var
    FROM sessions s
    WHERE s.id = NEW.session_id;
    
    -- Calculate new average rating
    SELECT AVG(f.rating), COUNT(f.id)
    INTO avg_rating, review_count
    FROM feedback f
    JOIN sessions s ON f.session_id = s.id
    WHERE s.mentor_id = mentor_id_var;
    
    -- Update mentor profile
    UPDATE mentor_profiles
    SET average_rating = avg_rating,
        total_reviews = review_count
    WHERE id = mentor_id_var;
END$$

DELIMITER ;

-- View for easy session overview
CREATE VIEW session_overview AS
SELECT 
    s.id,
    s.scheduled_at,
    s.duration,
    s.status,
    s.payment_status,
    s.amount,
    m.full_name AS mentor_name,
    m.hourly_rate AS mentor_rate,
    me.full_name AS mentee_name,
    f.rating AS feedback_rating,
    f.comment AS feedback_comment
FROM sessions s
JOIN mentor_profiles m ON s.mentor_id = m.id
JOIN mentee_profiles me ON s.mentee_id = me.id
LEFT JOIN feedback f ON s.id = f.session_id;

-- View for mentor statistics
CREATE VIEW mentor_stats AS
SELECT 
    m.id,
    m.full_name,
    m.status,
    m.average_rating,
    m.total_reviews,
    m.hourly_rate,
    COUNT(DISTINCT s.id) AS total_sessions,
    COUNT(DISTINCT CASE WHEN s.status = 'completed' THEN s.id END) AS completed_sessions,
    SUM(CASE WHEN s.payment_status = 'paid' THEN s.amount ELSE 0 END) AS total_earnings,
    GROUP_CONCAT(DISTINCT c.name) AS categories
FROM mentor_profiles m
LEFT JOIN sessions s ON m.id = s.mentor_id
LEFT JOIN mentor_categories mc ON m.id = mc.mentor_id
LEFT JOIN categories c ON mc.category_id = c.id
GROUP BY m.id;

-- Show table structure summary
SELECT 
    'Database setup complete!' AS status,
    'Tables created: 8' AS tables,
    'Sample data: Admin user, 1 mentor, 1 mentee, 1 session, 1 review' AS sample_data,
    'Ready to use!' AS message;

-- Display login credentials
SELECT 
    'Login Credentials' AS info,
    'Admin: admin@mentorbridge.com / admin123' AS admin,
    'Mentor: john.mentor@example.com / admin123' AS mentor,
    'Mentee: jane.student@example.com / admin123' AS mentee;