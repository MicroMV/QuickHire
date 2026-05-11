<script>
        // Auto-resize textarea
        const messageInput = document.getElementById('messageInput');
        if (messageInput) {
            messageInput.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 120) + 'px';
            });

            // Send on Enter (but not Shift+Enter)
            messageInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    document.getElementById('messageForm').dispatchEvent(new Event('submit'));
                }
            });
        }

        // Handle form submission
        const messageForm = document.getElementById('messageForm');
        if (messageForm) {
            messageForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const sendButton = document.getElementById('sendButton');
                const messageInput = document.getElementById('messageInput');
                
                if (!formData.get('message').trim() && !formData.get('file').name) {
                    return;
                }
                
                sendButton.disabled = true;
                sendButton.textContent = 'Sending...';
                
                try {
                    const response = await fetch('/QuickHire/Public/actions/send_message.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.ok) {
                        // Reload page to show new message
                        window.location.reload();
                    } else {
                        alert('Error: ' + result.error);
                    }
                } catch (error) {
                    alert('Error sending message');
                } finally {
                    sendButton.disabled = false;
                    sendButton.textContent = 'Send';
                }
            });
        }

        // Auto-scroll to bottom of messages
        const messagesArea = document.getElementById('messagesArea');
        if (messagesArea) {
            messagesArea.scrollTop = messagesArea.scrollHeight;
        }

        // Handle file selection
        const fileInput = document.getElementById('fileInput');
        if (fileInput) {
            fileInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    const fileName = this.files[0].name;
                    const messageInput = document.getElementById('messageInput');
                    if (messageInput.value.trim() === '') {
                        messageInput.value = `📎 ${fileName}`;
                    }
                }
            });
        }

        // Force reload when page is restored from bfcache (back/forward navigation)
        // This prevents stale conversation state from showing after navigating away
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                window.location.reload();
            }
        });
    </script>