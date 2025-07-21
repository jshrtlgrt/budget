-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 21, 2025 at 06:22 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `budget_database_schema`
--

-- --------------------------------------------------------

--
-- Table structure for table `account`
--

CREATE TABLE `account` (
  `id` int(11) NOT NULL,
  `username_email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `department_code` varchar(10) DEFAULT NULL,
  `role` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `account`
--

INSERT INTO `account` (`id`, `username_email`, `password`, `name`, `department_code`, `role`) VALUES
(12208418, 'josheart@gmail.com', '123', 'Josheart Legarte', '999', 'requester'),
(12241442, 'miko@gmail.com', '123', 'Miko Serrano', '999', 'approver');

-- --------------------------------------------------------

--
-- Table structure for table `budget_category`
--

CREATE TABLE `budget_category` (
  `code` varchar(20) NOT NULL,
  `expenditure_type` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `budget_category`
--

INSERT INTO `budget_category` (`code`, `expenditure_type`) VALUES
('CAT01', 'Salaries'),
('CAT02', 'Honoraria'),
('CAT03', 'Prof Fees');

-- --------------------------------------------------------

--
-- Table structure for table `budget_entries`
--

CREATE TABLE `budget_entries` (
  `request_id` varchar(20) NOT NULL,
  `row_num` int(11) NOT NULL,
  `month_year` date DEFAULT NULL,
  `gl_code` varchar(20) DEFAULT NULL,
  `budget_category_code` varchar(20) DEFAULT NULL,
  `budget_description` text DEFAULT NULL,
  `amount` decimal(12,2) DEFAULT NULL,
  `monthly_upload` tinyint(1) DEFAULT NULL,
  `manual_adjustment` tinyint(1) DEFAULT NULL,
  `upload_multiplier` decimal(5,2) DEFAULT NULL,
  `fund_type_code` varchar(10) DEFAULT NULL,
  `nature_code` varchar(10) DEFAULT NULL,
  `fund_account` varchar(100) DEFAULT NULL,
  `fund_name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `budget_entries`
--

INSERT INTO `budget_entries` (`request_id`, `row_num`, `month_year`, `gl_code`, `budget_category_code`, `budget_description`, `amount`, `monthly_upload`, `manual_adjustment`, `upload_multiplier`, `fund_type_code`, `nature_code`, `fund_account`, `fund_name`) VALUES
('BR-20250721-94129', 1, '2025-07-01', '210303011', 'CAT01', 'FHIT - SCHOLARSHIP EXPENSES', 200.00, 0, 0, 1.00, 'FT01', 'NT01', '62000701', 'eyy'),
('BR-20250721-E970E', 1, '2025-07-01', '210303011', 'CAT01', 'FHIT - SCHOLARSHIP EXPENSES', 100.00, 0, 0, 1.00, 'FT01', 'NT01', '62000701', 'eyy'),
('BR-20250721-E970E', 2, '2025-07-01', '210303025', 'CAT01', 'FHIT - ACCOMMODATION AND VENUE', 300.00, 0, 0, 1.00, 'FT01', 'NT01', '62000701', 'eyy');

-- --------------------------------------------------------

--
-- Table structure for table `budget_request`
--

CREATE TABLE `budget_request` (
  `request_id` varchar(20) NOT NULL,
  `account_id` int(11) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `department_code` varchar(10) DEFAULT NULL,
  `proposed_budget` decimal(12,2) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `academic_year` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `budget_request`
--

INSERT INTO `budget_request` (`request_id`, `account_id`, `timestamp`, `department_code`, `proposed_budget`, `status`, `academic_year`) VALUES
('BR-20250721-94129', 12208418, '2025-07-20 20:52:27', '999', 200.00, 'Submitted', '2025-2026'),
('BR-20250721-E970E', 12208418, '2025-07-20 20:56:12', '999', 400.00, 'Submitted', '2025-2026');

-- --------------------------------------------------------

--
-- Table structure for table `campus`
--

CREATE TABLE `campus` (
  `code` varchar(10) NOT NULL,
  `name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `campus`
--

INSERT INTO `campus` (`code`, `name`) VALUES
('11', 'Manila'),
('12', 'Makati'),
('13', 'McKinley'),
('21', 'Laguna'),
('31', 'BGC');

-- --------------------------------------------------------

--
-- Table structure for table `cluster`
--

CREATE TABLE `cluster` (
  `code` varchar(10) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `group_code` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cluster`
--

INSERT INTO `cluster` (`code`, `name`, `group_code`) VALUES
('CL01', 'Cluster A', 'GRP01');

-- --------------------------------------------------------

--
-- Table structure for table `department`
--

CREATE TABLE `department` (
  `code` varchar(10) NOT NULL,
  `college` varchar(100) DEFAULT NULL,
  `budget_deck` varchar(100) DEFAULT NULL,
  `division_code` varchar(10) DEFAULT NULL,
  `campus_code` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `department`
--

INSERT INTO `department` (`code`, `college`, `budget_deck`, `division_code`, `campus_code`) VALUES
('999', 'Test College', 'Test Deck', 'DIV01', '11');

-- --------------------------------------------------------

--
-- Table structure for table `dept_lookup`
--

CREATE TABLE `dept_lookup` (
  `id` int(11) NOT NULL,
  `account_id` int(11) DEFAULT NULL,
  `department_code` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `division`
--

CREATE TABLE `division` (
  `code` varchar(10) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `cluster_code` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `division`
--

INSERT INTO `division` (`code`, `name`, `cluster_code`) VALUES
('DIV01', 'Division X', 'CL01');

-- --------------------------------------------------------

--
-- Table structure for table `fund_type`
--

CREATE TABLE `fund_type` (
  `code` varchar(10) NOT NULL,
  `name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fund_type`
--

INSERT INTO `fund_type` (`code`, `name`) VALUES
('FT01', 'FHIT Fund');

-- --------------------------------------------------------

--
-- Table structure for table `gl_account`
--

CREATE TABLE `gl_account` (
  `code` varchar(20) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `bpr_line_item` varchar(100) DEFAULT NULL,
  `bpr_sub_item` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `gl_account`
--

INSERT INTO `gl_account` (`code`, `name`, `bpr_line_item`, `bpr_sub_item`) VALUES
('210303003', 'FHIT - TRAVEL ALLOWANCE / PER DIEM', 'Allowance', ''),
('210303004', 'FHIT - COMMUNICATION EXPENSES', 'Communication', ''),
('210303005', 'FHIT - REPAIRS AND MAINTENANCE OF FACILITIES', 'Repairs', 'Facilities'),
('210303006', 'FHIT - REPAIRS AND MAINTENANCE OF VEHICLES', 'Repairs', 'Vehicles'),
('210303007', 'FHIT - TRANSPORTATION AND DELIVERY EXPENSES', 'Transport', ''),
('210303008', 'FHIT - SUPPLIES AND MATERIALS EXPENSES', 'Supplies', ''),
('210303009', 'FHIT - UTILITY EXPENSES', 'Utilities', ''),
('210303010', 'FHIT - TRAINING, WORKSHOP, CONFERENCE', 'Training', ''),
('210303011', 'FHIT - SCHOLARSHIP EXPENSES', 'Scholarship', ''),
('210303012', 'FHIT - AWARDS/REWARDS, PRICES AND INDEMNITIES', 'Awards', ''),
('210303013', 'FHIT - SURVEY, RESEARCH, EXPLORATION AND DEVELOPMENT EXPENSES', 'Research', ''),
('210303014', 'FHIT - GENERAL SERVICES', 'Services', ''),
('210303015', 'FHIT - ADVERTISING EXPENSES', 'Advertising', ''),
('210303016', 'FHIT - PRINTING AND BINDING EXPENSES', 'Printing', ''),
('210303017', 'FHIT - RENT EXPENSES', 'Rent', ''),
('210303018', 'FHIT - REPRESENTATION EXPENSES', 'Representation', ''),
('210303019', 'FHIT - SUBSCRIPTION EXPENSES', 'Subscription', ''),
('210303020', 'FHIT - DONATIONS', 'Donations', ''),
('210303022', 'FHIT - TAXES, INSURANCE PREMIUMS AND OTHER FEES', 'Taxes & Insurance', ''),
('210303023', 'FHIT - OTHER MAINTENANCE AND OPERATING EXPENSES', 'Others', ''),
('210303025', 'FHIT - ACCOMMODATION AND VENUE', 'Accommodation', ''),
('210303026', 'FHIT - FOOD AND MEALS', 'Food', ''),
('210303027', 'FHIT - MEMBERSHIP FEE', 'Membership', ''),
('210303028', 'FHIT - TRAVEL (LOCAL)', 'Travel', 'Local'),
('210303029', 'FHIT - TRAVEL (FOREIGN)', 'Travel', 'Foreign'),
('210303040', 'FHIT - INDIRECT COST - RESEARCH FEE', 'Indirect Cost', ''),
('210303043', 'FHIT - WITHDRAWAL OF FUND', 'Withdrawal', ''),
('210304001', 'FHIT - SALARIES - 1', 'Salaries', '1'),
('210304002', 'FHIT - SALARIES - 2', 'Salaries', '2'),
('210304003', 'FHIT - SALARIES - 3', 'Salaries', '3'),
('210304004', 'FHIT - SALARIES - 4', 'Salaries', '4'),
('210304005', 'FHIT - SALARIES - 5', 'Salaries', '5'),
('210304006', 'FHIT - SALARIES - 6', 'Salaries', '6'),
('210304007', 'FHIT - SALARIES - 7', 'Salaries', '7'),
('210304008', 'FHIT - SALARIES - 8', 'Salaries', '8'),
('210304009', 'FHIT - SALARIES - 9', 'Salaries', '9'),
('210304010', 'FHIT - SALARIES - 10', 'Salaries', '10'),
('210305001', 'FHIT - HONORARIA - 1', 'Honoraria', '1'),
('210305002', 'FHIT - HONORARIA - 2', 'Honoraria', '2'),
('210305003', 'FHIT - HONORARIA - 3', 'Honoraria', '3'),
('210305004', 'FHIT - HONORARIA - 4', 'Honoraria', '4'),
('210305005', 'FHIT - HONORARIA - 5', 'Honoraria', '5'),
('210305006', 'FHIT - HONORARIA - 6', 'Honoraria', '6'),
('210305007', 'FHIT - HONORARIA - 7', 'Honoraria', '7'),
('210305008', 'FHIT - HONORARIA - 8', 'Honoraria', '8'),
('210305009', 'FHIT - HONORARIA - 9', 'Honoraria', '9'),
('210305010', 'FHIT - HONORARIA - 10', 'Honoraria', '10'),
('210306001', 'FHIT - PROFESSIONAL FEE - 1', 'Prof Fee', '1'),
('210306002', 'FHIT - PROFESSIONAL FEE - 2', 'Prof Fee', '2'),
('210306003', 'FHIT - PROFESSIONAL FEE - 3', 'Prof Fee', '3'),
('210306004', 'FHIT - PROFESSIONAL FEE - 4', 'Prof Fee', '4'),
('210306005', 'FHIT - PROFESSIONAL FEE - 5', 'Prof Fee', '5'),
('210306006', 'FHIT - PROFESSIONAL FEE - 6', 'Prof Fee', '6'),
('210306007', 'FHIT - PROFESSIONAL FEE - 7', 'Prof Fee', '7'),
('210306008', 'FHIT - PROFESSIONAL FEE - 8', 'Prof Fee', '8'),
('210306009', 'FHIT - PROFESSIONAL FEE - 9', 'Prof Fee', '9'),
('210306010', 'FHIT - PROFESSIONAL FEE - 10', 'Prof Fee', '10');

-- --------------------------------------------------------

--
-- Table structure for table `group_table`
--

CREATE TABLE `group_table` (
  `code` varchar(10) NOT NULL,
  `name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `group_table`
--

INSERT INTO `group_table` (`code`, `name`) VALUES
('GRP01', 'Admin Group');

-- --------------------------------------------------------

--
-- Table structure for table `history`
--

CREATE TABLE `history` (
  `history_id` int(11) NOT NULL,
  `request_id` varchar(20) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `action` text DEFAULT NULL,
  `account_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `nature`
--

CREATE TABLE `nature` (
  `code` varchar(10) NOT NULL,
  `name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `nature`
--

INSERT INTO `nature` (`code`, `name`) VALUES
('NT01', 'Operating');

-- --------------------------------------------------------

--
-- Table structure for table `project_account`
--

CREATE TABLE `project_account` (
  `code` varchar(10) NOT NULL,
  `name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_account`
--

INSERT INTO `project_account` (`code`, `name`) VALUES
('PA001', 'Project A');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `account`
--
ALTER TABLE `account`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username_email` (`username_email`),
  ADD KEY `department_code` (`department_code`);

--
-- Indexes for table `budget_category`
--
ALTER TABLE `budget_category`
  ADD PRIMARY KEY (`code`);

--
-- Indexes for table `budget_entries`
--
ALTER TABLE `budget_entries`
  ADD PRIMARY KEY (`request_id`,`row_num`),
  ADD KEY `gl_code` (`gl_code`),
  ADD KEY `budget_category_code` (`budget_category_code`),
  ADD KEY `fund_type_code` (`fund_type_code`),
  ADD KEY `nature_code` (`nature_code`);

--
-- Indexes for table `budget_request`
--
ALTER TABLE `budget_request`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `department_code` (`department_code`);

--
-- Indexes for table `campus`
--
ALTER TABLE `campus`
  ADD PRIMARY KEY (`code`);

--
-- Indexes for table `cluster`
--
ALTER TABLE `cluster`
  ADD PRIMARY KEY (`code`),
  ADD KEY `group_code` (`group_code`);

--
-- Indexes for table `department`
--
ALTER TABLE `department`
  ADD PRIMARY KEY (`code`),
  ADD KEY `division_code` (`division_code`),
  ADD KEY `campus_code` (`campus_code`);

--
-- Indexes for table `dept_lookup`
--
ALTER TABLE `dept_lookup`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `department_code` (`department_code`);

--
-- Indexes for table `division`
--
ALTER TABLE `division`
  ADD PRIMARY KEY (`code`),
  ADD KEY `cluster_code` (`cluster_code`);

--
-- Indexes for table `fund_type`
--
ALTER TABLE `fund_type`
  ADD PRIMARY KEY (`code`);

--
-- Indexes for table `gl_account`
--
ALTER TABLE `gl_account`
  ADD PRIMARY KEY (`code`);

--
-- Indexes for table `group_table`
--
ALTER TABLE `group_table`
  ADD PRIMARY KEY (`code`);

--
-- Indexes for table `history`
--
ALTER TABLE `history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `request_id` (`request_id`),
  ADD KEY `account_id` (`account_id`);

--
-- Indexes for table `nature`
--
ALTER TABLE `nature`
  ADD PRIMARY KEY (`code`);

--
-- Indexes for table `project_account`
--
ALTER TABLE `project_account`
  ADD PRIMARY KEY (`code`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `account`
--
ALTER TABLE `account`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12241444;

--
-- AUTO_INCREMENT for table `dept_lookup`
--
ALTER TABLE `dept_lookup`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `history`
--
ALTER TABLE `history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `account`
--
ALTER TABLE `account`
  ADD CONSTRAINT `account_ibfk_1` FOREIGN KEY (`department_code`) REFERENCES `department` (`code`);

--
-- Constraints for table `budget_entries`
--
ALTER TABLE `budget_entries`
  ADD CONSTRAINT `budget_entries_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `budget_request` (`request_id`),
  ADD CONSTRAINT `budget_entries_ibfk_2` FOREIGN KEY (`gl_code`) REFERENCES `gl_account` (`code`),
  ADD CONSTRAINT `budget_entries_ibfk_3` FOREIGN KEY (`budget_category_code`) REFERENCES `budget_category` (`code`),
  ADD CONSTRAINT `budget_entries_ibfk_4` FOREIGN KEY (`fund_type_code`) REFERENCES `fund_type` (`code`),
  ADD CONSTRAINT `budget_entries_ibfk_5` FOREIGN KEY (`nature_code`) REFERENCES `nature` (`code`);

--
-- Constraints for table `budget_request`
--
ALTER TABLE `budget_request`
  ADD CONSTRAINT `budget_request_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `account` (`id`),
  ADD CONSTRAINT `budget_request_ibfk_2` FOREIGN KEY (`department_code`) REFERENCES `department` (`code`);

--
-- Constraints for table `cluster`
--
ALTER TABLE `cluster`
  ADD CONSTRAINT `cluster_ibfk_1` FOREIGN KEY (`group_code`) REFERENCES `group_table` (`code`);

--
-- Constraints for table `department`
--
ALTER TABLE `department`
  ADD CONSTRAINT `department_ibfk_1` FOREIGN KEY (`division_code`) REFERENCES `division` (`code`),
  ADD CONSTRAINT `department_ibfk_2` FOREIGN KEY (`campus_code`) REFERENCES `campus` (`code`);

--
-- Constraints for table `dept_lookup`
--
ALTER TABLE `dept_lookup`
  ADD CONSTRAINT `dept_lookup_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `account` (`id`),
  ADD CONSTRAINT `dept_lookup_ibfk_2` FOREIGN KEY (`department_code`) REFERENCES `department` (`code`);

--
-- Constraints for table `division`
--
ALTER TABLE `division`
  ADD CONSTRAINT `division_ibfk_1` FOREIGN KEY (`cluster_code`) REFERENCES `cluster` (`code`);

--
-- Constraints for table `history`
--
ALTER TABLE `history`
  ADD CONSTRAINT `history_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `budget_request` (`request_id`),
  ADD CONSTRAINT `history_ibfk_2` FOREIGN KEY (`account_id`) REFERENCES `account` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
