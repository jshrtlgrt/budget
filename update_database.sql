-- Update existing database to add missing columns
USE budget_database_schema;

-- Add missing columns to budget_request table
ALTER TABLE budget_request 
ADD COLUMN campus_code VARCHAR(10) AFTER department_code,
ADD COLUMN fund_account VARCHAR(100) AFTER campus_code,
ADD COLUMN fund_name VARCHAR(255) AFTER fund_account,
ADD COLUMN duration VARCHAR(50) AFTER fund_name,
ADD COLUMN budget_title VARCHAR(255) AFTER duration,
ADD COLUMN description TEXT AFTER budget_title;

-- Add foreign key constraint for campus_code
ALTER TABLE budget_request 
ADD CONSTRAINT fk_budget_request_campus 
FOREIGN KEY (campus_code) REFERENCES campus(code);

-- Add remarks column to budget_entries table
ALTER TABLE budget_entries 
ADD COLUMN remarks TEXT AFTER budget_description;

-- Verify changes
SELECT 'Database updated successfully!' as status;
DESCRIBE budget_request;
DESCRIBE budget_entries;