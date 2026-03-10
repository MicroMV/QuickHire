# Unified Button Fix - Single Call System

## Problem Identified ✅

**Issue:** Two different call systems causing confusion:
- "Join now" button → Joins existing calls (RINGING/IN_CALL status)
- "Find Employer/Jobseeker" button → Tries to create new matches

**Result:** User sees "Join now" for existing call, but "Find Employer" says "no employers available" because it's looking for new matches instead of joining the existing call.

## Solution Implemented ✅

### Unified Logic: One Call System
Both buttons now work with the same logic:
1. **First:** Check if there's already an active call
2. **If yes:** Join that call (same as "Join now")
3. **If no:** Try to find/create a new match

### Changes Made

#### 1. Updated `find_employer.php` (Jobseeker Side)
```php
// FIRST: Check for existing calls (same as "Join now")
$incomingStmt = $pdo->prepare("
  SELECT room_code FROM calls
  WHERE jobseeker_user_id = ? AND status IN ('RINGING','IN_CALL')
  ORDER BY id DESC LIMIT 1
");

if ($incoming) {
  // Join existing call
  echo json_encode(['ok' => true, 'room' => $incoming['room_code']]);
  exit;
}

// SECOND: If no existing call, find new match
$room = $svc->findNextMatch($userId, 'JOBSEEKER');
```

#### 2. Updated Employer Dashboard JavaScript
```javascript
// First check for active calls
const checkResponse = await fetch('/actions/check_active_call.php');
if (checkData.ok && checkData.room) {
  // Join existing call directly
  window.location.href = '/call.php?room=' + checkData.room;
  return;
}

// No active call, proceed with new matching
const roleTitle = prompt('Enter role title:');
// ... continue with matching logic
```

#### 3. Created `check_active_call.php`
Universal endpoint to check for active calls for both employers and jobseekers.

#### 4. Updated Dashboard Notifications
**Before:**
- "🔔 Incoming call! [Join now]" (separate link)

**After:**
- "🔔 Incoming call! Click 'Find Employer/Jobseeker' to join the call."

## Flow Comparison

### Before (Confusing)
```
EXISTING CALL SCENARIO:
┌─────────────────┐    ┌─────────────────┐
│ "Join now"      │───▶│ Joins call ✅   │
└─────────────────┘    └─────────────────┘

┌─────────────────┐    ┌─────────────────┐
│ "Find Employer" │───▶│ "No employers"❌│
└─────────────────┘    └─────────────────┘
```

### After (Unified)
```
EXISTING CALL SCENARIO:
┌─────────────────┐    ┌─────────────────┐
│ "Find Employer" │───▶│ Joins call ✅   │
└─────────────────┘    └─────────────────┘

NO EXISTING CALL SCENARIO:
┌─────────────────┐    ┌─────────────────┐
│ "Find Employer" │───▶│ Creates new ✅  │
└─────────────────┘    └─────────────────┘
```

## User Experience

### Jobseeker Experience
1. **Has incoming call:** Click "Find Employer" → Joins existing call immediately
2. **No incoming call:** Click "Find Employer" → Searches for new employer match

### Employer Experience  
1. **Has active call:** Click "Find Jobseeker" → Joins existing call immediately
2. **No active call:** Click "Find Jobseeker" → Prompts for criteria → Searches for new jobseeker match

## Technical Details

### Priority Logic
```
1. Check for existing calls (RINGING/IN_CALL)
   ├─ If found: Return existing room_code
   └─ If not found: Proceed to step 2

2. Search for new matches
   ├─ If found: Create new call, return new room_code  
   └─ If not found: Return "no matches available"
```

### Database Queries
```sql
-- Check for existing calls (both roles)
SELECT room_code FROM calls 
WHERE (employer_user_id = ? OR jobseeker_user_id = ?) 
AND status IN ('RINGING','IN_CALL')
ORDER BY id DESC LIMIT 1

-- Only if no existing calls, then search for new matches
-- (existing matching logic in MatchmakingService)
```

## Files Modified

1. **`Public/actions/find_employer.php`** - Added existing call check
2. **`Public/actions/check_active_call.php`** - New universal call checker  
3. **`Public/employer-dashboard.php`** - Updated JavaScript logic
4. **`Public/jobseeker-dashboard.php`** - Updated notification text

## Testing Scenarios

### Scenario 1: Existing Call
1. Employer creates match with Jobseeker A
2. Jobseeker A sees "Incoming call" notification
3. Jobseeker A clicks "Find Employer" → Joins existing call ✅
4. Employer clicks "Find Jobseeker" → Joins same call ✅

### Scenario 2: No Existing Call
1. Jobseeker clicks "Find Employer" → Searches for new match
2. Employer clicks "Find Jobseeker" → Searches for new match
3. If match found → New call created ✅
4. If no match → "No matches available" ✅

### Scenario 3: Multiple Calls
1. System always returns the most recent active call
2. Older calls are ignored (ORDER BY id DESC LIMIT 1)

## Benefits

### User Experience
- ✅ Consistent button behavior
- ✅ No more confusion between "Join now" and "Find"
- ✅ Single button does everything
- ✅ Intuitive: one button, one action

### Technical
- ✅ Unified codebase
- ✅ Consistent logic across roles
- ✅ Proper priority handling
- ✅ No duplicate call creation

### Business Logic
- ✅ Existing calls take priority
- ✅ New matches only when needed
- ✅ Better resource utilization
- ✅ Cleaner call management

## Status: FIXED ✅

The issue has been resolved. Now both "Find Employer" and "Find Jobseeker" buttons work with unified logic:

1. **Priority 1:** Join existing calls (same as old "Join now")
2. **Priority 2:** Create new matches (same as old "Find" behavior)

Users will no longer see the confusing scenario where "Join now" works but "Find Employer" says "no employers available" for the same call.