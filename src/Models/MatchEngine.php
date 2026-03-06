<?php
namespace Rongie\QuickHire\Models;

class MatchEngine
{
    /**
     * Score a jobseeker against employer criteria
     * Returns a score from 0-100
     * 
     * @param array $criteria Employer criteria: role_title, employment_type, country
     * @param array $jobseeker Jobseeker profile data
     * @param array $requiredSkillIds Skills required by employer
     * @param array $jobseekerSkillIds Skills the jobseeker has
     * @return int Score 0-100
     */
    public function score(array $criteria, array $jobseeker, array $requiredSkillIds, array $jobseekerSkillIds): int
    {
        $score = 0;

        // Country match (30 points)
        if (!empty($criteria['country']) && !empty($jobseeker['country'])) {
            if (strtolower($criteria['country']) === strtolower($jobseeker['country'])) {
                $score += 30;
            }
        }

        // Role title match (25 points)
        if (!empty($criteria['role_title']) && !empty($jobseeker['role_title'])) {
            $empRole = strtolower(trim($criteria['role_title']));
            $jsRole = strtolower(trim($jobseeker['role_title']));
            
            if ($empRole === $jsRole) {
                $score += 25;
            } elseif (strpos($jsRole, $empRole) !== false || strpos($empRole, $jsRole) !== false) {
                $score += 15; // partial match
            }
        }

        // English mastery (20 points)
        if (!empty($jobseeker['english_mastery'])) {
            $englishLevel = strtoupper($jobseeker['english_mastery']);
            $englishScores = [
                'NATIVE' => 20,
                'FLUENT' => 20,
                'ADVANCED' => 15,
                'INTERMEDIATE' => 10,
                'BEGINNER' => 5
            ];
            $score += $englishScores[$englishLevel] ?? 0;
        }

        // Skills match (25 points)
        if (!empty($requiredSkillIds) && !empty($jobseekerSkillIds)) {
            $matchedSkills = count(array_intersect($requiredSkillIds, $jobseekerSkillIds));
            $totalRequired = count($requiredSkillIds);
            
            if ($totalRequired > 0) {
                $skillPercentage = ($matchedSkills / $totalRequired) * 25;
                $score += (int)$skillPercentage;
            }
        } elseif (empty($requiredSkillIds)) {
            // No specific skills required, give full points
            $score += 25;
        }

        // Availability bonus (if available_time is set)
        if (!empty($jobseeker['available_time'])) {
            $availableHours = (int)$jobseeker['available_time'];
            if ($availableHours >= 8) {
                $score += 0; // already counted in other factors
            }
        }

        // Cap score at 100
        return min(100, max(0, $score));
    }
}
