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
     * Get messages for a conversation, including call chat messages from webrtc_signals
     */
    public function getMessages(int $conversationId, int $limit = 50, int $offset = 0): array
    {
        // ── 1. Regular messages from the messages table ───────────────────────
        $stmt = $this->pdo->prepare("
            SELECT m.id,
                   m.conversation_id,
                   m.sender_id,
                   m.message_type,
                   m.content,
                   m.file_url,
                   m.file_name,
                   m.file_size,
                   m.room_code,
                   m.is_read,
                   m.created_at,
                   u.first_name,
                   u.last_name,
                   u.role,
                   COALESCE(jp.profile_picture_url, ep.profile_picture_url) as sender_avatar
            FROM messages m
            JOIN users u ON u.id = m.sender_id
            LEFT JOIN jobseeker_profiles jp ON jp.user_id = m.sender_id AND u.role = 'JOBSEEKER'
            LEFT JOIN employer_profiles ep ON ep.user_id = m.sender_id AND u.role = 'EMPLOYER'
            WHERE m.conversation_id = ?
              AND m.message_type <> 'job_application'
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$conversationId]);
        $dbMessages = $stmt->fetchAll();

        // ── 2. Call chat messages from webrtc_signals ─────────────────────────
        // Find all room_codes for calls between the two users in this conversation
        $convStmt = $this->pdo->prepare("
            SELECT employer_id, jobseeker_id FROM conversations WHERE id = ? LIMIT 1
        ");
        $convStmt->execute([$conversationId]);
        $conv = $convStmt->fetch();

        $signalMessages = [];
        if ($conv) {
            $roomStmt = $this->pdo->prepare("
                SELECT room_code FROM calls
                WHERE employer_user_id = ? AND jobseeker_user_id = ?
            ");
            $roomStmt->execute([$conv['employer_id'], $conv['jobseeker_id']]);
            $rooms = $roomStmt->fetchAll(\PDO::FETCH_COLUMN);

            if (!empty($rooms)) {
                // Collect message IDs already stored in messages table (to avoid duplicates)
                $existingRoomMsgIds = [];
                foreach ($dbMessages as $m) {
                    if ($m['room_code']) {
                        $existingRoomMsgIds[] = $m['room_code'] . '_' . $m['sender_id'] . '_' . $m['created_at'];
                    }
                }

                $placeholders = implode(',', array_fill(0, count($rooms), '?'));
                $sigStmt = $this->pdo->prepare("
                    SELECT s.id,
                           s.room_code,
                           s.sender_id,
                           s.payload,
                           s.created_at,
                           u.first_name,
                           u.last_name,
                           u.role,
                           COALESCE(jp.profile_picture_url, ep.profile_picture_url) as sender_avatar
                    FROM   webrtc_signals s
                    JOIN   users u ON u.id = s.sender_id
                    LEFT JOIN jobseeker_profiles jp ON jp.user_id = s.sender_id AND u.role = 'JOBSEEKER'
                    LEFT JOIN employer_profiles ep ON ep.user_id = s.sender_id AND u.role = 'EMPLOYER'
                    WHERE  s.room_code IN ($placeholders)
                      AND  s.message_type = 'chat'
                    ORDER  BY s.created_at ASC
                ");
                $sigStmt->execute($rooms);

                foreach ($sigStmt->fetchAll() as $s) {
                    $payload = json_decode($s['payload'], true);
                    $content = $payload['message'] ?? '';

                    // Skip if this message was already saved to messages table
                    // (chat_send.php mirrors to messages when both users are present)
                    // We detect duplicates by checking if a messages row with the same
                    // room_code, sender_id, and content exists within 2 seconds
                    $isDuplicate = false;
                    foreach ($dbMessages as $m) {
                        if ($m['room_code'] === $s['room_code']
                            && (int)$m['sender_id'] === (int)$s['sender_id']
                            && $m['content'] === $content
                            && abs(strtotime($m['created_at']) - strtotime($s['created_at'])) <= 2
                        ) {
                            $isDuplicate = true;
                            break;
                        }
                    }

                    if (!$isDuplicate) {
                        $signalMessages[] = [
                            'id'             => 'sig_' . $s['id'],
                            'conversation_id'=> $conversationId,
                            'sender_id'      => $s['sender_id'],
                            'message_type'   => 'text',
                            'content'        => $content,
                            'file_url'       => null,
                            'file_name'      => null,
                            'file_size'      => null,
                            'room_code'      => $s['room_code'],
                            'is_read'        => 1,
                            'created_at'     => $s['created_at'],
                            'first_name'     => $s['first_name'],
                            'last_name'      => $s['last_name'],
                            'role'           => $s['role'],
                            'sender_avatar'  => $s['sender_avatar'],
                        ];
                    }
                }
            }
        }

        // ── 3. Merge, sort by created_at, apply limit/offset ─────────────────
        $all = array_merge($dbMessages, $signalMessages);
        usort($all, fn($a, $b) => strcmp($a['created_at'], $b['created_at']));

        if ($limit <= 0) {
            return array_slice($all, $offset);
        }

        if ($offset > 0) {
            return array_slice($all, $offset, $limit);
        }

        return array_slice($all, max(0, count($all) - $limit), $limit);
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
                       DATE_FORMAT(u.last_active, '%Y-%m-%dT%H:%i:%sZ') as other_last_active,
                       jp.profile_picture_url as other_avatar,
                       jp.profile_picture_url as other_profile_picture_url,
                       jp.role_title as other_role,
                       jpost.title as job_post_title,
                       jpost.id as job_post_id,
                       (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND sender_id != ? AND is_read = 0 AND message_type <> 'job_application') as unread_count,
                       (SELECT content FROM messages WHERE conversation_id = c.id AND message_type <> 'job_application' ORDER BY created_at DESC LIMIT 1) as last_message,
                       (SELECT created_at FROM messages WHERE conversation_id = c.id AND message_type <> 'job_application' ORDER BY created_at DESC LIMIT 1) as last_message_time
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
                       DATE_FORMAT(u.last_active, '%Y-%m-%dT%H:%i:%sZ') as other_last_active,
                       ep.profile_picture_url as other_avatar,
                       ep.profile_picture_url as other_profile_picture_url,
                       ep.company_name as other_role,
                       jpost.title as job_post_title,
                       jpost.id as job_post_id,
                       (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND sender_id != ? AND is_read = 0 AND message_type <> 'job_application') as unread_count,
                       (SELECT content FROM messages WHERE conversation_id = c.id AND message_type <> 'job_application' ORDER BY created_at DESC LIMIT 1) as last_message,
                       (SELECT created_at FROM messages WHERE conversation_id = c.id AND message_type <> 'job_application' ORDER BY created_at DESC LIMIT 1) as last_message_time
                FROM conversations c
                JOIN users u ON u.id = c.employer_id
                LEFT JOIN employer_profiles ep ON ep.user_id = u.id
                LEFT JOIN job_posts jpost ON jpost.id = c.job_id
                WHERE c.jobseeker_id = ?
                ORDER BY c.updated_at DESC
            ");
            $stmt->execute([$userId, $userId]);
        }

        return $this->attachAppliedJobs($stmt->fetchAll());
    }

    /**
     * Record a jobseeker applying to a job in the conversation metadata.
     */
    public function recordJobApplication(int $conversationId, int $jobId): void
    {
        if ($conversationId <= 0 || $jobId <= 0) {
            return;
        }

        $jobStmt = $this->pdo->prepare("
            SELECT jp.id
            FROM conversations c
            JOIN job_posts jp ON jp.id = ? AND jp.employer_id = c.employer_id
            WHERE c.id = ?
            LIMIT 1
        ");
        $jobStmt->execute([$jobId, $conversationId]);

        if (!$jobStmt->fetch()) {
            return;
        }

        $existsStmt = $this->pdo->prepare("
            SELECT id
            FROM messages
            WHERE conversation_id = ?
              AND message_type = 'job_application'
              AND content = ?
            LIMIT 1
        ");
        $existsStmt->execute([$conversationId, (string)$jobId]);

        if (!$existsStmt->fetch()) {
            $insertStmt = $this->pdo->prepare("
                INSERT INTO messages (conversation_id, sender_id, message_type, content, is_read)
                SELECT c.id, c.jobseeker_id, 'job_application', ?, 1
                FROM conversations c
                WHERE c.id = ?
            ");
            $insertStmt->execute([(string)$jobId, $conversationId]);
        }

        $this->pdo->prepare("
            UPDATE conversations
            SET job_id = COALESCE(job_id, ?),
                updated_at = NOW()
            WHERE id = ?
        ")->execute([$jobId, $conversationId]);
    }

    private function attachAppliedJobs(array $conversations): array
    {
        if (empty($conversations)) {
            return $conversations;
        }

        $jobsByConversation = [];
        $conversationIds = [];

        foreach ($conversations as $conversation) {
            $conversationId = (int)$conversation['id'];
            $conversationIds[] = $conversationId;
            $jobsByConversation[$conversationId] = [];

            if (!empty($conversation['job_post_id']) && !empty($conversation['job_post_title'])) {
                $jobId = (int)$conversation['job_post_id'];
                $jobsByConversation[$conversationId][$jobId] = [
                    'id' => $jobId,
                    'title' => $conversation['job_post_title'],
                ];
            }
        }

        $placeholders = implode(',', array_fill(0, count($conversationIds), '?'));
        $stmt = $this->pdo->prepare("
            SELECT m.conversation_id,
                   jp.id,
                   jp.title,
                   MIN(m.created_at) as first_applied_at
            FROM messages m
            JOIN job_posts jp ON jp.id = CAST(m.content AS UNSIGNED)
            WHERE m.conversation_id IN ($placeholders)
              AND m.message_type = 'job_application'
            GROUP BY m.conversation_id, jp.id, jp.title
            ORDER BY first_applied_at ASC
        ");
        $stmt->execute($conversationIds);

        foreach ($stmt->fetchAll() as $row) {
            $conversationId = (int)$row['conversation_id'];
            $jobId = (int)$row['id'];
            $jobsByConversation[$conversationId][$jobId] = [
                'id' => $jobId,
                'title' => $row['title'],
            ];
        }

        foreach ($conversations as &$conversation) {
            $conversationId = (int)$conversation['id'];
            $conversation['applied_jobs'] = array_values($jobsByConversation[$conversationId] ?? []);
        }
        unset($conversation);

        return $conversations;
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
