<?php
require __DIR__ . '/../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Database;
use Rongie\QuickHire\Core\Csrf;

Session::start();
Auth::requireLogin();

// Never cache the call page — each room load must be fresh
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$room = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['room'] ?? '');
if ($room === '') {
    $role = Auth::role();
    $dest = $role === 'EMPLOYER' ? 'employer-dashboard.php' : 'jobseeker-dashboard.php';
    header("Location: /QuickHire/Public/{$dest}");
    exit;
}

// Optional: verify user is allowed in this call room
$config = require __DIR__ . '/../Config/config.php';
$db = new Database($config['db']);
$pdo = $db->pdo();

$stmt = $pdo->prepare("SELECT * FROM calls WHERE room_code = ? LIMIT 1");
$stmt->execute([$room]);
$call = $stmt->fetch();
if (!$call) {
    $role = Auth::role();
    $dest = $role === 'EMPLOYER' ? 'employer-dashboard.php' : 'jobseeker-dashboard.php';
    header("Location: /QuickHire/Public/{$dest}");
    exit;
}

$uid = Auth::userId();
$isEmployer = $uid === (int)$call['employer_user_id'];
$isJobseeker = $call['jobseeker_user_id'] && $uid === (int)$call['jobseeker_user_id'];
$publicBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/QuickHire/Public/call.php')), '/');
if ($publicBase === '' || $publicBase === '.') {
    $publicBase = '/';
}
$actionsBase = rtrim($publicBase, '/') . '/actions';

if (!$isEmployer && !$isJobseeker) {
    $role = Auth::role();
    $dest = $role === 'EMPLOYER' ? 'employer-dashboard.php' : 'jobseeker-dashboard.php';
    header("Location: /QuickHire/Public/{$dest}");
    exit;
}

if (in_array($call['status'], ['COMPLETED', 'MISSED', 'CANCELLED'], true)) {
    $role = Auth::role();
    $dest = $role === 'EMPLOYER' ? 'employer-dashboard.php' : 'jobseeker-dashboard.php';
    header("Location: /QuickHire/Public/{$dest}");
    exit;
}

// Update status based on current state
if ($call['status'] === 'WAITING' && $isJobseeker) {
    // Jobseeker joining a waiting room - change to RINGING
    $pdo->prepare("UPDATE calls SET status='RINGING' WHERE room_code=? AND status='WAITING'")->execute([$room]);
    $call['status'] = 'RINGING';
} elseif ($call['status'] === 'RINGING') {
    // Both parties are loading the call page - change to IN_CALL
    $pdo->prepare("UPDATE calls SET status='IN_CALL' WHERE room_code=? AND status='RINGING'")->execute([$room]);
    $call['status'] = 'IN_CALL';
}

// Load employer's saved queue criteria for the "Next Jobseeker" form
$nextJobseekerForm = '';
if ($isEmployer) {
    $qStmt = $pdo->prepare("SELECT mq.*, GROUP_CONCAT(mqs.skill_id) as skill_ids
        FROM matchmaking_queue mq
        LEFT JOIN matchmaking_queue_skills mqs ON mqs.queue_id = mq.id
        WHERE mq.user_id = ? AND mq.role = 'EMPLOYER'
        ORDER BY mq.id DESC LIMIT 1");
    $qStmt->execute([$uid]);
    $savedQ = $qStmt->fetch();

    $csrfToken = Csrf::token();

    if ($savedQ) {
        $skillInputs = '';
        if (!empty($savedQ['skill_ids'])) {
            foreach (explode(',', $savedQ['skill_ids']) as $sid) {
                $sid = (int)$sid;
                if ($sid > 0) $skillInputs .= '<input type="hidden" name="skill_ids[]" value="' . $sid . '">';
            }
        }
        $nextJobseekerForm = '
        <form id="nextJobseekerForm" method="POST" action="/QuickHire/Public/actions/find_match.php" style="display:none;">
            <input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken) . '">
            <input type="hidden" name="role_title" value="' . htmlspecialchars($savedQ['wanted_role'] ?? '') . '">
            <input type="hidden" name="country" value="' . htmlspecialchars($savedQ['wanted_country'] ?? '') . '">
            <input type="hidden" name="employment_type" value="' . htmlspecialchars($savedQ['employment_type'] ?? 'FULL_TIME') . '">
            ' . $skillInputs . '
        </form>';
    }
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>QuickHire Call</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/QuickHire/Public/assets/css/call.css">
    <link rel="stylesheet" href="/QuickHire/Public/assets/css/dark-theme.css">
</head>

<body class="landing-body">
    <div class="container">
        <!-- Video Section -->
        <div class="video-section">
            <div class="top-bar">
                <div class="badge">Room: <strong><?= htmlspecialchars($room) ?></strong></div>
                <div class="badge">You: <strong><?= htmlspecialchars(Auth::role()) ?></strong></div>
                <div class="badge" id="connectionStatus">Connection: <strong>Connecting...</strong></div>
            </div>

            <div class="video-grid">
                <div class="video-card">
                    <video id="localVideo" autoplay playsinline muted></video>
                    <div class="video-name" id="localVideoName">Loading...</div>
                </div>
                <div class="video-card">
                    <video id="remoteVideo" autoplay playsinline></video>
                    <div class="video-name" id="remoteVideoName">Waiting...</div>
                </div>
            </div>

            <div class="controls">
                <button id="btnCam">📹 Camera: ON</button>
                <button id="btnMic">🎤 Mic: ON</button>
                <button id="btnNext" class="success">Next</button>
                <button id="btnHang" class="danger">📞 End Call</button>
            </div>
        </div>
        <?= $nextJobseekerForm ?>

        <!-- Chat Section -->
        <div class="chat-section">
            <div class="chat-header">💬 Chat</div>
            <div class="chat-messages" id="chatMessages"></div>
            <div class="chat-input-area">
                <input type="text" id="chatInput" class="chat-input" placeholder="Connect to chat..." disabled />
                <button id="btnSend" class="send-btn" disabled style="opacity:0.4;cursor:not-allowed;">Send</button>
            </div>
        </div>
    </div>

    <script>
        let ROOM = <?= json_encode($room) ?>;
        const MY_ID = <?= json_encode(Auth::userId()) ?>;
        const MY_ROLE = <?= json_encode(Auth::role()) ?>;
        const CALL_STATUS = <?= json_encode($call['status']) ?>;
        const ACTIONS_BASE = <?= json_encode($actionsBase) ?>;
        const MATCHING_PREFS_KEY = 'matchingPreferences_' + MY_ID;

        function actionUrl(path) {
            return `${ACTIONS_BASE}/${path}`;
        }

        function getSavedMatchingPreferences() {
            const stored = localStorage.getItem(MATCHING_PREFS_KEY);
            if (!stored) return null;

            try {
                const preferences = JSON.parse(stored);
                if (!String(preferences.role_title || '').trim() || !String(preferences.country || '').trim()) {
                    localStorage.removeItem(MATCHING_PREFS_KEY);
                    return null;
                }
                return preferences;
            } catch (error) {
                localStorage.removeItem(MATCHING_PREFS_KEY);
                return null;
            }
        }

        // Always show video interface - no waiting screen
        // Regular call functionality

        let localStream = null;
        let pc = null;
        let afterSignalId = 0;
        let polling = true;
        let isOfferer = false; // Track who should create offer
        let jobseekerReconnectTimer = null;

        const iceConfig = {
            iceServers: [
                { urls: "stun:stun.l.google.com:19302" },
                { urls: "stun:stun1.l.google.com:19302" },
                {
                    urls: "turn:openrelay.metered.ca:80",
                    username: "openrelayproject",
                    credential: "openrelayproject"
                },
                {
                    urls: "turn:openrelay.metered.ca:443",
                    username: "openrelayproject",
                    credential: "openrelayproject"
                },
                {
                    urls: "turn:openrelay.metered.ca:443?transport=tcp",
                    username: "openrelayproject",
                    credential: "openrelayproject"
                }
            ],
            iceTransportPolicy: 'all',
            iceCandidatePoolSize: 10,
            bundlePolicy: 'max-bundle',
            rtcpMuxPolicy: 'require'
        };

        // ===== CALL STATUS MANAGEMENT =====
        // Removed complex status management - let's keep it simple

        // ===== HEARTBEAT MECHANISM =====
        let heartbeatInterval;

        function sendHeartbeat() {
            if (!isLeavingPage && polling) {
                fetch(actionUrl("heartbeat.php"), {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ room: ROOM })
                }).catch(() => {
                    // Ignore heartbeat errors
                });
            }
        }
        
        function startHeartbeat() {
            sendHeartbeat();
            // Send heartbeat every 10 seconds - less frequent to reduce interference
            heartbeatInterval = setInterval(sendHeartbeat, 10000); // 10 seconds
        }
        
        function stopHeartbeat() {
            if (heartbeatInterval) {
                clearInterval(heartbeatInterval);
                heartbeatInterval = null;
            }
        }

        // ===== REAL-TIME STATUS MONITORING =====
        // Simplified - only for detecting when partner leaves
        let statusCheckInterval;
        
        function startStatusMonitoring() {
            statusCheckInterval = setInterval(async () => {
                if (!isLeavingPage && polling) {
                    try {
                        const response = await fetch(actionUrl('check_room_status.php') + '?room=' + encodeURIComponent(ROOM));
                        const data = await response.json();
                        
                        if (data.ok && (data.status === 'COMPLETED' || data.status === 'MISSED')) {
                            polling = false;
                            stopHeartbeat();
                            stopStatusMonitoring();
                            
                            if (MY_ROLE === 'EMPLOYER') {
                                // Jobseeker left — go find the next one
                                nextMatch();
                            } else {
                                returnJobseekerToWaiting();
                            }
                        }
                        
                        if (data.ok && data.status === 'IN_CALL') {
                            const currentPartnerName = document.getElementById('remoteVideoName').textContent;
                            if (currentPartnerName === 'Waiting...') {
                                loadParticipantNames();
                            }
                        }
                    } catch (error) {
                    }
                }
            }, 10000);
        }
        
        function stopStatusMonitoring() {
            if (statusCheckInterval) {
                clearInterval(statusCheckInterval);
                statusCheckInterval = null;
            }
        }

        function returnJobseekerToWaiting() {
            if (MY_ROLE !== 'JOBSEEKER') return;

            polling = false;
            if (jobseekerReconnectTimer) {
                clearTimeout(jobseekerReconnectTimer);
                jobseekerReconnectTimer = null;
            }
            stopHeartbeat();
            stopStatusMonitoring();

            if (pc) {
                pc.close();
                pc = null;
            }

            if (localStream) {
                localStream.getTracks().forEach(t => t.stop());
                localStream = null;
            }

            window.location.href = '/QuickHire/Public/jobseeker-dashboard.php?auto_wait=1';
        }

        function scheduleJobseekerWaitingReturn(delay = 3000) {
            if (MY_ROLE !== 'JOBSEEKER' || jobseekerReconnectTimer || isLeavingPage) return;

            jobseekerReconnectTimer = setTimeout(async () => {
                jobseekerReconnectTimer = null;
                if (MY_ROLE !== 'JOBSEEKER' || isLeavingPage || !polling) return;

                try {
                    const response = await fetch(actionUrl('check_room_status.php') + '?room=' + encodeURIComponent(ROOM));
                    const data = await response.json();
                    if (data.ok && (data.status === 'COMPLETED' || data.status === 'MISSED')) {
                        returnJobseekerToWaiting();
                        return;
                    }
                } catch (error) {
                }

                if (pc && ['failed', 'disconnected', 'closed'].includes(pc.connectionState)) {
                    returnJobseekerToWaiting();
                }
            }, delay);
        }

        // ===== PAGE CLEANUP LOGIC =====
        let isLeavingPage = false;
        let cleanupStarted = false;
        
        // Handle page unload (browser close, navigation away, etc.)
        window.addEventListener('beforeunload', function(e) {
            // Send cleanup signal synchronously
            cleanupCall();
        });
        
        // Handle page visibility change (tab switching, minimizing)
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                // Don't cleanup on tab switch - only on actual page unload
            } else {
            }
        });
        
        // Cleanup function
        function cleanupCall() {
            if (cleanupStarted) return; // Prevent multiple calls
            cleanupStarted = true;
            isLeavingPage = true;
            
            polling = false;
            stopHeartbeat();
            stopStatusMonitoring();
            
            // Send cleanup request
            fetch(actionUrl("cleanup_call.php"), {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ room: ROOM }),
                keepalive: true // Ensure request completes even if page is closing
            }).catch(() => {
                // Ignore errors during cleanup
            });
            
            // Close peer connection
            if (pc) {
                pc.close();
                pc = null;
            }
            
            // Stop local stream
            if (localStream) {
                localStream.getTracks().forEach(t => t.stop());
                localStream = null;
            }
        }

        // ===== CHAT FUNCTIONS =====
        const displayedChatIds = new Set(); // dedup by messages.id
        let sendingChatMessage = false;

        function displayChatMessage(msg) {
            if (msg.id && displayedChatIds.has(msg.id)) return;
            if (msg.id) displayedChatIds.add(msg.id);

            const chatMessages = document.getElementById('chatMessages');
            const div = document.createElement('div');
            div.className = 'message ' + (msg.sender_id == MY_ID ? 'me' : 'them');

            const sender = document.createElement('div');
            sender.className = 'message-sender';
            const senderName = msg.first_name || 'Participant';
            const senderRole = msg.role ? ` (${msg.role})` : '';
            sender.textContent = msg.sender_id == MY_ID ? 'You' : `${senderName}${senderRole}`;

            const content = document.createElement('div');
            content.className = 'message-content';
            content.textContent = msg.message;

            div.appendChild(sender);
            div.appendChild(content);
            chatMessages.appendChild(div);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        async function sendChatMessage(message) {
            try {
                const res = await fetch(actionUrl("signal_send.php"), {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ room: ROOM, type: "chat", payload: { message } })
                });

                const text = await res.text();
                let data;
                try { data = JSON.parse(text); }
                catch (e) {
                    console.error('chat signal non-JSON:', text.substring(0, 500));
                    throw new Error(`Unexpected chat server response (${res.status})`);
                }

                if (!res.ok || !data.ok) {
                    throw new Error(data.error || 'Chat send failed');
                }

                displayChatMessage({
                    id: `local_${Date.now()}_${Math.random().toString(16).slice(2)}`,
                    sender_id: MY_ID,
                    message,
                    first_name: 'You',
                    last_name: '',
                    role: MY_ROLE,
                    created_at: new Date().toISOString()
                });

                return true;
            } catch (e) {
                console.error('Chat send error:', e);
                alert(e.message || 'Failed to send message');
                return false;
            }
        }

        document.getElementById('btnSend').addEventListener('click', async () => {
            if (sendingChatMessage) return;

            const input = document.getElementById('chatInput');
            const message = input.value.trim();
            if (!message) return;

            input.value = '';
            sendingChatMessage = true;
            const sent = await sendChatMessage(message);
            sendingChatMessage = false;

            if (!sent) {
                input.value = message;
                input.focus();
            }
        });

        document.getElementById('chatInput').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') document.getElementById('btnSend').click();
        });

        async function sendSignal(type, payload) {
            try {
                await fetch(actionUrl("signal_send.php"), {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ room: ROOM, type, payload })
                });
            } catch (error) {
            }
        }

        async function pollSignals() {
            let pollCount = 0;
            while (polling) {
                try {
                    const res = await fetch(`${actionUrl('signal_poll.php')}?room=${encodeURIComponent(ROOM)}&after=${afterSignalId}`);
                    const data = await res.json();
                    
                    pollCount++;
                    if (pollCount % 10 === 0) {
                    }
                    
                    if (data.ok) {
                        afterSignalId = data.after;
                        if (data.messages && data.messages.length > 0) {
                            for (const m of data.messages) {
                                const payload = m.type === 'chat'
                                    ? { ...(m.payload || {}), id: m.id, sender_id: m.sender_id, first_name: m.first_name, last_name: m.last_name, role: m.role }
                                    : m.payload;
                                await handleSignal(m.type, payload);
                            }
                        }
                    } else {
                    }
                } catch (e) {
                }
                await new Promise(r => setTimeout(r, 700));
            }
        }

        async function initMedia() {
            try {
                localStream = await navigator.mediaDevices.getUserMedia({
                    video: { 
                        width: { ideal: 640 },
                        height: { ideal: 480 },
                        frameRate: { ideal: 30 }
                    },
                    audio: {
                        echoCancellation: true,
                        noiseSuppression: true
                    }
                });
                
                localStream.getTracks().forEach(track => {
                });
                
                const localVideo = document.getElementById('localVideo');
                if (localVideo) {
                    localVideo.srcObject = localStream;
                    
                    // Force local video to play
                    localVideo.play().then(() => {
                    }).catch(e => {
                    });
                } else {
                }
            } catch (error) {
                alert("Could not access camera/microphone. Please check permissions and try again.");
            }
        }

        function initPeer() {
            pc = new RTCPeerConnection(iceConfig);
            
            // Add local tracks
            localStream.getTracks().forEach(track => {
                pc.addTrack(track, localStream);
            });

            // Handle incoming tracks
            pc.ontrack = (event) => {
                const remoteVideo = document.getElementById('remoteVideo');
                if (remoteVideo && event.streams[0]) {
                    remoteVideo.srcObject = event.streams[0];
                    
                    const statusElement = document.getElementById('connectionStatus');
                    if (statusElement) {
                        statusElement.innerHTML = 'Connection: <strong style="color: #10b981;">Connected</strong>';
                    }
                }
            };

            // Handle ICE candidates
            pc.onicecandidate = (event) => {
                if (event.candidate) {
                    sendSignal("candidate", event.candidate);
                } else {
                }
            };

            // Helper: enable/disable chat UI
            function setChatEnabled(enabled) {
                const chatInput = document.getElementById('chatInput');
                const btnSend   = document.getElementById('btnSend');
                chatInput.disabled        = !enabled;
                chatInput.placeholder     = enabled ? 'Type a message...' : 'Connect to chat...';
                btnSend.disabled          = !enabled;
                btnSend.style.opacity     = enabled ? '1' : '0.4';
                btnSend.style.cursor      = enabled ? 'pointer' : 'not-allowed';
            }

            // Monitor connection state
            pc.onconnectionstatechange = () => {
                const statusElement = document.getElementById('connectionStatus');

                if (pc.connectionState === 'connected') {
                    if (jobseekerReconnectTimer) {
                        clearTimeout(jobseekerReconnectTimer);
                        jobseekerReconnectTimer = null;
                    }
                    if (statusElement) statusElement.innerHTML = 'Connection: <strong style="color: #10b981;">Connected</strong>';
                    setChatEnabled(true);
                } else if (pc.connectionState === 'connecting') {
                    if (statusElement) statusElement.innerHTML = 'Connection: <strong style="color: #f59e0b;">Connecting...</strong>';
                } else if (pc.connectionState === 'failed') {
                    if (statusElement) statusElement.innerHTML = 'Connection: <strong style="color: #dc2626;">Failed - Retrying...</strong>';
                    setChatEnabled(false);
                    scheduleJobseekerWaitingReturn(2500);
                    setTimeout(() => {
                        if (pc && pc.connectionState === 'failed') pc.restartIce();
                    }, 1000);
                } else if (pc.connectionState === 'disconnected') {
                    if (statusElement) statusElement.innerHTML = 'Connection: <strong style="color: #f59e0b;">Reconnecting...</strong>';
                    setChatEnabled(false);
                    scheduleJobseekerWaitingReturn(5000);
                }
            };

            // ICE connection state is a reliable fallback — some browsers fire this
            // before connectionState reaches 'connected'
            pc.oniceconnectionstatechange = () => {
                if (pc.iceConnectionState === 'connected' || pc.iceConnectionState === 'completed') {
                    if (jobseekerReconnectTimer) {
                        clearTimeout(jobseekerReconnectTimer);
                        jobseekerReconnectTimer = null;
                    }
                    const statusElement = document.getElementById('connectionStatus');
                    if (statusElement) statusElement.innerHTML = 'Connection: <strong style="color: #10b981;">Connected</strong>';
                    setChatEnabled(true);
                } else if (pc.iceConnectionState === 'failed') {
                    setChatEnabled(false);
                    scheduleJobseekerWaitingReturn(2500);
                } else if (pc.iceConnectionState === 'disconnected') {
                    setChatEnabled(false);
                    scheduleJobseekerWaitingReturn(5000);
                }
            };
            
            pc.onicegatheringstatechange = () => {
            };
            
        }

        async function makeOffer() {
            if (!pc || !localStream) {
                return;
            }
            
            try {
                const offer = await pc.createOffer();
                await pc.setLocalDescription(offer);
                await sendSignal("offer", offer);
            } catch (error) {
            }
        }

        async function handleSignal(type, payload) {
            if (type === "chat") {
                displayChatMessage({
                    id: payload?.id || `signal_${Date.now()}_${Math.random().toString(16).slice(2)}`,
                    sender_id: payload?.sender_id || 0,
                    message: payload?.message || '',
                    first_name: payload?.first_name || 'Participant',
                    last_name: payload?.last_name || '',
                    role: payload?.role || '',
                    created_at: payload?.created_at || new Date().toISOString()
                });
                return;
            }

            if (!pc) {
                return;
            }

            try {
                if (type === "offer") {
                    await pc.setRemoteDescription(new RTCSessionDescription(payload));
                    const answer = await pc.createAnswer();
                    await pc.setLocalDescription(answer);
                    await sendSignal("answer", answer);
                }

                if (type === "answer") {
                    await pc.setRemoteDescription(new RTCSessionDescription(payload));
                }

                if (type === "candidate") {
                    if (payload && payload.candidate) {
                        await pc.addIceCandidate(new RTCIceCandidate(payload));
                    } else {
                    }
                }

                if (type === "leave") {
                    endCall();
                }
            } catch (error) {
            }
        }

        function endCall() {
            if (isLeavingPage) return;
            polling = false;
            isLeavingPage = true;
            cleanupStarted = true;
            stopHeartbeat();
            stopStatusMonitoring();

            // Notify the other participant we're leaving
            fetch(actionUrl("cleanup_call.php"), {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ room: ROOM }),
                keepalive: true
            }).catch(() => {});

            if (pc) { pc.close(); pc = null; }
            if (localStream) { localStream.getTracks().forEach(t => t.stop()); localStream = null; }

            if (MY_ROLE === 'EMPLOYER') {
                submitNextJobseeker();
            } else {
                returnJobseekerToWaiting();
            }
        }

        // Submit the hidden form to find_match.php — same as clicking Find Employer from dashboard
        function submitNextJobseeker() {
            const form = document.getElementById('nextJobseekerForm');
            if (form) {
                form.submit();
            } else {
                window.location.href = '/QuickHire/Public/employer-dashboard.php';
            }
        }

        function nextMatch() {
            if (MY_ROLE !== 'EMPLOYER') {
                window.location.href = "/QuickHire/Public/jobseeker-dashboard.php";
                return;
            }
            // Guard against double-click
            if (isLeavingPage) return;
            isLeavingPage = true;
            cleanupStarted = true;
            polling = false;
            stopHeartbeat();
            stopStatusMonitoring();

            // Notify current jobseeker we're leaving
            fetch(actionUrl("cleanup_call.php"), {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ room: ROOM }),
                keepalive: true
            }).catch(() => {});

            if (pc) { pc.close(); pc = null; }
            if (localStream) { localStream.getTracks().forEach(t => t.stop()); localStream = null; }

            // Submit form → find_match.php → new WAITING room → call.php?room=NEW
            submitNextJobseeker();
        }

        document.getElementById('btnHang').addEventListener('click', () => {
            if (MY_ROLE === 'EMPLOYER') {
                const choice = confirm("End call and find another jobseeker? (Cancel to return to dashboard)");
                if (choice) {
                    nextMatch();
                } else {
                    cleanupCall();
                    window.location.href = "/QuickHire/Public/employer-dashboard.php";
                }
            } else {
                if (confirm("End call and return to dashboard?")) {
                    cleanupCall();
                    window.location.href = "/QuickHire/Public/jobseeker-dashboard.php";
                }
            }
        });
        document.getElementById('btnNext').addEventListener('click', nextMatch);

        document.getElementById('btnCam').addEventListener('click', () => {
            const track = localStream?.getVideoTracks?.()?.[0];
            if (!track) return;
            track.enabled = !track.enabled;
            document.getElementById('btnCam').textContent = track.enabled ? "📹 Camera: ON" : "📹 Camera: OFF";
        });

        document.getElementById('btnMic').addEventListener('click', () => {
            const track = localStream?.getAudioTracks?.()?.[0];
            if (!track) return;
            track.enabled = !track.enabled;
            document.getElementById('btnMic').textContent = track.enabled ? "🎤 Mic: ON" : "🎤 Mic: OFF";
        });

        // Load and display participant names
        async function loadParticipantNames() {
            try {
                // Get current user's name (for local video)
                const response = await fetch(actionUrl('get_user_info.php'));
                const userData = await response.json();
                
                if (userData.ok) {
                    const localName = `${userData.first_name} ${userData.last_name}`;
                    document.getElementById('localVideoName').textContent = `${localName} (You)`;
                }
                
                // Get partner's name from call info
                const callResponse = await fetch(actionUrl('get_call_info.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ room: ROOM })
                });
                const callData = await callResponse.json();
                
                if (callData.ok && callData.partner) {
                    const partnerName = `${callData.partner.first_name} ${callData.partner.last_name}`;
                    const partnerRole = callData.partner.role === 'EMPLOYER' ? 'Employer' : 'Jobseeker';
                    document.getElementById('remoteVideoName').textContent = `${partnerName} (${partnerRole})`;
                    return true; // Partner exists
                } else {
                    // Show "Waiting..." when no partner is connected
                    document.getElementById('remoteVideoName').textContent = 'Waiting...';
                    return false; // No partner
                }
                
            } catch (error) {
                document.getElementById('localVideoName').textContent = 'You';
                document.getElementById('remoteVideoName').textContent = 'Waiting...';
                return false;
            }
        }

        (async () => {
            try {
                
                // Setup buttons
                if (MY_ROLE === 'EMPLOYER') {
                    document.getElementById('btnNext').innerHTML = 'Next Jobseeker';
                } else {
                    document.getElementById('btnNext').style.display = 'none';
                    document.getElementById('btnHang').innerHTML = '🚪 Leave Call';
                }
                
                // Initialize media and peer connection
                await initMedia();
                initPeer();
                
                // Start monitoring
                startHeartbeat();
                startStatusMonitoring();

                // ── Load existing chat history before starting the poll loop ──
                try {
                    // Chat history polling is disabled on hosted environments that block chat_poll.php.
                } catch (e) {
                    console.warn('Could not load chat history:', e);
                }
                
                // Start polling (will only fetch NEW messages after the history cursor)
                pollSignals();
                
                // Load names
                loadParticipantNames();
                
                // Check if we have a partner before attempting connection
                setTimeout(async () => {
                    try {
                        const callResponse = await fetch(actionUrl('get_call_info.php'), {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ room: ROOM })
                        });
                        const callData = await callResponse.json();
                        
                        if (callData.ok && callData.partner) {
                            
                            // Now create offer if employer
                            if (MY_ROLE === 'EMPLOYER') {
                                makeOffer();
                            } else {
                            }
                        } else {
                            // Check again in 2 seconds
                            setTimeout(() => {
                                if (MY_ROLE === 'EMPLOYER') {
                                    makeOffer();
                                }
                            }, 2000);
                        }
                    } catch (error) {
                        // Fallback: try creating offer anyway
                        if (MY_ROLE === 'EMPLOYER') {
                            makeOffer();
                        }
                    }
                }, 3000);
                
            } catch (error) {
                alert("Failed to initialize: " + error.message);
            }
        })();
    </script>
</body>

</html>
