-- QuickHire Database Schema
-- This file contains all necessary tables for the QuickHire platform

-- Users table
CREATE TABLE IF NOT EXISTS users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  role ENUM('JOBSEEKER', 'EMPLOYER') NOT NULL,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  email VARCHAR(255) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  is_profile_complete TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_email (email),
  INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jobseeker profiles table
CREATE TABLE IF NOT EXISTS jobseeker_profiles (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT UNIQUE NOT NULL,
  profile_picture_url VARCHAR(255),
  role_title VARCHAR(100),
  available_time INT,
  rate_per_hour DECIMAL(10, 2),
  bachelors_degree VARCHAR(255),
  profile_description TEXT,
  age INT,
  gender VARCHAR(50),
  portfolio_url VARCHAR(255),
  country VARCHAR(100),
  english_mastery VARCHAR(50),
  resume_url VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_role_title (role_title),
  INDEX idx_country (country)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Employer profiles table
CREATE TABLE IF NOT EXISTS employer_profiles (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT UNIQUE NOT NULL,
  profile_picture_url VARCHAR(255),
  country VARCHAR(100),
  company_name VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_country (country)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Skills table
CREATE TABLE IF NOT EXISTS skills (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) UNIQUE NOT NULL,
  category VARCHAR(100),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jobseeker skills table
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

-- Matchmaking queue table
CREATE TABLE IF NOT EXISTS matchmaking_queue (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  role ENUM('EMPLOYER', 'JOBSEEKER') NOT NULL,
  wanted_role VARCHAR(100),
  wanted_country VARCHAR(100),
  employment_type VARCHAR(50),
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_id (user_id),
  INDEX idx_is_active (is_active),
  INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Matchmaking queue skills table
CREATE TABLE IF NOT EXISTS matchmaking_queue_skills (
  id INT PRIMARY KEY AUTO_INCREMENT,
  queue_id INT NOT NULL,
  skill_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (queue_id) REFERENCES matchmaking_queue(id) ON DELETE CASCADE,
  FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE,
  UNIQUE KEY unique_queue_skill (queue_id, skill_id),
  INDEX idx_skill_id (skill_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Calls table
CREATE TABLE IF NOT EXISTS calls (
  id INT PRIMARY KEY AUTO_INCREMENT,
  room_code VARCHAR(50) UNIQUE NOT NULL,
  employer_user_id INT NOT NULL,
  jobseeker_user_id INT NOT NULL,
  status ENUM('RINGING', 'IN_CALL', 'COMPLETED', 'MISSED', 'REJECTED') DEFAULT 'RINGING',
  duration_seconds INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (employer_user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (jobseeker_user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_room_code (room_code),
  INDEX idx_employer_user_id (employer_user_id),
  INDEX idx_jobseeker_user_id (jobseeker_user_id),
  INDEX idx_status (status),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- WebRTC signals table
CREATE TABLE IF NOT EXISTS webrtc_signals (
  id INT PRIMARY KEY AUTO_INCREMENT,
  room_code VARCHAR(50) NOT NULL,
  sender_id INT NOT NULL,
  message_type VARCHAR(50) NOT NULL,
  payload LONGTEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_room_code (room_code),
  INDEX idx_sender_id (sender_id),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample skills data
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
