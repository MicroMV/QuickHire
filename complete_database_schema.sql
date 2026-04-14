-- QuickHire Complete Database Schema
-- This file contains ALL tables and data for the QuickHire platform
-- Import this file to set up a fresh database with all features

-- Drop existing tables if they exist (for clean import)
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS webrtc_signals;
DROP TABLE IF EXISTS chat_messages;
DROP TABLE IF EXISTS matchmaking_queue_skills;
DROP TABLE IF EXISTS matchmaking_queue;
DROP TABLE IF EXISTS employer_required_skills;
DROP TABLE IF EXISTS jobseeker_skills;
DROP TABLE IF EXISTS calls;
DROP TABLE IF EXISTS employer_profiles;
DROP TABLE IF EXISTS jobseeker_profiles;
DROP TABLE IF EXISTS skills;
DROP TABLE IF EXISTS users;
-- Drop unused tables
DROP TABLE IF EXISTS job_request_skills;
DROP TABLE IF EXISTS job_requests;
DROP TABLE IF EXISTS matches;

SET FOREIGN_KEY_CHECKS = 1;

-- Users table
CREATE TABLE users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  role ENUM('JOBSEEKER', 'EMPLOYER') NOT NULL,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  email VARCHAR(255) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  is_profile_complete TINYINT(1) DEFAULT 0,
  last_active TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_email (email),
  INDEX idx_role (role),
  INDEX idx_last_active (last_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Skills table
CREATE TABLE skills (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) UNIQUE NOT NULL,
  category VARCHAR(100),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_name (name),
  INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jobseeker profiles table
CREATE TABLE jobseeker_profiles (
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
  employment_type VARCHAR(50),
  resume_url VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_role_title (role_title),
  INDEX idx_country (country),
  INDEX idx_employment_type (employment_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Employer profiles table
CREATE TABLE employer_profiles (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT UNIQUE NOT NULL,
  profile_picture_url VARCHAR(255),
  country VARCHAR(100),
  company_name VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_country (country),
  INDEX idx_company_name (company_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jobseeker skills junction table
CREATE TABLE jobseeker_skills (
  id INT PRIMARY KEY AUTO_INCREMENT,
  jobseeker_user_id INT NOT NULL,
  skill_id INT NOT NULL,
  proficiency_level VARCHAR(50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (jobseeker_user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE,
  UNIQUE KEY unique_jobseeker_skill (jobseeker_user_id, skill_id),
  INDEX idx_jobseeker_user_id (jobseeker_user_id),
  INDEX idx_skill_id (skill_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Employer required skills junction table
CREATE TABLE employer_required_skills (
  id INT PRIMARY KEY AUTO_INCREMENT,
  employer_user_id INT NOT NULL,
  skill_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (employer_user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE,
  UNIQUE KEY unique_employer_skill (employer_user_id, skill_id),
  INDEX idx_employer_user_id (employer_user_id),
  INDEX idx_skill_id (skill_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Matchmaking queue table
CREATE TABLE matchmaking_queue (
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
  INDEX idx_role (role),
  INDEX idx_wanted_role (wanted_role),
  INDEX idx_wanted_country (wanted_country)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Matchmaking queue skills junction table
CREATE TABLE matchmaking_queue_skills (
  id INT PRIMARY KEY AUTO_INCREMENT,
  queue_id INT NOT NULL,
  skill_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (queue_id) REFERENCES matchmaking_queue(id) ON DELETE CASCADE,
  FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE,
  UNIQUE KEY unique_queue_skill (queue_id, skill_id),
  INDEX idx_queue_id (queue_id),
  INDEX idx_skill_id (skill_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Calls table
CREATE TABLE calls (
  id INT PRIMARY KEY AUTO_INCREMENT,
  room_code VARCHAR(50) UNIQUE NOT NULL,
  employer_user_id INT NOT NULL,
  jobseeker_user_id INT NULL,
  status ENUM('WAITING', 'RINGING', 'IN_CALL', 'COMPLETED', 'MISSED', 'REJECTED') DEFAULT 'RINGING',
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
CREATE TABLE webrtc_signals (
  id INT PRIMARY KEY AUTO_INCREMENT,
  room_code VARCHAR(50) NOT NULL,
  sender_id INT NOT NULL,
  message_type VARCHAR(50) NOT NULL,
  payload LONGTEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_room_code (room_code),
  INDEX idx_sender_id (sender_id),
  INDEX idx_message_type (message_type),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chat conversations table
CREATE TABLE conversations (
  id INT PRIMARY KEY AUTO_INCREMENT,
  employer_id INT NOT NULL,
  jobseeker_id INT NOT NULL,
  job_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (employer_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (jobseeker_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY unique_conversation (employer_id, jobseeker_id),
  INDEX idx_employer_id (employer_id),
  INDEX idx_jobseeker_id (jobseeker_id),
  INDEX idx_updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Messages table (unified for both call chat and main messaging)
CREATE TABLE messages (
  id INT PRIMARY KEY AUTO_INCREMENT,
  conversation_id INT NOT NULL,
  sender_id INT NOT NULL,
  message_type ENUM('text', 'file', 'job_application') DEFAULT 'text',
  content TEXT NOT NULL,
  file_url VARCHAR(500) NULL,
  file_name VARCHAR(255) NULL,
  file_size INT NULL,
  is_read TINYINT(1) DEFAULT 0,
  room_code VARCHAR(50) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
  FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_conversation_id (conversation_id),
  INDEX idx_sender_id (sender_id),
  INDEX idx_room_code (room_code),
  INDEX idx_created_at (created_at),
  INDEX idx_is_read (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Job posts table
CREATE TABLE job_posts (
  id INT PRIMARY KEY AUTO_INCREMENT,
  employer_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  role_title VARCHAR(100),
  employment_type VARCHAR(50),
  country VARCHAR(100),
  rate_per_hour DECIMAL(10, 2),
  hours_per_week INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (employer_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_employer_id (employer_id),
  INDEX idx_is_active (is_active),
  INDEX idx_role_title (role_title),
  INDEX idx_country (country),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Job post skills junction table
CREATE TABLE job_post_skills (
  id INT PRIMARY KEY AUTO_INCREMENT,
  job_post_id INT NOT NULL,
  skill_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (job_post_id) REFERENCES job_posts(id) ON DELETE CASCADE,
  FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE,
  UNIQUE KEY unique_job_skill (job_post_id, skill_id),
  INDEX idx_job_post_id (job_post_id),
  INDEX idx_skill_id (skill_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert comprehensive skills data
INSERT INTO skills (name, category) VALUES
-- Programming Languages
('JavaScript', 'Programming'),
('Python', 'Programming'),
('PHP', 'Programming'),
('Java', 'Programming'),
('C++', 'Programming'),
('C#', 'Programming'),
('TypeScript', 'Programming'),
('Go', 'Programming'),
('Rust', 'Programming'),
('Ruby', 'Programming'),
('Swift', 'Programming'),
('Kotlin', 'Programming'),

-- Frontend Technologies
('React', 'Frontend'),
('Vue.js', 'Frontend'),
('Angular', 'Frontend'),
('HTML', 'Frontend'),
('CSS', 'Frontend'),
('SASS/SCSS', 'Frontend'),
('Bootstrap', 'Frontend'),
('Tailwind CSS', 'Frontend'),
('jQuery', 'Frontend'),
('Webpack', 'Frontend'),

-- Backend Technologies
('Node.js', 'Backend'),
('Laravel', 'Backend'),
('Django', 'Backend'),
('Flask', 'Backend'),
('Express.js', 'Backend'),
('Spring Boot', 'Backend'),
('ASP.NET', 'Backend'),
('Ruby on Rails', 'Backend'),

-- Database Technologies
('MySQL', 'Database'),
('MongoDB', 'Database'),
('PostgreSQL', 'Database'),
('SQL Server', 'Database'),
('SQLite', 'Database'),
('Redis', 'Database'),
('Oracle', 'Database'),
('SQL', 'Database'),

-- Cloud & DevOps
('AWS', 'Cloud'),
('Google Cloud', 'Cloud'),
('Azure', 'Cloud'),
('Docker', 'DevOps'),
('Kubernetes', 'DevOps'),
('Jenkins', 'DevOps'),
('CI/CD', 'DevOps'),
('Terraform', 'DevOps'),

-- Tools & Version Control
('Git', 'Tools'),
('GitHub', 'Tools'),
('GitLab', 'Tools'),
('Jira', 'Tools'),
('Slack', 'Tools'),
('VS Code', 'Tools'),

-- API & Integration
('REST API', 'API'),
('GraphQL', 'API'),
('SOAP', 'API'),
('Microservices', 'API'),

-- Operating Systems
('Linux', 'System'),
('Windows', 'System'),
('macOS', 'System'),
('Ubuntu', 'System'),

-- Design & UI/UX
('UI/UX Design', 'Design'),
('Graphic Design', 'Design'),
('Figma', 'Design'),
('Adobe Photoshop', 'Design'),
('Adobe Illustrator', 'Design'),
('Sketch', 'Design'),

-- Project Management
('Project Management', 'Management'),
('Agile', 'Management'),
('Scrum', 'Management'),
('Kanban', 'Management'),
('Waterfall', 'Management'),

-- Soft Skills
('Communication', 'Soft Skills'),
('Leadership', 'Soft Skills'),
('Problem Solving', 'Soft Skills'),
('Team Collaboration', 'Soft Skills'),
('Time Management', 'Soft Skills'),
('Critical Thinking', 'Soft Skills'),

-- Data & Analytics
('Data Analysis', 'Analytics'),
('Machine Learning', 'AI'),
('Artificial Intelligence', 'AI'),
('Data Science', 'Analytics'),
('Power BI', 'Analytics'),
('Tableau', 'Analytics'),
('Excel', 'Analytics'),

-- Mobile Development
('Mobile Development', 'Development'),
('iOS Development', 'Development'),
('Android Development', 'Development'),
('React Native', 'Development'),
('Flutter', 'Development'),

-- Web Development
('Web Development', 'Development'),
('Full Stack Development', 'Development'),
('Frontend Development', 'Development'),
('Backend Development', 'Development'),

-- Testing & Quality Assurance
('QA Testing', 'Testing'),
('Automation Testing', 'Testing'),
('Manual Testing', 'Testing'),
('Unit Testing', 'Testing'),
('Integration Testing', 'Testing'),
('Selenium', 'Testing'),

-- Security
('Cybersecurity', 'Security'),
('Network Security', 'Security'),
('Penetration Testing', 'Security'),

-- Business & Marketing
('Digital Marketing', 'Marketing'),
('SEO', 'Marketing'),
('Content Writing', 'Marketing'),
('Social Media Marketing', 'Marketing'),
('Business Analysis', 'Business'),
('Sales', 'Business');

-- Create indexes for better performance
CREATE INDEX idx_skills_category ON skills(category);
CREATE INDEX idx_jobseeker_profiles_employment_type ON jobseeker_profiles(employment_type);
CREATE INDEX idx_matchmaking_queue_employment_type ON matchmaking_queue(employment_type);
