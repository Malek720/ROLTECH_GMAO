-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 06, 2026 at 06:35 PM
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
-- Database: `roltech_gmao`
--

-- --------------------------------------------------------

--
-- Table structure for table `doctrine_migration_versions`
--

CREATE TABLE `doctrine_migration_versions` (
  `version` varchar(191) NOT NULL,
  `executed_at` datetime DEFAULT NULL,
  `execution_time` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `doctrine_migration_versions`
--

INSERT INTO `doctrine_migration_versions` (`version`, `executed_at`, `execution_time`) VALUES
('DoctrineMigrations\\Version20260810200829', '2026-10-10 15:12:34', 212);

-- --------------------------------------------------------

--
-- Table structure for table `fault`
--

CREATE TABLE `fault` (
  `id` int(11) NOT NULL,
  `reported_at` datetime NOT NULL,
  `description` longtext NOT NULL,
  `severity` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL,
  `machine_id` int(11) NOT NULL,
  `reported_by_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `fault`
--

INSERT INTO `fault` (`id`, `reported_at`, `description`, `severity`, `status`, `machine_id`, `reported_by_id`) VALUES
(1, '2026-10-05 15:14:44', 'Arret brutal du moteur principal, bruit anormal au demarrage.', 'critique', 'ouverte', 2, 2),
(2, '2026-10-08 15:14:44', 'Convoyeur bloque par un desalignement de la bande.', 'moyenne', 'en cours', 3, 3),
(3, '2026-09-10 15:14:44', 'Fuite hydraulique mineure detectee lors de la ronde de controle.', 'faible', 'resolue', 1, 2);

-- --------------------------------------------------------

--
-- Table structure for table `intervention`
--

CREATE TABLE `intervention` (
  `id` int(11) NOT NULL,
  `type` varchar(255) NOT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime DEFAULT NULL,
  `status` varchar(255) NOT NULL,
  `comment` longtext DEFAULT NULL,
  `fault_id` int(11) NOT NULL,
  `technician_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `intervention`
--

INSERT INTO `intervention` (`id`, `type`, `start_date`, `end_date`, `status`, `comment`, `fault_id`, `technician_id`) VALUES
(1, 'curative', '2026-10-06 15:14:44', NULL, 'en cours', 'Diagnostic en cours, piece de rechange commandee.', 1, 2),
(2, 'curative', '2026-10-08 15:14:44', NULL, 'planifiee', 'Intervention prevue demain matin.', 2, 3),
(3, 'preventive', '2026-09-11 15:14:44', '2026-09-11 15:14:44', 'terminee', 'Joint remplace, fuite corrigee.', 3, 2);

-- --------------------------------------------------------

--
-- Table structure for table `machine`
--

CREATE TABLE `machine` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `model` varchar(255) NOT NULL,
  `serial_number` varchar(255) NOT NULL,
  `installation_date` date NOT NULL,
  `category` varchar(255) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `status` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `machine`
--

INSERT INTO `machine` (`id`, `name`, `model`, `serial_number`, `installation_date`, `category`, `location`, `status`) VALUES
(1, 'Presse hydraulique P-100', 'HP-2000X', 'SN-2021-00451', '2021-03-15', 'Presse', 'Atelier A', 'en service'),
(2, 'Tour CNC T-220', 'CNC-T220', 'SN-2019-00832', '2019-11-02', 'Usinage', 'Atelier B', 'en panne'),
(3, 'Convoyeur C-05', 'CV-500', 'SN-2022-00120', '2022-06-20', 'Manutention', 'Atelier A', 'en maintenance'),
(4, 'Compresseur CP-3', 'AirMax-300', 'SN-2020-00219', '2020-01-10', 'Utilitaire', 'Local technique', 'en service');

-- --------------------------------------------------------

--
-- Table structure for table `messenger_messages`
--

CREATE TABLE `messenger_messages` (
  `id` bigint(20) NOT NULL,
  `body` longtext NOT NULL,
  `headers` longtext NOT NULL,
  `queue_name` varchar(190) NOT NULL,
  `created_at` datetime NOT NULL,
  `available_at` datetime NOT NULL,
  `delivered_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `id` int(11) NOT NULL,
  `email` varchar(180) NOT NULL,
  `roles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`roles`)),
  `password` varchar(255) NOT NULL,
  `full_name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`id`, `email`, `roles`, `password`, `full_name`) VALUES
(1, 'admin@roltechsystems.com', '[\"ROLE_ADMIN\"]', '$2y$13$M0pUrGVf1mhvBmLLqyc1i.RxviESHlxJRW1nnEcJKWEC2CDL/r0l.', 'Ahmed Ben Salah'),
(2, 'karim.technicien@roltechsystems.com', '[\"ROLE_TECHNICIEN\"]', '$2y$13$4WqrqIGpjbmlqgz8RqO0ueSn0d1P76YNCqHgxmKc2N/Mf2zVypvSW', 'Karim Trabelsi'),
(3, 'sami.technicien@roltechsystems.com', '[\"ROLE_TECHNICIEN\"]', '$2y$13$mBwFhD7rfLzRNr9AXg40dONbdZeLkMW4feGN/veYjzbiU.fg6IRqS', 'Sami Gharbi'),
(4, 'malek.responsable@roltechsystems.com', '[\"ROLE_RESPONSABLE\"]', '$2y$13$EuZBmzC3Ps7527pRiVgcxuI68l6oFBh9sVN88XPOHQ7F9iU/9/S2y', 'Malek Ben Sassi');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `doctrine_migration_versions`
--
ALTER TABLE `doctrine_migration_versions`
  ADD PRIMARY KEY (`version`);

--
-- Indexes for table `fault`
--
ALTER TABLE `fault`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_9FD0DEA3F6B75B26` (`machine_id`),
  ADD KEY `IDX_9FD0DEA371CE806` (`reported_by_id`);

--
-- Indexes for table `intervention`
--
ALTER TABLE `intervention`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_D11814AB24171CD3` (`fault_id`),
  ADD KEY `IDX_D11814ABE6C5D496` (`technician_id`);

--
-- Indexes for table `machine`
--
ALTER TABLE `machine`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `messenger_messages`
--
ALTER TABLE `messenger_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750` (`queue_name`,`available_at`,`delivered_at`,`id`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_IDENTIFIER_EMAIL` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `fault`
--
ALTER TABLE `fault`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `intervention`
--
ALTER TABLE `intervention`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `machine`
--
ALTER TABLE `machine`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `messenger_messages`
--
ALTER TABLE `messenger_messages`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `fault`
--
ALTER TABLE `fault`
  ADD CONSTRAINT `FK_9FD0DEA371CE806` FOREIGN KEY (`reported_by_id`) REFERENCES `user` (`id`),
  ADD CONSTRAINT `FK_9FD0DEA3F6B75B26` FOREIGN KEY (`machine_id`) REFERENCES `machine` (`id`);

--
-- Constraints for table `intervention`
--
ALTER TABLE `intervention`
  ADD CONSTRAINT `FK_D11814AB24171CD3` FOREIGN KEY (`fault_id`) REFERENCES `fault` (`id`),
  ADD CONSTRAINT `FK_D11814ABE6C5D496` FOREIGN KEY (`technician_id`) REFERENCES `user` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
