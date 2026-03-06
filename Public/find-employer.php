<?php
require __DIR__ . '/../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Database;

Session::start();
Auth::requireLogin();

if (Auth::role() !== 'JOBSEEKER') {
  header("Location: /QuickHire/Public/index.php");
  exit;
}

$config = require __DIR__ . '/../config/config.php';
$db = new Database($config['db']);
$pdo = $db->pdo();

$userId = Auth::userId();

// Get jobseeker info
$stmt = $pdo->prepare("SELECT * FROM jobseeker_profiles WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$jobseeker = $stmt->fetch();

if (!$jobseeker) {
  header("Location: /QuickHire/Public/complete-profile.php");
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Find Employer - QuickHire</title>
  <link rel="stylesheet" href="/QuickHire/Public/assets/css/landingPage.css">

  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: Inter, system-ui, Arial;
      background: #0b1220;
      color: #fff;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .container {
      width: 100%;
      max-width: 1200px;
      padding: 20px;
    }

    .matching-screen {
      display: none;
      text-align: center;
      padding: 40px 20px;
    }

    .matching-screen.active {
      display: block;
    }

    .spinner {
      width: 60px;
      height: 60px;
      border: 4px solid rgba(255, 255, 255, 0.2);
      border-top-color: #1f6f82;
      border-radius: 50%;
      animation: spin 1s linear infinite;
      margin: 0 auto 20px;
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    .matching-text {
      font-size: 24px;
      font-weight: 900;
      margin-bottom: 10px;
    }

    .matching-subtext {
      color: #cbd5e1;
      margin-bottom: 30px;
    }

    .call-screen {
      display: none;
    }

    .call-screen.active {
      display: block;
    }

    .call-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      padding: 15px;
      background: rgba(255, 255, 255, 0.06);
      border-radius: 12px;
      border: 1px solid rgba(255, 255, 255, 0.12);
    }

    .call-info {
      display: flex;
      gap: 15px;
      align-items: center;
    }

    .call-timer {
      font-size: 18px;
      font-weight: 900;
    }

    .video-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 15px;
      margin-bottom: 20px;
    }

    .video-container {
      position: relative;
      background: #000;
      border-radius: 16px;
      overflow: hidden;
      aspect-ratio: 16/9;
    }

    .video-label {
      position: absolute;
      top: 15px;
      left: 15px;
      background: rgba(0, 0, 0, 0.6);
      padding: 8px 12px;
      border-radius: 8px;
      font-weight: 800;
      font-size: 14px;
      z-index: 10;
    }

    video {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .controls {
      display: flex;
      gap: 12px;
      justify-content: center;
      flex-wrap: wrap;
      margin-bottom: 20px;
    }

    .control-btn {
      padding: 12px 16px;
      border-radius: 12px;
      border: 1px solid rgba(255, 255, 255, 0.18);
      background: rgba(255, 255, 255, 0.06);
      color: #fff;
      font-weight: 800;
      cursor: pointer;
      transition: all 0.3s;
    }

    .control-btn:hover {
      background: rgba(255, 255, 255, 0.12);
    }

    .control-btn.danger {
      background: #b42318;
      border-color: #b42318;
    }

    .control-btn.danger:hover {
      background: #8a1a13;
    }

    .error-screen {
      display: none;
      text-align: center;
      padding: 40px 20px;
    }

    .error-screen.active {
      display: block;
    }

    .error-icon {
      font-size: 60px;
      margin-bottom: 20px;
    }

    .error-title {
      font-size: 24px;
      font-weight: 900;
      margin-bottom: 10px;
    }

    .error-message {
      color: #cbd5e1;
      margin-bottom: 30px;
      max-width: 500px;
      margin-left: auto;
      margin-right: auto;
    }

    .btn-primary {
      display: inline-block;
      padding: 12px 24px;
      background: #1f6f82;
      color: #fff;
      border: none;
      border-radius: 12px;
      font-weight: 900;
      cursor: pointer;
      text-decoration: none;
      transition: all 0.3s;
    }

    .btn-primary:hover {
      background: #165a6b;
    }

    .log {
      margin-top: 20px;
      padding: 15px;
      background: rgba(255, 255, 255, 0.06);
      border-radius: 12px;
      border: 1px solid rgba(255, 255, 255, 0.12);
      font-size: 12px;
      color: #cbd5e1;
      max-height: 150px;
      overflow-y: auto;
      white-space: pre-wrap;
      font-family: monospace;
    }

    @media (max-width: 768px) {
      .video-grid {
        grid-template-columns: 1fr;
      }

      .controls {
        flex-direction: column;
      }

      .control-btn {
        width: 100%;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <!-- MATCHING SCREEN -->
    <div class="matching-screen active" id="matchingScreen">
      <div class="spinner"></div>
      <div class="matching-text">Finding an employer...</div>
      <div class="matching-subtext">Please wait while we search for the perfect match</div>
    </div>

    <!-- CALL SCREEN -->
    <div class="call-screen" id="callScreen">
      <div class="call-header">
        <div class="call-info">
          <div>
            <div style="font-size: 12px; color: #cbd5e1;">Connected with</div>
            <div style="font-weight: 900; font-size: 16px;" id="employerName">Employer</div>
          </div>
        </div>
        <div class="call-timer" id="callTimer">00:00</div>
      </div>

      <div class="video-grid">
        <div class="video-container">
          <div class="video-label">You</div>
          <video id="localVideo" autoplay playsinline muted></video>
        </div>
        <div class="video-container">
          <div class="video-label">Employer</div>
          <video id="remoteVideo" autoplay playsinline></video>
        </div>
      </div>

      <div class="controls">
        <button class="control-btn" id="btnCam">📹 Camera: ON</button>
        <button class="control-btn" id="btnMic">🎤 Mic: ON</button>
        <button class="control-btn danger" id="btnHang">End Call</button>
      </div>

      <div class="log" id="log"></div>
    </div>

    <!-- ERROR SCREEN -->
    <div class="error-screen" id="errorScreen">
      <div class="error-icon">⚠️</div>
      <div class="error-title" id="errorTitle">No Match Found</div>
      <div class="error-message" id="errorMessage">Unable to find a suitable employer match at this time.</div>
      <a href="/QuickHire/Public/jobseeker-dashboard.php" class="btn-primary">Back to Dashboard</a>
    </div>
  </div>

  <script>
    const ROOM_ENDPOINT = '/QuickHire/Public/actions/find_employer.php';
    const SIGNAL_SEND_ENDPOINT = '/QuickHire/Public/actions/signal_send.php';
    const SIGNAL_POLL_ENDPOINT = '/QuickHire/Public/actions/signal_poll.php';

    let ROOM = null;
    let localStream = null;
    let pc = null;
    let afterId = 0;
    let polling = true;
    let callStartTime = null;

    const iceConfig = {
      iceServers: [{ urls: "stun:stun.l.google.com:19302" }]
    };

    function log(msg) {
      const logEl = document.getElementById('log');
      logEl.textContent += new Date().toLocaleTimeString() + ' - ' + msg + "\n";
      logEl.scrollTop = logEl.scrollHeight;
    }

    function showScreen(screenId) {
      document.getElementById('matchingScreen').classList.remove('active');
      document.getElementById('callScreen').classList.remove('active');
      document.getElementById('errorScreen').classList.remove('active');
      document.getElementById(screenId).classList.add('active');
    }

    async function findEmployer() {
      try {
        const res = await fetch(ROOM_ENDPOINT);
        const data = await res.json();

        if (!data.ok) {
          showError(data.error || 'No match found');
          return;
        }

        ROOM = data.room;
        log('Match found! Room: ' + ROOM);

        await initMedia();
        initPeer();
        showScreen('callScreen');

        await sendSignal('join', { joined: true });
        setTimeout(() => makeOffer().catch(() => {}), 900);

        pollSignals();
        startCallTimer();
      } catch (e) {
        log('Error: ' + e.message);
        showError('Connection error: ' + e.message);
      }
    }

    async function sendSignal(type, payload) {
      try {
        await fetch(SIGNAL_SEND_ENDPOINT, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ room: ROOM, type, payload })
        });
      } catch (e) {
        log('Send signal error: ' + e.message);
      }
    }

    async function pollSignals() {
      while (polling) {
        try {
          const res = await fetch(`${SIGNAL_POLL_ENDPOINT}?room=${encodeURIComponent(ROOM)}&after=${afterId}`);
          const data = await res.json();
          if (data.ok) {
            afterId = data.after;
            for (const m of data.messages) {
              await handleSignal(m.type, m.payload);
            }
          }
        } catch (e) {
          log('Poll error: ' + e.message);
        }
        await new Promise(r => setTimeout(r, 700));
      }
    }

    async function initMedia() {
      try {
        localStream = await navigator.mediaDevices.getUserMedia({
          video: true,
          audio: true
        });
        document.getElementById('localVideo').srcObject = localStream;
        log('Camera and microphone ready');
      } catch (e) {
        log('Media error: ' + e.message);
        throw e;
      }
    }

    function initPeer() {
      pc = new RTCPeerConnection(iceConfig);

      localStream.getTracks().forEach(t => pc.addTrack(t, localStream));

      pc.ontrack = (ev) => {
        document.getElementById('remoteVideo').srcObject = ev.streams[0];
        log('Remote stream connected');
      };

      pc.onicecandidate = (ev) => {
        if (ev.candidate) {
          sendSignal('candidate', ev.candidate);
        }
      };

      pc.onconnectionstatechange = () => {
        log('Connection: ' + pc.connectionState);
      };
    }

    async function makeOffer() {
      const offer = await pc.createOffer();
      await pc.setLocalDescription(offer);
      await sendSignal('offer', offer);
      log('Offer sent');
    }

    async function handleSignal(type, payload) {
      if (!pc) return;

      if (type === 'offer') {
        log('Offer received');
        await pc.setRemoteDescription(payload);
        const answer = await pc.createAnswer();
        await pc.setLocalDescription(answer);
        await sendSignal('answer', answer);
        log('Answer sent');
      }

      if (type === 'answer') {
        log('Answer received');
        await pc.setRemoteDescription(payload);
      }

      if (type === 'candidate') {
        try {
          await pc.addIceCandidate(payload);
        } catch (e) {}
      }

      if (type === 'leave') {
        log('Employer disconnected');
        endCall();
      }
    }

    function endCall() {
      polling = false;
      sendSignal('leave', { bye: true }).catch(() => {});
      if (pc) {
        pc.close();
        pc = null;
      }
      if (localStream) {
        localStream.getTracks().forEach(t => t.stop());
        localStream = null;
      }
      setTimeout(() => {
        window.location.href = '/QuickHire/Public/jobseeker-dashboard.php';
      }, 1000);
    }

    function startCallTimer() {
      callStartTime = Date.now();
      setInterval(() => {
        if (!callStartTime) return;
        const elapsed = Math.floor((Date.now() - callStartTime) / 1000);
        const mins = Math.floor(elapsed / 60);
        const secs = elapsed % 60;
        document.getElementById('callTimer').textContent = 
          String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
      }, 1000);
    }

    function showError(message) {
      document.getElementById('errorTitle').textContent = 'No Match Found';
      document.getElementById('errorMessage').textContent = message;
      showScreen('errorScreen');
    }

    document.getElementById('btnHang').addEventListener('click', endCall);

    document.getElementById('btnCam').addEventListener('click', () => {
      const track = localStream?.getVideoTracks?.()[0];
      if (!track) return;
      track.enabled = !track.enabled;
      document.getElementById('btnCam').textContent = '📹 Camera: ' + (track.enabled ? 'ON' : 'OFF');
    });

    document.getElementById('btnMic').addEventListener('click', () => {
      const track = localStream?.getAudioTracks?.()[0];
      if (!track) return;
      track.enabled = !track.enabled;
      document.getElementById('btnMic').textContent = '🎤 Mic: ' + (track.enabled ? 'ON' : 'OFF');
    });

    // Start finding employer
    findEmployer();
  </script>
</body>
</html>
