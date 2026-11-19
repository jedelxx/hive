-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 19, 2026 at 11:43 AM
-- Server version: 10.11.16-MariaDB
-- PHP Version: 8.5.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `hive_pup_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `chat_message`
--

CREATE TABLE `chat_message` (
  `message_id` int(11) NOT NULL,
  `thread_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `message_text` text NOT NULL,
  `sent_at` timestamp NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chat_thread`
--

CREATE TABLE `chat_thread` (
  `thread_id` int(11) NOT NULL,
  `listing_id` int(11) DEFAULT NULL,
  `seeker_id` int(11) NOT NULL,
  `keeper_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `house_rule`
--

CREATE TABLE `house_rule` (
  `rule_id` int(11) NOT NULL,
  `rule_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `house_rule`
--

INSERT INTO `house_rule` (`rule_id`, `rule_name`) VALUES
(5, 'Allowed cooking'),
(3, 'Allowed visitors'),
(1, 'No pets'),
(4, 'Not allowed cooking'),
(2, 'Not allowed visitors');

-- --------------------------------------------------------

--
-- Table structure for table `housing_type`
--

CREATE TABLE `housing_type` (
  `type_id` int(11) NOT NULL,
  `housing_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `housing_type`
--

INSERT INTO `housing_type` (`type_id`, `housing_name`) VALUES
(1, 'Apartment'),
(2, 'Condominium');

-- --------------------------------------------------------

--
-- Table structure for table `inclusion`
--

CREATE TABLE `inclusion` (
  `inclusion_id` int(11) NOT NULL,
  `inclusion_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inclusion`
--

INSERT INTO `inclusion` (`inclusion_id`, `inclusion_name`) VALUES
(4, 'Aircon'),
(1, 'Bed'),
(3, 'Electric Fan'),
(6, 'Induction Cooker'),
(2, 'Refrigerator'),
(7, 'Study Table'),
(5, 'Water Heater');

-- --------------------------------------------------------

--
-- Table structure for table `listing`
--

CREATE TABLE `listing` (
  `listing_id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `keeper_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `purpose` enum('Lessor','Sharer') NOT NULL,
  `description` text DEFAULT NULL,
  `monthly_rent` decimal(10,2) NOT NULL,
  `assoc_dues` decimal(10,2) DEFAULT 0.00,
  `advance_months` int(11) DEFAULT 1,
  `deposit_months` int(11) DEFAULT 1,
  `water_included` tinyint(1) DEFAULT 0,
  `electricity_included` tinyint(1) DEFAULT 0,
  `wifi_included` tinyint(1) DEFAULT 0,
  `status` enum('Available','Occupied','Removed','Taken Down') DEFAULT 'Available',
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `no_tenant` int(11) DEFAULT NULL,
  `is_long_term` tinyint(1) DEFAULT NULL,
  `furnish` enum('Unfurnished','Semi-furnished','Furnished') DEFAULT NULL
) ;

--
-- Dumping data for table `listing`
--

INSERT INTO `listing` (`listing_id`, `property_id`, `keeper_id`, `title`, `purpose`, `description`, `monthly_rent`, `assoc_dues`, `advance_months`, `deposit_months`, `water_included`, `electricity_included`, `wifi_included`, `status`, `is_featured`, `created_at`, `updated_at`, `no_tenant`, `is_long_term`, `furnish`) VALUES
(1001, 1, 3, '2BR Unit 6pax', 'Lessor', 'Bare unit', 15000.00, 1500.00, 2, 1, 0, 0, 0, 'Available', 0, NULL, '2026-11-18 17:42:08', 2, NULL, NULL),
(1002, 1, 1, '2BR Unit 6pax', 'Sharer', 'Bare unit', 10000.00, 1200.00, 2, 1, 0, 0, 0, 'Occupied', 0, NULL, NULL, NULL, NULL, NULL),
(1003, 3, 4, '3BR Unit 6pax', 'Sharer', 'Bare unit', 10000.00, 1200.00, 2, 1, 0, 0, 0, 'Occupied', 1, NULL, NULL, NULL, NULL, NULL),
(1004, 2, 2, '1BR Unit', 'Lessor', 'Bare unit', 10000.00, 1200.00, 2, 1, 0, 0, 0, 'Occupied', 1, NULL, NULL, NULL, NULL, NULL),
(1005, 4, 1, '2BR Unit 2pax', 'Sharer', 'Bare unit', 10000.00, 1200.00, 2, 1, 0, 0, 0, 'Available', 1, NULL, NULL, NULL, NULL, NULL),
(1006, 5, 3, '2BR Unit', 'Lessor', 'Bare unit', 10000.00, 1200.00, 2, 1, 0, 0, 0, 'Occupied', 0, NULL, NULL, NULL, NULL, NULL),
(1007, 2, 1, '2BR Unit 6pax', 'Lessor', 'Bare unit', 10000.00, 1200.00, 2, 1, 0, 0, 0, 'Available', 1, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `listing_inclusion`
--

CREATE TABLE `listing_inclusion` (
  `listing_id` int(11) NOT NULL,
  `inclusion_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `listing_inclusion`
--

INSERT INTO `listing_inclusion` (`listing_id`, `inclusion_id`) VALUES
(1001, 2),
(1001, 4),
(1002, 4);

-- --------------------------------------------------------

--
-- Table structure for table `listing_photo`
--

CREATE TABLE `listing_photo` (
  `photo_id` int(11) NOT NULL,
  `listing_id` int(11) NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `listing_rule`
--

CREATE TABLE `listing_rule` (
  `listing_id` int(11) NOT NULL,
  `rule_id` int(11) NOT NULL,
  `rule_value` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `property`
--

CREATE TABLE `property` (
  `property_id` int(11) NOT NULL,
  `type_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `address` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `property`
--

INSERT INTO `property` (`property_id`, `type_id`, `name`, `address`) VALUES
(1, 2, 'El Pueblo Manila', 'Anonas Street, Sta. Mesa, Manila'),
(2, 2, 'Maui Oasis', 'Anonas Street, Sta. Mesa, Manila'),
(3, 2, 'Covent Garden', 'Anonas Street, Sta. Mesa, Manila'),
(4, 2, 'Silk Residences', 'Anonas Street, Sta. Mesa, Manila'),
(5, 2, 'Futura Centro', 'Anonas Street, Sta. Mesa, Manila');

-- --------------------------------------------------------

--
-- Table structure for table `report`
--

CREATE TABLE `report` (
  `report_id` int(11) NOT NULL,
  `listing_id` int(11) NOT NULL,
  `reporter_id` int(11) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('Pending','Confirmed Fraud','Dismissed') NOT NULL DEFAULT 'Pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `review`
--

CREATE TABLE `review` (
  `review_id` int(11) NOT NULL,
  `listing_id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL,
  `comment` text DEFAULT NULL,
  `is_verified_tenant` tinyint(1) DEFAULT 0,
  `tenant_status` enum('Current','Past','None') NOT NULL DEFAULT 'None',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Table structure for table `roommate_profile`
--

CREATE TABLE `roommate_profile` (
  `profile_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `budget_min` decimal(10,2) DEFAULT NULL,
  `budget_max` decimal(10,2) DEFAULT NULL,
  `preferred_sex` enum('Male','Female','Any') DEFAULT 'Any',
  `sleep_schedule` enum('Early Bird','Night Owl','Flexible') DEFAULT NULL,
  `study_habit` enum('Quiet Environment','Group Study','Flexible') DEFAULT NULL,
  `cleanliness_level` int(11) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ;

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `user_id` int(11) NOT NULL,
  `user_code` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `contact_no` varchar(20) DEFAULT NULL,
  `sex` enum('Male','Female','Prefer not to say') DEFAULT NULL,
  `id_type` varchar(50) DEFAULT NULL,
  `id_document_url` varchar(255) DEFAULT NULL,
  `is_id_verified` tinyint(1) DEFAULT 0,
  `account_status` enum('Active','Suspended','Banned') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`user_id`, `user_code`, `username`, `email`, `password_hash`, `first_name`, `last_name`, `contact_no`, `sex`, `id_type`, `id_document_url`, `is_id_verified`, `account_status`, `created_at`) VALUES
(1, 1, 'jedel', 'jennifer@gmail.com', 'jedel', 'Jennifer', 'Cruz', '12345', 'Female', NULL, NULL, NULL, 'Active', NULL),
(2, 1, 'cleo', 'jennifer1@gmail.com', 'cleo', 'Cleo', 'Cruz', '12345', 'Female', NULL, NULL, NULL, 'Active', NULL),
(3, 2, 'holly', 'jennifer2@gmail.com', 'holly', 'Holly', 'Cruz', '12345', 'Female', NULL, NULL, NULL, 'Banned', NULL),
(4, 1, 'faith', 'jennifer3@gmail.com', 'faith', 'Kierstien', 'Cruz', '12345', 'Female', NULL, NULL, NULL, 'Active', NULL),
(5, 2, 'helios', 'jennifer4@gmail.com', 'helios', 'Helios', 'Cruz', '12345', 'Female', NULL, NULL, NULL, 'Active', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_type`
--

CREATE TABLE `user_type` (
  `user_code` int(11) NOT NULL,
  `user_type` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_type`
--

INSERT INTO `user_type` (`user_code`, `user_type`) VALUES
(1, 'Seeker'),
(2, 'Keeper');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `chat_message`
--
ALTER TABLE `chat_message`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `thread_id` (`thread_id`),
  ADD KEY `sender_id` (`sender_id`);

--
-- Indexes for table `chat_thread`
--
ALTER TABLE `chat_thread`
  ADD PRIMARY KEY (`thread_id`),
  ADD UNIQUE KEY `uq_thread` (`listing_id`,`seeker_id`,`keeper_id`),
  ADD KEY `seeker_id` (`seeker_id`),
  ADD KEY `keeper_id` (`keeper_id`);

--
-- Indexes for table `house_rule`
--
ALTER TABLE `house_rule`
  ADD PRIMARY KEY (`rule_id`),
  ADD UNIQUE KEY `rule_name` (`rule_name`);

--
-- Indexes for table `housing_type`
--
ALTER TABLE `housing_type`
  ADD PRIMARY KEY (`type_id`);

--
-- Indexes for table `inclusion`
--
ALTER TABLE `inclusion`
  ADD PRIMARY KEY (`inclusion_id`),
  ADD UNIQUE KEY `amenity_name` (`inclusion_name`);

--
-- Indexes for table `listing`
--
ALTER TABLE `listing`
  ADD PRIMARY KEY (`listing_id`),
  ADD KEY `property_id` (`property_id`),
  ADD KEY `keeper_id` (`keeper_id`),
  ADD KEY `idx_listing_search` (`status`,`monthly_rent`,`property_id`);

--
-- Indexes for table `listing_inclusion`
--
ALTER TABLE `listing_inclusion`
  ADD PRIMARY KEY (`listing_id`,`inclusion_id`),
  ADD KEY `amenity_id` (`inclusion_id`);

--
-- Indexes for table `listing_photo`
--
ALTER TABLE `listing_photo`
  ADD PRIMARY KEY (`photo_id`),
  ADD KEY `listing_id` (`listing_id`);

--
-- Indexes for table `listing_rule`
--
ALTER TABLE `listing_rule`
  ADD PRIMARY KEY (`listing_id`,`rule_id`),
  ADD KEY `rule_id` (`rule_id`);

--
-- Indexes for table `property`
--
ALTER TABLE `property`
  ADD PRIMARY KEY (`property_id`),
  ADD KEY `type_id` (`type_id`);

--
-- Indexes for table `report`
--
ALTER TABLE `report`
  ADD PRIMARY KEY (`report_id`),
  ADD UNIQUE KEY `uq_report` (`listing_id`,`reporter_id`),
  ADD KEY `reporter_id` (`reporter_id`),
  ADD KEY `reviewed_by` (`reviewed_by`);

--
-- Indexes for table `review`
--
ALTER TABLE `review`
  ADD PRIMARY KEY (`review_id`),
  ADD UNIQUE KEY `uq_review` (`listing_id`,`reviewer_id`),
  ADD KEY `reviewer_id` (`reviewer_id`);

--
-- Indexes for table `roommate_profile`
--
ALTER TABLE `roommate_profile`
  ADD PRIMARY KEY (`profile_id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `user_code` (`user_code`);

--
-- Indexes for table `user_type`
--
ALTER TABLE `user_type`
  ADD PRIMARY KEY (`user_code`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `chat_message`
--
ALTER TABLE `chat_message`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_thread`
--
ALTER TABLE `chat_thread`
  MODIFY `thread_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `house_rule`
--
ALTER TABLE `house_rule`
  MODIFY `rule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `housing_type`
--
ALTER TABLE `housing_type`
  MODIFY `type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `inclusion`
--
ALTER TABLE `inclusion`
  MODIFY `inclusion_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `listing`
--
ALTER TABLE `listing`
  MODIFY `listing_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `listing_photo`
--
ALTER TABLE `listing_photo`
  MODIFY `photo_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `property`
--
ALTER TABLE `property`
  MODIFY `property_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `report`
--
ALTER TABLE `report`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `review`
--
ALTER TABLE `review`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roommate_profile`
--
ALTER TABLE `roommate_profile`
  MODIFY `profile_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `user_type`
--
ALTER TABLE `user_type`
  MODIFY `user_code` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `chat_message`
--
ALTER TABLE `chat_message`
  ADD CONSTRAINT `chat_message_ibfk_1` FOREIGN KEY (`thread_id`) REFERENCES `chat_thread` (`thread_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chat_message_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `user` (`user_id`);

--
-- Constraints for table `chat_thread`
--
ALTER TABLE `chat_thread`
  ADD CONSTRAINT `chat_thread_ibfk_1` FOREIGN KEY (`listing_id`) REFERENCES `listing` (`listing_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chat_thread_ibfk_2` FOREIGN KEY (`seeker_id`) REFERENCES `user` (`user_id`),
  ADD CONSTRAINT `chat_thread_ibfk_3` FOREIGN KEY (`keeper_id`) REFERENCES `user` (`user_id`);

--
-- Constraints for table `listing`
--
ALTER TABLE `listing`
  ADD CONSTRAINT `listing_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `property` (`property_id`),
  ADD CONSTRAINT `listing_ibfk_2` FOREIGN KEY (`keeper_id`) REFERENCES `user` (`user_id`);

--
-- Constraints for table `listing_inclusion`
--
ALTER TABLE `listing_inclusion`
  ADD CONSTRAINT `listing_inclusion_ibfk_1` FOREIGN KEY (`listing_id`) REFERENCES `listing` (`listing_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `listing_inclusion_ibfk_2` FOREIGN KEY (`inclusion_id`) REFERENCES `inclusion` (`inclusion_id`) ON DELETE CASCADE;

--
-- Constraints for table `listing_photo`
--
ALTER TABLE `listing_photo`
  ADD CONSTRAINT `listing_photo_ibfk_1` FOREIGN KEY (`listing_id`) REFERENCES `listing` (`listing_id`) ON DELETE CASCADE;

--
-- Constraints for table `listing_rule`
--
ALTER TABLE `listing_rule`
  ADD CONSTRAINT `listing_rule_ibfk_1` FOREIGN KEY (`listing_id`) REFERENCES `listing` (`listing_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `listing_rule_ibfk_2` FOREIGN KEY (`rule_id`) REFERENCES `house_rule` (`rule_id`) ON DELETE CASCADE;

--
-- Constraints for table `property`
--
ALTER TABLE `property`
  ADD CONSTRAINT `property_ibfk_1` FOREIGN KEY (`type_id`) REFERENCES `housing_type` (`type_id`);

--
-- Constraints for table `report`
--
ALTER TABLE `report`
  ADD CONSTRAINT `report_ibfk_1` FOREIGN KEY (`listing_id`) REFERENCES `listing` (`listing_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `report_ibfk_2` FOREIGN KEY (`reporter_id`) REFERENCES `user` (`user_id`),
  ADD CONSTRAINT `report_ibfk_3` FOREIGN KEY (`reviewed_by`) REFERENCES `user` (`user_id`);

--
-- Constraints for table `review`
--
ALTER TABLE `review`
  ADD CONSTRAINT `review_ibfk_1` FOREIGN KEY (`listing_id`) REFERENCES `listing` (`listing_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `review_ibfk_2` FOREIGN KEY (`reviewer_id`) REFERENCES `user` (`user_id`);

--
-- Constraints for table `roommate_profile`
--
ALTER TABLE `roommate_profile`
  ADD CONSTRAINT `roommate_profile_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `user`
--
ALTER TABLE `user`
  ADD CONSTRAINT `user_ibfk_1` FOREIGN KEY (`user_code`) REFERENCES `user_type` (`user_code`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
