<?php
namespace Rongie\QuickHire\Services;

use PDO;
use Exception;
use Rongie\QuickHire\Models\MatchEngine;

class MatchmakingService
{
    private PDO $pdo;
    private MatchEngine $engine;

    public function __construct(PDO $pdo, MatchEngine $engine)
    {
        $this->pdo = $pdo;
        $this->engine = $engine;
    }

    /** Employer enters matchmaking: creates queue + skills */
    public function enqueueEmployer(int $employerId, array $criteria, array $skillIds): int
    {
        if (!$this->userHasRole($employerId, 'EMPLOYER')) {
            throw new Exception("Only employers can start matchmaking.");
        }

        $this->pdo->beginTransaction();
        try {
            // deactivate old queue if any
            $this->pdo->prepare("UPDATE matchmaking_queue SET is_active=0 WHERE user_id=?")->execute([$employerId]);

            $stmt = $this->pdo->prepare("
              INSERT INTO matchmaking_queue (user_id, role, wanted_role, wanted_country, employment_type)
              VALUES (?, 'EMPLOYER', ?, ?, ?)
            ");
            $stmt->execute([
                $employerId,
                trim($criteria['role_title'] ?? ''),
                trim($criteria['country'] ?? ''),
                trim($criteria['employment_type'] ?? '')
            ]);

            $queueId = (int)$this->pdo->lastInsertId();

            if (!empty($skillIds)) {
                // Save to queue skills
                $ins = $this->pdo->prepare("INSERT INTO matchmaking_queue_skills (queue_id, skill_id) VALUES (?, ?)");
                foreach ($skillIds as $sid) $ins->execute([$queueId, (int)$sid]);
                
                // Also save to employer_required_skills for persistence
                $this->pdo->prepare("DELETE FROM employer_required_skills WHERE employer_user_id = ?")->execute([$employerId]);
                $empIns = $this->pdo->prepare("INSERT INTO employer_required_skills (employer_user_id, skill_id) VALUES (?, ?)");
                foreach ($skillIds as $sid) $empIns->execute([$employerId, (int)$sid]);
            }

            $this->pdo->commit();
            return $queueId;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            error_log("MatchmakingService::enqueueEmployer error: " . $e->getMessage());
            throw new Exception("Failed to start matchmaking: " . $e->getMessage());
        }
    }

    /** Find a jobseeker that matches employer criteria >=80; create call room; return room_code */
    /** Find a jobseeker that matches employer criteria >=80; create call room; return room_code */
    public function matchEmployerNow(int $queueId, int $employerId): ?string
    {
        if (!$this->userHasRole($employerId, 'EMPLOYER')) {
            return null;
        }

        // load queue criteria
        $q = $this->getQueue($queueId);
        if (!$q || (int)$q['is_active'] !== 1) return null;

        // required skills - try queue first, then employer_required_skills as fallback
        $reqSkillIds = $this->getQueueSkillIds($queueId);
        if (empty($reqSkillIds)) {
            $reqSkillIds = $this->getEmployerRequiredSkillIds($employerId);
        }

        // jobseeker candidates: completed profiles only, not already in call/queue
        try {
            $candidates = $this->pdo->query("
              SELECT u.id AS user_id, p.*
              FROM users u
              JOIN jobseeker_profiles p ON p.user_id = u.id
              WHERE u.role='JOBSEEKER' AND u.is_profile_complete=1
            ")->fetchAll();
        } catch (\Throwable $e) {
            return null;
        }

        $best = null;
        $bestScore = 0;

        foreach ($candidates as $js) {
            $jobseekerId = (int)$js['user_id'];

            // skip if jobseeker already in an active call
            if ($this->isInActiveCall($jobseekerId)) continue;

            $jsSkillIds = $this->getJobseekerSkillIds($jobseekerId);

            $score = $this->engine->score(
                [
                  'role_title' => $q['wanted_role'] ?? '',
                  'employment_type' => $q['employment_type'] ?? 'PART_TIME',
                  'country' => $q['wanted_country'] ?? ''
                ],
                $js,
                $reqSkillIds,
                $jsSkillIds
            );

            if ($score >= 80 && $score > $bestScore) {
                $bestScore = $score;
                $best = $jobseekerId;
            }
        }

        if (!$best) return null;

        // create call room and deactivate queue
        $room = $this->newRoomCode();

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare("
              INSERT INTO calls (room_code, employer_user_id, jobseeker_user_id, status)
              VALUES (?, ?, ?, 'RINGING')
            ")->execute([$room, $employerId, $best]);

            $this->pdo->prepare("UPDATE matchmaking_queue SET is_active=0 WHERE id=?")->execute([$queueId]);

            $this->pdo->commit();
            return $room;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            return null;
        }
    }

    /** Jobseeker enters matchmaking queue */
    public function enqueueJobseeker(int $jobseekerId): int
    {
        throw new \LogicException("Jobseekers cannot create matchmaking rooms. They can only join employer-created rooms.");
    }

    /** Find next match for user (skip current partner) */
    public function findNextMatch(int $userId, string $role, ?int $skipUserId = null): ?string
    {
        if ($role === 'EMPLOYER') {
            // Get employer's last active queue criteria
            $lastQueue = $this->pdo->prepare("
                SELECT * FROM matchmaking_queue 
                WHERE user_id=? AND role='EMPLOYER' 
                ORDER BY id DESC LIMIT 1
            ");
            $lastQueue->execute([$userId]);
            $q = $lastQueue->fetch();

            if (!$q) return null;

            $reqSkillIds = $this->getQueueSkillIds((int)$q['id']);
            if (empty($reqSkillIds)) {
                $reqSkillIds = $this->getEmployerRequiredSkillIds($userId);
            }

            // Find jobseekers
            $candidates = $this->pdo->query("
              SELECT u.id AS user_id, p.*
              FROM users u
              JOIN jobseeker_profiles p ON p.user_id = u.id
              WHERE u.role='JOBSEEKER' AND u.is_profile_complete=1
            ")->fetchAll();

            $best = null;
            $bestScore = 0;

            foreach ($candidates as $js) {
                $jobseekerId = (int)$js['user_id'];

                // Skip current partner and users in active calls
                if ($jobseekerId === $skipUserId) continue;
                if ($this->isInActiveCall($jobseekerId)) continue;

                $jsSkillIds = $this->getJobseekerSkillIds($jobseekerId);

                $score = $this->engine->score(
                    [
                      'role_title' => $q['wanted_role'] ?? '',
                      'employment_type' => $q['employment_type'] ?? 'PART_TIME',
                      'country' => $q['wanted_country'] ?? ''
                    ],
                    $js,
                    $reqSkillIds,
                    $jsSkillIds
                );

                if ($score >= 80 && $score > $bestScore) {
                    $bestScore = $score;
                    $best = $jobseekerId;
                }
            }

            if (!$best) return null;

            // Create new call room
            $room = $this->newRoomCode();
            $this->pdo->prepare("
              INSERT INTO calls (room_code, employer_user_id, jobseeker_user_id, status)
              VALUES (?, ?, ?, 'RINGING')
            ")->execute([$room, $userId, $best]);

            return $room;

        } else {
            // JOBSEEKERS CANNOT CREATE NEW CALLS
            // They can only join existing calls created by employers
            // This method should not be used for jobseekers to create new matches
            return null;
        }
    }

    // -------- private helpers ----------
    private function getQueue(int $queueId): ?array
    {
        $st = $this->pdo->prepare("SELECT * FROM matchmaking_queue WHERE id=? LIMIT 1");
        $st->execute([$queueId]);
        $r = $st->fetch();
        return $r ?: null;
    }

    private function getQueueSkillIds(int $queueId): array
    {
        $st = $this->pdo->prepare("SELECT skill_id FROM matchmaking_queue_skills WHERE queue_id=?");
        $st->execute([$queueId]);
        return array_map(fn($x) => (int)$x['skill_id'], $st->fetchAll());
    }

    private function getJobseekerSkillIds(int $jobseekerId): array
    {
        $st = $this->pdo->prepare("SELECT skill_id FROM jobseeker_skills WHERE jobseeker_user_id=?");
        $st->execute([$jobseekerId]);
        return array_map(fn($x) => (int)$x['skill_id'], $st->fetchAll());
    }

    private function getEmployerRequiredSkillIds(int $employerId): array
    {
        $st = $this->pdo->prepare("SELECT skill_id FROM employer_required_skills WHERE employer_user_id=?");
        $st->execute([$employerId]);
        return array_map(fn($x) => (int)$x['skill_id'], $st->fetchAll());
    }

    private function isInActiveCall(int $userId): bool
    {
        $st = $this->pdo->prepare("
          SELECT id FROM calls
          WHERE status IN ('RINGING','IN_CALL')
            AND (employer_user_id=? OR jobseeker_user_id=?)
          LIMIT 1
        ");
        $st->execute([$userId, $userId]);
        return (bool)$st->fetch();
    }

    /**
     * Get available employer rooms that match jobseeker criteria
     */
    public function getAvailableEmployerRooms(int $jobseekerId): array
    {
        // Get jobseeker profile
        $jsProfile = $this->pdo->prepare("
            SELECT jp.*, u.id as user_id
            FROM jobseeker_profiles jp
            JOIN users u ON u.id = jp.user_id
            WHERE u.id = ? AND u.role = 'JOBSEEKER' AND u.is_profile_complete = 1
        ")->execute([$jobseekerId]);
        
        $jobseeker = $this->pdo->prepare("
            SELECT jp.*, u.id as user_id
            FROM jobseeker_profiles jp
            JOIN users u ON u.id = jp.user_id
            WHERE u.id = ? AND u.role = 'JOBSEEKER' AND u.is_profile_complete = 1
        ");
        $jobseeker->execute([$jobseekerId]);
        $jsData = $jobseeker->fetch();
        
        if (!$jsData) return [];

        // Get jobseeker skills
        $jsSkillIds = $this->getJobseekerSkillIds($jobseekerId);

        // Get waiting employer rooms
        $waitingRooms = $this->pdo->query("
            SELECT c.*, mq.wanted_role, mq.wanted_country, mq.employment_type, mq.id as queue_id,
                   u.first_name, u.last_name, ep.company_name
            FROM calls c
            JOIN matchmaking_queue mq ON mq.user_id = c.employer_user_id AND mq.is_active = 1
            JOIN users u ON u.id = c.employer_user_id
            LEFT JOIN employer_profiles ep ON ep.user_id = c.employer_user_id
            WHERE c.status = 'WAITING'
            ORDER BY c.created_at ASC
        ")->fetchAll();

        $availableRooms = [];
        foreach ($waitingRooms as $room) {
            // Get employer required skills
            $empSkillIds = $this->getQueueSkillIds($room['queue_id']);
            if (empty($empSkillIds)) {
                $empSkillIds = $this->getEmployerRequiredSkillIds($room['employer_user_id']);
            }

            // Calculate match score
            $criteria = [
                'role_title' => $room['wanted_role'],
                'employment_type' => $room['employment_type'],
                'country' => $room['wanted_country']
            ];

            $score = $this->engine->score($criteria, $jsData, $empSkillIds, $jsSkillIds);

            // Only show rooms with score >= 60 (lower threshold for browsing)
            if ($score >= 60) {
                $room['match_score'] = $score;
                $room['employer_name'] = trim($room['first_name'] . ' ' . $room['last_name']);
                $availableRooms[] = $room;
            }
        }

        // Sort by match score (highest first)
        usort($availableRooms, function($a, $b) {
            return $b['match_score'] - $a['match_score'];
        });

        return $availableRooms;
    }

    /**
     * Join an employer's waiting room
     */
    public function joinEmployerRoom(string $roomCode, int $jobseekerId): bool
    {
        if (!$this->userHasRole($jobseekerId, 'JOBSEEKER')) {
            return false;
        }

        $this->pdo->beginTransaction();
        try {
            // Check if room exists and is waiting
            $room = $this->pdo->prepare("
                SELECT * FROM calls WHERE room_code = ? AND status = 'WAITING' AND jobseeker_user_id IS NULL
            ");
            $room->execute([$roomCode]);
            $roomData = $room->fetch();

            if (!$roomData) {
                $this->pdo->rollBack();
                return false;
            }

            // Update room with jobseeker and change status to RINGING
            $this->pdo->prepare("
                UPDATE calls 
                SET jobseeker_user_id = ?, status = 'RINGING', updated_at = CURRENT_TIMESTAMP
                WHERE room_code = ? AND status = 'WAITING'
            ")->execute([$jobseekerId, $roomCode]);

            $this->pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            error_log("MatchmakingService::joinEmployerRoom error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a room for employer to wait for jobseekers
     * Always creates a room regardless of available matches
     */
    public function createEmployerRoom(int $employerId, array $criteria, array $skillIds): ?string
    {
        if (!$this->userHasRole($employerId, 'EMPLOYER')) {
            throw new Exception("Only employers can create call rooms.");
        }

        $this->pdo->beginTransaction();
        try {
            // Deactivate old queue if any
            $this->pdo->prepare("UPDATE matchmaking_queue SET is_active=0 WHERE user_id=?")->execute([$employerId]);

            // Create queue entry
            $stmt = $this->pdo->prepare("
              INSERT INTO matchmaking_queue (user_id, role, wanted_role, wanted_country, employment_type)
              VALUES (?, 'EMPLOYER', ?, ?, ?)
            ");
            $stmt->execute([
                $employerId,
                trim($criteria['role_title'] ?? ''),
                trim($criteria['country'] ?? ''),
                trim($criteria['employment_type'] ?? '')
            ]);

            $queueId = (int)$this->pdo->lastInsertId();

            // Save skills if provided - but don't overwrite existing skills if empty
            if (!empty($skillIds)) {
                $ins = $this->pdo->prepare("INSERT INTO matchmaking_queue_skills (queue_id, skill_id) VALUES (?, ?)");
                foreach ($skillIds as $sid) $ins->execute([$queueId, (int)$sid]);
                
                // Only update employer_required_skills if skills are actually provided
                // Don't delete existing skills if no skills are provided
                $this->pdo->prepare("DELETE FROM employer_required_skills WHERE employer_user_id = ?")->execute([$employerId]);
                $empIns = $this->pdo->prepare("INSERT INTO employer_required_skills (employer_user_id, skill_id) VALUES (?, ?)");
                foreach ($skillIds as $sid) $empIns->execute([$employerId, (int)$sid]);
            } else {
                // If no skills provided, use existing employer skills for the queue
                $existingSkills = $this->getEmployerRequiredSkillIds($employerId);
                if (!empty($existingSkills)) {
                    $ins = $this->pdo->prepare("INSERT INTO matchmaking_queue_skills (queue_id, skill_id) VALUES (?, ?)");
                    foreach ($existingSkills as $sid) $ins->execute([$queueId, (int)$sid]);
                }
            }

            // Create room immediately (WAITING status means employer is waiting for jobseeker)
            $room = $this->newRoomCode();
            $this->pdo->prepare("
              INSERT INTO calls (room_code, employer_user_id, status)
              VALUES (?, ?, 'WAITING')
            ")->execute([$room, $employerId]);

            $this->pdo->commit();
            return $room;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            error_log("MatchmakingService::createEmployerRoom error: " . $e->getMessage());
            throw new Exception("Failed to create room: " . $e->getMessage());
        }
    }

    private function newRoomCode(): string
    {
        return 'QH-' . bin2hex(random_bytes(6));
    }

    private function userHasRole(int $userId, string $role): bool
    {
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE id = ? AND role = ? LIMIT 1");
        $stmt->execute([$userId, $role]);
        return (bool)$stmt->fetch();
    }
}
