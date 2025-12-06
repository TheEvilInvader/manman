# 🎓 MentorBridge - Complete PHP Mentorship Platform

A full-featured mentorship platform connecting students with expert mentors. Built with PHP, MySQLi, and modern UI/UX design with a professional blue color scheme.

## ✨ Features

### For Mentees (Students)
- 🔍 Browse mentors by category with beautiful cards
- ⭐ View mentor profiles with ratings and reviews
- 📅 Book sessions with real-time availability checking
- 💳 Secure payment processing (20% platform fee)
- 💬 Leave feedback and ratings after sessions
- 📊 Track your session history
- 🎯 View detailed mentor expertise and experience

### For Mentors
- 📝 Create and manage professional profile
- 🎯 Set expertise categories (multiple selection)
- 💰 Set hourly rates (platform adds 20% fee)
- 🕐 **Dynamic availability management** - Add/remove time slots
- ⏳ Two-tier approval system:
  - New mentor → Admin approval → Activate
  - Profile updates → Re-approval (sessions continue uninterrupted)
- 📊 **Dual dashboard system**:
  - **Mentor Dashboard** - Main hub with sessions, earnings, stats
  - **Profile Editing** - Separate page for profile updates
- 📅 **Four-state availability system**:
  - ✅ Available (open for booking)
  - 📅 Booked (mentee reserved)
  - ⏳ Waiting for Feedback (session done, no review yet)
  - 🚫 Disabled (mentor toggled off)
- 📈 Real-time statistics (total sessions, upcoming, rating, earnings)
- 💼 Session management with completion marking
- 🌟 View mentee feedback on completed sessions

### For Admins
- ✅ **Separated approval workflows**:
  - New Applications tab (first-time mentors)
  - Profile Updates tab (existing mentors with edit requests)
- 👥 Comprehensive user management
- 📈 Platform-wide statistics dashboard
- 📊 Monitor all sessions and payments
- 💰 Track platform revenue (20% of all paid sessions)
- 🎯 View top mentors by earnings
- 📋 Session and feedback oversight

## 🚀 Quick Start

### Prerequisites
- PHP 7.4+ or PHP 8.x
- MySQL 5.7+ or 8.0+
- Apache/Nginx web server
- MySQLi extension enabled
- phpMyAdmin (optional, for easy database management)

### Installation Steps

#### 1. Create Database
```sql
CREATE DATABASE mentorbridge;
USE mentorbridge;

-- Run the complete SQL from database.sql
```

#### 2. Project Structure
```
mentorbridge/
├── config.php                  # Database & utilities
├── index.php                   # Landing page
├── login.php                   # User login
├── register.php                # User registration (Professional UI)
├── dashboard.php               # Smart routing
├── mentor-dashboard.php        # Mentor sessions hub (was mentor-home.php)
├── mentor-profile.php          # Mentor profile editing (was mentor-dashboard.php)
├── manage-availability.php     # Availability management
├── mentee-dashboard.php        # Mentee hub
├── my-sessions.php             # Mentee session tracking
├── mentor-detail.php           # Mentor detail view
├── book-session.php            # Session booking
├── payment.php                 # Payment processing
├── admin-dashboard.php         # Admin panel
├── logout.php                  # Logout
├── database.sql                # Database schema
└── uploads/                    # Profile images
```

#### 3. Configure Database
Edit `config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'mentorbridge');
define('DB_USER', 'root');        // Your MySQL username
define('DB_PASS', '');            // Your MySQL password
```

#### 4. Set Permissions
```bash
chmod 777 uploads/
```

#### 5. Create Admin Account
Use the database to create an admin user:
```sql
INSERT INTO users (email, password, role, status) 
VALUES ('admin@mentorbridge.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active');
-- Password: password
```

#### 6. Access the Application
Navigate to: `http://localhost/mentorbridge/`

## 📁 Key File Descriptions

### Core Files

**config.php**
- MySQLi database connection
- Session management
- Helper functions (authentication, sanitization)
- **Note:** Sanitization uses `real_escape_string()` and `strip_tags()` only (no double-encoding)
- Result cleanup with `result->free()` to prevent sync errors

**index.php**
- Landing page with animations
- Hero section with call-to-action
- Feature showcase
- Statistics counter
- Dark/light mode toggle

**register.php**
- Professional UI with Inter font
- Role selection (Mentor/Mentee)
- Email validation
- Password hashing
- Platform color scheme (#638ECB blues)

**login.php**
- User authentication
- Session creation
- Role-based redirects

**dashboard.php**
- Smart routing based on user role and mentor status
- Approved mentors → mentor-dashboard.php
- New/pending mentors → mentor-profile.php
- Mentees → mentee-dashboard.php
- Admins → admin-dashboard.php

### Mentor Files

**mentor-dashboard.php** (Main Hub - was mentor-home.php)
- Sessions dashboard for approved mentors only
- Statistics: Total sessions, upcoming, rating, earnings
- Upcoming sessions list with "Mark as Completed" button
- Completed sessions with mentee feedback display
- Navigation to Profile Editing and Manage Availability
- Real-time earnings calculation (80% after platform fee)

**mentor-profile.php** (Profile Editing - was mentor-dashboard.php)
- Profile creation for new mentors
- Profile editing for approved mentors
- **Re-approval system**: Changes set status back to 'pending'
- Shows different messages based on mentor status
- Default name fallback: "John Doe" if name empty
- Category selection (multiple)
- Skills, bio, experience, hourly rate
- Profile image upload
- Navigation back to dashboard for approved mentors

**manage-availability.php**
- Dynamic time slot management
- Add/remove available time slots
- **Four-state system**:
  - Available (green) - Open for booking
  - Booked (yellow) - Mentee reserved the slot
  - Waiting for Feedback (amber) - Session completed, no review yet
  - Disabled (red) - Mentor manually disabled
- Enhanced query with `LEFT JOIN sessions` and `feedback`
- Prevents disabling/deleting booked or feedback-pending slots
- Grouped by day of week
- Conflict detection (slots must be 1 hour apart)
### Mentee Files

**mentee-dashboard.php**
- Category-based mentor browsing
- Mentor search and filtering
- Beautiful mentor cards with ratings
- Quick booking access
- Search by name/skills

**my-sessions.php**
- Session history tracking
- Upcoming sessions display
- Completed sessions with feedback option
- Session details (date, time, amount, status)
- Leave ratings and reviews

**mentor-detail.php**
- Detailed mentor profile view
- Full bio and experience display
- Skills showcase
- Reviews and ratings section
- Available time slots display
- Booking sidebar with rate info

**book-session.php**
- Session booking processor
- Availability checking with database queries
- Prevents double-booking
- Calculates next available date
- Creates session record
- Redirects to payment

**payment.php**
- Payment processing interface
- Booking summary display
- Amount calculation with 20% platform fee
- Session confirmation
- Payment status update

### Admin Files

**admin-dashboard.php**
- **Two separate tabs**:
  - **New Applications**: First-time mentors (no sessions)
  - **Profile Updates**: Existing mentors requesting changes (has sessions)
- Platform statistics (users, mentors, sessions, revenue)
- Mentor profile details with categories
- Approve/Reject buttons with different actions:
  - New: "Approve" / "Reject"
  - Updates: "Re-Approve" / "Reject Changes"
- Session count and last updated timestamp for updates
- Top mentors by earnings
- Recent sessions overview
- User management

**logout.php**
- Session destruction
- Secure logout
- Redirect to home

## 🗄️ Database Schema

### Main Tables

**users**
- User authentication credentials
- Role assignment (mentor/mentee/admin)
- Account status (active/suspended)
- Email and password (hashed)

**mentor_profiles**
- Mentor information
- Bio, skills, experience
- Hourly rate
- Approval status (pending/approved/rejected)
- Average rating and rating count
- Profile image path
- Created and updated timestamps

**mentee_profiles**
- Mentee information
- Full name
- Interests

**categories**
- Service categories (Programming, School, University, Biology, etc.)
- Icons (emojis) and descriptions
- Used for filtering and organization

**mentor_categories**
- Many-to-many relationship
- Links mentors to multiple categories

**mentor_availability**
- Dynamic availability slots
- Day of week (Monday-Sunday)
- Time slot (HH:MM:SS format)
- Availability flag (enabled/disabled)
- Created by approved mentors

**sessions**
- Booked mentorship sessions
- Scheduling information (date/time)
- Payment status (pending/paid)
- Session status (pending/confirmed/completed/cancelled)
- Amount (mentor rate + 20% platform fee)
- Links to mentor and mentee

**feedback**
- Session reviews and ratings
- Rating (1-5 stars)
- Comments
- Timestamps
- Links to session

## 🎨 Design Features

### Animations
- ✨ Floating background shapes
- 📊 Animated statistics counters
- 🎯 Smooth scroll-based reveals
- 🎪 Hover effects on cards
- 🎭 Page transition animations
- 💫 Slide-down alerts
- 🔄 Smooth role card selection

### Responsive Design
- 📱 Mobile-first approach
- 💻 Tablet optimization
- 🖥️ Desktop layouts
- 🔄 Flexible grid systems
- 📐 Adaptive navigation

### Color Scheme
```css
Primary: #638ECB (Professional Blue)
Primary Dark: #395886 (Deep Blue)
Primary Light: #8AAEE0 (Light Blue)
Accent: #B1C9EF (Soft Blue)
Background Light: #F0F3FA (Very Light Blue)
Background Lighter: #D5DEEF (Light Blue-Gray)
```

### Typography
- Font Family: Inter (Modern, professional sans-serif)
- Fallback: -apple-system, BlinkMacSystemFont
- Weight Range: 300-800
- Optimized for readability

## 🔐 Security Features

✅ **Password Security**
- `PASSWORD_DEFAULT` hashing (bcrypt)
- Minimum 6 characters enforced
- Confirmation validation

✅ **SQL Injection Prevention**
- MySQLi prepared statements throughout
- All queries use parameter binding
- No direct string concatenation

✅ **XSS Protection**
- `htmlspecialchars()` on all output
- Only at display time (not in storage)

✅ **Input Sanitization**
- `sanitize()` function using `real_escape_string()` and `strip_tags()`
- Trim whitespace
- No double-encoding issues

✅ **Session Security**
- Secure session management
- Role-based access control
- Login requirement enforcement

✅ **Database Connection**
- MySQLi with proper error handling
- UTF-8 character set
- Result cleanup with `result->free()` to prevent sync errors

✅ **File Upload Security**
- Extension whitelist (jpg, jpeg, png, gif)
- Unique filename generation
- Upload directory isolation

## 🧪 Testing Scenarios

### Registration Flow
1. Register as mentor with profile completion
2. Register as mentee with interests
3. Test validation errors (password mismatch, email duplicate)
4. Test role card selection

### Mentor Workflow
1. **New Mentor**:
   - Complete profile in mentor-profile.php
   - Upload profile image
   - Select multiple categories
   - Check "pending" status banner
   - Wait for admin approval

2. **Approved Mentor**:
   - Redirected to mentor-dashboard.php (sessions hub)
   - Add availability slots in manage-availability.php
   - View session statistics
   - Mark sessions as completed
   - View mentee feedback

3. **Profile Editing**:
   - Click "Profile Editing" button
   - Edit profile in mentor-profile.php
   - Status changes to "pending" (re-approval)
   - Sessions continue uninterrupted
   - Shows in admin "Profile Updates" tab

4. **Availability Management**:
   - Add time slots by day/time
   - View four states: Available/Booked/Waiting/Disabled
   - Cannot disable booked slots
   - Cannot delete slots awaiting feedback

### Mentee Workflow
1. Browse categories
2. Search mentors by name/skills
3. View mentor details with reviews
4. Check available time slots
5. Book session
6. Process payment (with 20% platform fee)
7. View session in my-sessions.php
8. Leave feedback after completion

### Admin Workflow
1. **New Applications Tab**:
   - View first-time mentor applications
   - See full profile details
   - Approve or reject
   - Creates availability slots on approval

2. **Profile Updates Tab**:
   - View existing mentors with changes
   - See "UPDATE REQUEST" badge
   - View session count
   - Re-approve or reject changes
   - Mentors keep existing sessions

3. **Statistics**:
   - Monitor platform metrics
   - Track revenue (20% of paid sessions)
   - View top mentors
   - Review recent sessions

## 🔧 Recent Updates & Features

### Version 2.0 - Major Workflow Overhaul
✅ Separated mentor dashboards (profile editing vs sessions)
✅ Renamed files for clarity:
   - `mentor-home.php` → `mentor-dashboard.php` (sessions hub)
   - `mentor-dashboard.php` → `mentor-profile.php` (editing)
✅ Smart routing in dashboard.php based on approval status
✅ Two-tier admin approval system
✅ Four-state availability system
✅ MySQLi query optimization with result cleanup
✅ Fixed comma encoding issues
✅ Professional register page UI
✅ Removed development/debug utilities

### Platform Fee System
- Mentee pays: Mentor Rate × 1.20
- Mentor earns: Mentee Payment ÷ 1.20
- Platform earns: 20% of all paid sessions
- Transparent calculation displayed to both parties

### Availability Intelligence
- Database queries detect actual bookings vs manual disables
- LEFT JOIN with sessions and feedback tables
- Prevents mentor from disabling booked slots
- Shows "Waiting for Feedback" for completed sessions without reviews
- Real-time availability checking

## 🐛 Bug Fixes

✅ **MySQLi "Commands out of sync" errors**
- Added `result->free()` calls after all `get_result()` operations
- Proper query cleanup before next query

✅ **Comma encoding in database**
- Removed `htmlspecialchars()` from sanitize() function
- Only escape at display time, not storage

✅ **Availability status confusion**
- Enhanced queries to distinguish booked vs disabled
- Four-state system with clear visual indicators

✅ **Profile editing interrupting sessions**
- Re-approval system keeps mentor active during review
- Sessions continue without interruption

## 🔮 Future Enhancements
## 🔮 Future Enhancements

- [ ] Real-time chat between mentor and mentee
- [ ] Email notifications (booking confirmations, reminders)
- [ ] Calendar integration (Google Calendar, iCal)
- [ ] Video call integration (Zoom, Google Meet)
- [ ] Advanced search filters (rating, price range, availability)
- [ ] Session rescheduling functionality
- [ ] Refund system for cancelled sessions
- [ ] Multi-language support
- [ ] Mobile app (React Native)
- [ ] Mentor portfolio/achievement badges
- [ ] Recurring session bookings
- [ ] Group mentorship sessions
- [ ] Mentor certification system
- [ ] Analytics dashboard for mentors
- [ ] Payment gateway integration (Stripe/PayPal)

## 📊 Project Statistics

- **Total Files**: 15+ PHP files
- **Database Tables**: 8 main tables
- **User Roles**: 3 (Admin, Mentor, Mentee)
- **Availability States**: 4 (Available, Booked, Waiting, Disabled)
- **Color Scheme**: Professional Blue (#638ECB)
- **Framework**: Vanilla PHP with MySQLi
- **Authentication**: Session-based with role management

## 🤝 Contributing

This is a complete mentorship platform ready for deployment. To customize:

1. **Add Categories**: Insert into `categories` table
2. **Modify Colors**: Update CSS variables in each file
3. **Add Features**: Follow existing code patterns
4. **Payment Integration**: Replace `payment.php` logic
5. **Email System**: Add SMTP configuration

## 📝 License

This project is open source and available for educational and commercial use.

## 🙏 Acknowledgments

- Built with modern PHP best practices
- MySQLi for secure database operations
- Inter font family for professional typography
- Responsive design for all device sizes
- Security-first approach throughout

## 📞 Support

For issues or questions:
1. Check the database schema in `database.sql`
2. Verify MySQLi extension is enabled
3. Ensure `uploads/` folder has write permissions
4. Check PHP error logs for detailed error messages

## 🎯 Key Differentiators

✅ **Separated Workflows**: Clear distinction between profile editing and session management
✅ **Smart Approval System**: Re-approval doesn't interrupt active sessions
✅ **Intelligent Availability**: System knows difference between booked and disabled slots
✅ **Platform Fee Integration**: Transparent 20% fee calculation
✅ **Professional Design**: Consistent blue color scheme throughout
✅ **Security Focused**: MySQLi prepared statements, proper sanitization
✅ **User-Friendly**: Clear navigation, status indicators, helpful messages

---

**MentorBridge** - Connecting knowledge seekers with expert mentors 🎓✨
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

