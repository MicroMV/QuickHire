<?php
require __DIR__ . '/../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Database;

Session::start();
Auth::requireLogin();

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
        
        function startHeartbeat() {
            // Send heartbeat every 10 seconds - less frequent to reduce interference
            heartbeatInterval = setInterval(() => {
                if (!isLeavingPage && polling) {
                    fetch(actionUrl("heartbeat.php"), {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify({ room: ROOM })
                    }).catch(() => {
                        // Ignore heartbeat errors
                    });
                }
            }, 10000); // 10 seconds
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
            // Check call status every 10 seconds - less frequent to reduce interference
            statusCheckInterval = setInterval(async () => {
                if (!isLeavingPage && polling) {
                    try {
                        const response = await fetch(actionUrl('check_room_status.php') + '?room=' + encodeURIComponent(ROOM));
                        const data = await response.json();
                        
                        if (data.ok && (data.status === 'COMPLETED' || data.status === 'MISSED')) {
                            polling = false;
                            stopHeartbeat();
                            stopStatusMonitoring();
                            
                            // Redirect based on role
                            if (MY_ROLE === 'EMPLOYER') {
                                window.location.href = '/QuickHire/Public/employer-dashboard.php';
                            } else {
                                window.location.href = '/QuickHire/Public/jobseeker-dashboard.php';
                            }
                        }
                        
                        // Update partner name if status changed (someone joined)
                        if (data.ok && data.status === 'IN_CALL') {
                            const currentPartnerName = document.getElementById('remoteVideoName').textContent;
                            if (currentPartnerName === 'Waiting...') {
                                // Partner joined, update name
                                loadParticipantNames();
                            }
                        }
                    } catch (error) {
                    }
                }
            }, 10000); // Check every 10 seconds
        }
        
        function stopStatusMonitoring() {
            if (statusCheckInterval) {
                clearInterval(statusCheckInterval);
                statusCheckInterval = null;
            }
        }

        // ===== PAGE CLEANUP LOGIC =====
        let isLeavingPage = false;
        
        // Handle page unload (browser close, navigation away, etc.)
        window.addEventListener('beforeunload', function(e) {
            isLeavingPage = true;
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
            if (isLeavingPage) return; // Prevent multiple calls
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
                    if (statusElement) statusElement.innerHTML = 'Connection: <strong style="color: #10b981;">Connected</strong>';
                    setChatEnabled(true);
                } else if (pc.connectionState === 'connecting') {
                    if (statusElement) statusElement.innerHTML = 'Connection: <strong style="color: #f59e0b;">Connecting...</strong>';
                } else if (pc.connectionState === 'failed') {
                    if (statusElement) statusElement.innerHTML = 'Connection: <strong style="color: #dc2626;">Failed - Retrying...</strong>';
                    setChatEnabled(false);
                    setTimeout(() => {
                        if (pc && pc.connectionState === 'failed') pc.restartIce();
                    }, 1000);
                } else if (pc.connectionState === 'disconnected') {
                    if (statusElement) statusElement.innerHTML = 'Connection: <strong style="color: #f59e0b;">Reconnecting...</strong>';
                    setChatEnabled(false);
                }
            };

            // ICE connection state is a reliable fallback — some browsers fire this
            // before connectionState reaches 'connected'
            pc.oniceconnectionstatechange = () => {
                if (pc.iceConnectionState === 'connected' || pc.iceConnectionState === 'completed') {
                    const statusElement = document.getElementById('connectionStatus');
                    if (statusElement) statusElement.innerHTML = 'Connection: <strong style="color: #10b981;">Connected</strong>';
                    setChatEnabled(true);
                } else if (pc.iceConnectionState === 'failed') {
                    setChatEnabled(false);
                } else if (pc.iceConnectionState === 'disconnected') {
                    setChatEnabled(false);
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
            cleanupCall();

            if (MY_ROLE === 'EMPLOYER') {
                // Employer auto-finds next match
                findNextMatchAuto();
            } else {
                // Jobseeker returns to dashboard when partner leaves
                window.location.href = '/QuickHire/Public/jobseeker-dashboard.php';
            }
        }

        async function findNextMatchAuto() {
            if (MY_ROLE !== 'EMPLOYER') {
                window.location.href = '/QuickHire/Public/jobseeker-dashboard.php';
                return;
            }
            
            // Show "Finding next match..." in the interface
            showFindingNextMatch();
            
            try {
                const response = await fetch(actionUrl("next_match.php"), {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ room: ROOM })
                });
                const data = await response.json();
                
                if (data.ok && data.room) {
                    // Update room code and restart call
                    ROOM = data.room;
                    restartCall();
                } else if (data.redirect === 'dashboard') {
                    // Jobseeker should return to dashboard
                    window.location.href = '/QuickHire/Public/jobseeker-dashboard.php';
                } else {
                    // Try to find a completely new match (employers only)
                    await findNewMatch();
                }
            } catch (error) {
                // Fallback: try to find new match
                await findNewMatch();
            }
        }

        async function findNewMatch() {
            try {
                if (MY_ROLE === 'EMPLOYER') {
                    const preferences = getSavedMatchingPreferences();

                    if (!preferences) {
                        alert('Please set your matching preferences before finding another jobseeker.');
                        window.location.href = '/QuickHire/Public/employer-dashboard.php';
                        return;
                    }

                    const formData = new FormData();
                    formData.append('role_title', preferences.role_title);
                    formData.append('country', preferences.country);
                    formData.append('employment_type', preferences.employment_type || 'FULL_TIME');

                    if (preferences.skill_ids && preferences.skill_ids.length > 0) {
                        preferences.skill_ids.forEach(skillId => {
                            formData.append('skill_ids[]', skillId);
                        });
                    }

                    const response = await fetch(actionUrl('find_match.php'), {
                        method: 'POST',
                        body: formData
                    });
                    
                    if (response.redirected) {
                        // Extract room from redirect URL
                        const url = new URL(response.url);
                        const roomParam = url.searchParams.get('room');
                        if (roomParam) {
                            ROOM = roomParam;
                            restartCall();
                            return;
                        }
                    }
                    
                    // No match found for employer
                    showNoMatchFound();
                    
                } else {
                    // Jobseekers can't create new matches - redirect to dashboard
                    window.location.href = '/QuickHire/Public/jobseeker-dashboard.php';
                }
                
            } catch (error) {
                if (MY_ROLE === 'JOBSEEKER') {
                    // Redirect jobseekers to dashboard on error
                    window.location.href = '/QuickHire/Public/jobseeker-dashboard.php';
                } else {
                    showNoMatchFound();
                }
            }
        }

        function restartCall() {
            
            // Reset try again counter on successful match
            resetTryAgainCounter();
            
            // Reset variables
            afterSignalId = 0;
            polling = true;
            
            // Clear chat messages and dedup sets for the new room
            document.getElementById('chatMessages').innerHTML = '';
            displayedChatIds.clear();
            sendingChatMessage = false;
            
            // Hide "finding match" message
            hideFindingNextMatch();
            
            // Reset name displays
            document.getElementById('remoteVideoName').textContent = 'Waiting...';
            
            // Initialize new peer connection
            initPeer();
            
            // Send join signal for new room
            sendSignal("join", { joined: true });
            
            // Restart polling
            pollSignals();
            
            // Reload participant names for new call
            setTimeout(() => loadParticipantNames(), 1000);
            
        }

        function showFindingNextMatch() {
            // Show overlay message
            const overlay = document.createElement('div');
            overlay.id = 'findingOverlay';
            overlay.style.cssText = `
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: #0f172a;
                padding: 30px 40px;
                border-radius: 16px;
                z-index: 9999;
                color: #f8fafc;
                font-size: 20px;
                font-weight: 900;
                text-align: center;
                border: 1px solid rgba(255,255,255,0.1);
                box-shadow: 0 24px 60px rgba(0,0,0,0.5);
            `;
            overlay.innerHTML = `
                <div style="margin-bottom: 15px;">🔍 Finding next match...</div>
                <div style="font-size: 14px; opacity: 0.8;">Keep your camera ready!</div>
            `;
            document.body.appendChild(overlay);
        }

        function hideFindingNextMatch() {
            const overlay = document.getElementById('findingOverlay');
            if (overlay) {
                overlay.remove();
            }
        }

        function showNoMatchFound() {
            const overlay = document.getElementById('findingOverlay');
            if (overlay) {
                overlay.innerHTML = `
                    <div style="margin-bottom:20px;font-size:22px;font-weight:900;color:#f8fafc;">😔 No more matches available</div>
                    <div style="font-size:14px;margin-bottom:25px;color:#94a3b8;">Try again later or return to dashboard</div>
                    <button onclick="findNextMatchAuto()" 
                            style="padding:11px 22px;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:white;border:none;border-radius:10px;font-weight:700;cursor:pointer;margin-right:10px;font-size:14px;">
                        Try Again
                    </button>
                    <button onclick="window.location.href='/QuickHire/Public/' + (MY_ROLE === 'EMPLOYER' ? 'employer' : 'jobseeker') + '-dashboard.php'" 
                            style="padding:11px 22px;background:rgba(255,255,255,0.08);color:#e2e8f0;border:1px solid rgba(255,255,255,0.15);border-radius:10px;font-weight:700;cursor:pointer;font-size:14px;">
                        Dashboard
                    </button>
                `;
            }
        }

        async function nextMatch() {
            let message;
            
            if (MY_ROLE === 'EMPLOYER') {
                message = "Skip to next jobseeker?";
            } else {
                message = "End call and return to dashboard? (Jobseekers cannot initiate new matches)";
            }
            
            if (!confirm(message)) return;
            
            cleanupCall();
            
            if (MY_ROLE === 'EMPLOYER') {
                // Employers can find next match
                // Don't stop camera - keep it running for next match
                findNextMatchAuto();
            } else {
                // Jobseekers return to dashboard
                window.location.href = "/QuickHire/Public/jobseeker-dashboard.php";
            }
        }

        document.getElementById('btnHang').addEventListener('click', () => {
            let message, choice;
            
            if (MY_ROLE === 'EMPLOYER') {
                message = "End call and find another jobseeker? (Cancel to return to dashboard)";
                choice = confirm(message);
                if (choice) {
                    // Employer can find another match
                    cleanupCall();
                    findNextMatchAuto();
                } else {
                    // Return to dashboard
                    cleanupCall();
                    window.location.href = "/QuickHire/Public/employer-dashboard.php";
                }
            } else {
                // Jobseeker - can only return to dashboard
                message = "End call and return to dashboard?";
                choice = confirm(message);
                if (choice) {
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
