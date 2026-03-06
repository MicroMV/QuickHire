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
        body {
            margin: 0;
            font-family: Inter, system-ui, Arial;
            background: #0b1220;
            color: #fff;
        }

        .wrap {
            max-width: 1100px;
            margin: 0 auto;
            padding: 18px;
        }

        .top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }

        .badge {
            padding: 8px 12px;
            border: 1px solid rgba(255, 255, 255, .15);
            border-radius: 999px;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .card {
            background: rgba(255, 255, 255, .06);
            border: 1px solid rgba(255, 255, 255, .12);
            border-radius: 16px;
            padding: 12px;
        }

        video {
            width: 100%;
            height: 420px;
            background: #000;
            border-radius: 12px;
            object-fit: cover;
        }

        .controls {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 12px;
        }

        button {
            padding: 12px 14px;
            border-radius: 14px;
            border: 1px solid rgba(255, 255, 255, .18);
            background: rgba(255, 255, 255, .06);
            color: #fff;
            font-weight: 800;
            cursor: pointer;
        }

        button.danger {
            background: #b42318;
            border-color: #b42318;
        }

        .log {
            margin-top: 12px;
            color: #cbd5e1;
            font-size: 12px;
            line-height: 1.4;
            white-space: pre-wrap;
        }

        @media (max-width: 900px) {
            .grid {
                grid-template-columns: 1fr;
            }

            video {
                height: 320px;
            }
        }
    </style>
</head>

<body>
    <div class="wrap">
        <div class="top">
            <div class="badge">Room: <strong><?= htmlspecialchars($room) ?></strong></div>
            <div class="badge">You: <strong><?= htmlspecialchars(Auth::role()) ?></strong></div>
        </div>

        <div class="grid">
            <div class="card">
                <div style="font-weight:900;margin-bottom:8px;">You</div>
                <video id="localVideo" autoplay playsinline muted></video>
            </div>
            <div class="card">
                <div style="font-weight:900;margin-bottom:8px;">Partner</div>
                <video id="remoteVideo" autoplay playsinline></video>
            </div>
        </div>

        <div class="controls">
            <button id="btnCam">Camera: ON</button>
            <button id="btnMic">Mic: ON</button>
            <button id="btnHang" class="danger">Hang up</button>
        </div>

        <div class="log" id="log"></div>
    </div>

    <script>
        const ROOM = <?= json_encode($room) ?>;

        const logEl = document.getElementById('log');

        function log(msg) {
            logEl.textContent += msg + "\n";
        }

        let localStream = null;
        let pc = null;
        let afterId = 0;
        let polling = true;

        const iceConfig = {
            iceServers: [{
                    urls: "stun:stun.l.google.com:19302"
                } // ✅ free STUN
            ]
        };

        async function sendSignal(type, payload) {
            await fetch("/QuickHire/Public/actions/signal_send.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    room: ROOM,
                    type,
                    payload
                })
            });
        }

        async function pollSignals() {
            while (polling) {
                try {
                    const res = await fetch(`/QuickHire/Public/actions/signal_poll.php?room=${encodeURIComponent(ROOM)}&after=${afterId}`);
                    const data = await res.json();
                    if (data.ok) {
                        afterId = data.after;
                        for (const m of data.messages) {
                            await handleSignal(m.type, m.payload);
                        }
                    }
                } catch (e) {
                    log("Poll error: " + e.message);
                }
                await new Promise(r => setTimeout(r, 700)); // polling interval
            }
        }

        async function initMedia() {
            localStream = await navigator.mediaDevices.getUserMedia({
                video: true,
                audio: true
            });
            document.getElementById('localVideo').srcObject = localStream;
            log("Media ready (camera+mic).");
        }

        function initPeer() {
            pc = new RTCPeerConnection(iceConfig);

            // send local tracks
            localStream.getTracks().forEach(t => pc.addTrack(t, localStream));

            // receive remote tracks
            pc.ontrack = (ev) => {
                document.getElementById('remoteVideo').srcObject = ev.streams[0];
                log("Remote stream connected.");
            };

            pc.onicecandidate = (ev) => {
                if (ev.candidate) {
                    sendSignal("candidate", ev.candidate);
                }
            };

            pc.onconnectionstatechange = () => {
                log("Connection state: " + pc.connectionState);
            };
        }

        async function makeOffer() {
            const offer = await pc.createOffer();
            await pc.setLocalDescription(offer);
            await sendSignal("offer", offer);
            log("Offer sent.");
        }

        async function handleSignal(type, payload) {
            if (!pc) return;

            if (type === "offer") {
                log("Offer received.");
                await pc.setRemoteDescription(payload);
                const answer = await pc.createAnswer();
                await pc.setLocalDescription(answer);
                await sendSignal("answer", answer);
                log("Answer sent.");
            }

            if (type === "answer") {
                log("Answer received.");
                await pc.setRemoteDescription(payload);
            }

            if (type === "candidate") {
                try {
                    await pc.addIceCandidate(payload);
                } catch (e) {
                    // sometimes candidates arrive early; ignore minor errors
                }
            }

            if (type === "leave") {
                log("Partner left.");
                endCall();
            }
        }

        function endCall() {
            polling = false;
            sendSignal("leave", {
                bye: true
            }).catch(() => {});
            if (pc) {
                pc.close();
                pc = null;
            }
            if (localStream) {
                localStream.getTracks().forEach(t => t.stop());
                localStream = null;
            }
            alert("Call ended.");
            window.location.href = "/QuickHire/Public/jobseeker-dashboard.php";
        }

        document.getElementById('btnHang').addEventListener('click', endCall);

        document.getElementById('btnCam').addEventListener('click', () => {
            const track = localStream?.getVideoTracks?.()[0];
            if (!track) return;
            track.enabled = !track.enabled;
            document.getElementById('btnCam').textContent = "Camera: " + (track.enabled ? "ON" : "OFF");
        });

        document.getElementById('btnMic').addEventListener('click', () => {
            const track = localStream?.getAudioTracks?.()[0];
            if (!track) return;
            track.enabled = !track.enabled;
            document.getElementById('btnMic').textContent = "Mic: " + (track.enabled ? "ON" : "OFF");
        });

        (async () => {
            await initMedia();
            initPeer();

            // announce join
            await sendSignal("join", {
                joined: true
            });

            // small trick: first user to join creates offer after a delay
            // (works for 2-person rooms)
            setTimeout(() => makeOffer().catch(() => {}), 900);

            pollSignals();
        })();
    </script>
</body>

</html>