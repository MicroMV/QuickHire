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
    <link rel="stylesheet" href="/QuickHire/Public/assets/css/messages.css">
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
                    <div class="empty-conversations">
                        <p>No conversations yet</p>
                        <p class="empty-conversations-sub">Start messaging with <?= $userRole === 'EMPLOYER' ? 'job seekers' : 'employers' ?>!</p>
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
                        <h3>
                            <?php if ($userRole === 'EMPLOYER'): ?>
                                <a href="/QuickHire/Public/employer-dashboard.php?jobseeker_profile=<?= urlencode((string)$selectedConversation['jobseeker_id']) ?>">
                                    <?= htmlspecialchars($selectedConversation['jobseeker_first_name'] . ' ' . $selectedConversation['jobseeker_last_name']) ?>
                                </a>
                            <?php else: ?>
                                <?= htmlspecialchars($selectedConversation['employer_first_name'] . ' ' . $selectedConversation['employer_last_name']) ?>
                            <?php endif; ?>
                        </h3>
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
                                        <div class="video-call-message-label">
                                            📞 Video Call Message
                                        </div>
                                    <?php endif; ?>
                                    
                                    <p class="message-text"><?= nl2br(htmlspecialchars($message['content'])) ?></p>
                                    
                                    <?php if ($message['message_type'] === 'file' && $message['file_url']): ?>
                                        <div class="message-file">
                                            📎 <a href="<?= htmlspecialchars($message['file_url']) ?>" target="_blank" class="message-file-link">
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
                            <input type="file" id="fileInput" name="file" class="message-file-input" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.txt,.zip">
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

    <?php require __DIR__ . '/../Partials/scripts/messages-script-1.php'; ?>
</body>
</html>
