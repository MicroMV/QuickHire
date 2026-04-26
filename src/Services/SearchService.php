<?php
namespace Rongie\QuickHire\Services;

use PDO;

class SearchService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Search for job seekers based on criteria
     */
    public function searchJobSeekers(string $query, int $limit = 20): array
    {
        $searchTerm = '%' . trim($query) . '%';
        
        $sql = "
            SELECT DISTINCT
                u.id,
                u.first_name,
                u.last_name,
                jp.role_title,
                jp.rate_per_hour,
                jp.country,
                jp.employment_type,
                jp.english_mastery,
                jp.profile_picture_url,
                jp.profile_description,
                jp.available_time,
                jp.bachelors_degree,
                jp.gender,
                jp.age,
                jp.portfolio_url,
                jp.resume_url,
                GROUP_CONCAT(DISTINCT s.name ORDER BY s.name ASC SEPARATOR ', ') as skills
            FROM users u
            JOIN jobseeker_profiles jp ON u.id = jp.user_id
            LEFT JOIN jobseeker_skills js ON u.id = js.jobseeker_user_id
            LEFT JOIN skills s ON js.skill_id = s.id
            WHERE u.role = 'JOBSEEKER'
            AND (
                u.first_name LIKE ? OR
                u.last_name LIKE ? OR
                CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR
                jp.role_title LIKE ? OR
                s.name LIKE ?
            )
            GROUP BY u.id, u.first_name, u.last_name, jp.role_title, jp.rate_per_hour, 
                     jp.country, jp.employment_type, jp.english_mastery, jp.profile_picture_url,
                     jp.profile_description, jp.available_time, jp.bachelors_degree, jp.gender,
                     jp.age, jp.portfolio_url, jp.resume_url
            ORDER BY 
                CASE 
                    WHEN jp.role_title LIKE ? THEN 1
                    WHEN CONCAT(u.first_name, ' ', u.last_name) LIKE ? THEN 2
                    WHEN s.name LIKE ? THEN 3
                    ELSE 4
                END,
                u.first_name ASC
            LIMIT ?
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, // WHERE conditions
            $searchTerm, $searchTerm, $searchTerm, // ORDER BY conditions
            $limit
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Get job seeker details by ID
     */
    public function getJobSeekerDetails(int $userId): ?array
    {
        $sql = "
            SELECT 
                u.id,
                u.first_name,
                u.last_name,
                u.email,
                jp.*,
                GROUP_CONCAT(DISTINCT s.name ORDER BY s.name ASC SEPARATOR ', ') as skills
            FROM users u
            JOIN jobseeker_profiles jp ON u.id = jp.user_id
            LEFT JOIN jobseeker_skills js ON u.id = js.jobseeker_user_id
            LEFT JOIN skills s ON js.skill_id = s.id
            WHERE u.id = ? AND u.role = 'JOBSEEKER'
            GROUP BY u.id
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        
        return $stmt->fetch() ?: null;
    }
}