-- Add availability table to database
CREATE TABLE IF NOT EXISTS mentor_availability (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mentor_id INT NOT NULL,
    day_of_week VARCHAR(20) NOT NULL,
    time_slot TIME NOT NULL,
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (mentor_id) REFERENCES mentor_profiles(id) ON DELETE CASCADE,
    UNIQUE KEY unique_slot (mentor_id, day_of_week, time_slot)
);

-- Insert default 9:00 AM slot for all mentors
INSERT INTO mentor_availability (mentor_id, day_of_week, time_slot)
SELECT id, 'Monday', '09:00:00' FROM mentor_profiles
UNION ALL
SELECT id, 'Tuesday', '09:00:00' FROM mentor_profiles
UNION ALL
SELECT id, 'Wednesday', '09:00:00' FROM mentor_profiles
UNION ALL
SELECT id, 'Thursday', '09:00:00' FROM mentor_profiles
UNION ALL
SELECT id, 'Friday', '09:00:00' FROM mentor_profiles;
