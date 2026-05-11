<script>
// Activity tracking - update on any click/interaction
let lastActivityUpdate = Date.now();

// Shared helper: returns green dot (active) or grey dot (offline) HTML
function statusDot(lastActive) {
  if (lastActive && (new Date() - new Date(lastActive)) < 60000) {
    return `<span class="status-dot status-dot--active"></span>`;
  }
  return `<span class="status-dot status-dot--offline"></span>`;
}
const updateActivity = () => {
  const now = Date.now();
  // Only send update if 5 seconds have passed since last update (throttle)
  if (now - lastActivityUpdate > 5000) {
    fetch('/QuickHire/Public/actions/update_activity.php', { method: 'POST' })
      .catch(() => {});
    lastActivityUpdate = now;
  }
};

// Track clicks anywhere in the app
document.addEventListener('click', updateActivity);
document.addEventListener('keypress', updateActivity);
document.addEventListener('scroll', updateActivity);

// Fallback: update every 30 seconds if user is idle but page is open
setInterval(() => {
  fetch('/QuickHire/Public/actions/update_activity.php', { method: 'POST' })
    .catch(() => {});
}, 30000);

// Update on page load and when tab regains focus
fetch('/QuickHire/Public/actions/update_activity.php', { method: 'POST' });
window.addEventListener('focus', updateActivity);
</script>
