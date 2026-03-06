# QuickHire - Quick Start Guide

## 🚀 Getting Started

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- Modern web browser with WebRTC support

### Installation Steps

#### 1. Database Setup

**Option A: Using Windows (Recommended for Windows users)**
```bash
cd c:\xampp\htdocs\QuickHire
setup_db.bat
```

**Option B: Using Linux/Mac**
```bash
cd /path/to/QuickHire
chmod +x setup_db.sh
./setup_db.sh
```

**Option C: Manual Setup**
1. Open phpMyAdmin (http://localhost/phpmyadmin)
2. Create a new database named `quick_hire`
3. Import the `database_schema.sql` file
4. Verify all tables are created

#### 2. Verify Configuration

Check `Config/config.php`:
```php
'db' => [
    'host' => 'localhost',
    'name' => 'quick_hire',
    'user' => 'root',
    'pass' => '',
    'charset' => 'utf8mb4'
]
```

#### 3. Start the Application

1. Start Apache and MySQL (via XAMPP Control Panel)
2. Navigate to: `http://localhost/QuickHire/Public/index.php`
3. You should see the QuickHire landing page

---

## 📋 User Workflows

### Employer Workflow

#### Step 1: Register
1. Click "Register" on the landing page
2. Select "Employer" as your role
3. Enter your details (first name, last name, email, password)
4. Click "Register"

#### Step 2: Complete Profile
1. You'll be redirected to the profile completion page
2. Fill in:
   - **Company Name** (required)
   - **Country** (required)
   - **Profile Picture** (optional)
3. Click "Save Profile"

#### Step 3: Find Jobseekers
1. You'll be redirected to the Employer Dashboard
2. Click "Find Jobseeker" button
3. Fill in the matching criteria:
   - **Role Title**: e.g., "Web Developer", "Data Analyst"
   - **Country**: e.g., "Philippines", "USA"
   - **Employment Type**: Select from dropdown
   - **Required Skills**: Check the skills you need
4. Click "Find Match"

#### Step 4: Video Interview
1. Once a match is found, you'll be connected to a jobseeker
2. The video call interface will open automatically
3. Use the controls to:
   - Toggle camera (📹)
   - Toggle microphone (🎤)
   - End call (red button)
4. After the call, you'll return to the dashboard

#### Step 5: View History
- All calls are logged in the "Recent Calls" section
- View jobseeker details, role, country, and call status

---

### Jobseeker Workflow

#### Step 1: Register
1. Click "Register" on the landing page
2. Select "Jobseeker" as your role
3. Enter your details (first name, last name, email, password)
4. Click "Register"

#### Step 2: Complete Profile
1. You'll be redirected to the profile completion page
2. Fill in all required fields:
   - **Desired Job Role** (required)
   - **Rate per Hour** (required)
   - **Available Hours per Day** (required)
   - **Country** (required)
   - **English Mastery** (required)
   - **Profile Description** (required)
3. Optional fields:
   - Profile picture
   - Bachelor's degree
   - Portfolio/Website
   - Age
   - Gender
   - Resume (PDF)
4. Click "Save Profile"

#### Step 3: Add Skills
1. Go to your dashboard
2. Click "Update Skills"
3. Select skills that match your expertise
4. Save your skills

#### Step 4: Find Employers
1. On your dashboard, click "Find Employer"
2. The system will automatically search for matching employers
3. Wait for a match (you'll see a spinner)

#### Step 5: Accept Interview
1. When an employer finds a match, you'll receive a notification
2. Click "Join now" to accept the call
3. The video call interface will open
4. Use the controls to manage your camera and microphone
5. After the call, you'll return to your dashboard

---

## 🎯 Matching Algorithm Explained

The system uses an intelligent matching algorithm that scores candidates on multiple factors:

### Scoring Breakdown

| Factor | Points | How It Works |
|--------|--------|-------------|
| **Country Match** | 30 | Exact country match = 30 points |
| **Role Title Match** | 25 | Exact match = 25, Partial match = 15 |
| **English Proficiency** | 20 | Native/Fluent = 20, Advanced = 15, Intermediate = 10, Beginner = 5 |
| **Skills Match** | 25 | (Matched Skills / Required Skills) × 25 |
| **Minimum Score** | **80** | Required to initiate a call |

### Example Match

**Employer Looking For:**
- Role: Web Developer
- Country: Philippines
- Skills: JavaScript, React, Node.js
- Employment: Full-time

**Jobseeker Profile:**
- Role: Full Stack Developer
- Country: Philippines
- Skills: JavaScript, React, Python
- English: Fluent
- Available: 40 hours/week

**Score Calculation:**
```
Country Match:    30 (Philippines = Philippines) ✓
Role Match:       15 (Partial: "Developer" in both) ✓
English:          20 (Fluent) ✓
Skills:           16.67 (2/3 skills = 66.67% × 25) ✓
─────────────────────────────────
Total Score:      81.67/100 ✅ MATCH FOUND!
```

---

## 🎥 Video Call Features

### During a Call

**Camera Control**
- Click "📹 Camera: ON" to toggle your camera
- Button will show "OFF" when disabled

**Microphone Control**
- Click "🎤 Mic: ON" to toggle your microphone
- Button will show "OFF" when disabled

**End Call**
- Click the red "End Call" button to disconnect
- You'll be returned to your dashboard

**Call Timer**
- Shows elapsed time in MM:SS format
- Helps track interview duration

**Debug Log**
- Shows connection status and events
- Useful for troubleshooting

---

## 🔧 Troubleshooting

### "No Match Found" Error

**Possible Causes:**
1. No jobseekers have completed their profiles
2. Jobseekers don't have the required skills
3. Matching score is below 80
4. All available jobseekers are already in calls

**Solutions:**
- Try adjusting the required skills
- Change the role title to be more flexible
- Wait a few minutes and try again
- Ensure jobseekers have added skills to their profiles

### Video Call Won't Connect

**Possible Causes:**
1. Camera/microphone permissions not granted
2. Browser doesn't support WebRTC
3. Network connectivity issues
4. STUN server unreachable

**Solutions:**
- Check browser permissions for camera/microphone
- Use a modern browser (Chrome, Firefox, Edge, Safari)
- Check your internet connection
- Try refreshing the page
- Check browser console for errors (F12)

### Database Connection Error

**Possible Causes:**
1. MySQL is not running
2. Database credentials are incorrect
3. Database doesn't exist

**Solutions:**
- Start MySQL service (XAMPP Control Panel)
- Verify credentials in `Config/config.php`
- Run `setup_db.bat` (Windows) or `setup_db.sh` (Linux/Mac)
- Check MySQL error logs

### Profile Won't Save

**Possible Causes:**
1. Required fields are empty
2. File upload failed
3. Database error

**Solutions:**
- Fill in all required fields (marked with *)
- Check file size (max 5MB for images, 10MB for PDFs)
- Check file permissions in `Public/uploads/`
- Check browser console for errors

---

## 📱 Browser Compatibility

| Browser | Version | WebRTC Support |
|---------|---------|---|
| Chrome | 60+ | ✅ Full Support |
| Firefox | 55+ | ✅ Full Support |
| Safari | 11+ | ✅ Full Support |
| Edge | 79+ | ✅ Full Support |
| Opera | 47+ | ✅ Full Support |
| IE 11 | Any | ❌ Not Supported |

---

## 🔐 Security Notes

- All passwords are hashed using PHP's `password_hash()`
- CSRF tokens protect all forms
- SQL prepared statements prevent injection
- Session-based authentication
- Role-based access control

---

## 📞 Support

For issues or questions:

1. **Check the logs**: Browser console (F12) and PHP error logs
2. **Verify database**: Use phpMyAdmin to check tables
3. **Test connectivity**: Ensure MySQL and Apache are running
4. **Review configuration**: Check `Config/config.php`

---

## 🎓 Learning Resources

- [WebRTC Documentation](https://developer.mozilla.org/en-US/docs/Web/API/WebRTC_API)
- [PHP PDO](https://www.php.net/manual/en/book.pdo.php)
- [MySQL Documentation](https://dev.mysql.com/doc/)

---

## 📝 File Structure

```
QuickHire/
├── Public/
│   ├── index.php                    Landing page
│   ├── employer-dashboard.php       Employer dashboard
│   ├── jobseeker-dashboard.php      Jobseeker dashboard
│   ├── complete-profile.php         Profile setup
│   ├── call.php                     Video call interface
│   ├── find-employer.php            Jobseeker search
│   ├── actions/
│   │   ├── login.php                Login handler
│   │   ├── register.php             Registration handler
│   │   ├── logout.php               Logout handler
│   │   ├── find_match.php           Employer search handler
│   │   ├── find_employer.php        Jobseeker search handler
│   │   ├── signal_send.php          WebRTC signal sender
│   │   ├── signal_poll.php          WebRTC signal receiver
│   │   └── save_profile.php         Profile save handler
│   └── assets/
│       └── css/
│           └── landingPage.css      Styling
├── src/
│   ├── Core/
│   │   ├── Auth.php                 Authentication
│   │   ├── Database.php             Database connection
│   │   ├── Session.php              Session management
│   │   └── Csrf.php                 CSRF protection
│   ├── Models/
│   │   └── MatchEngine.php          Matching algorithm
│   └── Services/
│       ├── AuthService.php          Auth service
│       ├── ProfileService.php       Profile management
│       ├── FileUpload.php           File upload handling
│       └── MatchmakingService.php   Matchmaking service
├── Config/
│   └── config.php                   Configuration
├── database_schema.sql              Database schema
├── setup_db.bat                     Windows setup script
├── setup_db.sh                      Linux/Mac setup script
└── EMPLOYER_DASHBOARD_SETUP.md      Detailed setup guide
```

---

## 🎉 You're Ready!

Your QuickHire platform is now set up and ready to use. Start by:

1. ✅ Running the database setup script
2. ✅ Registering as an employer or jobseeker
3. ✅ Completing your profile
4. ✅ Finding matches and conducting interviews

Good luck! 🚀

---

**Version**: 1.0  
**Last Updated**: 2024  
**Status**: Production Ready
