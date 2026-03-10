<?php
namespace Rongie\QuickHire\Services;

use PDO;
use Exception;

class ProfileService
{
    private PDO $pdo;
    private FileUpload $upload;

    public function __construct(PDO $pdo, FileUpload $upload)
    {
        $this->pdo = $pdo;
        $this->upload = $upload;
    }

    /** Save jobseeker profile + mark profile complete */
    public function saveJobseeker(int $userId, array $data, array $files, string $avatarAbs, string $avatarRel, string $resumeAbs, string $resumeRel): void
    {
        // Required fields (based on your scenario)
        $roleTitle = trim($data['role_title'] ?? '');
        $available = trim($data['available_time'] ?? '');
        $rate = $data['rate_per_hour'] ?? '';
        $country = trim($data['country'] ?? '');
        $english = $data['english_mastery'] ?? '';
        $employmentType = $data['employment_type'] ?? '';
        $desc = trim($data['profile_description'] ?? '');

        if ($roleTitle === '' || $available === '' || $rate === '' || $country === '' || $english === '' || $employmentType === '' || $desc === '') {
            throw new Exception("Please fill out all required jobseeker fields.");
        }

        $rateNum = (float)$rate;
        if ($rateNum <= 0) throw new Exception("Rate per hour must be greater than 0.");

        $avatarPath = $this->upload->uploadAvatar($files['profile_picture'] ?? [], $avatarAbs, $avatarRel);
        $resumePath = $this->upload->uploadResume($files['resume'] ?? [], $resumeAbs, $resumeRel);

        // Keep existing files if user didn't re-upload
        $existing = $this->getJobseeker($userId);
        if (!$avatarPath) $avatarPath = $existing['profile_picture_url'] ?? null;
        if (!$resumePath) $resumePath = $existing['resume_url'] ?? null;

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO jobseeker_profiles (
                  user_id, profile_picture_url, role_title, available_time, rate_per_hour,
                  bachelors_degree, profile_description, age, gender, portfolio_url, country, english_mastery, employment_type, resume_url
                ) VALUES (
                  ?, ?, ?, ?, ?,
                  ?, ?, ?, ?, ?, ?, ?, ?, ?
                )
                ON DUPLICATE KEY UPDATE
                  profile_picture_url = COALESCE(VALUES(profile_picture_url), profile_picture_url),
                  role_title = VALUES(role_title),
                  available_time = VALUES(available_time),
                  rate_per_hour = VALUES(rate_per_hour),
                  bachelors_degree = VALUES(bachelors_degree),
                  profile_description = VALUES(profile_description),
                  age = VALUES(age),
                  gender = VALUES(gender),
                  portfolio_url = VALUES(portfolio_url),
                  country = VALUES(country),
                  english_mastery = VALUES(english_mastery),
                  employment_type = VALUES(employment_type),
                  resume_url = COALESCE(VALUES(resume_url), resume_url)
            ");

            $stmt->execute([
                $userId,
                $avatarPath,
                $roleTitle,
                $available,
                $rateNum,
                trim($data['bachelors_degree'] ?? ''),
                $desc,
                $data['age'] !== '' ? (int)$data['age'] : null,
                $data['gender'] ?? null,
                trim($data['portfolio_url'] ?? ''),
                $country,
                $english,
                $employmentType,
                $resumePath
            ]);

            // Handle skills
            $skillIds = $data['skill_ids'] ?? [];
            if (is_array($skillIds)) {
                // Delete existing skills
                $this->pdo->prepare("DELETE FROM jobseeker_skills WHERE jobseeker_user_id = ?")->execute([$userId]);
                
                // Insert new skills
                if (!empty($skillIds)) {
                    $skillStmt = $this->pdo->prepare("INSERT INTO jobseeker_skills (jobseeker_user_id, skill_id) VALUES (?, ?)");
                    foreach ($skillIds as $skillId) {
                        $skillStmt->execute([$userId, (int)$skillId]);
                    }
                }
            }

            $this->pdo->commit();
            $this->markComplete($userId);
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /** Save employer profile + mark profile complete */
    public function saveEmployer(int $userId, array $data, array $files, string $avatarAbs, string $avatarRel): void
    {
        $company = trim($data['company_name'] ?? '');
        $country = trim($data['country'] ?? '');

        if ($company === '' || $country === '') {
            throw new Exception("Please fill out all required employer fields.");
        }

        $avatarPath = $this->upload->uploadAvatar($files['profile_picture'] ?? [], $avatarAbs, $avatarRel);

        $existing = $this->getEmployer($userId);
        if (!$avatarPath) $avatarPath = $existing['profile_picture_url'] ?? null;

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO employer_profiles (user_id, profile_picture_url, country, company_name)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                  profile_picture_url = COALESCE(VALUES(profile_picture_url), profile_picture_url),
                  country = VALUES(country),
                  company_name = VALUES(company_name)
            ");
            $stmt->execute([$userId, $avatarPath, $country, $company]);

            // Handle required skills
            $requiredSkillIds = $data['required_skill_ids'] ?? [];
            if (is_array($requiredSkillIds)) {
                // Delete existing required skills
                $this->pdo->prepare("DELETE FROM employer_required_skills WHERE employer_user_id = ?")->execute([$userId]);
                
                // Insert new required skills
                if (!empty($requiredSkillIds)) {
                    $skillStmt = $this->pdo->prepare("INSERT INTO employer_required_skills (employer_user_id, skill_id) VALUES (?, ?)");
                    foreach ($requiredSkillIds as $skillId) {
                        $skillStmt->execute([$userId, (int)$skillId]);
                    }
                }
            }

            $this->pdo->commit();
            $this->markComplete($userId);
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function getJobseeker(int $userId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM jobseeker_profiles WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: [];
    }

    public function getEmployer(int $userId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM employer_profiles WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: [];
    }

    // -------- private helper --------
    private function markComplete(int $userId): void
    {
        $stmt = $this->pdo->prepare("UPDATE users SET is_profile_complete = 1 WHERE id = ?");
        $stmt->execute([$userId]);
    }
}