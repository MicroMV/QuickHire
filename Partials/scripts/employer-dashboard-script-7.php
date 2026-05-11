<script>
function openImageModal(url, name) {
  const lb = document.getElementById('imageLightbox');
  document.getElementById('lightboxImg').src = url;
  document.getElementById('lightboxName').textContent = name || '';
  document.getElementById('lightboxDownload').href = url;
  lb.style.display = 'flex';
  document.body.style.overflow = 'hidden';
}
function closeImageModal() {
  document.getElementById('imageLightbox').style.display = 'none';
  document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeImageModal(); });
</script>
