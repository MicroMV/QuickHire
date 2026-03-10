# Omegle-Style Matching System

## Overview
QuickHire now features an Omegle-style matching system that connects employers and jobseekers in real-time video calls with integrated chat functionality. The system includes intelligent matching based on skills, role, and location with an 80% minimum match threshold.

## Key Features

### 1. Intelligent Matching (80% Threshold)
- Employers specify role title, country, employment type, and required skills
- System scores jobseekers based on:
  - **Country match** (30 points)
  - **Role title match** (25 points) - exact or partial match
  - **English mastery** (20 points) - Native/Fluent get full points
  - **Skills match** (25 points) - percentage of required skills matched
- Only matches with 80+ score are connected

### 2. Real-Time Video & Audio
- WebRTC-based peer-to-peer video calling
- Camera and microphone controls (toggle on/off)
- High-quality video streaming
- STUN server for NAT traversal

### 3. Integrated Chat
- Real-time text chat alongside video
- Messages persist in database
- Sender identification (role-based)
- Auto-scroll to latest messages
- Clean, modern chat interface

### 4. "Next" Functionality
- Skip to next match with one click
- Automatically finds new partner
- Previous call marked as completed
- Skips recently matched partners
- Seamless transition to new call

### 5. Dual-Sided Matching
- **Employers**: Search for jobseekers with specific criteria
- **Jobseekers**: Find employers looking for their skills
- Both can use "Next" to find better matches

## Database Schema Updates

### New Table: `chat_messages`
```sql
CREATE TABLE chat_messages (
  id INT PRIMARY KEY AUTO_INCREMENT,
  room_code VARCHAR(50) NOT NULL,
  sender_id INT NOT NULL,
  message TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_room_code (room_code),
  INDEX idx_created_at (created_at)
);
```

## New API Endpoints

### Chat Endpoints
- **POST** `/Public/actions/chat_send.php` - Send chat message
  - Body: `{ "room": "room_code", "message": "text" }`
  - Returns: `{ "ok": true }`

- **GET** `/Public/actions/chat_poll.php` - Poll for new messages
  - Query: `?room=room_code&after=message_id`
  - Returns: `{ "ok": true, "messages": [...], "after": last_id }`

### Matching Endpoints
- **POST** `/Public/actions/next_match.php` - Find next match
  - Body: `{ "room": "current_room_code" }`
  - Returns: `{ "ok": true, "room": "new_room_code" }`

- **GET** `/Public/actions/find_employer.php` - Jobseeker finds employer
  - Returns: `{ "ok": true, "room": "room_code" }`

## Updated Services

### MatchmakingService.php
New methods:
- `enqueueJobseeker(int $jobseekerId): int` - Add jobseeker to queue
- `findNextMatch(int $userId, string $role, ?int $skipUserId): ?string` - Find next match, skipping specified user

## User Interface

### Call Screen Layout
```
┌─────────────────────────────────────────────────────┐
│  Video Section (70%)        │  Chat Section (30%)   │
│  ┌──────────────────────┐   │  ┌─────────────────┐ │
│  │  Your Video          │   │  │  Chat Header    │ │
│  └──────────────────────┘   │  ├─────────────────┤ │
│  ┌──────────────────────┐   │  │                 │ │
│  │  Partner's Video     │   │  │  Messages       │ │
│  └──────────────────────┘   │  │                 │ │
│  [Camera] [Mic] [Next] [End]│  ├─────────────────┤ │
│                              │  │  Input | Send   │ │
└─────────────────────────────┴──┴─────────────────┴─┘
```

### Features in Call Screen
- Split-screen video layout
- Real-time chat sidebar
- Control buttons: Camera, Mic, Next, End Call
- Room code and role display
- Responsive design (mobile-friendly)

## How It Works

### For Employers
1. Click "Find Jobseeker" on dashboard
2. Fill in criteria (role, country, skills, employment type)
3. System finds best match (80%+ score)
4. Automatically connects to video call with chat
5. Can click "Next" to find another jobseeker
6. Click "End Call" to return to dashboard

### For Jobseekers
1. Click "Find Employer" on dashboard
2. System automatically matches with available employer
3. Connects to video call with chat
4. Can click "Next" to find another employer
5. Click "End Call" to return to dashboard

## Matching Algorithm

### Scoring Breakdown
```php
Country Match:     30 points (exact match)
Role Title:        25 points (exact) or 15 points (partial)
English Mastery:   20 points (Native/Fluent) to 5 points (Beginner)
Skills Match:      25 points (percentage of required skills)
─────────────────────────────────
Total:            100 points maximum
Minimum Required:  80 points
```

### Example Match
```
Employer wants:
- Role: "Web Developer"
- Country: "Philippines"
- Skills: JavaScript, React, Node.js
- Employment: Full-time

Jobseeker has:
- Role: "Full Stack Web Developer"
- Country: "Philippines"
- Skills: JavaScript, React, Node.js, Python
- English: Fluent

Score Calculation:
- Country: 30 (exact match)
- Role: 15 (partial match)
- English: 20 (fluent)
- Skills: 25 (3/3 = 100%)
Total: 90 points ✅ MATCH!
```

## Technical Implementation

### WebRTC Flow
1. User A creates offer
2. Offer sent via signaling server (database)
3. User B receives offer, creates answer
4. Answer sent back via signaling server
5. ICE candidates exchanged
6. Peer-to-peer connection established
7. Video/audio streams shared

### Chat Flow
1. User types message and clicks Send
2. Message sent to `chat_send.php`
3. Stored in `chat_messages` table
4. Other user polls `chat_poll.php` every 500ms
5. New messages retrieved and displayed
6. Auto-scroll to latest message

### Next Match Flow
1. User clicks "Next" button
2. Current call marked as COMPLETED
3. Partner ID saved to skip list
4. `findNextMatch()` called with skip parameter
5. New match found (excluding skipped partner)
6. New room created
7. Page redirects to new call
8. WebRTC connection established

## Configuration

### STUN Server
Default: `stun:stun.l.google.com:19302` (free Google STUN)

For production, consider:
- Twilio STUN/TURN servers
- Xirsys
- Your own TURN server

### Polling Intervals
- Signal polling: 700ms
- Chat polling: 500ms

Adjust in `call.php` if needed for performance.

## Browser Compatibility
- Chrome/Edge: ✅ Full support
- Firefox: ✅ Full support
- Safari: ✅ Full support (iOS 11+)
- Opera: ✅ Full support

## Security Considerations
1. Room codes are unique and random
2. Users verified before joining calls
3. Only participants can send/receive messages
4. Signals and chat messages tied to authenticated users
5. SQL injection prevention via prepared statements

## Future Enhancements
- [ ] Add TURN server for better connectivity
- [ ] Video recording capability
- [ ] Screen sharing
- [ ] File sharing in chat
- [ ] Emoji support
- [ ] Read receipts
- [ ] Typing indicators
- [ ] Call quality indicators
- [ ] Report/block functionality
- [ ] Match history with notes
- [ ] Favorite/bookmark matches

## Troubleshooting

### No video/audio
- Check browser permissions
- Ensure HTTPS (required for getUserMedia)
- Check camera/mic hardware

### Connection fails
- Firewall blocking WebRTC
- Need TURN server (not just STUN)
- Check browser console for errors

### No matches found
- Adjust matching threshold (currently 80%)
- Ensure profiles are complete
- Check if other users are online

### Chat not updating
- Check polling interval
- Verify database connection
- Check browser console for errors

## Performance Tips
1. Use TURN server for better connectivity
2. Optimize video resolution for bandwidth
3. Add connection quality indicators
4. Implement reconnection logic
5. Cache user profiles for faster matching

## Support
For issues or questions, check:
- Browser console for errors
- PHP error logs
- Database connection
- WebRTC compatibility
