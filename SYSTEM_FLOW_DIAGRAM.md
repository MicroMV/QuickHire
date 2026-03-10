# QuickHire Omegle-Style Matching - System Flow

## Overall Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                         QuickHire Platform                       │
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│  ┌──────────────┐                           ┌──────────────┐    │
│  │   EMPLOYER   │                           │  JOBSEEKER   │    │
│  │  Dashboard   │                           │  Dashboard   │    │
│  └──────┬───────┘                           └──────┬───────┘    │
│         │                                           │            │
│         │ Click "Find Jobseeker"                   │            │
│         │ + Fill Criteria                          │            │
│         ▼                                           ▼            │
│  ┌──────────────────┐                     ┌──────────────────┐ │
│  │  find_match.php  │                     │find_employer.php │ │
│  └──────┬───────────┘                     └──────┬───────────┘ │
│         │                                         │             │
│         │                                         │             │
│         └────────────┬────────────────────────────┘             │
│                      ▼                                           │
│           ┌─────────────────────┐                               │
│           │ MatchmakingService  │                               │
│           │  - Score candidates │                               │
│           │  - Find 80%+ match  │                               │
│           │  - Create room      │                               │
│           └──────────┬──────────┘                               │
│                      ▼                                           │
│              ┌───────────────┐                                  │
│              │  calls table  │                                  │
│              │  room_code    │                                  │
│              └───────┬───────┘                                  │
│                      │                                           │
│         ┌────────────┴────────────┐                             │
│         ▼                         ▼                             │
│  ┌─────────────┐           ┌─────────────┐                     │
│  │  call.php   │◄─────────►│  call.php   │                     │
│  │  (Employer) │  WebRTC   │ (Jobseeker) │                     │
│  └─────────────┘           └─────────────┘                     │
│         │                         │                             │
│         └────────────┬────────────┘                             │
│                      ▼                                           │
│              ┌───────────────┐                                  │
│              │  Chat + Video │                                  │
│              │  + Next Match │                                  │
│              └───────────────┘                                  │
│                                                                  │
└──────────────────────────────────────────────────────────────────┘
```

## Matching Flow (Detailed)

```
EMPLOYER SIDE                    SERVER                    JOBSEEKER SIDE

1. Fill Criteria
   ├─ Role: "Web Dev"
   ├─ Country: "PH"
   ├─ Skills: [JS, React]
   └─ Employment: Full-time
         │
         ▼
2. POST find_match.php ──────────►
                                  3. MatchmakingService
                                     ├─ Get all jobseekers
                                     ├─ Score each one
                                     │  ├─ Country: 30pts
                                     │  ├─ Role: 25pts
                                     │  ├─ English: 20pts
                                     │  └─ Skills: 25pts
                                     ├─ Filter >= 80%
                                     └─ Pick best match
                                           │
                                           ▼
                                  4. Create call room
                                     ├─ Generate room_code
                                     ├─ Link employer_id
                                     ├─ Link jobseeker_id
                                     └─ Status: RINGING
                                           │
         ◄────────────────────────────────┤
5. Redirect to call.php                   │
   ?room=QH-abc123                        │
         │                                 │
         ▼                                 ▼
6. Load call interface        7. Jobseeker sees notification
   ├─ Request camera/mic         "Incoming call!"
   ├─ Init WebRTC peer           │
   └─ Start polling              ▼
         │                    8. Click "Join"
         │                       ├─ Load call.php
         │                       ├─ Request camera/mic
         │                       └─ Init WebRTC peer
         │                             │
         ├─────────────────────────────┤
         │    9. WebRTC Handshake      │
         │    ├─ Offer ────────────►   │
         │    ◄──────────── Answer     │
         │    ├─ ICE Candidates ───►   │
         │    ◄─── ICE Candidates      │
         │                             │
         ├─────────────────────────────┤
         │   10. P2P Connection        │
         │   ├─ Video stream ──────►   │
         │   ◄────────── Video stream  │
         │   ├─ Audio stream ──────►   │
         │   ◄────────── Audio stream  │
         │                             │
         ├─────────────────────────────┤
         │   11. Chat Messages         │
         │   ├─ "Hello!" ──────────►   │
         │   ◄──────── "Hi there!"     │
         │                             │
```

## Chat Message Flow

```
USER A                          SERVER                          USER B

1. Type message
   "Hello!"
      │
      ▼
2. Click Send ──────────────►
                            3. POST chat_send.php
                               ├─ Validate user
                               ├─ Validate room
                               └─ INSERT INTO chat_messages
                                     │
                                     ▼
                            4. Store in database
                               ┌─────────────────┐
                               │ chat_messages   │
                               ├─────────────────┤
                               │ id: 1           │
                               │ room: QH-abc123 │
                               │ sender: User A  │
                               │ message: Hello! │
                               │ time: 10:30:45  │
                               └─────────────────┘
                                     │
      ◄──────────────────────────────┤
5. Success response                  │
                                     │
                                     │         6. Poll every 500ms
                                     │         GET chat_poll.php
                                     │         ?after=0
                                     │              │
                                     ├──────────────►
                                     │
                            7. SELECT new messages
                               WHERE id > 0
                                     │
                                     ├──────────────►
                                                8. Receive messages
                                                   [{
                                                     id: 1,
                                                     sender: "User A",
                                                     message: "Hello!",
                                                     time: "10:30:45"
                                                   }]
                                                      │
                                                      ▼
                                                9. Display in chat
                                                   ┌──────────────┐
                                                   │ User A       │
                                                   │ Hello!       │
                                                   └──────────────┘
```

## Next Match Flow

```
CURRENT CALL                    SERVER                    NEW MATCH

1. Click "Next" button
      │
      ▼
2. Confirm dialog
   "Skip to next match?"
      │ Yes
      ▼
3. POST next_match.php ──────────►
   { room: "QH-abc123" }
                                4. Get current call
                                   ├─ employer_id: 1
                                   ├─ jobseeker_id: 2
                                   └─ status: IN_CALL
                                         │
                                         ▼
                                5. Mark as COMPLETED
                                   UPDATE calls
                                   SET status='COMPLETED'
                                         │
                                         ▼
                                6. Find next match
                                   ├─ Get user role
                                   ├─ Skip partner (id: 2)
                                   ├─ Score candidates
                                   ├─ Filter >= 80%
                                   └─ Pick best match
                                         │
                                         ▼
                                7. Create new room
                                   ├─ room: QH-xyz789
                                   ├─ employer: 1
                                   ├─ jobseeker: 3 ◄────── NEW PARTNER
                                   └─ status: RINGING
                                         │
      ◄─────────────────────────────────┤
8. Receive new room
   { ok: true, room: "QH-xyz789" }
      │
      ▼
9. Redirect to new call
   window.location = 
   "call.php?room=QH-xyz789"
      │
      ▼
10. Start new call ──────────────────────────────────────►
    ├─ Load interface                              11. Receive notification
    ├─ Init WebRTC                                     "New call incoming!"
    └─ Start chat polling                                    │
                                                             ▼
                                                    12. Join new call
                                                        ├─ Load interface
                                                        ├─ Init WebRTC
                                                        └─ Connect
```

## Matching Score Calculation

```
┌─────────────────────────────────────────────────────────────┐
│                    MATCHING ALGORITHM                        │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  EMPLOYER CRITERIA          JOBSEEKER PROFILE                │
│  ─────────────────          ─────────────────                │
│  Role: "Web Developer"      Role: "Full Stack Developer"    │
│  Country: "Philippines"     Country: "Philippines"           │
│  Skills: [JS, React, Node]  Skills: [JS, React, Node, CSS]  │
│  Employment: Full-time      English: Fluent                  │
│                                                              │
│  ┌────────────────────────────────────────────────────┐     │
│  │              SCORE CALCULATION                     │     │
│  ├────────────────────────────────────────────────────┤     │
│  │                                                    │     │
│  │  1. Country Match                                  │     │
│  │     "Philippines" === "Philippines"                │     │
│  │     ✅ EXACT MATCH → 30 points                     │     │
│  │                                                    │     │
│  │  2. Role Match                                     │     │
│  │     "Web Developer" in "Full Stack Developer"     │     │
│  │     ✅ PARTIAL MATCH → 15 points                   │     │
│  │                                                    │     │
│  │  3. English Mastery                                │     │
│  │     Level: "Fluent"                                │     │
│  │     ✅ FLUENT → 20 points                          │     │
│  │                                                    │     │
│  │  4. Skills Match                                   │     │
│  │     Required: [JS, React, Node]                    │     │
│  │     Has: [JS, React, Node, CSS]                    │     │
│  │     Match: 3/3 = 100%                              │     │
│  │     ✅ PERFECT MATCH → 25 points                   │     │
│  │                                                    │     │
│  │  ─────────────────────────────────────────────     │     │
│  │  TOTAL SCORE: 90 / 100                             │     │
│  │  ✅ ABOVE 80% THRESHOLD → MATCH!                   │     │
│  │                                                    │     │
│  └────────────────────────────────────────────────────┘     │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

## Database Schema Relationships

```
┌─────────────┐
│    users    │
├─────────────┤
│ id (PK)     │◄────────┬──────────────┬──────────────┐
│ role        │         │              │              │
│ email       │         │              │              │
│ ...         │         │              │              │
└─────────────┘         │              │              │
                        │              │              │
        ┌───────────────┤              │              │
        │               │              │              │
        ▼               ▼              ▼              ▼
┌──────────────┐  ┌──────────────┐  ┌──────────┐  ┌──────────────┐
│  jobseeker_  │  │  employer_   │  │  calls   │  │chat_messages │
│  profiles    │  │  profiles    │  ├──────────┤  ├──────────────┤
├──────────────┤  ├──────────────┤  │ id (PK)  │  │ id (PK)      │
│ user_id (FK) │  │ user_id (FK) │  │ room_code│  │ room_code    │
│ role_title   │  │ company_name │  │ emp_id   │  │ sender_id(FK)│
│ country      │  │ country      │  │ job_id   │  │ message      │
│ skills       │  │ ...          │  │ status   │  │ created_at   │
│ ...          │  └──────────────┘  └────┬─────┘  └──────────────┘
└──────────────┘                         │
                                         │
                    ┌────────────────────┴────────────────────┐
                    │                                         │
                    ▼                                         ▼
            ┌──────────────┐                         ┌──────────────┐
            │matchmaking_  │                         │  webrtc_     │
            │   queue      │                         │  signals     │
            ├──────────────┤                         ├──────────────┤
            │ id (PK)      │                         │ id (PK)      │
            │ user_id (FK) │                         │ room_code    │
            │ role         │                         │ sender_id(FK)│
            │ wanted_role  │                         │ type         │
            │ is_active    │                         │ payload      │
            └──────────────┘                         └──────────────┘
```

## Call Interface Layout

```
┌─────────────────────────────────────────────────────────────────────┐
│  Room: QH-abc123                              You: EMPLOYER          │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ┌──────────────────────────────────┐  ┌─────────────────────────┐ │
│  │                                  │  │  💬 Chat                │ │
│  │  ┌────────────────────────────┐  │  ├─────────────────────────┤ │
│  │  │                            │  │  │                         │ │
│  │  │     Your Video             │  │  │  Employer:              │ │
│  │  │                            │  │  │  Hello! Looking for     │ │
│  │  │                            │  │  │  a web developer        │ │
│  │  └────────────────────────────┘  │  │                         │ │
│  │                                  │  │  Jobseeker:             │ │
│  │  ┌────────────────────────────┐  │  │  Hi! I have 5 years    │ │
│  │  │                            │  │  │  experience in React   │ │
│  │  │   Partner's Video          │  │  │                         │ │
│  │  │                            │  │  │  Employer:              │ │
│  │  │                            │  │  │  Great! Tell me more   │ │
│  │  └────────────────────────────┘  │  │                         │ │
│  │                                  │  │                         │ │
│  └──────────────────────────────────┘  ├─────────────────────────┤ │
│                                        │ [Type message...]  [Send]│ │
│  [📹 Camera: ON] [🎤 Mic: ON]          └─────────────────────────┘ │
│  [⏭️ Next] [📞 End Call]                                           │
│                                                                      │
└──────────────────────────────────────────────────────────────────────┘
```

## State Diagram

```
                    ┌─────────────┐
                    │   LOGGED    │
                    │     IN      │
                    └──────┬──────┘
                           │
              ┌────────────┴────────────┐
              │                         │
              ▼                         ▼
      ┌──────────────┐          ┌──────────────┐
      │  EMPLOYER    │          │  JOBSEEKER   │
      │  DASHBOARD   │          │  DASHBOARD   │
      └──────┬───────┘          └──────┬───────┘
             │                         │
             │ Click "Find"            │ Click "Find"
             ▼                         ▼
      ┌──────────────┐          ┌──────────────┐
      │  SEARCHING   │          │  SEARCHING   │
      │  FOR MATCH   │          │  FOR MATCH   │
      └──────┬───────┘          └──────┬───────┘
             │                         │
             │ Match found             │ Match found
             │ (80%+)                  │
             └────────────┬────────────┘
                          ▼
                   ┌──────────────┐
                   │   RINGING    │
                   └──────┬───────┘
                          │
                          │ Both join
                          ▼
                   ┌──────────────┐
                   │   IN_CALL    │◄──────┐
                   │              │       │
                   │ • Video ON   │       │
                   │ • Chat ON    │       │
                   │ • Controls   │       │
                   └──────┬───────┘       │
                          │               │
              ┌───────────┴───────────┐   │
              │                       │   │
              ▼                       ▼   │
       ┌──────────────┐        ┌──────────────┐
       │  END CALL    │        │  NEXT MATCH  │
       └──────┬───────┘        └──────┬───────┘
              │                       │
              │                       │ Find new
              │                       │ partner
              │                       └───────┘
              ▼
       ┌──────────────┐
       │  COMPLETED   │
       │              │
       │ Return to    │
       │ Dashboard    │
       └──────────────┘
```

This visual documentation should help understand how all the pieces fit together!
