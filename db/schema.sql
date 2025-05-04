-- MySQL Database Schema for Freelance Platform
-- This script creates all tables required for the freelance platform

-- Create users table
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    user_type ENUM('freelancer', 'client', 'admin') NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    headline VARCHAR(255),
    summary TEXT,
    location VARCHAR(255),
    profile_picture VARCHAR(255),
    registration_date DATETIME NOT NULL,
    last_login DATETIME,
    account_status ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    email_verified TINYINT(1) NOT NULL DEFAULT 0,
    verification_token VARCHAR(255),
    two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create user_tokens table for "remember me" functionality
CREATE TABLE IF NOT EXISTS user_tokens (
    token_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    expiry DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create freelancer_profiles table
CREATE TABLE IF NOT EXISTS freelancer_profiles (
    profile_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    headline VARCHAR(255),
    summary TEXT,
    hourly_rate DECIMAL(10,2),
    availability_status ENUM('available', 'limited', 'unavailable') DEFAULT 'available',
    experience_level ENUM('entry', 'intermediate', 'expert') DEFAULT 'entry',
    profile_completion INT DEFAULT 0,
    total_earnings DECIMAL(10,2) DEFAULT 0,
    success_score DECIMAL(3,2) DEFAULT 0,
    on_time_completion DECIMAL(5,2) DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create client_profiles table
CREATE TABLE IF NOT EXISTS client_profiles (
    profile_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    company_name VARCHAR(255),
    industry VARCHAR(255),
    company_size VARCHAR(100),
    website VARCHAR(255),
    description TEXT,
    total_spent DECIMAL(10,2) DEFAULT 0,
    payment_verified TINYINT(1) DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create categories table
CREATE TABLE IF NOT EXISTS categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL,
    parent_category_id INT,
    description TEXT,
    FOREIGN KEY (parent_category_id) REFERENCES categories(category_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create skills table
CREATE TABLE IF NOT EXISTS skills (
    skill_id INT AUTO_INCREMENT PRIMARY KEY,
    skill_name VARCHAR(100) NOT NULL UNIQUE,
    category_id INT,
    description TEXT,
    FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create user_skills table
CREATE TABLE IF NOT EXISTS user_skills (
    user_skill_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    skill_id INT NOT NULL,
    proficiency_level ENUM('beginner', 'intermediate', 'expert') DEFAULT 'intermediate',
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(skill_id) ON DELETE CASCADE,
    UNIQUE KEY user_skill (user_id, skill_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create projects table
CREATE TABLE IF NOT EXISTS projects (
    project_id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    category_id INT,
    subcategory_id INT,
    budget_min DECIMAL(10,2),
    budget_max DECIMAL(10,2),
    project_type ENUM('hourly', 'fixed') NOT NULL,
    status ENUM('draft', 'open', 'in progress', 'completed', 'cancelled') NOT NULL DEFAULT 'open',
    creation_date DATETIME NOT NULL,
    deadline DATE,
    visibility ENUM('public', 'invite_only') DEFAULT 'public',
    featured TINYINT(1) DEFAULT 0,
    completed_date DATETIME,
    FOREIGN KEY (client_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE SET NULL,
    FOREIGN KEY (subcategory_id) REFERENCES categories(category_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create project_skills table
CREATE TABLE IF NOT EXISTS project_skills (
    project_skill_id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    skill_id INT NOT NULL,
    importance_level ENUM('required', 'preferred', 'nice_to_have') DEFAULT 'required',
    FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(skill_id) ON DELETE CASCADE,
    UNIQUE KEY project_skill (project_id, skill_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create project_attachments table
CREATE TABLE IF NOT EXISTS project_attachments (
    attachment_id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    upload_date DATETIME NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create proposals table
CREATE TABLE IF NOT EXISTS proposals (
    proposal_id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    freelancer_id INT NOT NULL,
    cover_letter TEXT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    estimated_completion_days INT NOT NULL,
    status ENUM('submitted', 'shortlisted', 'accepted', 'rejected', 'withdrawn') DEFAULT 'submitted',
    submission_date DATETIME NOT NULL,
    is_featured TINYINT(1) DEFAULT 0,
    last_updated DATETIME,
    FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE,
    FOREIGN KEY (freelancer_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY project_freelancer (project_id, freelancer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create contracts table
CREATE TABLE IF NOT EXISTS contracts (
    contract_id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    client_id INT NOT NULL,
    freelancer_id INT NOT NULL,
    proposal_id INT NOT NULL,
    start_date DATETIME NOT NULL,
    end_date DATETIME,
    status ENUM('active', 'completed', 'cancelled', 'disputed') DEFAULT 'active',
    terms TEXT,
    contract_type ENUM('hourly', 'fixed') NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (freelancer_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (proposal_id) REFERENCES proposals(proposal_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create milestones table
CREATE TABLE IF NOT EXISTS milestones (
    milestone_id INT AUTO_INCREMENT PRIMARY KEY,
    contract_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    amount DECIMAL(10,2) NOT NULL,
    due_date DATE,
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    completion_date DATETIME,
    payment_status ENUM('unpaid', 'in_escrow', 'released', 'refunded') DEFAULT 'unpaid',
    FOREIGN KEY (contract_id) REFERENCES contracts(contract_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create conversations table
CREATE TABLE IF NOT EXISTS conversations (
    conversation_id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT,
    created_date DATETIME NOT NULL,
    status ENUM('active', 'archived') DEFAULT 'active',
    FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create conversation_participants table
CREATE TABLE IF NOT EXISTS conversation_participants (
    participant_id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    user_id INT NOT NULL,
    last_read_timestamp DATETIME,
    FOREIGN KEY (conversation_id) REFERENCES conversations(conversation_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY conversation_user (conversation_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create messages table
CREATE TABLE IF NOT EXISTS messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_id INT NOT NULL,
    message_text TEXT NOT NULL,
    timestamp DATETIME NOT NULL,
    read_status TINYINT(1) DEFAULT 0,
    FOREIGN KEY (conversation_id) REFERENCES conversations(conversation_id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create message_attachments table
CREATE TABLE IF NOT EXISTS message_attachments (
    attachment_id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    FOREIGN KEY (message_id) REFERENCES messages(message_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create reviews table
CREATE TABLE IF NOT EXISTS reviews (
    review_id INT AUTO_INCREMENT PRIMARY KEY,
    contract_id INT NOT NULL,
    reviewer_id INT NOT NULL,
    reviewee_id INT NOT NULL,
    rating DECIMAL(3,2) NOT NULL,
    comment TEXT,
    submission_date DATETIME NOT NULL,
    communication_rating DECIMAL(3,2),
    quality_rating DECIMAL(3,2),
    expertise_rating DECIMAL(3,2),
    deadline_rating DECIMAL(3,2),
    value_rating DECIMAL(3,2),
    public_status TINYINT(1) DEFAULT 1,
    FOREIGN KEY (contract_id) REFERENCES contracts(contract_id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (reviewee_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY contract_reviewer (contract_id, reviewer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create portfolio_items table
CREATE TABLE IF NOT EXISTS portfolio_items (
    portfolio_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    completion_date DATE,
    category_id INT,
    is_featured TINYINT(1) DEFAULT 0,
    client_name VARCHAR(255),
    external_link VARCHAR(255),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create portfolio_attachments table
CREATE TABLE IF NOT EXISTS portfolio_attachments (
    attachment_id INT AUTO_INCREMENT PRIMARY KEY,
    portfolio_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    file_size INT NOT NULL,
    is_thumbnail TINYINT(1) DEFAULT 0,
    FOREIGN KEY (portfolio_id) REFERENCES portfolio_items(portfolio_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT  NOT NULL,
    type         VARCHAR(50),             -- e.g., message, proposal, contract, system
    content      TEXT NOT NULL,
    related_id   INT        DEFAULT NULL, -- ID of related entity like project, proposal etc.
    read_status  TINYINT(1) DEFAULT 0,
    created_date DATETIME   DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (user_id)
);

CREATE TABLE IF NOT EXISTS user_education (
education_id INT AUTO_INCREMENT PRIMARY KEY,
user_id INT NOT NULL,
institution VARCHAR(255) NOT NULL,
degree VARCHAR(255),
field_of_study VARCHAR(255),
start_year YEAR,
end_year YEAR,
description TEXT,
FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create user_certifications table
CREATE TABLE IF NOT EXISTS user_certifications (
certification_id INT AUTO_INCREMENT PRIMARY KEY,
user_id INT NOT NULL,
name VARCHAR(255) NOT NULL,
issuing_organization VARCHAR(255),
issue_date DATE,
expiration_date DATE,
credential_id VARCHAR(255),
credential_url VARCHAR(255),
FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create user_work_experience table
CREATE TABLE IF NOT EXISTS user_work_experience (
experience_id INT AUTO_INCREMENT PRIMARY KEY,
user_id           INT          NOT NULL,
company           VARCHAR(255) NOT NULL,
title             VARCHAR(255) NOT NULL,
location          VARCHAR(255),
start_date        DATE,
end_date          DATE,
currently_working TINYINT(1) DEFAULT 0,
description       TEXT,
 FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create languages table (if not already implicitly defined by usage)
CREATE TABLE IF NOT EXISTS languages (
                                         language_id INT AUTO_INCREMENT PRIMARY KEY,
                                         language_name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create user_languages table
CREATE TABLE IF NOT EXISTS user_languages (
user_language_id INT AUTO_INCREMENT PRIMARY KEY,
user_id           INT                                                  NOT NULL,
language_id       INT                                                  NOT NULL,
FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE,
FOREIGN KEY (language_id) REFERENCES languages (language_id) ON DELETE CASCADE,
UNIQUE KEY user_language (user_id, language_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transactions (
transaction_id INT AUTO_INCREMENT PRIMARY KEY,

user_id                 INT                                                                                                        NOT NULL COMMENT 'User initiating or receiving the transaction',
related_user_id         INT COMMENT 'Other user involved (e.g., client for payout, freelancer for deposit)',

contract_id             INT COMMENT 'Related contract, if applicable',
milestone_id            INT COMMENT 'Related milestone, if applicable',

transaction_type        ENUM ('deposit', 'withdrawal', 'escrow_funding', 'escrow_release', 'refund', 'bonus', 'fee', 'adjustment') NOT NULL,
amount                  DECIMAL(10, 2)                                                                                             NOT NULL,
currency                VARCHAR(3)                                                                                                 NOT NULL DEFAULT 'USD',

status                  ENUM ('pending', 'completed', 'failed', 'cancelled')                                                       NOT NULL DEFAULT 'pending',

description             TEXT,
transaction_date        DATETIME                                                                                                   NOT NULL DEFAULT CURRENT_TIMESTAMP,

external_transaction_id VARCHAR(255) COMMENT 'ID from external payment gateway, if used',

FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE,
FOREIGN KEY (related_user_id) REFERENCES users (user_id) ON DELETE SET NULL,
FOREIGN KEY (contract_id) REFERENCES contracts (contract_id) ON DELETE SET NULL,
FOREIGN KEY (milestone_id) REFERENCES milestones (milestone_id) ON DELETE SET NULL
);
