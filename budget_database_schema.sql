


CREATE DATABASE IF NOT EXISTS budget_database_schema;
USE budget_database_schema;

-- Drop existing tables if they exist
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS approval_progress;
DROP TABLE IF EXISTS approval_workflow;
DROP TABLE IF EXISTS dept_lookup;
DROP TABLE IF EXISTS history;
DROP TABLE IF EXISTS budget_entries;
DROP TABLE IF EXISTS budget_request;
DROP TABLE IF EXISTS project_account;
DROP TABLE IF EXISTS campus;
DROP TABLE IF EXISTS cluster;
DROP TABLE IF EXISTS group_table;
DROP TABLE IF EXISTS nature;
DROP TABLE IF EXISTS fund_type;
DROP TABLE IF EXISTS department;
DROP TABLE IF EXISTS division;
DROP TABLE IF EXISTS budget_category;
DROP TABLE IF EXISTS gl_account;
DROP TABLE IF EXISTS account;
SET FOREIGN_KEY_CHECKS = 1;

-- Create all tables
CREATE TABLE group_table (
    code VARCHAR(10) PRIMARY KEY,
    name VARCHAR(100)
);

CREATE TABLE cluster (
    code VARCHAR(10) PRIMARY KEY,
    name VARCHAR(100),
    group_code VARCHAR(10),
    FOREIGN KEY (group_code) REFERENCES group_table(code)
);

CREATE TABLE division (
    code VARCHAR(10) PRIMARY KEY,
    name VARCHAR(100),
    cluster_code VARCHAR(10),
    FOREIGN KEY (cluster_code) REFERENCES cluster(code)
);

CREATE TABLE campus (
    code VARCHAR(10) PRIMARY KEY,
    name VARCHAR(100)
);

CREATE TABLE department (
    code VARCHAR(10) PRIMARY KEY,
    college VARCHAR(100),
    budget_deck VARCHAR(100),
    division_code VARCHAR(10),
    campus_code VARCHAR(10),
    FOREIGN KEY (division_code) REFERENCES division(code),
    FOREIGN KEY (campus_code) REFERENCES campus(code)
);

CREATE TABLE account (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username_email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(255),
    department_code VARCHAR(10),
    role VARCHAR(100),
    FOREIGN KEY (department_code) REFERENCES department(code)
);

CREATE TABLE gl_account (
    code VARCHAR(20) PRIMARY KEY,
    name VARCHAR(255),
    bpr_line_item VARCHAR(100),
    bpr_sub_item VARCHAR(100)
);

CREATE TABLE budget_category (
    code VARCHAR(20) PRIMARY KEY,
    expenditure_type VARCHAR(100)
);

CREATE TABLE fund_type (
    code VARCHAR(10) PRIMARY KEY,
    name VARCHAR(100)
);

CREATE TABLE nature (
    code VARCHAR(10) PRIMARY KEY,
    name VARCHAR(100)
);

CREATE TABLE budget_request (
    request_id VARCHAR(20) PRIMARY KEY,
    account_id INT,
    timestamp TIMESTAMP,
    department_code VARCHAR(10),
    campus_code VARCHAR(10),
    fund_account VARCHAR(100),
    fund_name VARCHAR(255),
    duration VARCHAR(50),
    budget_title VARCHAR(255),
    description TEXT,
    proposed_budget DECIMAL(12, 2),
    approved_budget DECIMAL(15, 2) DEFAULT NULL,
    status VARCHAR(50),
    academic_year VARCHAR(10),
    current_approval_level INT DEFAULT 1,
    total_approval_levels INT DEFAULT 3,
    workflow_complete BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (account_id) REFERENCES account(id),
    FOREIGN KEY (department_code) REFERENCES department(code),
    FOREIGN KEY (campus_code) REFERENCES campus(code)
);

CREATE TABLE budget_entries (
    request_id VARCHAR(20),
    row_num INT,
    month_year DATE,
    gl_code VARCHAR(20),
    budget_category_code VARCHAR(20),
    budget_description TEXT,
    remarks TEXT,
    amount DECIMAL(12, 2),
    approved_amount DECIMAL(15, 2) DEFAULT NULL,
    monthly_upload BOOLEAN,
    manual_adjustment BOOLEAN,
    upload_multiplier DECIMAL(5, 2),
    fund_type_code VARCHAR(10),
    nature_code VARCHAR(10),
    fund_account VARCHAR(100),
    fund_name VARCHAR(100),
    PRIMARY KEY (request_id, row_num),
    FOREIGN KEY (request_id) REFERENCES budget_request(request_id),
    FOREIGN KEY (gl_code) REFERENCES gl_account(code),
    FOREIGN KEY (budget_category_code) REFERENCES budget_category(code),
    FOREIGN KEY (fund_type_code) REFERENCES fund_type(code),
    FOREIGN KEY (nature_code) REFERENCES nature(code)
);

CREATE TABLE history (
    history_id INT AUTO_INCREMENT PRIMARY KEY,
    request_id VARCHAR(20),
    timestamp TIMESTAMP,
    action TEXT,
    account_id INT,
    FOREIGN KEY (request_id) REFERENCES budget_request(request_id),
    FOREIGN KEY (account_id) REFERENCES account(id)
);

CREATE TABLE approval_workflow (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_code VARCHAR(10),
    amount_threshold DECIMAL(12, 2),
    approval_level INT,
    approver_role VARCHAR(50),
    approver_id INT,
    is_required BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (department_code) REFERENCES department(code),
    FOREIGN KEY (approver_id) REFERENCES account(id)
);

CREATE TABLE approval_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id VARCHAR(20),
    approval_level INT,
    approver_id INT,
    status ENUM('pending', 'approved', 'rejected', 'skipped', 'waiting') DEFAULT 'pending',
    comments TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES budget_request(request_id),
    FOREIGN KEY (approver_id) REFERENCES account(id)
);

CREATE TABLE project_account (
    code VARCHAR(10) PRIMARY KEY,
    name VARCHAR(100)
);

CREATE TABLE dept_lookup (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT,
    department_code VARCHAR(10),
    FOREIGN KEY (account_id) REFERENCES account(id),
    FOREIGN KEY (department_code) REFERENCES department(code)
);

CREATE TABLE attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id VARCHAR(20),
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    upload_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    uploaded_by INT,
    FOREIGN KEY (request_id) REFERENCES budget_request(request_id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES account(id)
);

-- Amendment System Tables
CREATE TABLE budget_amendments (
    amendment_id INT AUTO_INCREMENT PRIMARY KEY,
    request_id VARCHAR(20) NOT NULL,
    amendment_number INT NOT NULL,
    created_by INT NOT NULL,
    created_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    amendment_type ENUM('budget_change', 'description_change', 'timeline_change', 'general_modification') DEFAULT 'general_modification',
    amendment_title VARCHAR(255) NOT NULL,
    amendment_reason TEXT NOT NULL,
    original_total_budget DECIMAL(15, 2),
    amended_total_budget DECIMAL(15, 2),
    status ENUM('draft', 'pending', 'approved', 'rejected') DEFAULT 'draft',
    approved_by INT NULL,
    approved_timestamp TIMESTAMP NULL,
    approval_comments TEXT NULL,
    amendment_data JSON DEFAULT NULL COMMENT 'Stores detailed amendment data like new descriptions, timeline changes, etc.',
    FOREIGN KEY (request_id) REFERENCES budget_request(request_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES account(id),
    FOREIGN KEY (approved_by) REFERENCES account(id),
    UNIQUE KEY unique_amendment (request_id, amendment_number)
);

CREATE TABLE budget_amendment_entries (
    amendment_id INT NOT NULL,
    row_num INT NOT NULL,
    gl_code VARCHAR(20) NOT NULL,
    budget_description TEXT NOT NULL,
    original_amount DECIMAL(12, 2) NOT NULL,
    amended_amount DECIMAL(12, 2) NOT NULL,
    PRIMARY KEY (amendment_id, row_num),
    FOREIGN KEY (amendment_id) REFERENCES budget_amendments(amendment_id) ON DELETE CASCADE
);

CREATE TABLE amendment_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    amendment_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    upload_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    uploaded_by INT,
    FOREIGN KEY (amendment_id) REFERENCES budget_amendments(amendment_id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES account(id)
);

-- Insert all sample data
INSERT INTO group_table (code, name) VALUES ('GRP01', 'Admin Group');
INSERT INTO cluster (code, name, group_code) VALUES ('CL01', 'Cluster A', 'GRP01');
INSERT INTO division (code, name, cluster_code) VALUES ('DIV01', 'Division X', 'CL01');

INSERT INTO campus (code, name) VALUES 
('11', 'Manila'), ('12', 'Makati'), ('13', 'McKinley'), ('21', 'Laguna'), ('31', 'BGC');

INSERT INTO department (code, college, budget_deck, division_code, campus_code) VALUES 
('999', 'Test College', 'Test Deck', 'DIV01', '11'),
('CCS', 'College of Computer Studies', 'CCS Deck', 'DIV01', '11'),
('CLA', 'College of Liberal Arts', 'CLA Deck', 'DIV01', '11'),
('COB', 'Ramon V. del Rosario College of Business', 'COB Deck', 'DIV01', '11'),
('GCOE', 'Gokongwei College of Engineering', 'GCOE Deck', 'DIV01', '11'),
('COS', 'College of Science', 'COS Deck', 'DIV01', '11'),
('SOE', 'School of Economics', 'SOE Deck', 'DIV01', '11'),
('BAGCED', 'Br. Andrew Gonzalez College of Education', 'BAGCED Deck', 'DIV01', '11'),
('COL', 'College of Law', 'COL Deck', 'DIV01', '11'),
('JMRIG', 'Jesse M. Robredo Institute of Governance', 'JMRIG Deck', 'DIV01', '11'),
('GSB', 'Graduate School of Business', 'GSB Deck', 'DIV01', '11'),
('IGSP', 'La Salle Institute for Governance and Strategic Policy', 'IGSP Deck', 'DIV01', '11');

-- Insert test accounts (ALL ROLES) - PASSWORDS EXPLICITLY SET
DELETE FROM account WHERE username_email LIKE '%@example.com';
INSERT INTO account (username_email, password, name, department_code, role) VALUES
('testuser@example.com', 'testpass', 'Test User', '999', 'requester'),
('dept.head@example.com', 'testpass', 'Department Head', '999', 'department_head'),
('dean@example.com', 'testpass', 'College Dean', '999', 'dean'),
('vp.finance@example.com', 'testpass', 'VP Finance', '999', 'vp_finance'),
('approver@example.com', 'testpass', 'General Approver', '999', 'approver');

-- Verify passwords are set correctly
UPDATE account SET password = 'testpass' WHERE username_email IN (
    'testuser@example.com',
    'dept.head@example.com', 
    'dean@example.com',
    'vp.finance@example.com',
    'approver@example.com'
);

INSERT INTO gl_account (code, name, bpr_line_item, bpr_sub_item) VALUES 
-- SALARIES (1-10)
('210304001', 'FHIT - SALARIES - 1', 'Salaries', 'Regular'),
('210304002', 'FHIT - SALARIES - 2', 'Salaries', 'Regular'),
('210304003', 'FHIT - SALARIES - 3', 'Salaries', 'Regular'),
('210304004', 'FHIT - SALARIES - 4', 'Salaries', 'Regular'),
('210304005', 'FHIT - SALARIES - 5', 'Salaries', 'Regular'),
('210304006', 'FHIT - SALARIES - 6', 'Salaries', 'Regular'),
('210304007', 'FHIT - SALARIES - 7', 'Salaries', 'Regular'),
('210304008', 'FHIT - SALARIES - 8', 'Salaries', 'Regular'),
('210304009', 'FHIT - SALARIES - 9', 'Salaries', 'Regular'),
('210304010', 'FHIT - SALARIES - 10', 'Salaries', 'Regular'),

-- HONORARIA (1-10)
('210305001', 'FHIT - HONORARIA - 1', 'Honoraria', 'Professional'),
('210305002', 'FHIT - HONORARIA - 2', 'Honoraria', 'Professional'),
('210305003', 'FHIT - HONORARIA - 3', 'Honoraria', 'Professional'),
('210305004', 'FHIT - HONORARIA - 4', 'Honoraria', 'Professional'),
('210305005', 'FHIT - HONORARIA - 5', 'Honoraria', 'Professional'),
('210305006', 'FHIT - HONORARIA - 6', 'Honoraria', 'Professional'),
('210305007', 'FHIT - HONORARIA - 7', 'Honoraria', 'Professional'),
('210305008', 'FHIT - HONORARIA - 8', 'Honoraria', 'Professional'),
('210305009', 'FHIT - HONORARIA - 9', 'Honoraria', 'Professional'),
('210305010', 'FHIT - HONORARIA - 10', 'Honoraria', 'Professional'),

-- PROFESSIONAL FEE (1-10)
('210306001', 'FHIT - PROFESSIONAL FEE - 1', 'Professional Fee', 'Services'),
('210306002', 'FHIT - PROFESSIONAL FEE - 2', 'Professional Fee', 'Services'),
('210306003', 'FHIT - PROFESSIONAL FEE - 3', 'Professional Fee', 'Services'),
('210306004', 'FHIT - PROFESSIONAL FEE - 4', 'Professional Fee', 'Services'),
('210306005', 'FHIT - PROFESSIONAL FEE - 5', 'Professional Fee', 'Services'),
('210306006', 'FHIT - PROFESSIONAL FEE - 6', 'Professional Fee', 'Services'),
('210306007', 'FHIT - PROFESSIONAL FEE - 7', 'Professional Fee', 'Services'),
('210306008', 'FHIT - PROFESSIONAL FEE - 8', 'Professional Fee', 'Services'),
('210306009', 'FHIT - PROFESSIONAL FEE - 9', 'Professional Fee', 'Services'),
('210306010', 'FHIT - PROFESSIONAL FEE - 10', 'Professional Fee', 'Services'),

-- OTHER FHIT EXPENSES
('210303007', 'FHIT - TRANSPORTATION AND DELIVERY EXPENSES', 'Transportation', 'Delivery'),
('210303028', 'FHIT - TRAVEL (LOCAL)', 'Travel', 'Local'),
('210303029', 'FHIT - TRAVEL (FOREIGN)', 'Travel', 'Foreign'),
('210303025', 'FHIT - ACCOMMODATION AND VENUE', 'Accommodation', 'Venue'),
('210303003', 'FHIT - TRAVEL ALLOWANCE / PER DIEM', 'Travel Allowance', 'Per Diem'),
('210303026', 'FHIT - FOOD AND MEALS', 'Food', 'Meals'),
('210303018', 'FHIT - REPRESENTATION EXPENSES', 'Representation', 'Expenses'),
('210303005', 'FHIT - REPAIRS AND MAINTENANCE OF FACILITIES', 'Repairs', 'Facilities'),
('210303006', 'FHIT - REPAIRS AND MAINTENANCE OF VEHICLES', 'Repairs', 'Vehicles'),
('210303008', 'FHIT - SUPPLIES AND MATERIALS EXPENSES', 'Supplies', 'Materials'),
('210303015', 'FHIT - ADVERTISING EXPENSES', 'Advertising', 'Marketing'),
('210303016', 'FHIT - PRINTING AND BINDING EXPENSES', 'Printing', 'Binding'),
('210303014', 'FHIT - GENERAL SERVICES', 'General', 'Services'),
('210303004', 'FHIT - COMMUNICATION EXPENSES', 'Communication', 'Utilities'),
('210303009', 'FHIT - UTILITY EXPENSES', 'Utilities', 'General'),
('210303011', 'FHIT - SCHOLARSHIP EXPENSES', 'Scholarship', 'Educational'),
('210303010', 'FHIT - TRAINING, WORKSHOP, CONFERENCE', 'Training', 'Development'),
('210303027', 'FHIT - MEMBERSHIP FEE', 'Membership', 'Fees'),
('210303040', 'FHIT - INDIRECT COST - RESEARCH FEE', 'Research', 'Indirect'),
('210303043', 'FHIT - WITHDRAWAL OF FUND', 'Withdrawal', 'Fund'),
('210303012', 'FHIT - AWARDS/REWARDS, PRICES AND INDEMNITIES', 'Awards', 'Rewards'),
('210303013', 'FHIT - SURVEY, RESEARCH, EXPLORATION AND DEVELOPMENT EXPENSES', 'Research', 'Development'),
('210303017', 'FHIT - RENT EXPENSES', 'Rent', 'Facilities'),
('210303019', 'FHIT - SUBSCRIPTION EXPENSES', 'Subscription', 'Services'),
('210303020', 'FHIT - DONATIONS', 'Donations', 'Charitable'),
('210303022', 'FHIT - TAXES, INSURANCE PREMIUMS AND OTHER FEES', 'Taxes', 'Insurance'),
('210303023', 'FHIT - OTHER MAINTENANCE AND OPERATING EXPENSES', 'Maintenance', 'Operating');

INSERT INTO fund_type (code, name) VALUES ('FT01', 'FHIT Fund');
INSERT INTO nature (code, name) VALUES ('NT01', 'Operating');

INSERT INTO budget_category (code, expenditure_type) VALUES 
('CAT01', 'Salaries'), ('CAT02', 'Honoraria'), ('CAT03', 'Prof Fees');

INSERT INTO project_account (code, name) VALUES ('PA001', 'Project A');

-- WORKFLOW SETUP: ALL REQUESTS GO THROUGH ALL 3 LEVELS 
-- Level 1: Department Head 
INSERT INTO approval_workflow (department_code, amount_threshold, approval_level, approver_role, is_required) 
VALUES ('999', 0.01, 1, 'department_head', TRUE);

-- Level 2: Dean (pwede naman ibahin names)  
INSERT INTO approval_workflow (department_code, amount_threshold, approval_level, approver_role, is_required) 
VALUES ('999', 0.01, 2, 'dean', TRUE);

-- Level 3: VP Finance (siya final diba)
INSERT INTO approval_workflow (department_code, amount_threshold, approval_level, approver_role, is_required) 
VALUES ('999', 0.01, 3, 'vp_finance', TRUE);

-- Update existing database structure (for existing installations)
-- Add missing columns to budget_request table if they don't exist
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_name = 'budget_request' 
     AND table_schema = 'budget_database_schema' 
     AND column_name = 'campus_code') = 0,
    'ALTER TABLE budget_request ADD COLUMN campus_code VARCHAR(10) AFTER department_code',
    'SELECT "campus_code column already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_name = 'budget_request' 
     AND table_schema = 'budget_database_schema' 
     AND column_name = 'fund_account') = 0,
    'ALTER TABLE budget_request ADD COLUMN fund_account VARCHAR(100) AFTER campus_code',
    'SELECT "fund_account column already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_name = 'budget_request' 
     AND table_schema = 'budget_database_schema' 
     AND column_name = 'fund_name') = 0,
    'ALTER TABLE budget_request ADD COLUMN fund_name VARCHAR(255) AFTER fund_account',
    'SELECT "fund_name column already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_name = 'budget_request' 
     AND table_schema = 'budget_database_schema' 
     AND column_name = 'duration') = 0,
    'ALTER TABLE budget_request ADD COLUMN duration VARCHAR(50) AFTER fund_name',
    'SELECT "duration column already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_name = 'budget_request' 
     AND table_schema = 'budget_database_schema' 
     AND column_name = 'budget_title') = 0,
    'ALTER TABLE budget_request ADD COLUMN budget_title VARCHAR(255) AFTER duration',
    'SELECT "budget_title column already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_name = 'budget_request' 
     AND table_schema = 'budget_database_schema' 
     AND column_name = 'description') = 0,
    'ALTER TABLE budget_request ADD COLUMN description TEXT AFTER budget_title',
    'SELECT "description column already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add remarks column to budget_entries table if it doesn't exist
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_name = 'budget_entries' 
     AND table_schema = 'budget_database_schema' 
     AND column_name = 'remarks') = 0,
    'ALTER TABLE budget_entries ADD COLUMN remarks TEXT AFTER budget_description',
    'SELECT "remarks column already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add foreign key constraint for campus_code if it doesn't exist
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
     WHERE table_name = 'budget_request' 
     AND table_schema = 'budget_database_schema' 
     AND constraint_name = 'fk_budget_request_campus') = 0,
    'ALTER TABLE budget_request ADD CONSTRAINT fk_budget_request_campus FOREIGN KEY (campus_code) REFERENCES campus(code)',
    'SELECT "campus foreign key already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Show setup completion message
SELECT 
    'SETUP COMPLETE!' as status,
    'All budget requests require ALL 3 approval levels' as workflow_rule,
    'Database updated with new columns' as update_status;

-- Show test accounts with actual passwords
SELECT 'TEST ACCOUNTS:' as info;
SELECT username_email as email, role, password, 'testpass' as expected_password FROM account WHERE username_email LIKE '%@example.com' ORDER BY role;

-- Verify all passwords are correct
SELECT 
    CASE 
        WHEN COUNT(*) = 5 AND MIN(password) = 'testpass' AND MAX(password) = 'testpass' 
        THEN '✅ ALL PASSWORDS CORRECT' 
        ELSE '❌ PASSWORD MISMATCH DETECTED' 
    END as password_status
FROM account WHERE username_email LIKE '%@example.com';

-- Add workflow configurations for all La Salle departments
INSERT INTO approval_workflow (department_code, amount_threshold, approval_level, approver_role, is_required) VALUES
-- CCS - College of Computer Studies
('CCS', 0.01, 1, 'department_head', TRUE),
('CCS', 0.01, 2, 'dean', TRUE),
('CCS', 0.01, 3, 'vp_finance', TRUE),
-- CLA - College of Liberal Arts
('CLA', 0.01, 1, 'department_head', TRUE),
('CLA', 0.01, 2, 'dean', TRUE),
('CLA', 0.01, 3, 'vp_finance', TRUE),
-- COB - Ramon V. del Rosario College of Business
('COB', 0.01, 1, 'department_head', TRUE),
('COB', 0.01, 2, 'dean', TRUE),
('COB', 0.01, 3, 'vp_finance', TRUE),
-- GCOE - Gokongwei College of Engineering
('GCOE', 0.01, 1, 'department_head', TRUE),
('GCOE', 0.01, 2, 'dean', TRUE),
('GCOE', 0.01, 3, 'vp_finance', TRUE),
-- COS - College of Science
('COS', 0.01, 1, 'department_head', TRUE),
('COS', 0.01, 2, 'dean', TRUE),
('COS', 0.01, 3, 'vp_finance', TRUE),
-- SOE - School of Economics
('SOE', 0.01, 1, 'department_head', TRUE),
('SOE', 0.01, 2, 'dean', TRUE),
('SOE', 0.01, 3, 'vp_finance', TRUE),
-- BAGCED - Br. Andrew Gonzalez College of Education
('BAGCED', 0.01, 1, 'department_head', TRUE),
('BAGCED', 0.01, 2, 'dean', TRUE),
('BAGCED', 0.01, 3, 'vp_finance', TRUE),
-- COL - College of Law
('COL', 0.01, 1, 'department_head', TRUE),
('COL', 0.01, 2, 'dean', TRUE),
('COL', 0.01, 3, 'vp_finance', TRUE),
-- JMRIG - Jesse M. Robredo Institute of Governance
('JMRIG', 0.01, 1, 'department_head', TRUE),
('JMRIG', 0.01, 2, 'dean', TRUE),
('JMRIG', 0.01, 3, 'vp_finance', TRUE),
-- GSB - Graduate School of Business
('GSB', 0.01, 1, 'department_head', TRUE),
('GSB', 0.01, 2, 'dean', TRUE),
('GSB', 0.01, 3, 'vp_finance', TRUE),
-- IGSP - La Salle Institute for Governance and Strategic Policy
('IGSP', 0.01, 1, 'department_head', TRUE),
('IGSP', 0.01, 2, 'dean', TRUE),
('IGSP', 0.01, 3, 'vp_finance', TRUE);

-- Insert sample amendments for demonstration (after test data)
INSERT INTO budget_amendments (request_id, amendment_number, created_by, amendment_type, amendment_title, amendment_reason, original_total_budget, amended_total_budget, status, approved_by, approved_timestamp, approval_comments)
SELECT 
    'CCS2025001',
    1,
    (SELECT id FROM account WHERE username_email = 'vp.finance@example.com'),
    'budget_change',
    'Budget Increase for Additional Equipment',
    'After further review, additional laboratory equipment is needed to meet project objectives. This amendment increases the budget to accommodate the procurement of specialized research instruments.',
    85000.00,
    97750.00,
    'approved',
    (SELECT id FROM account WHERE username_email = 'vp.finance@example.com'),
    '2025-01-22 14:30:00',
    'Approved by VP Finance after reviewing project requirements and available funding.'
WHERE EXISTS (SELECT 1 FROM budget_request WHERE request_id = 'CCS2025001');

INSERT INTO budget_amendments (request_id, amendment_number, created_by, amendment_type, amendment_title, amendment_reason, original_total_budget, amended_total_budget, status, approved_by, approved_timestamp, approval_comments)
SELECT 
    'GCOE2025001',
    1,
    (SELECT id FROM account WHERE username_email = 'vp.finance@example.com'),
    'timeline_change',
    'Project Timeline Extension',
    'Due to vendor delays and supply chain issues, the project timeline needs to be extended by 3 months. This amendment adjusts the project schedule while maintaining the same budget allocation.',
    120000.00,
    120000.00,
    'approved',
    (SELECT id FROM account WHERE username_email = 'vp.finance@example.com'),
    '2025-01-25 10:15:00',
    'Timeline extension approved due to external factors beyond department control.'
WHERE EXISTS (SELECT 1 FROM budget_request WHERE request_id = 'GCOE2025001');

INSERT INTO budget_amendments (request_id, amendment_number, created_by, amendment_type, amendment_title, amendment_reason, original_total_budget, amended_total_budget, status, approved_by, approved_timestamp, approval_comments)
SELECT 
    'CCS2025001',
    2,
    (SELECT id FROM account WHERE username_email = 'vp.finance@example.com'),
    'description_change',
    'Updated Project Description',
    'Project scope has been refined to include additional networking components and software licensing. This amendment updates the project description to reflect the comprehensive IT infrastructure upgrade.',
    97750.00,
    97750.00,
    'approved',
    (SELECT id FROM account WHERE username_email = 'vp.finance@example.com'),
    '2025-02-01 09:45:00',
    'Description update approved to align with updated project specifications.'
WHERE EXISTS (SELECT 1 FROM budget_request WHERE request_id = 'CCS2025001');

-- Add diverse budget request test data across La Salle departments using existing test user
SET @test_user = (SELECT id FROM account WHERE username_email = 'testuser@example.com');

-- CCS - College of Computer Studies (High Success Rate)
INSERT INTO budget_request (request_id, account_id, timestamp, department_code, campus_code, academic_year, status, proposed_budget, current_approval_level, workflow_complete, fund_account, fund_name, duration, budget_title, description) VALUES
('CCS2025001', @test_user, '2025-01-15 09:00:00', 'CCS', '11', '2025-2026', 'approved', 85000.00, 3, TRUE, 'CCS-FUND-001', 'Computer Studies Research Fund', 'Annually', 'Laboratory Equipment', 'New computers and networking equipment for labs'),
('CCS2025002', @test_user, '2025-01-20 10:00:00', 'CCS', '11', '2025-2026', 'approved', 65000.00, 3, TRUE, 'CCS-FUND-002', 'CCS Software Fund', 'Quarterly', 'Software Licenses', 'Development tools and software licenses'),
('CCS2025003', @test_user, '2025-02-01 11:00:00', 'CCS', '11', '2025-2026', 'pending', 90000.00, 2, FALSE, 'CCS-FUND-003', 'CCS Operations Fund', 'Monthly', 'Server Infrastructure', 'Server upgrade and maintenance');

-- CLA - College of Liberal Arts (Moderate Success Rate)
INSERT INTO budget_request (request_id, account_id, timestamp, department_code, campus_code, academic_year, status, proposed_budget, current_approval_level, workflow_complete, fund_account, fund_name, duration, budget_title, description) VALUES
('CLA2025001', @test_user, '2025-01-12 08:30:00', 'CLA', '11', '2025-2026', 'approved', 45000.00, 3, TRUE, 'CLA-FUND-001', 'Liberal Arts Culture Fund', 'Annually', 'Cultural Events', 'Art exhibitions and cultural programs'),
('CLA2025002', @test_user, '2025-01-25 14:00:00', 'CLA', '11', '2025-2026', 'rejected', 75000.00, 2, TRUE, 'CLA-FUND-002', 'CLA Research Fund', 'Quarterly', 'Research Materials', 'Books and research resources'),
('CLA2025003', @test_user, '2025-02-05 16:30:00', 'CLA', '11', '2025-2026', 'approved', 38000.00, 3, TRUE, 'CLA-FUND-003', 'CLA Development Fund', 'Monthly', 'Faculty Development', 'Training and workshops for faculty');

-- COB - Ramon V. del Rosario College of Business (Good Success Rate)
INSERT INTO budget_request (request_id, account_id, timestamp, department_code, campus_code, academic_year, status, proposed_budget, current_approval_level, workflow_complete, fund_account, fund_name, duration, budget_title, description) VALUES
('COB2025001', @test_user, '2025-01-18 10:15:00', 'COB', '11', '2025-2026', 'approved', 95000.00, 3, TRUE, 'COB-FUND-001', 'Business Excellence Fund', 'Annually', 'Business Lab Upgrade', 'Trading simulation and business analysis tools'),
('COB2025002', @test_user, '2025-02-03 09:45:00', 'COB', '11', '2025-2026', 'approved', 72000.00, 3, TRUE, 'COB-FUND-002', 'COB Training Fund', 'Quarterly', 'Executive Programs', 'Business executive training programs'),
('COB2025003', @test_user, '2025-02-08 13:20:00', 'COB', '11', '2025-2026', 'pending', 56000.00, 1, FALSE, 'COB-FUND-003', 'COB Research Fund', 'Monthly', 'Market Research', 'Business intelligence and market analysis tools');

-- GCOE - Gokongwei College of Engineering (High Success Rate)
INSERT INTO budget_request (request_id, account_id, timestamp, department_code, campus_code, academic_year, status, proposed_budget, current_approval_level, workflow_complete, fund_account, fund_name, duration, budget_title, description) VALUES
('GCOE2025001', @test_user, '2025-01-22 11:30:00', 'GCOE', '11', '2025-2026', 'approved', 120000.00, 3, TRUE, 'GCOE-FUND-001', 'Engineering Excellence Fund', 'Annually', 'Laboratory Equipment', 'Advanced engineering laboratory equipment'),
('GCOE2025002', @test_user, '2025-02-10 15:00:00', 'GCOE', '11', '2025-2026', 'approved', 88000.00, 3, TRUE, 'GCOE-FUND-002', 'GCOE Innovation Fund', 'Quarterly', 'Research Projects', 'Engineering research and innovation projects');

-- COS - College of Science (High Success Rate)
INSERT INTO budget_request (request_id, account_id, timestamp, department_code, campus_code, academic_year, status, proposed_budget, current_approval_level, workflow_complete, fund_account, fund_name, duration, budget_title, description) VALUES
('COS2025001', @test_user, '2025-01-16 12:00:00', 'COS', '11', '2025-2026', 'approved', 110000.00, 3, TRUE, 'COS-FUND-001', 'Science Research Fund', 'Annually', 'Laboratory Chemicals', 'Chemical reagents and scientific supplies'),
('COS2025002', @test_user, '2025-02-07 10:30:00', 'COS', '11', '2025-2026', 'approved', 79000.00, 3, TRUE, 'COS-FUND-002', 'COS Equipment Fund', 'Quarterly', 'Scientific Instruments', 'Microscopes and analytical instruments');

-- SOE - School of Economics (Moderate Success Rate)
INSERT INTO budget_request (request_id, account_id, timestamp, department_code, campus_code, academic_year, status, proposed_budget, current_approval_level, workflow_complete, fund_account, fund_name, duration, budget_title, description) VALUES
('SOE2025001', @test_user, '2025-01-28 14:45:00', 'SOE', '11', '2025-2026', 'approved', 52000.00, 3, TRUE, 'SOE-FUND-001', 'Economics Research Fund', 'Annually', 'Economic Analysis Tools', 'Statistical software and economic databases'),
('SOE2025002', @test_user, '2025-02-12 16:15:00', 'SOE', '11', '2025-2026', 'rejected', 67000.00, 1, TRUE, 'SOE-FUND-002', 'SOE Development Fund', 'Quarterly', 'Policy Research', 'Economic policy research and analysis');

-- BAGCED - Br. Andrew Gonzalez College of Education (Low Success Rate)
INSERT INTO budget_request (request_id, account_id, timestamp, department_code, campus_code, academic_year, status, proposed_budget, current_approval_level, workflow_complete, fund_account, fund_name, duration, budget_title, description) VALUES
('BAGCED2025001', @test_user, '2025-01-14 13:30:00', 'BAGCED', '11', '2025-2026', 'rejected', 83000.00, 1, TRUE, 'BAGCED-FUND-001', 'Education Innovation Fund', 'Annually', 'Teaching Materials', 'Educational technology and teaching aids'),
('BAGCED2025002', @test_user, '2025-02-04 11:20:00', 'BAGCED', '11', '2025-2026', 'rejected', 61000.00, 2, TRUE, 'BAGCED-FUND-002', 'BAGCED Training Fund', 'Quarterly', 'Teacher Training', 'Professional development for educators');

-- COL - College of Law (Low Success Rate)
INSERT INTO budget_request (request_id, account_id, timestamp, department_code, campus_code, academic_year, status, proposed_budget, current_approval_level, workflow_complete, fund_account, fund_name, duration, budget_title, description) VALUES
('COL2025001', @test_user, '2025-01-30 15:45:00', 'COL', '11', '2025-2026', 'rejected', 74000.00, 1, TRUE, 'COL-FUND-001', 'Law Library Fund', 'Annually', 'Legal Databases', 'Online legal research databases and resources');

-- JMRIG - Jesse M. Robredo Institute of Governance (Good Success Rate)
INSERT INTO budget_request (request_id, account_id, timestamp, department_code, campus_code, academic_year, status, proposed_budget, current_approval_level, workflow_complete, fund_account, fund_name, duration, budget_title, description) VALUES
('JMRIG2025001', @test_user, '2025-01-26 09:15:00', 'JMRIG', '11', '2025-2026', 'approved', 98000.00, 3, TRUE, 'JMRIG-FUND-001', 'Governance Research Fund', 'Annually', 'Policy Research', 'Governance and policy research projects'),
('JMRIG2025002', @test_user, '2025-02-09 12:40:00', 'JMRIG', '11', '2025-2026', 'approved', 63000.00, 3, TRUE, 'JMRIG-FUND-002', 'JMRIG Training Fund', 'Quarterly', 'Leadership Programs', 'Governance and leadership training programs');

-- GSB - Graduate School of Business (High Success Rate)
INSERT INTO budget_request (request_id, account_id, timestamp, department_code, campus_code, academic_year, status, proposed_budget, current_approval_level, workflow_complete, fund_account, fund_name, duration, budget_title, description) VALUES
('GSB2025001', @test_user, '2025-01-24 10:50:00', 'GSB', '11', '2025-2026', 'approved', 115000.00, 3, TRUE, 'GSB-FUND-001', 'Graduate Business Fund', 'Annually', 'Executive Education', 'Executive MBA and graduate programs enhancement'),
('GSB2025002', @test_user, '2025-02-11 14:25:00', 'GSB', '11', '2025-2026', 'approved', 87000.00, 3, TRUE, 'GSB-FUND-002', 'GSB Innovation Fund', 'Quarterly', 'Business Innovation', 'Innovation labs and entrepreneurship programs');

-- IGSP - La Salle Institute for Governance and Strategic Policy (Moderate Success Rate)
INSERT INTO budget_request (request_id, account_id, timestamp, department_code, campus_code, academic_year, status, proposed_budget, current_approval_level, workflow_complete, fund_account, fund_name, duration, budget_title, description) VALUES
('IGSP2025001', @test_user, '2025-02-02 11:00:00', 'IGSP', '11', '2025-2026', 'approved', 69000.00, 3, TRUE, 'IGSP-FUND-001', 'Strategic Policy Fund', 'Annually', 'Policy Analysis', 'Strategic policy research and analysis tools'),
('IGSP2025002', @test_user, '2025-02-13 13:15:00', 'IGSP', '11', '2025-2026', 'more_info_requested', 81000.00, 2, FALSE, 'IGSP-FUND-002', 'IGSP Development Fund', 'Quarterly', 'Governance Tools', 'Governance analysis and strategic planning tools');

-- Show workflow configuration
SELECT 'WORKFLOW CONFIGURATION:' as info;
SELECT 
    approval_level as level,
    approver_role as role,
    'ALL AMOUNTS' as applies_to,
    is_required as required
FROM approval_workflow 
ORDER BY approval_level;

-- Show amendment system status
SELECT 'AMENDMENT SYSTEM STATUS:' as info;
SELECT 
    TABLE_NAME as table_name,
    'CREATED' as status
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name LIKE '%amendment%'
ORDER BY table_name;

-- Show sample amendments created
SELECT 'SAMPLE AMENDMENTS CREATED:' as info;
SELECT 
    COUNT(*) as total_amendments,
    COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_amendments,
    COUNT(DISTINCT request_id) as requests_with_amendments
FROM budget_amendments;

-- Show amendment details
SELECT 
    ba.request_id,
    ba.amendment_number,
    ba.amendment_type,
    ba.amendment_title,
    ba.status,
    ba.created_timestamp
FROM budget_amendments ba
ORDER BY ba.request_id, ba.amendment_number;

-- Final completion message
SELECT 
    '🎉 COMPLETE BUDGET MANAGEMENT SYSTEM SETUP!' as status,
    'Database with Amendment System Ready!' as message,
    'VP Finance can now amend approved requests' as amendment_feature,
    'All tables created with sample data' as data_status;