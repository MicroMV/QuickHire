-- Database migration for skills and employment type features
-- Run this script to update existing QuickHire databases

-- Add employment_type column to jobseeker_profiles if it doesn't exist
ALTER TABLE jobseeker_profiles 
ADD COLUMN IF NOT EXISTS employment_type VARCHAR(50) AFTER english_mastery;

-- Create skills table if it doesn't exist
CREATE TABLE IF NOT EXISTS skills (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) UNIQUE NOT NULL,
  category VARCHAR(100),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create jobseeker_skills table if it doesn't exist
CREATE TABLE IF NOT EXISTS jobseeker_skills (
  id INT PRIMARY KEY AUTO_INCREMENT,
  jobseeker_user_id INT NOT NULL,
  skill_id INT NOT NULL,
  proficiency_level VARCHAR(50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (jobseeker_user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE,
  UNIQUE KEY unique_jobseeker_skill (jobseeker_user_id, skill_id),
  INDEX idx_skill_id (skill_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create employer_required_skills table if it doesn't exist
CREATE TABLE IF NOT EXISTS employer_required_skills (
  id INT PRIMARY KEY AUTO_INCREMENT,
  employer_user_id INT NOT NULL,
  skill_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (employer_user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE,
  UNIQUE KEY unique_employer_skill (employer_user_id, skill_id),
  INDEX idx_skill_id (skill_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample skills data (ignore if already exists)
INSERT IGNORE INTO skills (name, category) VALUES
('JavaScript', 'Programming'),
('Python', 'Programming'),
('PHP', 'Programming'),
('Java', 'Programming'),
('C++', 'Programming'),
('React', 'Frontend'),
('Vue.js', 'Frontend'),
('Angular', 'Frontend'),
('Node.js', 'Backend'),
('Laravel', 'Backend'),
('Django', 'Backend'),
('MySQL', 'Database'),
('MongoDB', 'Database'),
('PostgreSQL', 'Database'),
('AWS', 'Cloud'),
('Google Cloud', 'Cloud'),
('Azure', 'Cloud'),
('Docker', 'DevOps'),
('Kubernetes', 'DevOps'),
('Git', 'Tools'),
('REST API', 'API'),
('GraphQL', 'API'),
('HTML', 'Frontend'),
('CSS', 'Frontend'),
('TypeScript', 'Programming'),
('SQL', 'Database'),
('Linux', 'System'),
('Windows', 'System'),
('UI/UX Design', 'Design'),
('Graphic Design', 'Design'),
('Project Management', 'Management'),
('Agile', 'Management'),
('Scrum', 'Management'),
('Communication', 'Soft Skills'),
('Leadership', 'Soft Skills'),
('Problem Solving', 'Soft Skills'),
('Data Analysis', 'Analytics'),
('Machine Learning', 'AI'),
('Artificial Intelligence', 'AI'),
('Mobile Development', 'Development'),
('iOS Development', 'Development'),
('Android Development', 'Development'),
('Web Development', 'Development'),
('Full Stack Development', 'Development'),
('QA Testing', 'Testing'),
('Automation Testing', 'Testing'),
('Manual Testing', 'Testing');