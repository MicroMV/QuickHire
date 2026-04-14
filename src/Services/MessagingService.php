<?php
namespace Rongie\QuickHire\Services;

use PDO;
use Exception;

class MessagingService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get or create conversation between employer and jobseeker
     */
    public function getOrCreateConversation(int $employerId, int $jobseekerId, ?int $jobId = null): int
    {
        // Check if conversation already exists
        $stmt = $this->pdo->prepare("
            SELECT id FROM conversations 
            WHERE employer_id = ? AND jobseeker_id = ?
        ");
        $stmt->execute([$employerId, $jobseekerId]);
        $conversation = $stmt->fetch();

        if ($conversation) {
            // Update the timestamp of existing conversation to bring it to top
            $this->pdo->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?")->execute([$conversation['id']]);
            return $conversation['id'];
        }

        // Create new conversation
        $stmt = $this->pdo->prepare("
            INSERT INTO conversations (employer_id, jobseeker_id, job_id, created_at, updated_at)
            VALUES (?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([$employerId, $jobseekerId, $jobId]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Send a message
     */
    public function sendMessage(int $conversationId, int $senderId, string $content, string $type = 'text', ?string $fileUrl = null, ?string $fileName = null, ?int $fileSize = null, ?string $roomCode = null): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO messages (conversation_id, sender_id, message_type, content, file_url, file_name, file_size, room_code)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$conversationId, $senderId, $type, $content, $fileUrl, $fileName, $fileSize, $roomCode]);

        $messageId = (int)$this->pdo->lastInsertId();

        // Update conversation timestamp
        $this->pdo->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?")->execute([$conversationId]);

        return $messageId;
    }

    /**
     * Get messages for a conversation
     */
    public function getMessages(int $conversationId, int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare("
            SELECT m.*, u.first_name, u.last_name, u.role,
                   COALESCE(jp.profile_picture_url, ep.profile_picture_url) as sender_avatar
            FROM messages m
            JOIN users u ON u.id = m.sender_id
            LEFT JOIN jobseeker_profiles jp ON jp.user_id = m.sender_id AND u.role = 'JOBSEEKER'
            LEFT JOIN employer_profiles ep ON ep.user_id = m.sender_id AND u.role = 'EMPLOYER'
            WHERE m.conversation_id = ?
            ORDER BY m.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$conversationId, $limit, $offset]);
        return array_reverse($stmt->fetchAll());
    }

    /**
     * Get messages for a call room
     */
    public function getRoomMessages(string $roomCode, int $afterId = 0): array
    {
        $stmt = $this->pdo->prepare("
            SELECT m.*, u.first_name, u.last_name, u.role
            FROM messages m
            JOIN users u ON u.id = m.sender_id
            WHERE m.room_code = ? AND m.id > ?
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$roomCode, $afterId]);
        return $stmt->fetchAll();
    }

    /**
     * Get conversations for a user
     */
    public function getUserConversations(int $userId, string $userRole): array
    {
        if ($userRole === 'EMPLOYER') {
            $stmt = $this->pdo->prepare("
                SELECT c.*, 
                       u.first_name as other_first_name, 
                       u.last_name as other_last_name,
                       DATE_FORMAT(CONVERT_TZ(u.last_active, @@session.time_zone, '+00:00'), '%Y-%m-%dT%H:%i:%sZ') as other_last_active,
                       jp.profile_picture_url as other_avatar,
                       jp.role_title as other_role,
                       jpost.title as job_post_title,
                       jpost.id as job_post_id,
                       (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND sender_id != ? AND is_read = 0) as unread_count,
                       (SELECT content FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message,
                       (SELECT created_at FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message_time
                FROM conversations c
                JOIN users u ON u.id = c.jobseeker_id
                LEFT JOIN jobseeker_profiles jp ON jp.user_id = u.id
                LEFT JOIN job_posts jpost ON jpost.id = c.job_id
                WHERE c.employer_id = ?
                ORDER BY c.updated_at DESC
            ");
            $stmt->execute([$userId, $userId]);
        } else {
            $stmt = $this->pdo->prepare("
                SELECT c.*, 
                       u.first_name as other_first_name, 
                       u.last_name as other_last_name,
                       DATE_FORMAT(CONVERT_TZ(u.last_active, @@session.time_zone, '+00:00'), '%Y-%m-%dT%H:%i:%sZ') as other_last_active,
                       ep.profile_picture_url as other_avatar,
                       ep.company_name as other_role,
                       (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND sender_id != ? AND is_read = 0) as unread_count,
                       (SELECT content FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message,
                       (SELECT created_at FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message_time
                FROM conversations c
                JOIN users u ON u.id = c.employer_id
                LEFT JOIN employer_profiles ep ON ep.user_id = u.id
                WHERE c.jobseeker_id = ?
                ORDER BY c.updated_at DESC
            ");
            $stmt->execute([$userId, $userId]);
        }

        return $stmt->fetchAll();
    }

    /**
     * Mark messages as read
     */
    public function markMessagesAsRead(int $conversationId, int $userId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE messages 
            SET is_read = 1 
            WHERE conversation_id = ? AND sender_id != ? AND is_read = 0
        ");
        $stmt->execute([$conversationId, $userId]);
    }

    /**
     * Get conversation details
     */
    public function getConversation(int $conversationId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.*,
                   emp.first_name as employer_first_name,
                   emp.last_name as employer_last_name,
                   js.first_name as jobseeker_first_name,
                   js.last_name as jobseeker_last_name,
                   ep.company_name,
                   jp.role_title
            FROM conversations c
            JOIN users emp ON emp.id = c.employer_id
            JOIN users js ON js.id = c.jobseeker_id
            LEFT JOIN employer_profiles ep ON ep.user_id = emp.id
            LEFT JOIN jobseeker_profiles jp ON jp.user_id = js.id
            WHERE c.id = ?
        ");
        $stmt->execute([$conversationId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Upload file for message
     */
    public function uploadMessageFile(array $file): array
    {
        // Use absolute path from the project root
        $uploadDir = __DIR__ . '/../../Public/uploads/messages/';
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'zip'];
        $maxSize = 10 * 1024 * 1024; // 10MB

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error');
        }

        if ($file['size'] > $maxSize) {
            throw new Exception('File too large (max 10MB)');
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedTypes)) {
            throw new Exception('File type not allowed');
        }

        // Ensure upload directory exists
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                throw new Exception('Failed to create upload directory');
            }
        }

        $fileName = 'msg_' . uniqid() . '.' . $extension;
        $filePath = $uploadDir . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw new Exception('Failed to save file');
        }

        return [
            'url' => '/QuickHire/Public/uploads/messages/' . $fileName,
            'name' => $file['name'],
            'size' => $file['size']
        ];
    }
}