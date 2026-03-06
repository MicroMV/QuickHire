# QuickHire - Technical Documentation

## Architecture Overview

QuickHire is a PHP-based web application that enables real-time video interviews between employers and jobseekers using WebRTC technology. The system uses skill-based matching to connect the right candidates with the right opportunities.

### Technology Stack

- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Real-time Communication**: WebRTC (peer-to-peer)
- **Signaling**: HTTP polling
- **Authentication**: Session-based with password hashing

---

## System Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     Web Browser (Client)                     │
│  ┌──────────────────────────────────────────────────────┐   │
│  │  HTML/CSS/JavaScript                                 │   │
│  │  - Employer Dashboard                                │   │
│  │  - Jobseeker Dashboard                               │   │
│  │  - Video Call Interface                              │   │
│  │  - WebRTC Peer Connection                            │   │
│  └──────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
                            ↕ HTTP/HTTPS
┌─────────────────────────────────────────────────────────────┐
│                    Web Server (Apache/Nginx)                 │
│  ┌──────────────────────────────────────────────────────┐   │
│  │  PHP Application                                      │   │
│  │  ┌────────────────────────────────────────────────┐  │   │
│  │  │ Public/                                        │  │   │
│  │  │ - index.php (Landing)                          │  │   │
│  │  │ - employer-dashboard.php                       │  │   │
│  │  │ - jobseeker-dashboard.php                      │  │   │
│  │  │ - call.php (Video Interface)                   │  │   │
│  │  │ - actions/ (Request Handlers)                  │  │   │
│  │  └────────────────────────────────────────────────┘  │   │
│  │  ┌────────────────────────────────────────────────┐  │   │
│  │  │ src/                                           │  │   │
│  │  │ - Core/ (Auth, Database, Session, CSRF)       │  │   │
│  │  │ - Models/ (MatchEngine)                        │  │   │
│  │  │ - Services/ (Business Logic)                   │  │   │
│  │  └────────────────────────────────────────────────┘  │   │
│  └──────────────────────────────────────────────────────┘   │
���─────────────────────────────────────────────────────────────┘
                            ↕ MySQL Protocol
┌─────────────────────────────────────────────────────────────┐
│                    MySQL Database                            │
│  - users                                                     │
│  - jobseeker_profiles                                        │
│  - employer_profiles                                         │
│  - skills                                                    │
│  - jobseeker_skills                                          │
│  - matchmaking_queue                                         │
│  - matchmaking_queue_skills                                  │
│  - calls                                                     │
│  - webrtc_signals                                            │
└─────────────────────────────────────────────────────────────┘
```

---

## Database Schema

### Users Table
```sql
CREATE TABLE users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  role ENUM('JOBSEEKER', 'EMPLOYER'),
  first_name VARCHAR(100),
  last_name VARCHAR(100),
  email VARCHAR(255) UNIQUE,
  password_hash VARCHAR(255),
  is_profile_complete TINYINT(1),
  created_at TIMESTAMP,
  updated_at TIMESTAMP
);
```

### Jobseeker Profiles Table
```sql
CREATE TABLE jobseeker_profiles (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT UNIQUE,
  profile_picture_url VARCHAR(255),
  role_title VARCHAR(100),
  available_time INT,
  rate_per_hour DECIMAL(10, 2),
  country VARCHAR(100),
  english_mastery VARCHAR(50),
  profile_description TEXT,
  resume_url VARCHAR(255),
  ...
  FOREIGN KEY (user_id) REFERENCES users(id)
);
```

### Employer Profiles Table
```sql
CREATE TABLE employer_profiles (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT UNIQUE,
  profile_picture_url VARCHAR(255),
  country VARCHAR(100),
  company_name VARCHAR(255),
  FOREIGN KEY (user_id) REFERENCES users(id)
);
```

### Skills Table
```sql
CREATE TABLE skills (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) UNIQUE,
  category VARCHAR(100)
);
```

### Jobseeker Skills Table
```sql
CREATE TABLE jobseeker_skills (
  id INT PRIMARY KEY AUTO_INCREMENT,
  jobseeker_user_id INT,
  skill_id INT,
  UNIQUE KEY (jobseeker_user_id, skill_id),
  FOREIGN KEY (jobseeker_user_id) REFERENCES users(id),
  FOREIGN KEY (skill_id) REFERENCES skills(id)
);
```

### Matchmaking Queue Table
```sql
CREATE TABLE matchmaking_queue (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT,
  role ENUM('EMPLOYER', 'JOBSEEKER'),
  wanted_role VARCHAR(100),
  wanted_country VARCHAR(100),
  employment_type VARCHAR(50),
  is_active TINYINT(1),
  created_at TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);
```

### Matchmaking Queue Skills Table
```sql
CREATE TABLE matchmaking_queue_skills (
  id INT PRIMARY KEY AUTO_INCREMENT,
  queue_id INT,
  skill_id INT,
  UNIQUE KEY (queue_id, skill_id),
  FOREIGN KEY (queue_id) REFERENCES matchmaking_queue(id),
  FOREIGN KEY (skill_id) REFERENCES skills(id)
);
```

### Calls Table
```sql
CREATE TABLE calls (
  id INT PRIMARY KEY AUTO_INCREMENT,
  room_code VARCHAR(50) UNIQUE,
  employer_user_id INT,
  jobseeker_user_id INT,
  status ENUM('RINGING', 'IN_CALL', 'COMPLETED', 'MISSED', 'REJECTED'),
  duration_seconds INT,
  created_at TIMESTAMP,
  FOREIGN KEY (employer_user_id) REFERENCES users(id),
  FOREIGN KEY (jobseeker_user_id) REFERENCES users(id)
);
```

### WebRTC Signals Table
```sql
CREATE TABLE webrtc_signals (
  id INT PRIMARY KEY AUTO_INCREMENT,
  room_code VARCHAR(50),
  sender_id INT,
  message_type VARCHAR(50),
  payload LONGTEXT,
  created_at TIMESTAMP,
  FOREIGN KEY (sender_id) REFERENCES users(id)
);
```

---

## Core Classes

### Auth.php
Handles authentication and authorization.

```php
class Auth {
  public static function requireLogin(): void
  public static function userId(): int
  public static function role(): string
  public static function isLoggedIn(): bool
}
```

### Database.php
Manages database connections.

```php
class Database {
  public function __construct(array $dbConfig)
  public function pdo(): PDO
}
```

### Session.php
Manages user sessions.

```php
class Session {
  public static function start(): void
  public static function set(string $key, mixed $value): void
  public static function get(string $key, mixed $default = null): mixed
  public static function flash(string $key, string $value = null): ?string
}
```

### Csrf.php
Provides CSRF token protection.

```php
class Csrf {
  public static function token(): string
  public static function verify(?string $token): bool
}
```

---

## Service Classes

### AuthService.php
Handles user registration and login.

```php
class AuthService {
  public function register(string $role, string $firstName, string $lastName, 
                          string $email, string $password): array
  public function login(string $email, string $password): array
  public function emailExists(string $email): bool
}
```

### ProfileService.php
Manages user profiles.

```php
class ProfileService {
  public function saveJobseeker(int $userId, array $data, array $files, ...): void
  public function saveEmployer(int $userId, array $data, array $files, ...): void
  public function getJobseeker(int $userId): array
  public function getEmployer(int $userId): array
}
```

### MatchmakingService.php
Handles skill-based matching.

```php
class MatchmakingService {
  public function enqueueEmployer(int $employerId, array $criteria, 
                                  array $skillIds): int
  public function matchEmployerNow(int $queueId, int $employerId): ?string
}
```

### FileUpload.php
Handles file uploads.

```php
class FileUpload {
  public function uploadAvatar(array $file, string $absPath, string $relPath): ?string
  public function uploadResume(array $file, string $absPath, string $relPath): ?string
}
```

---

## Models

### MatchEngine.php
Implements the matching algorithm.

```php
class MatchEngine {
  public function score(array $criteria, array $jobseeker, 
                       array $requiredSkillIds, array $jobseekerSkillIds): int
}
```

**Scoring Algorithm:**
- Country Match: 30 points (exact match)
- Role Title Match: 25 points (exact) or 15 points (partial)
- English Proficiency: 20 points (native/fluent), 15 (advanced), 10 (intermediate), 5 (beginner)
- Skills Match: 25 points (percentage of required skills matched)
- **Minimum Score: 80/100**

---

## Request Flow

### Employer Finding Jobseeker

```
1. Employer clicks "Find Jobseeker"
   ↓
2. Modal opens with matching criteria form
   ↓
3. Employer fills in:
   - Role Title
   - Country
   - Employment Type
   - Required Skills
   ↓
4. Form submitted to find_match.php
   ↓
5. find_match.php:
   - Validates input
   - Calls MatchmakingService::enqueueEmployer()
   - Creates matchmaking queue entry
   - Calls MatchmakingService::matchEmployerNow()
   ↓
6. matchEmployerNow():
   - Loads all active jobseeker profiles
   - Scores each jobseeker using MatchEngine
   - Finds best match (score >= 80)
   - Creates call room
   - Returns room code
   ↓
7. Redirect to call.php?room={room_code}
   ↓
8. call.php:
   - Initializes WebRTC peer connection
   - Starts media capture
   - Begins signal polling
   - Displays video interface
```

### Jobseeker Finding Employer

```
1. Jobseeker clicks "Find Employer"
   ↓
2. find-employer.php loads
   ↓
3. JavaScript calls find_employer.php (AJAX)
   ↓
4. find_employer.php:
   - Gets all active employer queues
   - Gets jobseeker profile
   - Scores against each employer
   - Finds best match (score >= 80)
   - Creates call room
   - Returns room code as JSON
   ↓
5. JavaScript redirects to call.php?room={room_code}
   ↓
6. call.php:
   - Same as employer flow
```

---

## WebRTC Signaling Flow

### Signal Types

1. **join**: User joined the room
2. **offer**: SDP offer for peer connection
3. **answer**: SDP answer for peer connection
4. **candidate**: ICE candidate for connection
5. **leave**: User left the room

### Signal Exchange

```
Employer                          Jobseeker
   |                                 |
   |--- POST signal_send.php ------->|
   |    (type: "join")               |
   |                                 |
   |<-- GET signal_poll.php ---------|
   |    (type: "join")               |
   |                                 |
   |--- POST signal_send.php ------->|
   |    (type: "offer", sdp)         |
   |                                 |
   |<-- GET signal_poll.php ---------|
   |    (type: "offer", sdp)         |
   |                                 |
   |--- POST signal_send.php ------->|
   |    (type: "answer", sdp)        |
   |                                 |
   |<-- GET signal_poll.php ---------|
   |    (type: "answer", sdp)        |
   |                                 |
   |--- POST signal_send.php ------->|
   |    (type: "candidate", ice)     |
   |                                 |
   |<-- GET signal_poll.php ---------|
   |    (type: "candidate", ice)     |
   |                                 |
   |========== WebRTC Connected =====|
   |                                 |
   |<===== Video/Audio Stream =====>|
   |                                 |
```

---

## API Endpoints

### Authentication
- `POST /actions/register.php` - Register new user
- `POST /actions/login.php` - Login user
- `POST /actions/logout.php` - Logout user

### Profile Management
- `POST /actions/save_profile.php` - Save user profile

### Matching
- `POST /actions/find_match.php` - Employer finds jobseeker
- `GET /actions/find_employer.php` - Jobseeker finds employer

### WebRTC Signaling
- `POST /actions/signal_send.php` - Send WebRTC signal
- `GET /actions/signal_poll.php` - Poll for WebRTC signals

---

## Security Implementation

### Password Security
```php
// Registration
$hash = password_hash($password, PASSWORD_BCRYPT);

// Login
if (password_verify($password, $hash)) {
  // Valid password
}
```

### CSRF Protection
```php
// Generate token
$token = Csrf::token();

// Verify token
if (!Csrf::verify($_POST['csrf_token'])) {
  die("CSRF token invalid");
}
```

### SQL Injection Prevention
```php
// Using prepared statements
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
```

### Session Security
```php
// Session-based authentication
Session::start();
Auth::requireLogin();
$userId = Auth::userId();
```

---

## Performance Optimization

### Database Indexes
- `users.email` - Fast email lookups
- `users.role` - Filter by role
- `jobseeker_profiles.role_title` - Search by role
- `jobseeker_profiles.country` - Filter by country
- `matchmaking_queue.is_active` - Find active queues
- `calls.status` - Filter by call status
- `calls.created_at` - Sort by date

### Query Optimization
- Use LIMIT for pagination
- Select only needed columns
- Use JOINs instead of multiple queries
- Cache frequently accessed data

### WebRTC Optimization
- Use STUN server for NAT traversal
- Implement connection timeout
- Graceful degradation for poor connections
- Adaptive bitrate streaming

---

## Error Handling

### Database Errors
```php
try {
  $pdo->beginTransaction();
  // Database operations
  $pdo->commit();
} catch (Throwable $e) {
  $pdo->rollBack();
  throw new Exception("Database error");
}
```

### File Upload Errors
```php
if (!$file['tmp_name'] || $file['error'] !== UPLOAD_ERR_OK) {
  throw new Exception("File upload failed");
}
```

### WebRTC Errors
```javascript
pc.onconnectionstatechange = () => {
  if (pc.connectionState === 'failed') {
    log('Connection failed');
    endCall();
  }
};
```

---

## Testing

### Unit Tests
- Test MatchEngine scoring algorithm
- Test AuthService registration/login
- Test ProfileService profile operations

### Integration Tests
- Test complete employer workflow
- Test complete jobseeker workflow
- Test matching and call creation

### Manual Tests
- Test video call with different browsers
- Test skill matching with various profiles
- Test error scenarios

---

## Deployment

### Production Checklist
- [ ] Set `display_errors = Off` in php.ini
- [ ] Enable HTTPS/SSL
- [ ] Set strong database password
- [ ] Configure firewall rules
- [ ] Set up automated backups
- [ ] Monitor error logs
- [ ] Test all features
- [ ] Set up monitoring/alerting

### Environment Variables
```php
// Use environment variables for sensitive data
$dbPassword = getenv('DB_PASSWORD');
$apiKey = getenv('API_KEY');
```

---

## Future Enhancements

1. **Call Recording**
   - Record video calls for review
   - Store recordings securely

2. **Advanced Matching**
   - Machine learning for better matches
   - Historical match success rates

3. **Payment Integration**
   - Stripe/PayPal integration
   - Subscription plans

4. **Mobile App**
   - React Native or Flutter app
   - Push notifications

5. **Real-time Notifications**
   - WebSocket for instant updates
   - Email/SMS notifications

6. **Analytics Dashboard**
   - Call statistics
   - Match success rates
   - User engagement metrics

---

## Troubleshooting Guide

### Common Issues

**Database Connection Failed**
- Check MySQL is running
- Verify credentials in config.php
- Check database exists

**WebRTC Connection Failed**
- Check browser permissions
- Verify STUN server is accessible
- Check firewall rules

**File Upload Failed**
- Check upload directory permissions
- Verify file size limits
- Check disk space

**Matching Not Working**
- Verify jobseeker profiles are complete
- Check skills are added
- Verify matching score >= 80

---

## References

- [WebRTC API](https://developer.mozilla.org/en-US/docs/Web/API/WebRTC_API)
- [PHP PDO](https://www.php.net/manual/en/book.pdo.php)
- [MySQL Documentation](https://dev.mysql.com/doc/)
- [OWASP Security Guidelines](https://owasp.org/)

---

**Version**: 1.0  
**Last Updated**: 2024  
**Status**: Production Ready
