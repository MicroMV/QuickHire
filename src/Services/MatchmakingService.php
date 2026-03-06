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
                $criteria['employment_type'] ?? null
            ]);

            $queueId = (int)$this->pdo->lastInsertId();

            if (!empty($skillIds)) {
                $ins = $this->pdo->prepare("INSERT INTO matchmaking_queue_skills (queue_id, skill_id) VALUES (?, ?)");
                foreach ($skillIds as $sid) $ins->execute([$queueId, (int)$sid]);
            }

            $this->pdo->commit();
            return $queueId;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw new Exception("Failed to start matchmaking.");
        }
    }

    /** Find a jobseeker that matches employer criteria >=80; create call room; return room_code */
    public function matchEmployerNow(int $queueId, int $employerId): ?string
    {
        // load queue criteria
        $q = $this->getQueue($queueId);
        if (!$q || (int)$q['is_active'] !== 1) return null;

        // required skills
        $reqSkillIds = $this->getQueueSkillIds($queueId);

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

    private function newRoomCode(): string
    {
        return 'QH-' . bin2hex(random_bytes(6));
    }
}