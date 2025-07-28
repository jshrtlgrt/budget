


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
    proposed_budget DECIMAL(12, 2),
    status VARCHAR(50),
    academic_year VARCHAR(10),
    current_approval_level INT DEFAULT 1,
    total_approval_levels INT DEFAULT 3,
    workflow_complete BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (account_id) REFERENCES account(id),
    FOREIGN KEY (department_code) REFERENCES department(code)
);

CREATE TABLE budget_entries (
    request_id VARCHAR(20),
    row_num INT,
    month_year DATE,
    gl_code VARCHAR(20),
    budget_category_code VARCHAR(20),
    budget_description TEXT,
    amount DECIMAL(12, 2),
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

-- Insert all sample data
INSERT INTO group_table (code, name) VALUES ('GRP01', 'Admin Group');
INSERT INTO cluster (code, name, group_code) VALUES ('CL01', 'Cluster A', 'GRP01');
INSERT INTO division (code, name, cluster_code) VALUES ('DIV01', 'Division X', 'CL01');

INSERT INTO campus (code, name) VALUES 
('11', 'Manila'), ('12', 'Makati'), ('13', 'McKinley'), ('21', 'Laguna'), ('31', 'BGC');

INSERT INTO department (code, college, budget_deck, division_code, campus_code) VALUES 
('999', 'Test College', 'Test Deck', 'DIV01', '11');

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

-- Show setup completion message
SELECT 
    'SETUP COMPLETE!' as status,
    'All budget requests require ALL 3 approval levels' as workflow_rule,
    'No amount-based skipping allowed' as policy;

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

-- Show workflow configuration
SELECT 'WORKFLOW CONFIGURATION:' as info;
SELECT 
    approval_level as level,
    approver_role as role,
    'ALL AMOUNTS' as applies_to,
    is_required as required
FROM approval_workflow 
ORDER BY approval_level;