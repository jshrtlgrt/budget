-- Populate database with test data for analytics testing
USE budget_database_schema;

-- Clear existing test data (keep the schema intact)
DELETE FROM approval_progress WHERE request_id LIKE 'BR-%';
DELETE FROM history WHERE request_id LIKE 'BR-%';
DELETE FROM budget_entries WHERE request_id LIKE 'BR-%';
DELETE FROM budget_request WHERE request_id LIKE 'BR-%';

-- Insert test budget requests with varied statuses and dates
INSERT INTO budget_request (
    request_id, account_id, timestamp, department_code, campus_code,
    fund_account, fund_name, duration, budget_title, description,
    proposed_budget, status, academic_year, current_approval_level,
    total_approval_levels, workflow_complete
) VALUES
-- September 2025 requests
('BR-20250915-TEST1', 1, '2025-09-15 10:30:00', '999', '11', '62000701', 'Research Fund A', 'Annually', 'AI Research Project', 'Machine learning research for educational enhancement', 250000.00, 'approved', '2025-2026', 3, 3, TRUE),
('BR-20250918-TEST2', 1, '2025-09-18 14:15:00', '999', '12', '62000702', 'Training Fund B', 'Quarterly', 'Faculty Development Program', 'Professional development workshops for faculty', 180000.00, 'approved', '2025-2026', 3, 3, TRUE),
('BR-20250920-TEST3', 1, '2025-09-20 09:45:00', '999', '13', '62000703', 'Equipment Fund C', 'Monthly', 'Laboratory Equipment Upgrade', 'Upgrading chemistry lab equipment', 320000.00, 'rejected', '2025-2026', 2, 3, FALSE),

-- October 2025 requests
('BR-20251005-TEST4', 1, '2025-10-05 11:20:00', '999', '21', '62000704', 'Conference Fund D', 'Quarterly', 'International Conference Attendance', 'IEEE conference participation for CS faculty', 95000.00, 'approved', '2025-2026', 3, 3, TRUE),
('BR-20251012-TEST5', 1, '2025-10-12 16:30:00', '999', '31', '62000705', 'Software Fund E', 'Annually', 'Software Licensing Renewal', 'Annual software licenses for academic use', 150000.00, 'pending', '2025-2026', 1, 3, FALSE),
('BR-20251018-TEST6', 1, '2025-10-18 08:15:00', '999', '11', '62000706', 'Publication Fund F', 'Monthly', 'Journal Publication Support', 'Open access publication fees', 75000.00, 'approved', '2025-2026', 3, 3, TRUE),

-- November 2025 requests
('BR-20251110-TEST7', 1, '2025-11-10 13:45:00', '999', '12', '62000707', 'Seminar Fund G', 'Quarterly', 'Guest Speaker Series', 'Inviting industry experts for seminars', 120000.00, 'rejected', '2025-2026', 1, 3, FALSE),
('BR-20251115-TEST8', 1, '2025-11-15 10:00:00', '999', '13', '62000708', 'Materials Fund H', 'Monthly', 'Teaching Materials Procurement', 'Books and educational materials', 85000.00, 'approved', '2025-2026', 3, 3, TRUE),
('BR-20251122-TEST9', 1, '2025-11-22 15:20:00', '999', '21', '62000709', 'Workshop Fund I', 'Annually', 'Student Skills Workshop', 'Career development workshops for students', 200000.00, 'more_info_requested', '2025-2026', 2, 3, FALSE),

-- December 2025 requests
('BR-20251203-TEST10', 1, '2025-12-03 09:30:00', '999', '31', '62000710', 'Technology Fund J', 'Quarterly', 'Campus WiFi Upgrade', 'Improving campus internet infrastructure', 450000.00, 'pending', '2025-2026', 2, 3, FALSE),
('BR-20251210-TEST11', 1, '2025-12-10 14:45:00', '999', '11', '62000711', 'Event Fund K', 'Monthly', 'Academic Symposium 2026', 'Annual academic symposium organization', 175000.00, 'approved', '2025-2026', 3, 3, TRUE),
('BR-20251218-TEST12', 1, '2025-12-18 11:15:00', '999', '12', '62000712', 'Research Fund L', 'Annually', 'Climate Change Study', 'Environmental research project', 380000.00, 'rejected', '2025-2026', 3, 3, TRUE),

-- January 2026 requests
('BR-20260108-TEST13', 1, '2026-01-08 08:45:00', '999', '13', '62000713', 'Training Fund M', 'Quarterly', 'Digital Literacy Program', 'Staff digital skills training', 90000.00, 'approved', '2025-2026', 3, 3, TRUE),
('BR-20260115-TEST14', 1, '2026-01-15 16:00:00', '999', '21', '62000714', 'Equipment Fund N', 'Monthly', 'Projector Replacement', 'Classroom projector upgrades', 125000.00, 'pending', '2025-2026', 1, 3, FALSE),
('BR-20260125-TEST15', 1, '2026-01-25 12:30:00', '999', '31', '62000715', 'Library Fund O', 'Annually', 'Digital Library Expansion', 'E-book and database subscriptions', 220000.00, 'approved', '2025-2026', 3, 3, TRUE),

-- February 2026 requests
('BR-20260205-TEST16', 1, '2026-02-05 10:15:00', '999', '11', '62000716', 'Security Fund P', 'Quarterly', 'Campus Security Enhancement', 'CCTV and access control systems', 300000.00, 'more_info_requested', '2025-2026', 2, 3, FALSE),
('BR-20260212-TEST17', 1, '2026-02-12 13:20:00', '999', '12', '62000717', 'Sports Fund Q', 'Monthly', 'Athletic Equipment Purchase', 'Sports equipment for PE classes', 65000.00, 'rejected', '2025-2026', 1, 3, FALSE),
('BR-20260220-TEST18', 1, '2026-02-20 15:45:00', '999', '13', '62000718', 'Maintenance Fund R', 'Annually', 'Building Maintenance Program', 'Annual building upkeep and repairs', 280000.00, 'approved', '2025-2026', 3, 3, TRUE),

-- March 2026 requests
('BR-20260305-TEST19', 1, '2026-03-05 09:00:00', '999', '21', '62000719', 'Innovation Fund S', 'Quarterly', 'Student Innovation Lab', 'Makerspace setup for engineering students', 195000.00, 'pending', '2025-2026', 3, 3, FALSE),
('BR-20260315-TEST20', 1, '2026-03-15 14:30:00', '999', '31', '62000720', 'Health Fund T', 'Monthly', 'Campus Health Program', 'Student health and wellness initiatives', 110000.00, 'approved', '2025-2026', 3, 3, TRUE);

-- Insert corresponding budget entries for each request
INSERT INTO budget_entries (
    request_id, row_num, month_year, gl_code, budget_category_code,
    budget_description, remarks, amount, monthly_upload, manual_adjustment,
    upload_multiplier, fund_type_code, nature_code, fund_account, fund_name
) VALUES
-- BR-20250915-TEST1 entries
('BR-20250915-TEST1', 1, '2025-09-01', '210304001', 'CAT01', 'FHIT - SALARIES - 1', 'Principal investigator salary', 150000.00, 0, 0, 1.0, 'FT01', 'NT01', '62000701', 'Research Fund A'),
('BR-20250915-TEST1', 2, '2025-09-01', '210303008', 'CAT01', 'FHIT - SUPPLIES AND MATERIALS EXPENSES', 'Research materials and supplies', 100000.00, 0, 0, 1.0, 'FT01', 'NT01', '62000701', 'Research Fund A'),

-- BR-20250918-TEST2 entries
('BR-20250918-TEST2', 1, '2025-09-01', '210305001', 'CAT02', 'FHIT - HONORARIA - 1', 'Workshop facilitator fees', 80000.00, 0, 0, 1.0, 'FT01', 'NT01', '62000702', 'Training Fund B'),
('BR-20250918-TEST2', 2, '2025-09-01', '210303026', 'CAT01', 'FHIT - FOOD AND MEALS', 'Catering for training sessions', 50000.00, 0, 0, 1.0, 'FT01', 'NT01', '62000702', 'Training Fund B'),
('BR-20250918-TEST2', 3, '2025-09-01', '210303025', 'CAT01', 'FHIT - ACCOMMODATION AND VENUE', 'Venue rental for workshops', 50000.00, 0, 0, 1.0, 'FT01', 'NT01', '62000702', 'Training Fund B'),

-- BR-20250920-TEST3 entries
('BR-20250920-TEST3', 1, '2025-09-01', '210303008', 'CAT01', 'FHIT - SUPPLIES AND MATERIALS EXPENSES', 'Laboratory equipment', 320000.00, 0, 0, 1.0, 'FT01', 'NT01', '62000703', 'Equipment Fund C'),

-- BR-20251005-TEST4 entries
('BR-20251005-TEST4', 1, '2025-10-01', '210303028', 'CAT01', 'FHIT - TRAVEL (LOCAL)', 'Conference registration and travel', 45000.00, 0, 0, 1.0, 'FT01', 'NT01', '62000704', 'Conference Fund D'),
('BR-20251005-TEST4', 2, '2025-10-01', '210303025', 'CAT01', 'FHIT - ACCOMMODATION AND VENUE', 'Hotel accommodation', 50000.00, 0, 0, 1.0, 'FT01', 'NT01', '62000704', 'Conference Fund D'),

-- BR-20251012-TEST5 entries
('BR-20251012-TEST5', 1, '2025-10-01', '210303019', 'CAT01', 'FHIT - SUBSCRIPTION EXPENSES', 'Software license renewals', 150000.00, 0, 0, 1.0, 'FT01', 'NT01', '62000705', 'Software Fund E'),

-- BR-20251018-TEST6 entries
('BR-20251018-TEST6', 1, '2025-10-01', '210303016', 'CAT01', 'FHIT - PRINTING AND BINDING EXPENSES', 'Journal publication fees', 75000.00, 0, 0, 1.0, 'FT01', 'NT01', '62000706', 'Publication Fund F'),

-- BR-20251110-TEST7 entries
('BR-20251110-TEST7', 1, '2025-11-01', '210305002', 'CAT02', 'FHIT - HONORARIA - 2', 'Guest speaker honoraria', 90000.00, 0, 0, 1.0, 'FT01', 'NT01', '62000707', 'Seminar Fund G'),
('BR-20251110-TEST7', 2, '2025-11-01', '210303007', 'CAT01', 'FHIT - TRANSPORTATION AND DELIVERY EXPENSES', 'Speaker travel expenses', 30000.00, 0, 0, 1.0, 'FT01', 'NT01', '62000707', 'Seminar Fund G'),

-- BR-20251115-TEST8 entries
('BR-20251115-TEST8', 1, '2025-11-01', '210303008', 'CAT01', 'FHIT - SUPPLIES AND MATERIALS EXPENSES', 'Educational materials and books', 85000.00, 0, 0, 1.0, 'FT01', 'NT01', '62000708', 'Materials Fund H'),

-- BR-20251122-TEST9 entries
('BR-20251122-TEST9', 1, '2025-11-01', '210303010', 'CAT01', 'FHIT - TRAINING, WORKSHOP, CONFERENCE', 'Student workshop expenses', 120000.00, 0, 0, 1.0, 'FT01', 'NT01', '62000709', 'Workshop Fund I'),
('BR-20251122-TEST9', 2, '2025-11-01', '210303008', 'CAT01', 'FHIT - SUPPLIES AND MATERIALS EXPENSES', 'Workshop materials', 80000.00, 0, 0, 1.0, 'FT01', 'NT01', '62000709', 'Workshop Fund I'),

-- Continue with more entries for remaining requests...
('BR-20251203-TEST10', 1, '2025-12-01', '210303009', 'CAT01', 'FHIT - UTILITY EXPENSES', 'Network infrastructure upgrade', 450000.00, 0, 0, 1.0, 'FT01', 'NT01', '62000710', 'Technology Fund J'),

('BR-20251210-TEST11', 1, '2025-12-01', '210303025', 'CAT01', 'FHIT - ACCOMMODATION AND VENUE', 'Symposium venue and logistics', 100000.00, 0, 0, 1.0, 'FT01', 'NT01', '62000711', 'Event Fund K'),
('BR-20251210-TEST11', 2, '2025-12-01', '210303026', 'CAT01', 'FHIT - FOOD AND MEALS', 'Symposium catering', 75000.00, 0, 0, 1.0, 'FT01', 'NT01', '62000711', 'Event Fund K'),

('BR-20251218-TEST12', 1, '2025-12-01', '210304002', 'CAT01', 'FHIT - SALARIES - 2', 'Research staff salaries', 200000.00, 0, 0, 1.0, 'FT01', 'NT01', '62000712', 'Research Fund L'),
('BR-20251218-TEST12', 2, '2025-12-01', '210303008', 'CAT01', 'FHIT - SUPPLIES AND MATERIALS EXPENSES', 'Research equipment and materials', 180000.00, 0, 0, 1.0, 'FT01', 'NT01', '62000712', 'Research Fund L'),

('BR-20260108-TEST13', 1, '2026-01-01', '210303010', 'CAT01', 'FHIT - TRAINING, WORKSHOP, CONFERENCE', 'Digital literacy training', 90000.00, 0, 0, 1.0, 'FT01', 'NT01', '62000713', 'Training Fund M'),

('BR-20260115-TEST14', 1, '2026-01-01', '210303008', 'CAT01', 'FHIT - SUPPLIES AND MATERIALS EXPENSES', 'Classroom projectors', 125000.00, 0, 0, 1.0, 'FT01', 'NT01', '62000714', 'Equipment Fund N'),

('BR-20260125-TEST15', 1, '2026-01-01', '210303019', 'CAT01', 'FHIT - SUBSCRIPTION EXPENSES', 'Digital library subscriptions', 220000.00, 0, 0, 1.0, 'FT01', 'NT01', '62000715', 'Library Fund O'),

('BR-20260205-TEST16', 1, '2026-02-01', '210303008', 'CAT01', 'FHIT - SUPPLIES AND MATERIALS EXPENSES', 'Security equipment', 300000.00, 0, 0, 1.0, 'FT01', 'NT01', '62000716', 'Security Fund P'),

('BR-20260212-TEST17', 1, '2026-02-01', '210303008', 'CAT01', 'FHIT - SUPPLIES AND MATERIALS EXPENSES', 'Sports equipment', 65000.00, 0, 0, 1.0, 'FT01', 'NT01', '62000717', 'Sports Fund Q'),

('BR-20260220-TEST18', 1, '2026-02-01', '210303005', 'CAT01', 'FHIT - REPAIRS AND MAINTENANCE OF FACILITIES', 'Building maintenance', 280000.00, 0, 0, 1.0, 'FT01', 'NT01', '62000718', 'Maintenance Fund R'),

('BR-20260305-TEST19', 1, '2026-03-01', '210303008', 'CAT01', 'FHIT - SUPPLIES AND MATERIALS EXPENSES', 'Innovation lab equipment', 195000.00, 0, 0, 1.0, 'FT01', 'NT01', '62000719', 'Innovation Fund S'),

('BR-20260315-TEST20', 1, '2026-03-01', '210303011', 'CAT01', 'FHIT - SCHOLARSHIP EXPENSES', 'Health program expenses', 110000.00, 0, 0, 1.0, 'FT01', 'NT01', '62000720', 'Health Fund T');

-- Initialize approval workflows for all test requests
INSERT INTO approval_progress (request_id, approval_level, approver_id, status, comments, timestamp)
SELECT 
    br.request_id,
    1,
    (SELECT id FROM account WHERE role = 'department_head' LIMIT 1),
    CASE 
        WHEN br.status IN ('approved', 'rejected') THEN 'approved'
        WHEN br.status = 'more_info_requested' THEN 'request_info'
        ELSE 'pending'
    END,
    CASE 
        WHEN br.status = 'rejected' AND br.current_approval_level = 1 THEN 'Budget allocation exceeds department limits'
        WHEN br.status = 'more_info_requested' THEN 'Please provide more detailed breakdown of expenses'
        WHEN br.status = 'approved' THEN 'Approved at department level'
        ELSE NULL
    END,
    DATE_ADD(br.timestamp, INTERVAL 1 DAY)
FROM budget_request br
WHERE br.request_id LIKE 'BR-%TEST%';

-- Add level 2 approvals for requests that passed level 1
INSERT INTO approval_progress (request_id, approval_level, approver_id, status, comments, timestamp)
SELECT 
    br.request_id,
    2,
    (SELECT id FROM account WHERE role = 'dean' LIMIT 1),
    CASE 
        WHEN br.status = 'approved' THEN 'approved'
        WHEN br.status = 'rejected' AND br.current_approval_level >= 2 THEN 'rejected'
        WHEN br.status = 'more_info_requested' AND br.current_approval_level >= 2 THEN 'request_info'
        WHEN br.status = 'pending' AND br.current_approval_level >= 2 THEN 'pending'
        ELSE 'waiting'
    END,
    CASE 
        WHEN br.status = 'rejected' AND br.current_approval_level >= 2 THEN 'Project scope not aligned with college priorities'
        WHEN br.status = 'more_info_requested' AND br.current_approval_level >= 2 THEN 'Need additional justification for budget amount'
        WHEN br.status = 'approved' THEN 'Approved at college level'
        ELSE NULL
    END,
    DATE_ADD(br.timestamp, INTERVAL 2 DAY)
FROM budget_request br
WHERE br.request_id LIKE 'BR-%TEST%'
AND br.current_approval_level >= 2;

-- Add level 3 approvals for requests that passed level 2
INSERT INTO approval_progress (request_id, approval_level, approver_id, status, comments, timestamp)
SELECT 
    br.request_id,
    3,
    (SELECT id FROM account WHERE role = 'vp_finance' LIMIT 1),
    CASE 
        WHEN br.status = 'approved' THEN 'approved'
        WHEN br.status = 'rejected' AND br.current_approval_level >= 3 THEN 'rejected'
        WHEN br.status = 'pending' AND br.current_approval_level >= 3 THEN 'pending'
        ELSE 'waiting'
    END,
    CASE 
        WHEN br.status = 'rejected' AND br.current_approval_level >= 3 THEN 'Budget exceeds available funds for this category'
        WHEN br.status = 'approved' THEN 'Final approval granted'
        ELSE NULL
    END,
    DATE_ADD(br.timestamp, INTERVAL 3 DAY)
FROM budget_request br
WHERE br.request_id LIKE 'BR-%TEST%'
AND br.current_approval_level >= 3;

-- Add some history entries
INSERT INTO history (request_id, timestamp, action, account_id)
SELECT 
    br.request_id,
    br.timestamp,
    CONCAT('Budget request submitted for ', br.budget_title),
    br.account_id
FROM budget_request br
WHERE br.request_id LIKE 'BR-%TEST%';

-- Show summary of inserted test data
SELECT 
    'TEST DATA SUMMARY' as info,
    COUNT(*) as total_requests,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'more_info_requested' THEN 1 ELSE 0 END) as info_requested,
    CONCAT('₱', FORMAT(SUM(proposed_budget), 2)) as total_budget
FROM budget_request 
WHERE request_id LIKE 'BR-%TEST%';

SELECT 'Test data populated successfully! You can now test the analytics dashboard.' as message;