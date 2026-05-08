<?php
require __DIR__ . '/../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Database;
use Rongie\QuickHire\Services\MessagingService;

Session::start();
Auth::requireLogin();

// Prevent browser from caching this page (fixes bfcache stale state issue)
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Release session lock before heavy DB work
if (session_status() === PHP_SESSION_ACTIVE) {
  session_write_close();
}

$config = require __DIR__ . '/../Config/config.php';
$db = new Database($config['db']);
$messagingService = new MessagingService($db->pdo());

$userId = Auth::userId();
$userRole = Auth::role();

// Get conversations
$conversations = $messagingService->getUserConversations($userId, $userRole);

// Get selected conversation
$selectedConversationId = (int)($_GET['c'] ?? 0);
$selectedConversation = null;
$messages = [];

if ($selectedConversationId > 0) {
    $selectedConversation = $messagingService->getConversation($selectedConversationId);
    if ($selectedConversation) {
        // Check if user has access to this conversation
        $hasAccess = ($userRole === 'EMPLOYER' && $selectedConversation['employer_id'] == $userId) ||
                     ($userRole === 'JOBSEEKER' && $selectedConversation['jobseeker_id'] == $userId);
        
        if ($hasAccess) {
            $messages = $messagingService->getMessages($selectedConversationId);
            $messagingService->markMessagesAsRead($selectedConversationId, $userId);
        } else {
            $selectedConversation = null;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - QuickHire</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/QuickHire/Public/assets/css/landingPage.css">
    <link rel="stylesheet" href="/QuickHire/Public/assets/css/dark-theme.css">
    <style>
        .messages-container {
            display: flex;
            height: calc(100vh - 80px);
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .conversations-sidebar {
            width: 350px;
            border-right: 1px solid #e2e8f0;
            background: #f8fafc;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
            background: white;
        }

        .sidebar-header h2 {
            margin: 0;
            font-size: 18px;
            color: #1e293b;
        }

        .conversation-item {
            padding: 16px 20px;
            border-bottom: 1px solid #e2e8f0;
            cursor: pointer;
            transition: background-color 0.2s;
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .conversation-item:hover {
            background: #e2e8f0;
        }

        .conversation-item.active {
            background: #3b82f6;
            color: white;
        }

        .conversation-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            margin-right: 12px;
            flex-shrink: 0;
        }

        .conversation-info {
            flex: 1;
            min-width: 0;
        }

        .conversation-name {
            font-weight: 600;
            margin-bottom: 4px;
        }

        .conversation-preview {
            font-size: 14px;
            opacity: 0.7;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .conversation-job-links {
            font-size: 12px;
            line-height: 1.35;
            margin-bottom: 4px;
        }

        .conversation-job-links a {
            color: #2563eb;
            font-weight: 700;
            text-decoration: none;
        }

        .conversation-job-links a:hover {
            text-decoration: underline;
        }

        .conversation-item.active .conversation-job-links a {
            color: white;
        }

        .conversation-meta {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .unread-badge {
            background: #ef4444;
            color: white;
            border-radius: 10px;
            padding: 2px 6px;
            font-size: 12px;
            font-weight: bold;
        }

        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .chat-header {
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
            background: white;
        }

        .chat-header h3 {
            margin: 0;
            font-size: 18px;
            color: #1e293b;
        }

        .chat-subtitle {
            font-size: 14px;
            color: #64748b;
            margin-top: 4px;
        }

        .messages-area {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: #f8fafc;
        }

        .message {
            margin-bottom: 16px;
            display: flex;
            gap: 12px;
        }

        .message.own {
            flex-direction: row-reverse;
        }

        .message-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            font-weight: bold;
            flex-shrink: 0;
        }

        .message-content {
            max-width: 70%;
            background: white;
            padding: 12px 16px;
            border-radius: 18px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .message.own .message-content {
            background: #3b82f6;
            color: white;
        }

        .message-text {
            margin: 0;
            line-height: 1.4;
        }

        .message-file {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px;
            background: rgba(0,0,0,0.05);
            border-radius: 8px;
            margin-top: 8px;
        }

        .message.own .message-file {
            background: rgba(255,255,255,0.2);
        }

        .message-time {
            font-size: 12px;
            color: #64748b;
            margin-top: 4px;
        }

        .message.own .message-time {
            color: rgba(255,255,255,0.8);
        }

        .message-input-area {
            padding: 20px;
            border-top: 1px solid #e2e8f0;
            background: white;
        }

        .message-form {
            display: flex;
            gap: 12px;
            align-items: flex-end;
        }

        .message-input {
            flex: 1;
            min-height: 40px;
            max-height: 120px;
            padding: 10px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            resize: none;
            font-family: inherit;
            font-size: 14px;
        }

        .message-input:focus {
            outline: none;
            border-color: #3b82f6;
        }

        .send-button, .file-button {
            padding: 10px 16px;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
        }

        .send-button {
            background: #3b82f6;
            color: white;
        }

        .send-button:hover {
            background: #2563eb;
        }

        .send-button:disabled {
            background: #94a3b8;
            cursor: not-allowed;
        }

        .file-button {
            background: #64748b;
            color: white;
        }

        .file-button:hover {
            background: #475569;
        }

        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #64748b;
            text-align: center;
        }

        .empty-state h3 {
            margin-bottom: 8px;
            color: #1e293b;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: #f1f5f9;
            color: #475569;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 20px;
            transition: background-color 0.2s;
        }

        .back-button:hover {
            background: #e2e8f0;
        }

        @media (max-width: 768px) {
            .messages-container {
                height: calc(100vh - 60px);
                flex-direction: column;
            }

            .conversations-sidebar {
                width: 100%;
                height: 200px;
            }

            .chat-area {
                height: calc(100vh - 260px);
            }
        }
    </style>
</head>
<body class="landing-body">
    <div class="container">
        <a href="/QuickHire/Public/<?= $userRole === 'EMPLOYER' ? 'employer-dashboard.php' : 'jobseeker-dashboard.php' ?>" class="back-button">
            ← Back to Dashboard
        </a>

        <div class="messages-container">
            <div class="conversations-sidebar">
                <div class="sidebar-header">
                    <h2>Messages</h2>
                </div>
                
                <?php if (empty($conversations)): ?>
                    <div style="padding: 40px 20px; text-align: center; color: #64748b;">
                        <p>No conversations yet</p>
                        <p style="font-size: 14px;">Start messaging with <?= $userRole === 'EMPLOYER' ? 'job seekers' : 'employers' ?>!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($conversations as $conv): ?>
                        <div class="conversation-item <?= $selectedConversationId == $conv['id'] ? 'active' : '' ?>" onclick="window.location.href='?c=<?= urlencode((string)$conv['id']) ?>'">
                            <div class="conversation-meta">
                                <div class="conversation-avatar">
                                    <?= strtoupper(substr($conv['other_first_name'], 0, 1)) ?>
                                </div>
                                <div class="conversation-info">
                                    <div class="conversation-name">
                                        <?= htmlspecialchars($conv['other_first_name'] . ' ' . $conv['other_last_name']) ?>
                                    </div>
                                    <?php if ($userRole === 'JOBSEEKER' && !empty($conv['applied_jobs'])): ?>
                                        <div class="conversation-job-links">
                                            <?php foreach ($conv['applied_jobs'] as $index => $job): ?>
                                                <?= $index > 0 ? ', ' : '' ?><a href="/QuickHire/Public/jobseeker-dashboard.php?job_id=<?= urlencode((string)$job['id']) ?>" onclick="event.stopPropagation();"><?= htmlspecialchars($job['title']) ?></a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="conversation-preview">
                                        <?= htmlspecialchars($conv['other_role'] ?? 'User') ?>
                                    </div>
                                    <?php if ($conv['last_message']): ?>
                                        <div class="conversation-preview">
                                            <?= htmlspecialchars(substr($conv['last_message'], 0, 50)) ?><?= strlen($conv['last_message']) > 50 ? '...' : '' ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php if ($conv['unread_count'] > 0): ?>
                                    <div class="unread-badge"><?= $conv['unread_count'] ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="chat-area">
                <?php if ($selectedConversation): ?>
                    <div class="chat-header">
                        <h3><?= htmlspecialchars(
                            $userRole === 'EMPLOYER' 
                                ? $selectedConversation['jobseeker_first_name'] . ' ' . $selectedConversation['jobseeker_last_name']
                                : $selectedConversation['employer_first_name'] . ' ' . $selectedConversation['employer_last_name']
                        ) ?></h3>
                        <div class="chat-subtitle">
                            <?= htmlspecialchars(
                                $userRole === 'EMPLOYER' 
                                    ? ($selectedConversation['role_title'] ?? 'Job Seeker')
                                    : ($selectedConversation['company_name'] ?? 'Employer')
                            ) ?>
                        </div>
                    </div>

                    <div class="messages-area" id="messagesArea">
                        <?php foreach ($messages as $message): ?>
                            <div class="message <?= $message['sender_id'] == $userId ? 'own' : '' ?>">
                                <div class="message-avatar">
                                    <?= strtoupper(substr($message['first_name'], 0, 1)) ?>
                                </div>
                                <div class="message-content">
                                    <?php if ($message['room_code']): ?>
                                        <div style="font-size: 12px; opacity: 0.7; margin-bottom: 4px;">
                                            📞 Video Call Message
                                        </div>
                                    <?php endif; ?>
                                    
                                    <p class="message-text"><?= nl2br(htmlspecialchars($message['content'])) ?></p>
                                    
                                    <?php if ($message['message_type'] === 'file' && $message['file_url']): ?>
                                        <div class="message-file">
                                            📎 <a href="<?= htmlspecialchars($message['file_url']) ?>" target="_blank" style="color: inherit;">
                                                <?= htmlspecialchars($message['file_name']) ?>
                                            </a>
                                            <?php if ($message['file_size']): ?>
                                                <span>(<?= number_format($message['file_size'] / 1024, 1) ?>KB)</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="message-time">
                                        <?= date('M j, g:i A', strtotime($message['created_at'])) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="message-input-area">
                        <form class="message-form" id="messageForm" enctype="multipart/form-data">
                            <input type="hidden" name="conversation_id" value="<?= $selectedConversationId ?>">
                            <textarea class="message-input" name="message" placeholder="Type your message..." rows="1" id="messageInput"></textarea>
                            <input type="file" id="fileInput" name="file" style="display: none;" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.txt,.zip">
                            <button type="button" class="file-button" onclick="document.getElementById('fileInput').click()">📎</button>
                            <button type="submit" class="send-button" id="sendButton">Send</button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <h3>Select a conversation</h3>
                        <p>Choose a conversation from the sidebar to start messaging</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

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
</body>
</html>
