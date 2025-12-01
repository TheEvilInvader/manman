🎓 MentorBridge - Complete PHP Web Application
A full-featured mentorship platform connecting students with expert mentors. Built with PHP, MySQL, and modern UI/UX design.

✨ Features
For Mentees (Students)
🔍 Browse mentors by category
⭐ View mentor profiles with ratings and reviews
📅 Book sessions with available time slots
💳 Secure payment processing
💬 Leave feedback after sessions
For Mentors
📝 Create and manage profile
🎯 Set expertise categories
💰 Set hourly rates
⏳ Wait for admin approval
📊 View session statistics
For Admins
✅ Approve/reject mentor applications
👥 Manage users
📈 View platform statistics
📊 Monitor all sessions
🚀 Quick Start
Prerequisites
PHP 7.4+ or PHP 8.x
MySQL 5.7+ or 8.0+
Apache/Nginx web server
phpMyAdmin (optional, for easy database management)
Installation Steps
1. Create Database
sql
CREATE DATABASE mentorbridge;
USE mentorbridge;

-- Copy and run the complete SQL from database.sql
-- (See the SQL code in config.php artifact comments)
2. Project Structure
Create this folder structure:

mentorbridge/
├── config.php
├── index.php
├── login.php
├── register.php
├── dashboard.php
├── mentor-dashboard.php
├── mentee-dashboard.php
├── mentor-detail.php
├── book-session.php
├── payment.php
├── admin-dashboard.php
├── logout.php
└── uploads/ (create this folder with write permissions)
3. Configure Database
Edit config.php:

php
define('DB_HOST', 'localhost');
define('DB_NAME', 'mentorbridge');
define('DB_USER', 'root');        // Your MySQL username
define('DB_PASS', '');            // Your MySQL password
4. Set Permissions
bash
chmod 777 uploads/
5. Access the Application
Navigate to: http://localhost/mentorbridge/index.php

📁 File Descriptions
Core Files
config.php

Database connection
Session management
Helper functions (authentication, sanitization)
index.php

Landing page with animations
Hero section
Feature showcase
Statistics counter
Dark/light mode toggle
register.php

User registration
Role selection (Mentor/Mentee)
Email validation
Password hashing
login.php

User authentication
Session creation
Role-based redirects
dashboard.php

Router that redirects based on user role
Mentor Files
mentor-dashboard.php

Mentor profile management
Edit bio, skills, experience
Upload profile image
Select categories
View statistics (sessions, ratings)
Status banner (pending/approved/rejected)
Mentee Files
mentee-dashboard.php

Category selection
Mentor search and filtering
Browse mentor cards
View ratings and reviews
Search by name/skills
mentor-detail.php

Detailed mentor profile
Full bio and experience
Skills list
Review section
Available time slots
Booking sidebar
book-session.php

Process booking
Calculate next available date
Create session record
Redirect to payment
payment.php

Payment interface (placeholder)
Booking summary
Confirm and process payment
Update session status
Admin Files
admin-dashboard.php

Platform statistics
Pending mentor approvals
Approve/reject mentors
User management overview
logout.php

Session destruction
Redirect to home
🗄️ Database Schema
Main Tables
users

User authentication
Role assignment (mentor/mentee/admin)
Account status
mentor_profiles

Mentor information
Bio, skills, experience
Approval status
Ratings and reviews count
mentee_profiles

Mentee information
Interests
categories

Service categories (Programming, School, University, Biology, etc.)
Icons and descriptions
mentor_categories

Links mentors to categories (many-to-many)
sessions

Booked mentorship sessions
Scheduling information
Payment status
Session status
feedback

Session reviews
Ratings (1-5 stars)
Comments
time_slots (optional)

Mentor availability
Day and time configurations
🎨 Design Features
Animations
✨ Floating background shapes
📊 Animated statistics counters
🎯 Smooth scroll-based reveals
🎪 Hover effects on cards
🎭 Page transition animations
Responsive Design
📱 Mobile-first approach
💻 Tablet optimization
🖥️ Desktop layouts
🔄 Flexible grid systems
Color Scheme
css
Primary: #6366f1 (Indigo)
Secondary: #8b5cf6 (Purple)
Accent: #ec4899 (Pink)
Background: Linear gradient (Indigo to Purple)
🔐 Security Features
✅ Password hashing (PASSWORD_DEFAULT)
✅ SQL injection prevention (PDO prepared statements)
✅ XSS protection (htmlspecialchars)
✅ CSRF tokens (can be added)
✅ Input sanitization
✅ Session security
✅ Role-based access control
🧪 Testing
Create Test Accounts
Admin Account

sql
INSERT INTO users (email, password, role, status) 
VALUES ('admin@mentorbridge.com', '$2y$10$yourhashedpassword', 'admin', 'active');
Test Mentor

Register as mentor
Complete profile
Wait for admin approval (or manually approve in database)
Test Mentee

Register as mentee
Browse categories
Book a session
Test Scenarios
Registration Flow
Register as mentor
Register as mentee
Test validation errors
Mentor Workflow
Complete profile
Upload image
Select categories
Check pending status
Mentee Workflow
Browse categories
Search mentors
View mentor details
Book session
Process payment
Admin Workflow
View pending mentors
Approve mentors
View statistics
🔧 Customization
Change Colors
Edit CSS in each file, update:

css
--primary: #6366f1;
--secondary: #8b5cf6;
--accent: #ec4899;
Add More Categories
sql
INSERT INTO categories (name, description, icon) 
VALUES ('New Category', 'Description', '🎯');
Modify Hourly Rate
Edit in mentor-dashboard.php profile form

Add Payment Gateway
Replace payment.php placeholder with:

Stripe integration
PayPal integration
Square integration
📈 Scalability Improvements
Short-term (0-1K users)
✅ Basic caching (enable OPcache)
✅ Database indexing (already in schema)
✅ Image compression
Medium-term (1K-10K users)
🔄 Redis for sessions
🔄 CDN for static assets
🔄 Database read replicas
🔄 Search optimization (Elasticsearch)
Long-term (10K+ users)
🚀 Load balancing
🚀 Microservices architecture
🚀 Horizontal scaling
🚀 Global distribution
(See scalability-plan artifact for full details)

🐛 Known Limitations
Time Slots: Currently hardcoded, should be dynamic from database
Payment: Placeholder only, needs real gateway integration
Notifications: Email notifications not implemented
Chat: Real-time messaging not included
File Upload: Basic implementation, consider cloud storage
Search: Basic SQL search, consider full-text search engine
🔮 Future Enhancements
 Real-time chat between mentor and mentee
 Email notifications (booking confirmations, reminders)
 Calendar integration (Google Calendar, iCal)
 Video call integration (Zoom, Google Meet)
 Advanced search filters
 Mentor availability calendar
 Session rescheduling
 Refund system
 Multi-language support
 Mobile app (React Native)
 Social media login (OAuth)
 Analytics dashboard
 Promotional codes/discounts
 Subscription plans for mentees
📞 Support
For issues or questions:

Check database connection in config.php
Verify file permissions on uploads/
Check PHP error logs
Ensure all SQL tables are created
Verify PHP version (7.4+ required)
📄 License
This is a demo/educational project. Feel free to modify and use as needed.

👥 Contributing
Feel free to fork and improve! Suggested areas:

Payment gateway integration
Real-time features
Advanced search
Mobile optimization
Performance improvements
🎉 Quick Test Commands
Create Admin User (via phpMyAdmin or MySQL CLI):

sql
-- Password is 'admin123'
INSERT INTO users (email, password, role, status) VALUES 
('admin@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active');
Approve All Pending Mentors:

sql
UPDATE mentor_profiles SET status = 'approved' WHERE status = 'pending';
View All Sessions:

sql
SELECT s.id, m.full_name as mentor, me.full_name as mentee, s.scheduled_at, s.status 
FROM sessions s
JOIN mentor_profiles m ON s.mentor_id = m.id  
JOIN mentee_profiles me ON s.mentee_id = me.id
ORDER BY s.scheduled_at DESC;
Built with ❤️ for education and mentorship

🚀 Happy Coding!

