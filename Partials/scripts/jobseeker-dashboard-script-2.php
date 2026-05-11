<script>
let avatarCameraStream = null;
let avatarCameraTargetInput = '';
let avatarCameraTargetPreview = '';
let avatarCameraImage = '';

async function openAvatarCamera(inputId, previewId) {
  avatarCameraTargetInput = inputId;
  avatarCameraTargetPreview = previewId;
  avatarCameraImage = '';
  const modal = document.getElementById('avatarCameraModal');
  const error = document.getElementById('avatarCameraError');
  const video = document.getElementById('avatarCameraVideo');
  const snapshot = document.getElementById('avatarCameraSnapshot');
  error.style.display = 'none';
  snapshot.style.display = 'none';
  video.style.display = 'block';
  document.getElementById('avatarCaptureBtn').style.display = '';
  document.getElementById('avatarRetakeBtn').style.display = 'none';
  document.getElementById('avatarUseBtn').style.display = 'none';
  modal.style.display = 'flex';
  try {
    avatarCameraStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
    video.srcObject = avatarCameraStream;
  } catch (err) {
    error.textContent = 'Camera access is required for profile photos. Please allow camera permission and try again.';
    error.style.display = 'block';
  }
}

function stopAvatarCamera() {
  if (avatarCameraStream) {
    avatarCameraStream.getTracks().forEach(track => track.stop());
    avatarCameraStream = null;
  }
}

function closeAvatarCamera() {
  stopAvatarCamera();
  document.getElementById('avatarCameraModal').style.display = 'none';
}

function captureAvatarPhoto() {
  const video = document.getElementById('avatarCameraVideo');
  if (!video.videoWidth || !video.videoHeight) return;
  const canvas = document.getElementById('avatarCameraCanvas');
  const size = Math.min(video.videoWidth, video.videoHeight);
  const sx = (video.videoWidth - size) / 2;
  const sy = (video.videoHeight - size) / 2;
  canvas.getContext('2d').drawImage(video, sx, sy, size, size, 0, 0, canvas.width, canvas.height);
  avatarCameraImage = canvas.toDataURL('image/jpeg', 0.9);
  document.getElementById('avatarCameraSnapshot').src = avatarCameraImage;
  document.getElementById('avatarCameraSnapshot').style.display = 'block';
  video.style.display = 'none';
  stopAvatarCamera();
  document.getElementById('avatarCaptureBtn').style.display = 'none';
  document.getElementById('avatarRetakeBtn').style.display = '';
  document.getElementById('avatarUseBtn').style.display = '';
}

function retakeAvatarPhoto() {
  openAvatarCamera(avatarCameraTargetInput, avatarCameraTargetPreview);
}

function useAvatarPhoto() {
  if (!avatarCameraImage) return;
  const input = document.getElementById(avatarCameraTargetInput);
  const preview = document.getElementById(avatarCameraTargetPreview);
  if (input) input.value = avatarCameraImage;
  if (preview) preview.innerHTML = `<img src="${avatarCameraImage}" style="width:100%;height:100%;object-fit:cover;">`;
  closeAvatarCamera();
}
</script>
