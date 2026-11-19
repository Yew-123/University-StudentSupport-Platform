-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 21, 2026 at 08:20 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `student_support_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `feedbackID` varchar(15) NOT NULL,
  `sessionID` varchar(15) NOT NULL,
  `rating` decimal(5,0) NOT NULL,
  `comment` varchar(250) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `session`
--

CREATE TABLE `session` (
  `sessionID` varchar(15) NOT NULL,
  `requestID` varchar(15) NOT NULL,
  `date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` varchar(10) NOT NULL,
  `studentID` varchar(15) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `skills`
--

CREATE TABLE `skills` (
  `skillID` varchar(15) NOT NULL,
  `skillName` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `skills`
--

INSERT INTO `skills` (`skillID`, `skillName`) VALUES
('1', 'Programming'),
('10', 'Graphic Design'),
('2', 'Data Analytics'),
('3', 'IT'),
('4', 'Business'),
('5', 'Cybersecurity'),
('6', 'Networking'),
('7', 'AI'),
('8', 'Web Development'),
('9', 'Database Management');

-- --------------------------------------------------------

--
-- Table structure for table `student`
--

CREATE TABLE `student` (
  `studentID` varchar(15) NOT NULL,
  `name` varchar(20) NOT NULL,
  `email` varchar(30) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phoneNo` varchar(10) NOT NULL,
  `availability` varchar(50) NOT NULL,
  `supportMode` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student`
--

INSERT INTO `student` (`studentID`, `name`, `email`, `password`, `phoneNo`, `availability`, `supportMode`) VALUES
('bda12344', 'student2', 'student2@gmail.com', '$2y$10$IN1U4oImGuZokkgiu1rbFuBiiEtrvomeXFrLaV7j2EloAw0mB7yyi', '013-246387', 'Not Specified', 'Online'),
('bda26001', 'ivan', 'ivanthy2005@gmail.com', '$2y$10$C/qUXypSw9x75vVrblx4g.2FQhl3v8vrxfNk5XdlPIq70JkZsQ8x.', '014-301941', 'weekdays', 'In-person'),
('BDA26666', 'student1', 'student1@gmail.com', '$2y$10$L9iCgBXKFcgfdaKujMh7/ebL9cbPaG1IOSLrhkPa22HufSnQ56lOS', '012-345678', 'Not Specified', 'Online');

-- --------------------------------------------------------

--
-- Table structure for table `supportrequest`
--

CREATE TABLE `supportrequest` (
  `requestID` varchar(15) NOT NULL,
  `requestDesc` varchar(250) NOT NULL,
  `studentID` varchar(15) NOT NULL,
  `created_at` datetime NOT NULL,
  `skillID` varchar(15) NOT NULL,
  `requestStatus` varchar(10) NOT NULL,
  `supportMode` varchar(10) NOT NULL,
  `preferred_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supportrequest`
--

INSERT INTO `supportrequest` (`requestID`, `requestDesc`, `studentID`, `created_at`, `skillID`, `requestStatus`, `supportMode`, `preferred_at`) VALUES
('REQ-6ad5cef2e85', 'Preferred Date: 2026-10-23, Time: 16:08', 'bda26001', '2026-10-19 10:04:02', '4', 'Pending', 'Both', '2026-10-19 08:04:02'),
('REQ-6ad5d0476d9', 'Preferred Date: 2026-11-07, Time: 16:13', 'bda26001', '2026-10-19 10:09:43', '3', 'Pending', 'In-person', '2026-10-19 08:09:43'),
('REQ-6ad5d057018', 'Preferred Date: 2026-12-04, Time: 16:13', 'bda26001', '2026-10-19 10:09:59', '3', 'Pending', 'Both', '2026-10-19 08:09:59'),
('REQ-6ad5d1124d7', 'Preferred Date: 2026-12-04, Time: 16:13', 'bda26001', '2026-10-19 10:13:06', '3', 'Pending', 'Both', '2026-10-19 08:13:06'),
('REQ-6ad73d2e8ea', 'Preferred Date: 2026-10-29, Time: 18:07', 'bda26001', '2026-10-20 12:06:38', '2', 'Pending', 'In-person', '2026-10-20 10:06:38'),
('REQ-6ad7409c793', 'Preferred Date: 2026-10-30, Time: 18:24', 'bda26001', '2026-10-20 12:21:16', '2', 'Pending', 'In-person', '2026-10-20 10:21:16'),
('REQ-6ad741a4b7e', 'Preferred Date: 2026-10-30, Time: 18:24', 'bda26001', '2026-10-20 12:25:40', '2', 'Pending', 'In-person', '2026-10-20 10:25:40'),
('REQ-6ad741f8c74', 'Preferred Date: 2026-10-30, Time: 18:29', 'bda26001', '2026-10-20 12:27:04', '2', 'Pending', 'In-person', '2026-10-20 10:27:04'),
('REQ-6ad7431a101', 'Preferred Date: 2026-10-30, Time: 18:29', 'bda26001', '2026-10-20 12:31:54', '2', 'Pending', 'In-person', '2026-10-20 10:31:54'),
('REQ-6ad841d0b10', 'Preferred Date: 2026-10-30, Time: 00:39 | AssignedVolunteer: bda26001', 'BDA26666', '2026-10-21 06:38:40', '3', 'Accepted', 'In-person', '2026-10-21 04:39:14'),
('REQ-6ad84214c45', 'Preferred Date: 2026-10-30, Time: 12:40 | AssignedVolunteer: bda26001', 'BDA26666', '2026-10-21 06:39:48', '9', 'Rejected', 'In-person', '2026-10-21 04:40:23'),
('REQ-6ad8479ee2c', 'Preferred Date: 2026-10-31, Time: 13:05 | AssignedVolunteer: bda26001', 'BDA26666', '2026-10-21 07:03:26', '3', 'Accepted', 'In-person', '2026-10-21 05:04:23'),
('REQ-6ad851d932e', 'Preferred Date: 2026-10-31, Time: 13:49 | AssignedVolunteer: bda26001', 'BDA26666', '2026-10-21 07:47:05', '7', 'Accepted', 'In-person', '2026-10-21 05:48:04');

-- --------------------------------------------------------

--
-- Table structure for table `volunteerskill`
--

CREATE TABLE `volunteerskill` (
  `studentID` varchar(15) NOT NULL,
  `skillID` varchar(15) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `volunteerskill`
--

INSERT INTO `volunteerskill` (`studentID`, `skillID`) VALUES
('bda26001', '2'),
('bda26001', '3'),
('bda26001', '5'),
('bda26001', '7'),
('bda26001', '8'),
('bda26001', '9');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`feedbackID`),
  ADD KEY `sessionID` (`sessionID`);

--
-- Indexes for table `session`
--
ALTER TABLE `session`
  ADD PRIMARY KEY (`sessionID`),
  ADD KEY `requestID` (`requestID`),
  ADD KEY `studentID` (`studentID`);

--
-- Indexes for table `skills`
--
ALTER TABLE `skills`
  ADD PRIMARY KEY (`skillID`);

--
-- Indexes for table `student`
--
ALTER TABLE `student`
  ADD PRIMARY KEY (`studentID`);

--
-- Indexes for table `supportrequest`
--
ALTER TABLE `supportrequest`
  ADD PRIMARY KEY (`requestID`),
  ADD KEY `studentID` (`studentID`),
  ADD KEY `skillID` (`skillID`);

--
-- Indexes for table `volunteerskill`
--
ALTER TABLE `volunteerskill`
  ADD PRIMARY KEY (`studentID`,`skillID`),
  ADD KEY `skillID` (`skillID`);

--
-- Constraints for dumped tables
--

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`sessionID`) REFERENCES `session` (`sessionID`);

--
-- Constraints for table `session`
--
ALTER TABLE `session`
  ADD CONSTRAINT `session_ibfk_1` FOREIGN KEY (`requestID`) REFERENCES `supportrequest` (`requestID`),
  ADD CONSTRAINT `session_ibfk_2` FOREIGN KEY (`studentID`) REFERENCES `student` (`studentID`);

--
-- Constraints for table `supportrequest`
--
ALTER TABLE `supportrequest`
  ADD CONSTRAINT `supportrequest_ibfk_1` FOREIGN KEY (`studentID`) REFERENCES `student` (`studentID`),
  ADD CONSTRAINT `supportrequest_ibfk_2` FOREIGN KEY (`skillID`) REFERENCES `skills` (`skillID`);

--
-- Constraints for table `volunteerskill`
--
ALTER TABLE `volunteerskill`
  ADD CONSTRAINT `volunteerskill_ibfk_1` FOREIGN KEY (`studentID`) REFERENCES `student` (`studentID`),
  ADD CONSTRAINT `volunteerskill_ibfk_2` FOREIGN KEY (`skillID`) REFERENCES `skills` (`skillID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
