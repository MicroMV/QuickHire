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
$config = require __DIR__ . '/../config/config.php';
$db = new Database($config['db']);
$pdo = $db->pdo();

$stmt = $pdo->prepare("SELECT * FROM calls WHERE room_code = ? LIMIT 1");
$stmt->execute([$room]);
$call = $stmt->fetch();
if (!$call) die("Call room not found.");

$uid = Auth::userId();
if ($uid !== (int)$call['employer_user_id'] && $uid !== (int)$call['jobseeker_user_id']) {
    die("You are not allowed in this room.");
}
$pdo->prepare("UPDATE calls SET status='IN_CALL' WHERE room_code=? AND status='RINGING'")->execute([$room]);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>QuickHire Call</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Inter, system-ui, Arial;
            background: #0b1220;
            color: #fff;
            overflow: hidden;
        }

        .container {
            display: flex;
            height: 100vh;
            width: 100vw;
        }

        /* Video Section */
        .video-section {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 16px;
            gap: 12px;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            background: rgba(255, 255, 255, .06);
            border: 1px solid rgba(255, 255, 255, .12);
            border-radius: 12px;
        }

        .badge {
            padding: 6px 12px;
            border: 1px solid rgba(255, 255, 255, .15);
            border-radius: 999px;
            font-size: 13px;
            font-weight: 700;
        }

        .video-grid {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 12px;
            min-height: 0;
            align-items: center;
            justify-content: center;
        }

        .video-card {
            background: rgba(255, 255, 255, .06);
            border: 1px solid rgba(255, 255, 255, .12);
            border-radius: 16px;
            padding: 12px;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
            width: 100%;
            max-width: 500px;
            aspect-ratio: 1;
        }

        .video-label {
            font-weight: 900;
            margin-bottom: 8px;
            font-size: 14px;
            z-index: 10;
            position: relative;
        }

        video {
            width: 100%;
            height: 100%;
            background: #000;
            border-radius: 12px;
            object-fit: cover;
            position: absolute;
            top: 0;
            left: 0;
        }

        .controls {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            padding: 12px 16px;
            background: rgba(255, 255, 255, .06);
            border: 1px solid rgba(255, 255, 255, .12);
            border-radius: 12px;
        }

        button {
            padding: 12px 16px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, .18);
            background: rgba(255, 255, 255, .08);
            color: #fff;
            font-weight: 800;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
        }

        button:hover {
            background: rgba(255, 255, 255, .15);
        }

        button.danger {
            background: #b42318;
            border-color: #b42318;
        }

        button.danger:hover {
            background: #991f14;
        }

        button.success {
            background: #16a34a;
            border-color: #16a34a;
        }

        button.success:hover {
            background: #15803d;
        }

        /* Chat Section */
        .chat-section {
            width: 380px;
            background: rgba(255, 255, 255, .04);
            border-left: 1px solid rgba(255, 255, 255, .12);
            display: flex;
            flex-direction: column;
        }

        .chat-header {
            padding: 16px;
            border-bottom: 1px solid rgba(255, 255, 255, .12);
            font-weight: 900;
            font-size: 16px;
        }

        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .message {
            display: flex;
            flex-direction: column;
            gap: 4px;
            max-width: 85%;
        }

        .message.me {
            align-self: flex-end;
        }

        .message.them {
            align-self: flex-start;
        }

        .message-sender {
            font-size: 11px;
            font-weight: 700;
            color: rgba(255, 255, 255, .5);
            padding: 0 8px;
        }

        .message-content {
            padding: 10px 14px;
            border-radius: 16px;
            word-wrap: break-word;
            line-height: 1.4;
            font-size: 14px;
        }

        .message.me .message-content {
            background: #1f6f82;
            color: #fff;
            border-bottom-right-radius: 4px;
        }

        .message.them .message-content {
            background: rgba(255, 255, 255, .1);
            color: #fff;
            border-bottom-left-radius: 4px;
        }

        .chat-input-area {
            padding: 16px;
            border-top: 1px solid rgba(255, 255, 255, .12);
            display: flex;
            gap: 10px;
        }

        .chat-input {
            flex: 1;
            padding: 12px 14px;
            border: 1px solid rgba(255, 255, 255, .18);
            border-radius: 12px;
            background: rgba(255, 255, 255, .06);
            color: #fff;
            font-family: inherit;
            font-size: 14px;
        }

        .chat-input:focus {
            outline: none;
            border-color: #1f6f82;
        }

        .chat-input::placeholder {
            color: rgba(255, 255, 255, .4);
        }

        .send-btn {
            padding: 12px 20px;
            background: #1f6f82;
            border: none;
            border-radius: 12px;
            color: #fff;
            font-weight: 800;
            cursor: pointer;
        }

        .send-btn:hover {
            background: #1a5f70;
        }

        @media (max-width: 900px) {
            .container {
                flex-direction: column;
            }

            .chat-section {
                width: 100%;
                height: 40vh;
                border-left: none;
                border-top: 1px solid rgba(255, 255, 255, .12);
            }

            .video-grid {
                flex-direction: row;
                gap: 8px;
            }

            .video-card {
                max-width: none;
                width: 50%;
                aspect-ratio: 1;
            }
        }

        /* Scrollbar styling */
        .chat-messages::-webkit-scrollbar {
            width: 6px;
        }

        .chat-messages::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, .05);
        }

        .chat-messages::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, .2);
            border-radius: 3px;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Video Section -->
        <div class="video-section">
            <div class="top-bar">
                <div class="badge">Room: <strong><?= htmlspecialchars($room) ?></strong></div>
                <div class="badge">You: <strong><?= htmlspecialchars(Auth::role()) ?></strong></div>
            </div>

            <div class="video-grid">
                <div class="video-card">
                    <div class="video-label">Your Video</div>
                    <video id="localVideo" autoplay playsinline muted></video>
                </div>
                <div class="video-card">
                    <div class="video-label">Partner's Video</div>
                    <video id="remoteVideo" autoplay playsinline></video>
                </div>
            </div>

            <div class="controls">
                <button id="btnCam">📹 Camera: ON</button>
                <button id="btnMic">🎤 Mic: ON</button>
                <button id="btnNext" class="success">⏭️ Next</button>
                <button id="btnHang" class="danger">📞 End Call</button>
            </div>
        </div>

        <!-- Chat Section -->
        <div class="chat-section">
            <div class="chat-header">💬 Chat</div>
            <div class="chat-messages" id="chatMessages"></div>
            <div class="chat-input-area">
                <input type="text" id="chatInput" class="chat-input" placeholder="Type a message..." />
                <button id="btnSend" class="send-btn">Send</button>
            </div>
        </div>
    </div>

    <script>
        const ROOM = <?= json_encode($room) ?>;
        const MY_ID = <?= json_encode(Auth::userId()) ?>;
        const MY_ROLE = <?= json_encode(Auth::role()) ?>;

        let localStream = null;
        let pc = null;
        let afterSignalId = 0;
        let afterChatId = 0;
        let polling = true;

        const iceConfig = {
            iceServers: [{
                urls: "stun:stun.l.google.com:19302"
            }]
        };

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

        // ===== WEBRTC FUNCTIONS =====
        async function sendSignal(type, payload) {
            await fetch("/QuickHire/Public/actions/singal_send.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ room: ROOM, type, payload })
            });
        }

        async function pollSignals() {
            while (polling) {
                try {
                    const res = await fetch(`/QuickHire/Public/actions/signal_poll.php?room=${encodeURIComponent(ROOM)}&after=${afterSignalId}`);
                    const data = await res.json();
                    if (data.ok) {
                        afterSignalId = data.after;
                        for (const m of data.messages) {
                            await handleSignal(m.type, m.payload);
                        }
                    }
                } catch (e) {
                    console.error("Signal poll error:", e);
                }
                await new Promise(r => setTimeout(r, 700));
            }
        }

        async function initMedia() {
            localStream = await navigator.mediaDevices.getUserMedia({
                video: true,
                audio: true
            });
            document.getElementById('localVideo').srcObject = localStream;
        }

        function initPeer() {
            pc = new RTCPeerConnection(iceConfig);
            localStream.getTracks().forEach(t => pc.addTrack(t, localStream));

            pc.ontrack = (ev) => {
                document.getElementById('remoteVideo').srcObject = ev.streams[0];
            };

            pc.onicecandidate = (ev) => {
                if (ev.candidate) {
                    sendSignal("candidate", ev.candidate);
                }
            };
        }

        async function makeOffer() {
            const offer = await pc.createOffer();
            await pc.setLocalDescription(offer);
            await sendSignal("offer", offer);
        }

        async function handleSignal(type, payload) {
            if (!pc) return;

            if (type === "offer") {
                console.log("Received offer");
                await pc.setRemoteDescription(payload);
                const answer = await pc.createAnswer();
                await pc.setLocalDescription(answer);
                await sendSignal("answer", answer);
            }

            if (type === "answer") {
                console.log("Received answer");
                await pc.setRemoteDescription(payload);
            }

            if (type === "candidate") {
                try {
                    await pc.addIceCandidate(payload);
                } catch (e) {}
            }

            if (type === "leave") {
                console.log("Partner left the call");
                endCall();
            }
        }

        function endCall() {
            console.log("endCall() triggered - finding next match...");
            polling = false;
            sendSignal("leave", { bye: true }).catch(() => {});
            if (pc) {
                pc.close();
                pc = null;
            }
            
            // Don't stop local stream - keep camera/mic running
            // if (localStream) {
            //     localStream.getTracks().forEach(t => t.stop());
            //     localStream = null;
            // }
            
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
                } else {
                    console.log("No more matches, trying to find new match...");
                    // Try to find a completely new match
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
                let response;
                if (MY_ROLE === 'EMPLOYER') {
                    // For employer, try to use saved preferences first
                    const savedPrefs = localStorage.getItem('matchingPreferences');
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
                        formData.append('skill_ids', []);
                        
                        response = await fetch('/QuickHire/Public/actions/find_match.php', {
                            method: 'POST',
                            body: formData
                        });
                    }
                } else {
                    // For jobseeker
                    response = await fetch('/QuickHire/Public/actions/find_employer.php');
                }
                
                if (MY_ROLE === 'EMPLOYER' && response.redirected) {
                    // Extract room from redirect URL
                    const url = new URL(response.url);
                    const roomParam = url.searchParams.get('room');
                    if (roomParam) {
                        ROOM = roomParam;
                        restartCall();
                        return;
                    }
                }
                
                if (MY_ROLE === 'JOBSEEKER') {
                    const data = await response.json();
                    if (data.ok && data.room) {
                        ROOM = data.room;
                        restartCall();
                        return;
                    }
                }
                
                // No match found
                showNoMatchFound();
                
            } catch (error) {
                console.error("Error finding new match:", error);
                showNoMatchFound();
            }
        }

        function restartCall() {
            console.log("Restarting call with room:", ROOM);
            
            // Reset variables
            afterSignalId = 0;
            afterChatId = 0;
            polling = true;
            
            // Clear chat messages
            document.getElementById('chatMessages').innerHTML = '';
            
            // Hide "finding match" message
            hideFindingNextMatch();
            
            // Initialize new peer connection
            initPeer();
            
            // Send join signal for new room
            sendSignal("join", { joined: true });
            
            // Start offer after delay
            setTimeout(() => makeOffer().catch(() => {}), 900);
            
            // Restart polling
            pollSignals();
            pollChatMessages();
            
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
                background: rgba(0, 0, 0, 0.9);
                padding: 30px 40px;
                border-radius: 16px;
                z-index: 1000;
                color: white;
                font-size: 20px;
                font-weight: 900;
                text-align: center;
                border: 2px solid #1f6f82;
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
                    <div style="margin-bottom: 20px;">😔 No more matches available</div>
                    <div style="font-size: 14px; margin-bottom: 25px; opacity: 0.8;">Try again later or return to dashboard</div>
                    <button onclick="findNextMatchAuto()" 
                            style="padding: 10px 20px; background: #1f6f82; color: white; border: none; border-radius: 8px; font-weight: 900; cursor: pointer; margin-right: 10px;">
                        Try Again
                    </button>
                    <button onclick="window.location.href='/QuickHire/Public/' + (MY_ROLE === 'EMPLOYER' ? 'employer' : 'jobseeker') + '-dashboard.php'" 
                            style="padding: 10px 20px; background: #666; color: white; border: none; border-radius: 8px; font-weight: 900; cursor: pointer;">
                        Dashboard
                    </button>
                `;
            }
        }

        async function nextMatch() {
            if (!confirm("Skip to next match?")) return;
            
            console.log("User clicked Next - finding next match...");
            polling = false;
            sendSignal("leave", { bye: true }).catch(() => {});
            
            if (pc) {
                pc.close();
                pc = null;
            }
            
            // Don't stop camera - keep it running for next match
            // Keep local stream active for seamless transition
            
            // Auto-find next match
            findNextMatchAuto();
        }

        document.getElementById('btnHang').addEventListener('click', () => {
            const choice = confirm("End call and find another match? (Cancel to return to dashboard)");
            if (choice) {
                // Find another match
                console.log("User chose to find another match");
                polling = false;
                sendSignal("leave", { bye: true }).catch(() => {});
                if (pc) {
                    pc.close();
                    pc = null;
                }
                findNextMatchAuto();
            } else {
                // Return to dashboard
                console.log("User chose to return to dashboard");
                polling = false;
                sendSignal("leave", { bye: true }).catch(() => {});
                if (pc) {
                    pc.close();
                    pc = null;
                }
                if (localStream) {
                    localStream.getTracks().forEach(t => t.stop());
                    localStream = null;
                }
                window.location.href = "/QuickHire/Public/" + (MY_ROLE === 'EMPLOYER' ? 'employer' : 'jobseeker') + "-dashboard.php";
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

        (async () => {
            try {
                console.log("Initializing call...");
                await initMedia();
                console.log("Media initialized");
                initPeer();
                console.log("Peer initialized");
                await sendSignal("join", { joined: true });
                console.log("Join signal sent");
                setTimeout(() => makeOffer().catch(() => {}), 900);
                pollSignals();
                pollChatMessages();
                console.log("Call setup complete");
            } catch (error) {
                console.error("Call initialization error:", error);
                alert("Failed to initialize call: " + error.message);
            }
        })();
    </script>
</body>

</html>