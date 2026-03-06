# QuickHire Employer Dashboard - Setup Guide

## Overview

The QuickHire Employer Dashboard has been successfully created with the following features:

### ✅ Features Implemented

1. **Employer Dashboard** (`employer-dashboard.php`)
   - Professional dashboard interface for employers
   - View company profile and status
   - Access to call history with jobseekers
   - Quick access to find jobseekers

2. **Skill-Based Matching System**
   - Employers can specify required skills when searching for jobseekers
   - Matching algorithm scores candidates based on:
     - **Skills Match (25 points)**: Percentage of required skills the jobseeker has
     - **Role Title Match (25 points)**: Exact or partial match with jobseeker's role
     - **Country Match (30 points)**: Geographic location match
     - **English Proficiency (20 points)**: English mastery level
     - **Availability Bonus**: Extra consideration for available hours
   - Minimum match score: 80/100 to initiate a call

3. **Call/Interview System**
   - Real-time WebRTC video calls between employers and jobseekers
   - Call history tracking with status (RINGING, IN_CALL, COMPLETED, MISSED, REJECTED)
   - Call duration tracking
   - Automatic call room creation when a match is found

4. **Employer Profile Management**
   - Company name and country
   - Profile picture upload
   - Profile completion tracking

## Database Setup

### Step 1: Import Database Schema

1. Open phpMyAdmin or your MySQL client
2. Create a new database named `quick_hire` (or use your configured database name)
3. Import the `database_schema.sql` file:
   ```sql
   -- Copy the entire contents of database_schema.sql and execute it
   ```

Alternatively, using MySQL command line:
```bash
mysql -u root -p quick_hire < database_schema.sql
```

### Step 2: Verify Database Configuration

Check that `Config/config.php` has the correct database credentials:
```php
'db' => [
    'host' => 'localhost',
    'name' => 'quick_hire',
    'user' => 'root',
    'pass' => '',
    'charset' => 'utf8mb4'
]
```

## File Structure

```
QuickHire/
├── Public/
│   ├── employer-dashboard.php          [NEW] Main employer dashboard
│   ├── jobseeker-dashboard.php         Jobseeker dashboard
│   ├── find-employer.php               Jobseeker finds employers
│   ├── call.php                        Video call interface
│   ├── complete-profile.php            Profile completion
│   ├── actions/
│   │   ├── find_match.php              [UPDATED] Employer finds jobseekers
│   │   ├── find_employer.php           Jobseeker finds employers
│   │   ├── signal_send.php             WebRTC signal handling
│   │   ├── signal_poll.php             WebRTC signal polling
│   │   ├── login.php                   [UPDATED] Redirects to employer dashboard
│   │   ├── register.php                User registration
│   │   └── logout.php                  User logout
│   └── assets/
│       └── css/
│           └── landingPage.css         Styling
├── src/
│   ├── Core/
│   │   ├── Auth.php                    Authentication
│   │   ├── Database.php                Database connection
│   │   ├── Session.php                 Session management
│   │   └── Csrf.php                    CSRF protection
│   ├── Models/
│   │   └── MatchEngine.php             Matching algorithm
│   └── Services/
│       ├── AuthService.php             Auth service
│       ├── ProfileService.php          Profile management
│       ├── FileUpload.php              File upload handling
│       └── MatchmakingService.php      Matchmaking service
├── Config/
│   └── config.php                      Database configuration
└── database_schema.sql                 [NEW] Database schema
```

## How to Use

### For Employers

1. **Register/Login**
   - Go to the landing page and register as an "Employer"
   - Complete your company profile (company name, country, profile picture)

2. **Find Jobseekers**
   - Click "Find Jobseeker" button on the dashboard
   - Fill in the matching criteria:
     - **Role Title**: The position you're looking for (e.g., "Web Developer")
     - **Country**: Geographic location preference
     - **Employment Type**: Part-time, Full-time, Contract, or Freelance
     - **Required Skills**: Select skills the jobseeker should have
   - Click "Find Match" to initiate the search

3. **Video Call**
   - Once a match is found, you'll be automatically connected to a jobseeker
   - Use the video call interface to conduct interviews
   - Control camera and microphone during the call
   - End the call when finished

4. **View Call History**
   - All calls are logged in the "Recent Calls" section
   - View jobseeker details, role, country, and call status

### For Jobseekers

1. **Register/Login**
   - Go to the landing page and register as a "Jobseeker"
   - Complete your profile with:
     - Role title
     - Available hours per week
     - Hourly rate
     - Country
     - English proficiency level
     - Profile description
     - Resume upload

2. **Add Skills**
   - Update your skills profile to match employer requirements
   - Select skills from the available list

3. **Find Employers**
   - Click "Find Employer" on your dashboard
   - The system will automatically match you with employers looking for your skills
   - Accept incoming calls from employers

## Matching Algorithm Details

The matching system uses a scoring algorithm that evaluates:

### Scoring Breakdown (Total: 100 points)

| Factor | Points | Criteria |
|--------|--------|----------|
| Country Match | 30 | Exact country match |
| Role Title Match | 25 | Exact (25) or partial (15) match |
| English Proficiency | 20 | Native/Fluent (20), Advanced (15), Intermediate (10), Beginner (5) |
| Skills Match | 25 | Percentage of required skills matched |
| **Minimum Score** | **80** | Required to initiate a call |

### Example Matching Scenario

**Employer Requirements:**
- Role: Web Developer
- Country: Philippines
- Skills: JavaScript, React, Node.js
- Employment Type: Full-time

**Jobseeker Profile:**
- Role: Full Stack Developer
- Country: Philippines
- Skills: JavaScript, React, Python
- English: Fluent
- Available: 40 hours/week

**Score Calculation:**
- Country Match: 30 (Philippines = Philippines) ✓
- Role Match: 15 (partial match: "Developer" in both) ✓
- English: 20 (Fluent) ✓
- Skills: 16.67 (2 out of 3 skills match = 66.67% × 25) ✓
- **Total: 81.67/100** ✅ Match Found!

## API Endpoints

### Employer Endpoints

- `POST /QuickHire/Public/actions/find_match.php`
  - Initiates jobseeker search with matching criteria
  - Parameters: `role_title`, `country`, `employment_type`, `skill_ids[]`
  - Returns: Redirect to call room or error message

### Jobseeker Endpoints

- `GET /QuickHire/Public/actions/find_employer.php`
  - Searches for available employer matches
  - Returns: JSON with room code or error message

### Shared Endpoints

- `POST /QuickHire/Public/actions/signal_send.php`
  - Sends WebRTC signals (offer, answer, candidate, leave)
  - Parameters: `room`, `type`, `payload`

- `GET /QuickHire/Public/actions/signal_poll.php`
  - Polls for incoming WebRTC signals
  - Parameters: `room`, `after` (signal ID)
  - Returns: JSON with new signals

## Troubleshooting

### No Matches Found
- Ensure jobseekers have completed their profiles
- Check that jobseekers have added skills
- Verify that the matching score is >= 80
- Try adjusting the required skills or role title

### Call Connection Issues
- Ensure both users have allowed camera/microphone access
- Check browser console for WebRTC errors
- Verify STUN server is accessible (using Google's free STUN server)
- Check that both users are in the same room code

### Database Errors
- Verify database credentials in `Config/config.php`
- Ensure all tables are created using `database_schema.sql`
- Check MySQL is running and accessible

## Security Features

- CSRF token protection on all forms
- Password hashing using PHP's password_hash()
- SQL prepared statements to prevent injection
- Session-based authentication
- Role-based access control (EMPLOYER/JOBSEEKER)

## Performance Optimization

- Database indexes on frequently queried columns
- Efficient skill matching algorithm
- WebRTC signal polling with 700ms interval
- Call history pagination (limited to 10 recent calls)

## Future Enhancements

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

## Support

For issues or questions, please check:
1. Database schema is properly imported
2. All required tables exist
3. File permissions are correct
4. PHP version is 7.4 or higher
5. MySQL version is 5.7 or higher

---

**Version**: 1.0  
**Last Updated**: 2024  
**Status**: Production Ready
