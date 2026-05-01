<?php
require __DIR__ . '/../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Database;

Session::start();
Auth::requireLogin();

$room = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['room'] ?? '');
if ($room === '') {
    die("Missing room code.");
}

// Optional: verify user is allowed in this call room
$config = require __DIR__ . '/../Config/config.php';
$db = new Database($config['db']);
$pdo = $db->pdo();

$stmt = $pdo->prepare("SELECT * FROM calls WHERE room_code = ? LIMIT 1");
$stmt->execute([$room]);
$call = $stmt->fetch();
if (!$call) die("Call room not found.");

$uid = Auth::userId();
$isEmployer = $uid === (int)$call['employer_user_id'];
$isJobseeker = $call['jobseeker_user_id'] && $uid === (int)$call['jobseeker_user_id'];

if (!$isEmployer && !$isJobseeker) {
    die("You are not allowed in this room.");
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
                    <div class="video-label">Your Video</div>
                    <video id="localVideo" autoplay playsinline muted></video>
                    <div class="video-name" id="localVideoName">Loading...</div>
                </div>
                <div class="video-card">
                    <div class="video-label">Partner's Video</div>
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
        const ROOM = <?= json_encode($room) ?>;
        const MY_ID = <?= json_encode(Auth::userId()) ?>;
        const MY_ROLE = <?= json_encode(Auth::role()) ?>;
        const CALL_STATUS = <?= json_encode($call['status']) ?>;

        // Always show video interface - no waiting screen
        // Regular call functionality

        let localStream = null;
        let pc = null;
        let afterSignalId = 0;
        let afterChatId = 0;
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
                    fetch("/QuickHire/Public/actions/heartbeat.php", {
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
                        const response = await fetch('/QuickHire/Public/actions/check_room_status.php?room=' + encodeURIComponent(ROOM));
                        const data = await response.json();
                        
                        if (data.ok && (data.status === 'COMPLETED' || data.status === 'MISSED')) {
                            console.log('Call ended by partner, redirecting to dashboard');
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
                        console.error('Status check error:', error);
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
                console.log('Tab hidden - but keeping call active');
                // Don't cleanup on tab switch - only on actual page unload
            } else {
                console.log('Tab visible again');
            }
        });
        
        // Cleanup function
        function cleanupCall() {
            if (isLeavingPage) return; // Prevent multiple calls
            isLeavingPage = true;
            
            console.log("Cleaning up call...");
            polling = false;
            stopHeartbeat();
            stopStatusMonitoring();
            
            // Send cleanup request
            fetch("/QuickHire/Public/actions/cleanup_call.php", {
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
        async function sendChatMessage(message) {
            await fetch("/QuickHire/Public/actions/chat_send.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ room: ROOM, message })
            });
        }

        async function pollChatMessages() {
            while (polling) {
                try {
                    const res = await fetch(`/QuickHire/Public/actions/chat_poll.php?room=${encodeURIComponent(ROOM)}&after=${afterChatId}`);
                    const data = await res.json();
                    if (data.ok) {
                        afterChatId = data.after;
                        for (const msg of data.messages) {
                            displayChatMessage(msg);
                        }
                    }
                } catch (e) {
                    console.error("Chat poll error:", e);
                }
                await new Promise(r => setTimeout(r, 500));
            }
        }

        function displayChatMessage(msg) {
            const chatMessages = document.getElementById('chatMessages');
            const messageDiv = document.createElement('div');
            messageDiv.className = 'message ' + (msg.sender_id == MY_ID ? 'me' : 'them');
            
            const senderDiv = document.createElement('div');
            senderDiv.className = 'message-sender';
            senderDiv.textContent = msg.sender_id == MY_ID ? 'You' : `${msg.first_name} (${msg.role})`;
            
            const contentDiv = document.createElement('div');
            contentDiv.className = 'message-content';
            contentDiv.textContent = msg.message;
            
            messageDiv.appendChild(senderDiv);
            messageDiv.appendChild(contentDiv);
            chatMessages.appendChild(messageDiv);
            
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        document.getElementById('btnSend').addEventListener('click', () => {
            const input = document.getElementById('chatInput');
            const message = input.value.trim();
            if (message) {
                sendChatMessage(message);
                input.value = '';
            }
        });

        document.getElementById('chatInput').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                document.getElementById('btnSend').click();
            }
        });

        async function sendSignal(type, payload) {
            try {
                await fetch("/QuickHire/Public/actions/signal_send.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ room: ROOM, type, payload })
                });
                console.log("Signal sent:", type);
            } catch (error) {
                console.error("Error sending signal:", type, error);
            }
        }

        async function pollSignals() {
            console.log("Starting signal polling...");
            let pollCount = 0;
            while (polling) {
                try {
                    const res = await fetch(`/QuickHire/Public/actions/signal_poll.php?room=${encodeURIComponent(ROOM)}&after=${afterSignalId}`);
                    const data = await res.json();
                    
                    pollCount++;
                    if (pollCount % 10 === 0) {
                        console.log(`📊 Poll #${pollCount} - afterSignalId: ${afterSignalId}`);
                    }
                    
                    if (data.ok) {
                        afterSignalId = data.after;
                        if (data.messages && data.messages.length > 0) {
                            console.log(`📬 Received ${data.messages.length} signal(s)`);
                            for (const m of data.messages) {
                                console.log("📨 Processing signal:", m.type);
                                await handleSignal(m.type, m.payload);
                            }
                        }
                    } else {
                        console.error("❌ Signal poll failed:", data);
                    }
                } catch (e) {
                    console.error("❌ Signal poll error:", e);
                }
                await new Promise(r => setTimeout(r, 700));
            }
            console.log("Signal polling stopped");
        }

        async function initMedia() {
            try {
                console.log("Requesting user media...");
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
                
                console.log("User media obtained, tracks:", localStream.getTracks().length);
                localStream.getTracks().forEach(track => {
                    console.log("Local track:", track.kind, track.enabled, track.readyState);
                });
                
                const localVideo = document.getElementById('localVideo');
                if (localVideo) {
                    localVideo.srcObject = localStream;
                    console.log("Local video stream assigned");
                    
                    // Force local video to play
                    localVideo.play().then(() => {
                        console.log("Local video playing successfully");
                    }).catch(e => {
                        console.log("Local video play error:", e);
                    });
                } else {
                    console.error("Local video element not found!");
                }
            } catch (error) {
                console.error("Error getting user media:", error);
                alert("Could not access camera/microphone. Please check permissions and try again.");
            }
        }

        function initPeer() {
            console.log("🔧 Initializing peer connection...");
            pc = new RTCPeerConnection(iceConfig);
            
            // Add local tracks
            localStream.getTracks().forEach(track => {
                console.log("➕ Adding track:", track.kind);
                pc.addTrack(track, localStream);
            });

            // Handle incoming tracks
            pc.ontrack = (event) => {
                console.log("🎉 Remote track received:", event.track.kind);
                const remoteVideo = document.getElementById('remoteVideo');
                if (remoteVideo && event.streams[0]) {
                    remoteVideo.srcObject = event.streams[0];
                    console.log("✅ Remote video connected!");
                    
                    const statusElement = document.getElementById('connectionStatus');
                    if (statusElement) {
                        statusElement.innerHTML = 'Connection: <strong style="color: #10b981;">Connected</strong>';
                    }
                }
            };

            // Handle ICE candidates
            pc.onicecandidate = (event) => {
                if (event.candidate) {
                    console.log("📤 Sending ICE candidate:", event.candidate.type || 'unknown');
                    sendSignal("candidate", event.candidate);
                } else {
                    console.log("✅ ICE gathering complete");
                }
            };

            // Monitor connection state
            pc.onconnectionstatechange = () => {
                console.log("🔄 Connection state:", pc.connectionState);
                const statusElement = document.getElementById('connectionStatus');
                const chatInput = document.getElementById('chatInput');
                const btnSend = document.getElementById('btnSend');

                if (statusElement) {
                    if (pc.connectionState === 'connected') {
                        statusElement.innerHTML = 'Connection: <strong style="color: #10b981;">Connected</strong>';
                        // Enable chat
                        chatInput.disabled = false;
                        chatInput.placeholder = 'Type a message...';
                        btnSend.disabled = false;
                        btnSend.style.opacity = '1';
                        btnSend.style.cursor = 'pointer';
                        console.log("🎉 WebRTC connection established!");
                    } else if (pc.connectionState === 'connecting') {
                        statusElement.innerHTML = 'Connection: <strong style="color: #f59e0b;">Connecting...</strong>';
                    } else if (pc.connectionState === 'failed') {
                        statusElement.innerHTML = 'Connection: <strong style="color: #dc2626;">Failed - Retrying...</strong>';
                        // Disable chat on failure
                        chatInput.disabled = true;
                        chatInput.placeholder = 'Connect to chat...';
                        btnSend.disabled = true;
                        btnSend.style.opacity = '0.4';
                        btnSend.style.cursor = 'not-allowed';
                        console.log("❌ Connection failed, attempting to restart...");
                        setTimeout(() => {
                            if (pc.connectionState === 'failed') {
                                console.log("🔄 Restarting ICE...");
                                pc.restartIce();
                            }
                        }, 1000);
                    } else if (pc.connectionState === 'disconnected') {
                        statusElement.innerHTML = 'Connection: <strong style="color: #f59e0b;">Reconnecting...</strong>';
                        // Disable chat while reconnecting
                        chatInput.disabled = true;
                        chatInput.placeholder = 'Reconnecting...';
                        btnSend.disabled = true;
                        btnSend.style.opacity = '0.4';
                        btnSend.style.cursor = 'not-allowed';
                    }
                }
            };
            
            pc.oniceconnectionstatechange = () => {
                console.log("🧊 ICE state:", pc.iceConnectionState);
                if (pc.iceConnectionState === 'failed') {
                    console.log("❌ ICE connection failed");
                } else if (pc.iceConnectionState === 'connected' || pc.iceConnectionState === 'completed') {
                    console.log("✅ ICE connection successful!");
                }
            };
            
            pc.onicegatheringstatechange = () => {
                console.log("📊 ICE gathering state:", pc.iceGatheringState);
            };
            
            console.log("✅ Peer connection initialized");
        }

        async function makeOffer() {
            if (!pc || !localStream) {
                console.error("❌ Cannot make offer - missing:", {
                    pc: !!pc,
                    localStream: !!localStream
                });
                return;
            }
            
            try {
                console.log("📤 Creating offer...");
                const offer = await pc.createOffer();
                console.log("✅ Offer created");
                await pc.setLocalDescription(offer);
                console.log("✅ Local description set");
                await sendSignal("offer", offer);
                console.log("✅ Offer sent to partner");
            } catch (error) {
                console.error("❌ Error making offer:", error);
            }
        }

        async function handleSignal(type, payload) {
            if (!pc) {
                console.log("❌ No peer connection for signal:", type);
                return;
            }

            try {
                if (type === "offer") {
                    console.log("📥 Received offer from partner");
                    await pc.setRemoteDescription(new RTCSessionDescription(payload));
                    console.log("✅ Remote description set");
                    const answer = await pc.createAnswer();
                    console.log("✅ Answer created");
                    await pc.setLocalDescription(answer);
                    console.log("✅ Local description set");
                    await sendSignal("answer", answer);
                    console.log("✅ Answer sent to partner");
                }

                if (type === "answer") {
                    console.log("📥 Received answer from partner");
                    await pc.setRemoteDescription(new RTCSessionDescription(payload));
                    console.log("✅ Answer processed");
                }

                if (type === "candidate") {
                    console.log("📥 Received ICE candidate");
                    if (payload && payload.candidate) {
                        await pc.addIceCandidate(new RTCIceCandidate(payload));
                        console.log("✅ ICE candidate added");
                    } else {
                        console.log("⚠️ Empty candidate (end of candidates)");
                    }
                }

                if (type === "leave") {
                    console.log("👋 Partner left");
                    endCall();
                }
            } catch (error) {
                console.error("❌ Error handling signal:", type, error);
            }
        }

        function endCall() {
            console.log("endCall() triggered - finding next match...");
            
            if (isLeavingPage) return; // Already cleaning up
            
            polling = false;
            
            // Use cleanup endpoint instead of just sending signal
            cleanupCall();
            
            // Auto-find next match instead of asking user
            findNextMatchAuto();
        }

        async function findNextMatchAuto() {
            console.log("Auto-finding next match...");
            
            // Show "Finding next match..." in the interface
            showFindingNextMatch();
            
            try {
                const response = await fetch("/QuickHire/Public/actions/next_match.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ room: ROOM })
                });
                const data = await response.json();
                
                if (data.ok && data.room) {
                    console.log("Next match found:", data.room);
                    // Update room code and restart call
                    ROOM = data.room;
                    restartCall();
                } else if (data.redirect === 'dashboard') {
                    console.log("Jobseeker redirecting to dashboard");
                    // Jobseeker should return to dashboard
                    window.location.href = '/QuickHire/Public/jobseeker-dashboard.php';
                } else {
                    console.log("No more matches, trying to find new match...");
                    // Try to find a completely new match (employers only)
                    await findNewMatch();
                }
            } catch (error) {
                console.error("Error finding next match:", error);
                // Fallback: try to find new match
                await findNewMatch();
            }
        }

        async function findNewMatch() {
            try {
                if (MY_ROLE === 'EMPLOYER') {
                    // For employer, try to use saved preferences first
                    const savedPrefs = localStorage.getItem('matchingPreferences');
                    let response;
                    
                    if (savedPrefs) {
                        const preferences = JSON.parse(savedPrefs);
                        const formData = new FormData();
                        formData.append('role_title', preferences.role_title);
                        formData.append('country', preferences.country);
                        formData.append('employment_type', preferences.employment_type);
                        
                        if (preferences.skill_ids && preferences.skill_ids.length > 0) {
                            preferences.skill_ids.forEach(skillId => {
                                formData.append('skill_ids[]', skillId);
                            });
                        }
                        
                        response = await fetch('/QuickHire/Public/actions/find_match.php', {
                            method: 'POST',
                            body: formData
                        });
                    } else {
                        // Fallback to default criteria if no preferences saved
                        const formData = new FormData();
                        formData.append('role_title', 'Developer');
                        formData.append('country', 'Philippines');
                        formData.append('employment_type', 'FULL_TIME');
                        formData.append('skill_ids[]', []);
                        
                        response = await fetch('/QuickHire/Public/actions/find_match.php', {
                            method: 'POST',
                            body: formData
                        });
                    }
                    
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
                    console.log("Jobseeker can't create new matches, redirecting to dashboard");
                    window.location.href = '/QuickHire/Public/jobseeker-dashboard.php';
                }
                
            } catch (error) {
                console.error("Error finding new match:", error);
                if (MY_ROLE === 'JOBSEEKER') {
                    // Redirect jobseekers to dashboard on error
                    window.location.href = '/QuickHire/Public/jobseeker-dashboard.php';
                } else {
                    showNoMatchFound();
                }
            }
        }

        function restartCall() {
            console.log("Restarting call with room:", ROOM);
            
            // Reset try again counter on successful match
            resetTryAgainCounter();
            
            // Reset variables
            afterSignalId = 0;
            afterChatId = 0;
            polling = true;
            
            // Clear chat messages
            document.getElementById('chatMessages').innerHTML = '';
            
            // Hide "finding match" message
            hideFindingNextMatch();
            
            // Reset name displays
            document.getElementById('remoteVideoName').textContent = 'Waiting...';
            
            // Initialize new peer connection
            initPeer();
            
            // Send join signal for new room
            sendSignal("join", { joined: true });
            
            // Don't create offer immediately - let join signal trigger it for employers
            console.log("Join signal sent, waiting for connection...");
            
            // Restart polling
            pollSignals();
            pollChatMessages();
            
            // Reload participant names for new call
            setTimeout(() => loadParticipantNames(), 1000);
            
            console.log("Call restarted successfully");
        }

        function showFindingNextMatch() {
            // Update video labels to show "Finding next match..."
            const labels = document.querySelectorAll('.video-label');
            labels.forEach(label => {
                if (label.textContent.includes('Your')) {
                    label.textContent = 'Your Video (Finding next match...)';
                } else {
                    label.textContent = 'Connecting to next match...';
                }
            });
            
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
            // Restore original video labels
            const labels = document.querySelectorAll('.video-label');
            labels.forEach(label => {
                if (label.textContent.includes('Your')) {
                    label.textContent = 'Your Video';
                } else {
                    label.textContent = "Partner's Video";
                }
            });
            
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
            
            console.log("User clicked Next - processing...");
            cleanupCall();
            
            if (MY_ROLE === 'EMPLOYER') {
                // Employers can find next match
                console.log("Employer finding next match...");
                // Don't stop camera - keep it running for next match
                findNextMatchAuto();
            } else {
                // Jobseekers return to dashboard
                console.log("Jobseeker returning to dashboard...");
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
                    console.log("Employer chose to find another match");
                    cleanupCall();
                    findNextMatchAuto();
                } else {
                    // Return to dashboard
                    console.log("Employer chose to return to dashboard");
                    cleanupCall();
                    window.location.href = "/QuickHire/Public/employer-dashboard.php";
                }
            } else {
                // Jobseeker - can only return to dashboard
                message = "End call and return to dashboard?";
                choice = confirm(message);
                if (choice) {
                    console.log("Jobseeker chose to return to dashboard");
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
                const response = await fetch('/QuickHire/Public/actions/get_user_info.php');
                const userData = await response.json();
                
                if (userData.ok) {
                    const localName = `${userData.first_name} ${userData.last_name}`;
                    document.getElementById('localVideoName').textContent = `${localName} (You)`;
                }
                
                // Get partner's name from call info
                const callResponse = await fetch('/QuickHire/Public/actions/get_call_info.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ room: ROOM })
                });
                const callData = await callResponse.json();
                
                if (callData.ok && callData.partner) {
                    const partnerName = `${callData.partner.first_name} ${callData.partner.last_name}`;
                    const partnerRole = callData.partner.role === 'EMPLOYER' ? 'Employer' : 'Jobseeker';
                    document.getElementById('remoteVideoName').textContent = `${partnerName} (${partnerRole})`;
                    console.log("👥 Partner found:", partnerName, "-", partnerRole);
                    return true; // Partner exists
                } else {
                    // Show "Waiting..." when no partner is connected
                    document.getElementById('remoteVideoName').textContent = 'Waiting...';
                    console.log("⏳ No partner yet");
                    return false; // No partner
                }
                
            } catch (error) {
                console.error('Error loading participant names:', error);
                document.getElementById('localVideoName').textContent = 'You';
                document.getElementById('remoteVideoName').textContent = 'Waiting...';
                return false;
            }
        }

        (async () => {
            try {
                console.log("=== INITIALIZING CALL ===");
                console.log("Room:", ROOM, "| Role:", MY_ROLE, "| User ID:", MY_ID);
                console.log("⚠️ IMPORTANT: Make sure you're testing with TWO DIFFERENT user accounts!");
                console.log("⚠️ Employer account in one browser, Jobseeker account in another!");
                
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
                
                // Start polling
                pollSignals();
                pollChatMessages();
                
                // Load names
                loadParticipantNames();
                
                // Check if we have a partner before attempting connection
                setTimeout(async () => {
                    try {
                        const callResponse = await fetch('/QuickHire/Public/actions/get_call_info.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ room: ROOM })
                        });
                        const callData = await callResponse.json();
                        
                        if (callData.ok && callData.partner) {
                            console.log("✅ Partner detected:", callData.partner.first_name);
                            
                            // Now create offer if employer
                            if (MY_ROLE === 'EMPLOYER') {
                                console.log("📤 Employer creating offer...");
                                makeOffer();
                            } else {
                                console.log("⏳ Jobseeker waiting for offer...");
                            }
                        } else {
                            console.log("⚠️ No partner in room yet, waiting...");
                            // Check again in 2 seconds
                            setTimeout(() => {
                                if (MY_ROLE === 'EMPLOYER') {
                                    console.log("📤 Employer creating offer (delayed)...");
                                    makeOffer();
                                }
                            }, 2000);
                        }
                    } catch (error) {
                        console.error("Error checking for partner:", error);
                        // Fallback: try creating offer anyway
                        if (MY_ROLE === 'EMPLOYER') {
                            console.log("📤 Employer creating offer (fallback)...");
                            makeOffer();
                        }
                    }
                }, 3000);
                
                console.log("=== INITIALIZATION COMPLETE ===");
            } catch (error) {
                console.error("Initialization error:", error);
                alert("Failed to initialize: " + error.message);
            }
        })();
    </script>
</body>

</html>