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

        // Country match (25 points)
        if (!empty($criteria['country']) && !empty($jobseeker['country'])) {
            if (strtolower($criteria['country']) === strtolower($jobseeker['country'])) {
                $score += 25;
            }
        }

        // Role title match (20 points)
        if (!empty($criteria['role_title']) && !empty($jobseeker['role_title'])) {
            $empRole = strtolower(trim($criteria['role_title']));
            $jsRole = strtolower(trim($jobseeker['role_title']));
            
            if ($empRole === $jsRole) {
                $score += 20;
            } elseif (strpos($jsRole, $empRole) !== false || strpos($empRole, $jsRole) !== false) {
                $score += 12; // partial match
            }
        }

        // Employment type match (15 points)
        if (!empty($criteria['employment_type']) && !empty($jobseeker['employment_type'])) {
            if (strtoupper($criteria['employment_type']) === strtoupper($jobseeker['employment_type'])) {
                $score += 15;
            } else {
                // Compatible employment types get partial points
                $empType = strtoupper($criteria['employment_type']);
                $jsType = strtoupper($jobseeker['employment_type']);
                
                // Full-time and contract are somewhat compatible
                if (($empType === 'FULL_TIME' && $jsType === 'CONTRACT') || 
                    ($empType === 'CONTRACT' && $jsType === 'FULL_TIME')) {
                    $score += 8;
                }
                // Part-time and freelance are somewhat compatible
                elseif (($empType === 'PART_TIME' && $jsType === 'FREELANCE') || 
                        ($empType === 'FREELANCE' && $jsType === 'PART_TIME')) {
                    $score += 8;
                }
            }
        }

        // English mastery (15 points)
        if (!empty($jobseeker['english_mastery'])) {
            $englishLevel = strtoupper($jobseeker['english_mastery']);
            $englishScores = [
                'NATIVE' => 15,
                'FLUENT' => 15,
                'ADVANCED' => 12,
                'INTERMEDIATE' => 8,
                'BEGINNER' => 4
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

        // Cap score at 100
        return min(100, max(0, $score));
    }
}
