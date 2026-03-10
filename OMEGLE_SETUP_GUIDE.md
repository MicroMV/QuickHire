# Omegle-Style Matching Setup Guide

## Quick Setup (5 minutes)

### Step 1: Update Database
If you already have QuickHire installed, run this SQL to add the chat table:

```bash
# Windows (using MySQL command line)
mysql -u root -p quickhire < update_database_chat.sql

# Or run the full schema again
mysql -u root -p quickhire < database_schema.sql
```

### Step 2: Test the System

#### As an Employer:
1. Login to your employer account
2. Go to Employer Dashboard
3. Click "Find Jobseeker" button
4. Fill in the matching criteria:
   - Role Title: e.g., "Web Developer"
   - Country: e.g., "Philippines"
   - Employment Type: Full-time/Part-time
   - Select required skills (optional)
5. Click "Find Match"
6. If a match is found (80%+ score), you'll be connected to a video call
7. Use the chat to communicate
8. Click "Next" to find another jobseeker
9. Click "End Call" when done

#### As a Jobseeker:
1. Login to your jobseeker account
2. Make sure your profile is complete (role, skills, country, etc.)
3. Go to Jobseeker Dashboard
4. Click "Find Employer" button
5. System will automatically search for matching employers
6. If found, you'll be connected to a video call
7. Use the chat to communicate
8. Click "Next" to find another employer
9. Click "End Call" when done

### Step 3: Test with Two Accounts

For best testing experience:
1. Create one employer account
2. Create one jobseeker account with matching criteria
3. Open two browser windows (or use incognito mode)
4. Login as employer in one window
5. Login as jobseeker in another window
6. Employer: Click "Find Jobseeker" and fill criteria
7. Jobseeker: Should see incoming call notification
8. Both: Join the call and test video/chat/next features

## Matching Criteria for Testing

### Example 1: Perfect Match (100 points)
**Employer wants:**
- Role: "Web Developer"
- Country: "Philippines"
- Skills: JavaScript, React
- Employment: Full-time

**Jobseeker profile:**
- Role: "Web Developer"
- Country: "Philippines"
- Skills: JavaScript, React, Node.js
- English: Fluent

**Score:** 30 (country) + 25 (role) + 20 (english) + 25 (skills) = 100 ✅

### Example 2: Good Match (85 points)
**Employer wants:**
- Role: "Frontend Developer"
- Country: "India"
- Skills: React, TypeScript, CSS
- Employment: Part-time

**Jobseeker profile:**
- Role: "Full Stack Developer"
- Country: "India"
- Skills: React, TypeScript, Node.js
- English: Advanced

**Score:** 30 (country) + 15 (partial role) + 15 (english) + 17 (2/3 skills) = 77 ❌ (below 80)

Adjust skills to get above 80!

### Example 3: Minimum Match (80 points)
**Employer wants:**
- Role: "Data Analyst"
- Country: "USA"
- Skills: Python, SQL
- Employment: Contract

**Jobseeker profile:**
- Role: "Data Analyst"
- Country: "USA"
- Skills: Python, SQL, Excel
- English: Intermediate

**Score:** 30 (country) + 25 (role) + 10 (english) + 25 (skills) = 90 ✅

## Features to Test

### ✅ Video Call
- [ ] Camera turns on automatically
- [ ] Can see your own video
- [ ] Can see partner's video
- [ ] Can toggle camera on/off
- [ ] Can toggle microphone on/off

### ✅ Chat
- [ ] Can send messages
- [ ] Messages appear in real-time
- [ ] Can see who sent each message
- [ ] Chat scrolls automatically
- [ ] Press Enter to send message

### ✅ Next Match
- [ ] Click "Next" button
- [ ] Current call ends
- [ ] New match is found
- [ ] Redirects to new call room
- [ ] Previous partner is skipped

### ✅ End Call
- [ ] Click "End Call" button
- [ ] Video/audio stops
- [ ] Returns to dashboard
- [ ] Call marked as completed

## Troubleshooting

### "No match found"
**Problem:** System can't find a match with 80%+ score

**Solutions:**
1. Lower the threshold in `MatchEngine.php` (line with `if ($score >= 80)`)
2. Create more test accounts with matching profiles
3. Make sure jobseeker profiles are complete (`is_profile_complete = 1`)
4. Check that skills are properly saved in database

### Camera/Microphone not working
**Problem:** Browser doesn't have permission

**Solutions:**
1. Click the camera icon in browser address bar
2. Allow camera and microphone access
3. Use HTTPS (required for getUserMedia)
4. Check if camera/mic is being used by another app

### Video not connecting
**Problem:** WebRTC connection fails

**Solutions:**
1. Check browser console for errors
2. Verify STUN server is accessible
3. May need TURN server for restrictive networks
4. Check firewall settings

### Chat not updating
**Problem:** Messages don't appear

**Solutions:**
1. Check browser console for errors
2. Verify `chat_messages` table exists
3. Check database connection
4. Verify polling is working (check Network tab)

### "Room not found" error
**Problem:** Call room doesn't exist

**Solutions:**
1. Check `calls` table has the room_code
2. Verify user is authorized for that room
3. Check if call was already completed
4. Try creating a new match

## Database Verification

Run these queries to check your setup:

```sql
-- Check if chat_messages table exists
SHOW TABLES LIKE 'chat_messages';

-- Check active calls
SELECT * FROM calls WHERE status IN ('RINGING', 'IN_CALL');

-- Check matchmaking queue
SELECT * FROM matchmaking_queue WHERE is_active = 1;

-- Check jobseeker profiles
SELECT u.email, jp.role_title, jp.country, u.is_profile_complete
FROM users u
JOIN jobseeker_profiles jp ON jp.user_id = u.id
WHERE u.role = 'JOBSEEKER';

-- Check employer profiles
SELECT u.email, ep.company_name, ep.country
FROM users u
JOIN employer_profiles ep ON ep.user_id = u.id
WHERE u.role = 'EMPLOYER';
```

## Performance Tips

### For Development
- Use localhost
- Keep polling intervals as is (500-700ms)
- Test with 2-3 concurrent users

### For Production
- Use HTTPS (required for WebRTC)
- Add TURN server for better connectivity
- Increase polling intervals if needed
- Add connection pooling for database
- Consider Redis for real-time features
- Add rate limiting to prevent abuse

## Next Steps

After basic testing works:
1. Adjust matching threshold if needed
2. Add more skills to the database
3. Customize the UI/styling
4. Add TURN server for production
5. Implement additional features (see OMEGLE_STYLE_MATCHING.md)

## Support

If you encounter issues:
1. Check browser console (F12)
2. Check PHP error logs
3. Check database for data
4. Verify all files are uploaded
5. Test with different browsers

## File Checklist

Make sure these files exist:
- ✅ `Public/call.php` (updated with chat UI)
- ✅ `Public/actions/chat_send.php`
- ✅ `Public/actions/chat_poll.php`
- ✅ `Public/actions/next_match.php`
- ✅ `Public/actions/find_employer.php`
- ✅ `Public/actions/signal_send.php`
- ✅ `src/Services/MatchmakingService.php` (updated)
- ✅ `database_schema.sql` (updated)
- ✅ `update_database_chat.sql`

Happy matching! 🎉
