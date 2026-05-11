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
        // Always check for a direct RINGING assignment first
        const direct = await checkDirectCall();
        if (direct) return;

        const res = await fetch(ROOM_ENDPOINT);
        const data = await res.json();

        if (!data.ok) {
          // Keep polling every 3s
          setTimeout(findEmployer, 3000);
          return;
        }

        await connectToRoom(data.room, data.employer_name);
      } catch (e) {
        log('Error: ' + e.message);
        showError('Connection error: ' + e.message);
      }
    }

    // Check if an employer assigned us directly (via "Next Jobseeker")
    async function checkDirectCall() {
      try {
        const res = await fetch('/QuickHire/Public/actions/check_waiting_calls.php');
        const data = await res.json();
        if (data.ok && data.has_call && data.room) {
          log('Matched via employer next — joining room: ' + data.room);
          await connectToRoom(data.room, null);
          return true;
        }
      } catch (e) {
        log('checkDirectCall error: ' + e.message);
      }
      return false;
    }

    async function connectToRoom(room, employerName) {
      ROOM = room;
      log('Match found! Room: ' + ROOM);
      if (employerName) document.getElementById('employerName').textContent = employerName;

      await initMedia();
      initPeer();
      showScreen('callScreen');

      await sendSignal('join', { joined: true });
      setTimeout(() => makeOffer().catch(() => {}), 900);

      pollSignals();
      startCallTimer();
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

    // Start finding employer — check for a direct RINGING call first, then search WAITING rooms
    async function start() {
      const direct = await checkDirectCall();
      if (!direct) findEmployer();
    }
    start();
  </script>
