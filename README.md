# QuickHire - Employer Dashboard & Skill-Based Matching System

## 🎯 Project Overview

QuickHire is a modern web platform that connects employers with qualified jobseekers through intelligent skill-based matching and real-time video interviews. The system uses WebRTC technology to enable peer-to-peer video calls between employers and candidates.

### Key Features
✅ **Employer Dashboard** - Professional interface for managing job searches  
✅ **Skill-Based Matching** - Intelligent algorithm matching candidates to requirements  
✅ **Real-time Video Calls** - WebRTC-powered interviews  
✅ **Call History** - Track all interviews and interactions  
✅ **Profile Management** - Complete profile setup for both roles  
✅ **Security** - CSRF protection, password hashing, SQL injection prevention  

---

## 🚀 Quick Start

### 1. Database Setup (Choose One)

**Windows Users:**
```bash
cd c:\xampp\htdocs\QuickHire
setup_db.bat
```

**Linux/Mac Users:**
```bash
cd /path/to/QuickHire
chmod +x setup_db.sh
./setup_db.sh
```

**Manual Setup:**
1. Open phpMyAdmin
2. Create database `quick_hire`
3. Import `database_schema.sql`

### 2. Start Application
1. Start Apache and MySQL (XAMPP Control Panel)
2. Navigate to: `http://localhost/QuickHire/Public/index.php`

### 3. Test the System
1. Register as Employer
2. Register as Jobseeker
3. Complete profiles
4. Add skills (jobseeker)
5. Find matches
6. Conduct video interview

---

## 📚 Documentation

### For Users
- **[QUICK_START.md](QUICK_START.md)** - Step-by-step user guide
  - Registration and profile setup
  - Finding matches
  - Conducting interviews
  - Troubleshooting

### For Administrators
- **[EMPLOYER_DASHBOARD_SETUP.md](EMPLOYER_DASHBOARD_SETUP.md)** - Setup and configuration
  - Database setup
  - Feature overview
  - Matching algorithm details
  - Troubleshooting guide

### For Developers
- **[TECHNICAL_DOCUMENTATION.md](TECHNICAL_DOCUMENTATION.md)** - Technical reference
  - Architecture overview
  - Database schema
  - API endpoints
  - Code structure
  - Security implementation

### Project Information
- **[IMPLEMENTATION_SUMMARY.md](IMPLEMENTATION_SUMMARY.md)** - What was implemented
  - Features overview
  - Files created/modified
  - Matching algorithm
  - Deployment checklist

- **[VERIFICATION_CHECKLIST.md](VERIFICATION_CHECKLIST.md)** - Testing checklist
  - Pre-deployment verification
  - Functional testing
  - Security testing
  - Performance testing

---

## 🎯 How It Works

### For Employers

```
1. Register as Employer
   ↓
2. Complete Company Profile
   ↓
3. Click "Find Jobseeker"
   ↓
4. Specify Requirements:
   - Role Title
   - Country
   - Employment Type
   - Required Skills
   ↓
5. System Finds Best Match (Score ≥ 80)
   ↓
6. Video Interview Starts
   ↓
7. Call Logged to History
```

### For Jobseekers

```
1. Register as Jobseeker
   ↓
2. Complete Profile
   ↓
3. Add Skills
   ↓
4. Click "Find Employer"
   ↓
5. System Searches for Matches
   ↓
6. Receive Interview Invitation
   ↓
7. Accept and Join Video Call
   ↓
8. Interview Recorded in History
```

---

## 🧮 Matching Algorithm

The system scores candidates on 4 factors (Total: 100 points):

| Factor | Points | Details |
|--------|--------|---------|
| **Country Match** | 30 | Exact geographic match |
| **Role Title Match** | 25 | Exact (25) or partial (15) match |
| **English Proficiency** | 20 | Native/Fluent (20), Advanced (15), Intermediate (10), Beginner (5) |
| **Skills Match** | 25 | Percentage of required skills matched |

**Minimum Score Required: 80/100**

### Example
```
Employer wants: Web Developer in Philippines with JavaScript, React, Node.js
Jobseeker is: Full Stack Developer in Philippines with JavaScript, React, Python

Score:
- Country: 30 (Philippines = Philippines) ✓
- Role: 15 (Partial match: "Developer") ✓
- English: 20 (Fluent) ✓
- Skills: 16.67 (2/3 skills = 66.67% × 25) ✓
─────────────────────────────────────
Total: 81.67/100 ✅ MATCH FOUND!
```

---

## 🎥 Video Call Features

- **Real-time Video/Audio** - WebRTC peer-to-peer
- **Camera Control** - Toggle on/off
- **Microphone Control** - Toggle on/off
- **Call Timer** - Track interview duration
- **Debug Log** - Connection status
- **Graceful Termination** - Proper cleanup

---

## 📁 Project Structure

```
QuickHire/
├── Public/
│   ├── index.php                    Landing page
│   ├── employer-dashboard.php       ⭐ NEW - Employer dashboard
│   ├── jobseeker-dashboard.php      Jobseeker dashboard
│   ├── complete-profile.php         Profile setup
│   ├── call.php                     Video call interface
│   ├── find-employer.php            Jobseeker search
│   ├── actions/
│   │   ├── login.php                Login handler
│   │   ├── register.php             Registration handler
│   │   ├── logout.php               Logout handler
│   │   ├── find_match.php           ⭐ UPDATED - Employer search
│   │   ├── find_employer.php        Jobseeker search
│   │   ├── signal_send.php          WebRTC signals
│   │   ├── signal_poll.php          WebRTC polling
│   │   └── save_profile.php         Profile save
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
│       ├── FileUpload.php           File uploads
│       └── MatchmakingService.php   Matchmaking service
├── Config/
│   └── config.php                   Database config
├── database_schema.sql              ⭐ NEW - Database schema
├── setup_db.bat                     ⭐ NEW - Windows setup
├── setup_db.sh                      ⭐ NEW - Linux/Mac setup
├── QUICK_START.md                   ⭐ NEW - User guide
├── EMPLOYER_DASHBOARD_SETUP.md      ⭐ NEW - Setup guide
├─��� TECHNICAL_DOCUMENTATION.md       ⭐ NEW - Developer guide
├── IMPLEMENTATION_SUMMARY.md        ⭐ NEW - Summary
├── VERIFICATION_CHECKLIST.md        ⭐ NEW - Testing checklist
└── README.md                        ⭐ NEW - This file
```

---

## 🔐 Security Features

✅ **CSRF Protection** - All forms protected with tokens  
✅ **Password Hashing** - Using PHP's password_hash()  
✅ **SQL Injection Prevention** - Prepared statements  
✅ **Session Security** - Session-based authentication  
✅ **Role-Based Access** - EMPLOYER/JOBSEEKER roles  
✅ **Input Validation** - All inputs validated  
✅ **File Upload Security** - Type and size validation  

---

## 🗄️ Database

### 9 Tables
1. **users** - User accounts
2. **jobseeker_profiles** - Jobseeker info
3. **employer_profiles** - Employer info
4. **skills** - Available skills (50+)
5. **jobseeker_skills** - Jobseeker skills
6. **matchmaking_queue** - Active searches
7. **matchmaking_queue_skills** - Required skills
8. **calls** - Call history
9. **webrtc_signals** - WebRTC messages

### Sample Skills Included
JavaScript, Python, PHP, Java, C++, React, Vue.js, Angular, Node.js, Laravel, Django, MySQL, MongoDB, PostgreSQL, AWS, Google Cloud, Azure, Docker, Kubernetes, Git, REST API, GraphQL, HTML, CSS, TypeScript, SQL, Linux, Windows, UI/UX Design, Graphic Design, Project Management, Agile, Scrum, Communication, Leadership, Problem Solving, Data Analysis, Machine Learning, AI, Mobile Development, iOS, Android, Web Development, Full Stack, QA Testing, Automation Testing, Manual Testing

---

## 🌐 Browser Support

| Browser | Version | Support |
|---------|---------|---------|
| Chrome | 60+ | ✅ Full |
| Firefox | 55+ | ✅ Full |
| Safari | 11+ | ✅ Full |
| Edge | 79+ | ✅ Full |
| Opera | 47+ | ✅ Full |
| IE 11 | Any | ❌ Not Supported |

---

## 📋 Requirements

- **PHP**: 7.4 or higher
- **MySQL**: 5.7 or higher
- **Apache/Nginx**: Any recent version
- **Browser**: Modern browser with WebRTC support

---

## 🔧 Configuration

Edit `Config/config.php`:
```php
'db' => [
    'host' => 'localhost',      // MySQL host
    'name' => 'quick_hire',     // Database name
    'user' => 'root',           // MySQL user
    'pass' => '',               // MySQL password
    'charset' => 'utf8mb4'      // Character set
]
```

---

## 🚨 Troubleshooting

### Database Connection Failed
- Ensure MySQL is running
- Check credentials in Config/config.php
- Verify database exists

### No Matches Found
- Ensure jobseekers have completed profiles
- Check jobseekers have added skills
- Verify matching score >= 80

### Video Call Won't Connect
- Check camera/microphone permissions
- Use modern browser
- Check internet connection
- Verify STUN server accessible

### File Upload Failed
- Check upload directory permissions
- Verify file size limits
- Check disk space

See **[QUICK_START.md](QUICK_START.md)** for more troubleshooting.

---

## 📞 Support

1. **User Issues**: See [QUICK_START.md](QUICK_START.md)
2. **Setup Issues**: See [EMPLOYER_DASHBOARD_SETUP.md](EMPLOYER_DASHBOARD_SETUP.md)
3. **Technical Issues**: See [TECHNICAL_DOCUMENTATION.md](TECHNICAL_DOCUMENTATION.md)
4. **Testing**: See [VERIFICATION_CHECKLIST.md](VERIFICATION_CHECKLIST.md)

---

## 🎓 Learning Resources

- [WebRTC API](https://developer.mozilla.org/en-US/docs/Web/API/WebRTC_API)
- [PHP PDO](https://www.php.net/manual/en/book.pdo.php)
- [MySQL Documentation](https://dev.mysql.com/doc/)
- [OWASP Security](https://owasp.org/)

---

## 📈 Future Enhancements

- [ ] Call recording and playback
- [ ] Skill endorsements from employers
- [ ] Rating and review system
- [ ] Advanced filtering options
- [ ] Scheduled interviews
- [ ] Payment integration
- [ ] Mobile app
- [ ] Real-time notifications
- [ ] Chat messaging system
- [ ] Analytics dashboard

---

## 📝 Version Information

- **Version**: 1.0
- **Release Date**: 2024
- **Status**: Production Ready
- **License**: [Your License]

---

## ✅ Implementation Checklist

- ✅ Employer dashboard created
- ✅ Skill-based matching implemented
- ✅ Video call system working
- ✅ Call history tracking
- ✅ Database schema complete
- ✅ Security features implemented
- ✅ Documentation comprehensive
- ✅ Setup scripts provided
- ✅ Testing checklist included
- ✅ Ready for production

---

## 🎉 Getting Started

1. **Read**: [QUICK_START.md](QUICK_START.md)
2. **Setup**: Run `setup_db.bat` or `setup_db.sh`
3. **Configure**: Update `Config/config.php` if needed
4. **Test**: Follow the test workflow
5. **Deploy**: Use [VERIFICATION_CHECKLIST.md](VERIFICATION_CHECKLIST.md)

---

## 📧 Contact & Support

For questions or issues:
1. Check the relevant documentation file
2. Review the troubleshooting section
3. Check browser console for errors (F12)
4. Review PHP error logs

---

**QuickHire - Connecting Employers with Talent Through Intelligent Matching** 🚀

---

**Last Updated**: 2024  
**Status**: Production Ready  
**Maintained By**: [Your Team]
