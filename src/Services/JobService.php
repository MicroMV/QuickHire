<?php

namespace Rongie\QuickHire\Services;

use PDO;
use Exception;

class JobService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Create a new job post
     */
    public function createJobPost(int $employerId, string $title, string $description, ?string $roleTitle = null, ?string $employmentType = null, ?string $country = null, ?float $ratePerHour = null, array $skillIds = [], ?int $hoursPerWeek = null): array
    {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("
                INSERT INTO job_posts (employer_id, title, description, role_title, employment_type, country, rate_per_hour, hours_per_week, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            
            $stmt->execute([
                $employerId,
                $title,
                $description,
                $roleTitle,
                $employmentType,
                $country,
                $ratePerHour,
                $hoursPerWeek
            ]);

            $jobPostId = $this->pdo->lastInsertId();

            // Add skills if provided
            if (!empty($skillIds)) {
                $this->addJobPostSkills($jobPostId, $skillIds);
            }

            $this->pdo->commit();

            return [
                'ok' => true,
                'job_post_id' => $jobPostId,
                'message' => 'Job posted successfully'
            ];

        } catch (Exception $e) {
            $this->pdo->rollBack();
            return [
                'ok' => false,
                'error' => 'Failed to create job post: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Add skills to a job post
     */
    private function addJobPostSkills(int $jobPostId, array $skillIds): void
    {
        if (empty($skillIds)) {
            return;
        }

        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO job_post_skills (job_post_id, skill_id) 
            VALUES (?, ?)
        ");

        foreach ($skillIds as $skillId) {
            if (is_numeric($skillId) && $skillId > 0) {
                $stmt->execute([$jobPostId, (int)$skillId]);
            }
        }
    }

    /**
     * Get job posts for an employer
     */
    public function getEmployerJobPosts(int $employerId, bool $activeOnly = true): array
    {
        try {
            $whereClause = $activeOnly ? "WHERE jp.employer_id = ? AND jp.is_active = 1" : "WHERE jp.employer_id = ?";
            
            $stmt = $this->pdo->prepare("
                SELECT 
                    jp.*,
                    COUNT(jps.skill_id) as skill_count
                FROM job_posts jp
                LEFT JOIN job_post_skills jps ON jp.id = jps.job_post_id
                $whereClause
                GROUP BY jp.id
                ORDER BY jp.created_at DESC
            ");
            
            $stmt->execute([$employerId]);
            $jobPosts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get skills for each job post
            foreach ($jobPosts as &$jobPost) {
                $jobPost['skills'] = $this->getJobPostSkills($jobPost['id']);
            }

            return [
                'ok' => true,
                'job_posts' => $jobPosts
            ];

        } catch (Exception $e) {
            return [
                'ok' => false,
                'error' => 'Failed to get job posts: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get all active job posts for job seekers
     */
    public function getActiveJobPosts(int $limit = 50, int $offset = 0, string $search = '', string $role = '', string $type = '', string $country = ''): array
    {
        try {
            $where = ['jp.is_active = 1'];
            $params = [];

            if ($search !== '') {
                $where[] = '(jp.title LIKE ? OR jp.description LIKE ? OR ep.company_name LIKE ?)';
                $params[] = "%$search%";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }
            if ($role !== '') {
                $where[] = 'jp.role_title = ?';
                $params[] = $role;
            }
            if ($type !== '') {
                $where[] = 'jp.employment_type = ?';
                $params[] = $type;
            }
            if ($country !== '') {
                $where[] = 'ep.country = ?';
                $params[] = $country;
            }

            $whereSQL = implode(' AND ', $where);
            $params[] = $limit;
            $params[] = $offset;

            $stmt = $this->pdo->prepare("
                SELECT 
                    jp.*,
                    u.first_name as employer_first_name,
                    u.last_name as employer_last_name,
                    DATE_FORMAT(u.last_active, '%Y-%m-%dT%H:%i:%sZ') as employer_last_active,
                    ep.company_name,
                    ep.country as employer_country,
                    ep.profile_picture_url as employer_profile_picture_url,
                    COUNT(jps.skill_id) as skill_count
                FROM job_posts jp
                JOIN users u ON jp.employer_id = u.id
                LEFT JOIN employer_profiles ep ON u.id = ep.user_id
                LEFT JOIN job_post_skills jps ON jp.id = jps.job_post_id
                WHERE $whereSQL
                GROUP BY jp.id
                ORDER BY jp.created_at DESC
                LIMIT ? OFFSET ?
            ");
            
            $stmt->execute($params);
            $jobPosts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($jobPosts as &$jobPost) {
                $jobPost['skills'] = $this->getJobPostSkills($jobPost['id']);
            }

            return [
                'ok' => true,
                'job_posts' => $jobPosts,
            ];

        } catch (Exception $e) {
            error_log('JobService::getActiveJobPosts error: ' . $e->getMessage());
            return [
                'ok' => false,
                'error' => 'Failed to get job posts: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get skills for a specific job post
     */
    private function getJobPostSkills(int $jobPostId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT s.id, s.name, s.category
            FROM job_post_skills jps
            JOIN skills s ON jps.skill_id = s.id
            WHERE jps.job_post_id = ?
            ORDER BY s.name
        ");
        
        $stmt->execute([$jobPostId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get a specific job post by ID
     */
    public function getJobPost(int $jobPostId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    jp.*,
                    u.first_name as employer_first_name,
                    u.last_name as employer_last_name,
                    DATE_FORMAT(u.last_active, '%Y-%m-%dT%H:%i:%sZ') as employer_last_active,
                    ep.company_name,
                    ep.country as employer_country,
                    ep.profile_picture_url as employer_profile_picture_url,
                    ep.profile_picture_url as employer_avatar
                FROM job_posts jp
                JOIN users u ON jp.employer_id = u.id
                LEFT JOIN employer_profiles ep ON u.id = ep.user_id
                WHERE jp.id = ? AND jp.is_active = 1
            ");
            
            $stmt->execute([$jobPostId]);
            $jobPost = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$jobPost) {
                return [
                    'ok' => false,
                    'error' => 'Job post not found'
                ];
            }

            // Get skills for the job post
            $jobPost['skills'] = $this->getJobPostSkills($jobPostId);

            return [
                'ok' => true,
                'job_post' => $jobPost
            ];

        } catch (Exception $e) {
            return [
                'ok' => false,
                'error' => 'Failed to get job post: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update job post status (activate/deactivate)
     */
    public function updateJobPostStatus(int $jobPostId, int $employerId, bool $isActive): array
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE job_posts 
                SET is_active = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ? AND employer_id = ?
            ");
            
            $stmt->execute([$isActive ? 1 : 0, $jobPostId, $employerId]);

            if ($stmt->rowCount() === 0) {
                return [
                    'ok' => false,
                    'error' => 'Job post not found or access denied'
                ];
            }

            return [
                'ok' => true,
                'message' => $isActive ? 'Job post activated' : 'Job post deactivated'
            ];

        } catch (Exception $e) {
            return [
                'ok' => false,
                'error' => 'Failed to update job post: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update a job post
     */
    public function updateJobPost(int $jobPostId, array $jobData, array $skillIds = []): bool
    {
        try {
            $this->pdo->beginTransaction();

            // Update job post
            $stmt = $this->pdo->prepare("
                UPDATE job_posts 
                SET title = ?, description = ?, role_title = ?, employment_type = ?, 
                    country = ?, rate_per_hour = ?, hours_per_week = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            
            $stmt->execute([
                $jobData['title'],
                $jobData['description'],
                $jobData['role_title'],
                $jobData['employment_type'],
                $jobData['country'],
                $jobData['rate_per_hour'],
                $jobData['hours_per_week'],
                $jobPostId
            ]);

            // Update skills - delete existing and add new ones
            $this->pdo->prepare("DELETE FROM job_post_skills WHERE job_post_id = ?")->execute([$jobPostId]);
            
            if (!empty($skillIds)) {
                $this->addJobPostSkills($jobPostId, $skillIds);
            }

            $this->pdo->commit();
            return true;

        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("JobService::updateJobPost error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a job post
     */
    public function deleteJobPost(int $jobPostId, int $employerId): array
    {
        try {
            $this->pdo->beginTransaction();

            // Delete job post skills first
            $stmt = $this->pdo->prepare("DELETE FROM job_post_skills WHERE job_post_id = ?");
            $stmt->execute([$jobPostId]);

            // Delete job post
            $stmt = $this->pdo->prepare("DELETE FROM job_posts WHERE id = ? AND employer_id = ?");
            $stmt->execute([$jobPostId, $employerId]);

            if ($stmt->rowCount() === 0) {
                $this->pdo->rollBack();
                return [
                    'ok' => false,
                    'error' => 'Job post not found or access denied'
                ];
            }

            $this->pdo->commit();

            return [
                'ok' => true,
                'message' => 'Job post deleted successfully'
            ];

        } catch (Exception $e) {
            $this->pdo->rollBack();
            return [
                'ok' => false,
                'error' => 'Failed to delete job post: ' . $e->getMessage()
            ];
        }
    }
}
