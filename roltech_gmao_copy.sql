-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 13, 2026 at 02:32 PM
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
-- Database: `roltech_gmao_copy`
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
('DoctrineMigrations\\Version20260810200829', '2026-10-10 15:12:34', 212),
('DoctrineMigrations\\Version20261108110609', '2026-11-08 12:07:31', 578),
('DoctrineMigrations\\Version20261108110837', '2026-11-08 12:12:00', 44),
('DoctrineMigrations\\Version20261108112255', '2026-11-08 12:23:05', 38),
('DoctrineMigrations\\Version20261113123000', '2026-11-13 14:11:10', 233),
('DoctrineMigrations\\Version20261113132646', '2026-11-13 14:26:55', 3);

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
  `reported_by_id` int(11) NOT NULL,
  `symptom` varchar(255) DEFAULT NULL,
  `module_id` int(11) DEFAULT NULL,
  `part_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `fault`
--

INSERT INTO `fault` (`id`, `reported_at`, `description`, `severity`, `status`, `machine_id`, `reported_by_id`, `symptom`, `module_id`, `part_id`) VALUES
(14, '2026-11-08 14:25:16', 'Le convoyeur ne démarre pas et le moteur s’arrête fréquemment.', 'critique', 'ouverte', 32, 19, 'Conveyor does not start or motor stops frequently', 55, NULL),
(15, '2026-11-11 14:25:16', 'Bruit de craquement observé au niveau des disques de renvoi.', 'moyenne', 'en cours', 30, 18, 'Cracking noise at the return discs (disques de renvoi)', 51, NULL),
(16, '2026-10-14 14:25:16', 'Le moteur ou le réducteur chauffe anormalement.', 'faible', 'resolue', 29, 20, 'Motor or reducer overheating', 55, NULL);

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
  `fault_id` int(11) DEFAULT NULL,
  `technician_id` int(11) DEFAULT NULL,
  `schedule_id` int(11) DEFAULT NULL,
  `assignment_source` varchar(30) NOT NULL,
  `assigned_by_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `intervention`
--

INSERT INTO `intervention` (`id`, `type`, `start_date`, `end_date`, `status`, `comment`, `fault_id`, `technician_id`, `schedule_id`, `assignment_source`, `assigned_by_id`) VALUES
(17, 'curative', '2026-11-09 14:25:16', NULL, 'en cours', 'Diagnostic en cours.', 14, 19, NULL, 'systeme', NULL),
(18, 'curative', '2026-11-11 14:25:16', NULL, 'planifiee', NULL, 15, 18, NULL, 'systeme', NULL),
(19, 'curative', '2026-10-15 14:25:16', '2026-10-15 14:25:16', 'terminee', 'Contrôle terminé.', 16, 18, NULL, 'systeme', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `machine`
--

CREATE TABLE `machine` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `serial_number` varchar(255) NOT NULL,
  `installation_date` date NOT NULL,
  `category` varchar(255) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `status` varchar(255) NOT NULL,
  `operating_hours_correction` int(11) NOT NULL,
  `site_id` int(11) DEFAULT NULL,
  `machine_model_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `machine`
--

INSERT INTO `machine` (`id`, `name`, `serial_number`, `installation_date`, `category`, `location`, `status`, `operating_hours_correction`, `site_id`, `machine_model_id`) VALUES
(29, 'OC-CA1100 Sousse 01', 'SERIAL #SO-1100-001', '2021-03-15', 'Convoyeur aérien (crochets)', 'Atelier A', 'en service', 0, 14, 3),
(30, 'OC-CA1100 Sousse 02', 'SERIAL #SO-1100-002', '2022-06-20', 'Convoyeur aérien (crochets)', 'Atelier B', 'en maintenance', 0, 14, 3),
(31, 'OC-CA1100 Béja 01', 'SERIAL #BJ-1100-001', '2020-01-10', 'Convoyeur aérien (crochets)', 'Ligne 1', 'en service', 0, 15, 3),
(32, 'OC-CA1100 Béja 02', 'SERIAL #BJ-1100-002', '2019-11-02', 'Convoyeur aérien (crochets)', 'Ligne 2', 'en panne', 0, 15, 3),
(33, 'OC-CA1100 Jendouba 01', 'SERIAL #JE-1100-001', '2021-09-12', 'Convoyeur aérien (crochets)', 'Atelier A', 'en service', 0, 16, 3),
(34, 'OC-CA1100 Jendouba 02', 'SERIAL #JE-1100-002', '2022-02-08', 'Convoyeur aérien (crochets)', 'Atelier B', 'en service', 0, 16, 3),
(35, 'OC-CA1100 Tunis 01', 'SERIAL #TU-1100-001', '2020-07-24', 'Convoyeur aérien (crochets)', 'Zone montage', 'en service', 0, 17, 3),
(36, 'OC-CA1100 Tunis 02', 'SERIAL #TU-1100-002', '2021-12-01', 'Convoyeur aérien (crochets)', 'Zone expédition', 'en maintenance', 0, 17, 3),
(37, 'HLS-CA1100 Sousse 01', 'HLS-2024-0001', '2024-01-10', 'Logistique bobines', 'Atelier C', 'en service', 0, 14, 4),
(38, 'HLS-CA1100 Béja 01', 'HLS-2024-0002', '2024-02-12', 'Logistique bobines', 'Ligne 3', 'en service', 0, 15, 4),
(39, 'HLS-CA1100 Jendouba 01', 'HLS-2024-0003', '2024-03-15', 'Logistique bobines', 'Atelier C', 'en service', 0, 16, 4),
(40, 'HLS-PXL-CA1100 Sousse 01', 'HLS-PXL-2024-0001', '2024-01-20', 'Logistique faisceaux', 'Ligne 4', 'en service', 0, 14, 5),
(41, 'HLS-PXL-CA1100 Sousse 02', 'HLS-PXL-2024-0002', '2024-02-20', 'Logistique faisceaux', 'Ligne 5', 'en maintenance', 0, 14, 5),
(42, 'HLS-PXL-CA1100 Tunis 01', 'HLS-PXL-2024-0003', '2024-03-20', 'Logistique faisceaux', 'Zone nord', 'en service', 0, 17, 5),
(43, 'HLS-PXL-CA1100-01 Béja 01', 'HLS-BOX-2024-0001', '2024-01-25', 'Logistique caisses', 'Ligne 6', 'en service', 0, 15, 6),
(44, 'HLS-PXL-CA1100-01 Jendouba 01', 'HLS-BOX-2024-0002', '2024-02-25', 'Logistique caisses', 'Ligne 7', 'en service', 0, 16, 6),
(45, 'HLS-PXL-CA1100-01 Tunis 01', 'HLS-BOX-2024-0003', '2024-03-25', 'Logistique caisses', 'Zone sud', 'en service', 0, 17, 6),
(46, 'PPXXL-CA1100 Sousse 01', 'PPXXL-2024-0001', '2024-01-30', 'Ligne de peinture', 'Peinture', 'en panne', 0, 14, 7),
(47, 'PPXXL-CA1100 Jendouba 01', 'PPXXL-2024-0002', '2024-02-28', 'Ligne de peinture', 'Peinture', 'en service', 0, 16, 7),
(48, 'HP-2000X Tunis 01', 'HP-2000X-2024-0001', '2024-01-05', 'Presse', 'Presse 1', 'en service', 0, 17, 8),
(49, 'HP-2000X Béja 01', 'HP-2000X-2024-0002', '2024-02-05', 'Presse', 'Presse 2', 'en maintenance', 0, 15, 8),
(50, 'AirMax-300 Sousse 01', 'AIRMAX-2024-0001', '2024-01-08', 'Utilitaire', 'Compresseur 1', 'en service', 0, 14, 9),
(51, 'AirMax-300 Jendouba 01', 'AIRMAX-2024-0002', '2024-02-08', 'Utilitaire', 'Compresseur 2', 'en service', 0, 16, 9);

-- --------------------------------------------------------

--
-- Table structure for table `machine_model`
--

CREATE TABLE `machine_model` (
  `id` int(11) NOT NULL,
  `code` varchar(100) NOT NULL,
  `name` varchar(255) NOT NULL,
  `family` varchar(255) NOT NULL,
  `max_load_kg` int(11) DEFAULT NULL,
  `data_status` varchar(20) NOT NULL,
  `notes` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `machine_model`
--

INSERT INTO `machine_model` (`id`, `code`, `name`, `family`, `max_load_kg`, `data_status`, `notes`) VALUES
(3, 'OC-CA1100', 'Convoyeur aérien OC-CA1100', 'Convoyeur aérien (crochets)', 15, 'confirmed', NULL),
(4, 'HLS-CA1100', 'Système logistique de bobines de câble', 'Logistique bobines', 60, 'demo', NULL),
(5, 'HLS-PXL-CA1100', 'Système logistique de gros faisceaux de câbles', 'Logistique faisceaux', 80, 'demo', NULL),
(6, 'HLS-PXL-CA1100-01', 'Système logistique de caisses', 'Logistique caisses', 25, 'demo', NULL),
(7, 'PPXXL-CA1100', 'Convoyeur de ligne de peinture', 'Ligne de peinture', 20, 'demo', NULL),
(8, 'HP-2000X', 'Presse hydraulique HP-2000X', 'Presse', NULL, 'demo', NULL),
(9, 'AirMax-300', 'Compresseur d’air AirMax-300', 'Utilitaire', NULL, 'demo', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `machine_module`
--

CREATE TABLE `machine_module` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `planche` varchar(255) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `data_status` varchar(20) NOT NULL,
  `machine_model_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `machine_module`
--

INSERT INTO `machine_module` (`id`, `code`, `name`, `planche`, `parent_id`, `data_status`, `machine_model_id`) VALUES
(22, 'M1', 'Groupe de renvoi 180°', 'M1', NULL, 'demo', 4),
(23, 'M2', 'Groupe de renvoi 90°', 'M2', NULL, 'demo', 4),
(24, 'M3', 'Groupe motorisation tendeur', 'M3', NULL, 'demo', 4),
(25, 'M4', 'Module poulie', 'M4', 24, 'demo', 4),
(26, 'M5', 'Tronçon', 'M5', NULL, 'demo', 4),
(27, 'M6', 'Trolley', 'M6', NULL, 'demo', 4),
(28, 'M7', 'Porteur de charge', 'M7', NULL, 'demo', 4),
(29, 'M1', 'Groupe de renvoi 180°', 'M1', NULL, 'demo', 5),
(30, 'M2', 'Groupe de renvoi 90°', 'M2', NULL, 'demo', 5),
(31, 'M3', 'Groupe motorisation tendeur', 'M3', NULL, 'demo', 5),
(32, 'M4', 'Module poulie', 'M4', 31, 'demo', 5),
(33, 'M5', 'Tronçon', 'M5', NULL, 'demo', 5),
(34, 'M6', 'Trolley', 'M6', NULL, 'demo', 5),
(35, 'M7', 'Porteur de charge', 'M7', NULL, 'demo', 5),
(36, 'M1', 'Groupe de renvoi 180°', 'M1', NULL, 'demo', 6),
(37, 'M2', 'Groupe de renvoi 90°', 'M2', NULL, 'demo', 6),
(38, 'M3', 'Groupe motorisation tendeur', 'M3', NULL, 'demo', 6),
(39, 'M4', 'Module poulie', 'M4', 38, 'demo', 6),
(40, 'M5', 'Tronçon', 'M5', NULL, 'demo', 6),
(41, 'M6', 'Trolley', 'M6', NULL, 'demo', 6),
(42, 'M7', 'Porteur de charge', 'M7', NULL, 'demo', 6),
(43, 'M1', 'Groupe de renvoi 180°', 'M1', NULL, 'demo', 7),
(44, 'M2', 'Groupe de renvoi 90°', 'M2', NULL, 'demo', 7),
(45, 'M3', 'Groupe motorisation tendeur', 'M3', NULL, 'demo', 7),
(46, 'M4', 'Module poulie', 'M4', 45, 'demo', 7),
(47, 'M5', 'Tronçon', 'M5', NULL, 'demo', 7),
(48, 'M6', 'Trolley', 'M6', NULL, 'demo', 7),
(49, 'M7', 'Porteur de charge', 'M7', NULL, 'demo', 7),
(50, 'M8', 'Zone de séchage', 'M8', NULL, 'demo', 7),
(51, 'M1', 'Groupe de renvoi 180° (GR 180 1100)', 'OC1100-01', NULL, 'confirmed', 3),
(52, 'M2', 'Groupe de renvoi 90° (GR 90 1100)', 'OC1100-02', NULL, 'confirmed', 3),
(53, 'M3', 'Groupe Motorisation Tendeur (GM T 200 1100)', 'OC1100-03', NULL, 'confirmed', 3),
(54, 'M4', 'Module poulie (GM-800 01 1100)', 'OC1100-04', 53, 'confirmed', 3),
(55, 'M5', 'Module support moteur (GM-700 02 1100)', 'OC1100-05', 53, 'confirmed', 3),
(56, 'M6', 'Tronçon (section)', 'OC1100-06', NULL, 'confirmed', 3),
(57, 'M7', 'Trolley (T-200-1100)', 'OC1100-07', 56, 'confirmed', 3),
(58, 'M1', 'Groupe hydraulique', 'M1', NULL, 'demo', 8),
(59, 'M2', 'Vérin principal', 'M2', NULL, 'demo', 8),
(60, 'M3', 'Armoire électrique', 'M3', NULL, 'demo', 8),
(61, 'M4', 'Châssis et outillage', 'M4', NULL, 'demo', 8),
(62, 'M1', 'Bloc de compression', 'M1', NULL, 'demo', 9),
(63, 'M2', 'Filtration', 'M2', NULL, 'demo', 9),
(64, 'M3', 'Réservoir et purge', 'M3', NULL, 'demo', 9),
(65, 'M4', 'Moteur et commande', 'M4', NULL, 'demo', 9);

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_schedule`
--

CREATE TABLE `maintenance_schedule` (
  `id` int(11) NOT NULL,
  `last_done_date` date NOT NULL,
  `last_done_hours` int(11) NOT NULL,
  `next_due_date` date NOT NULL,
  `next_due_hours` int(11) NOT NULL,
  `state` varchar(50) NOT NULL,
  `machine_id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `archived_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `maintenance_schedule`
--

INSERT INTO `maintenance_schedule` (`id`, `last_done_date`, `last_done_hours`, `next_due_date`, `next_due_hours`, `state`, `machine_id`, `task_id`, `archived_at`) VALUES
(313, '2026-11-13', 49670, '2026-11-20', 49838, 'ok', 29, 40, NULL),
(314, '2026-11-13', 49670, '2026-11-20', 49838, 'ok', 29, 41, NULL),
(315, '2026-11-13', 49670, '2026-12-13', 50390, 'ok', 29, 42, NULL),
(316, '2026-11-13', 49670, '2026-11-20', 49838, 'ok', 29, 43, NULL),
(317, '2026-11-13', 49670, '2026-12-13', 50390, 'ok', 29, 44, NULL),
(318, '2026-11-13', 49670, '2026-11-20', 49838, 'ok', 29, 45, NULL),
(319, '2026-11-13', 49670, '2026-12-13', 50390, 'ok', 29, 46, NULL),
(320, '2026-11-13', 49670, '2027-02-11', 51830, 'ok', 29, 47, NULL),
(321, '2026-11-13', 49670, '2026-12-13', 50390, 'ok', 29, 48, NULL),
(322, '2026-11-13', 49670, '2026-12-13', 50390, 'ok', 29, 49, NULL),
(323, '2026-11-13', 49670, '2026-11-20', 49838, 'ok', 29, 50, NULL),
(324, '2026-11-13', 49670, '2026-12-13', 50390, 'ok', 29, 51, NULL),
(325, '2026-11-13', 49670, '2026-12-13', 50390, 'ok', 29, 52, NULL),
(326, '2026-11-13', 38583, '2026-11-20', 38751, 'ok', 30, 40, NULL),
(327, '2026-11-13', 38583, '2026-11-20', 38751, 'ok', 30, 41, NULL),
(328, '2026-11-13', 38583, '2026-12-13', 39303, 'ok', 30, 42, NULL),
(329, '2026-11-13', 38583, '2026-11-20', 38751, 'ok', 30, 43, NULL),
(330, '2026-11-13', 38583, '2026-12-13', 39303, 'ok', 30, 44, NULL),
(331, '2026-11-13', 38583, '2026-11-20', 38751, 'ok', 30, 45, NULL),
(332, '2026-11-13', 38583, '2026-12-13', 39303, 'ok', 30, 46, NULL),
(333, '2026-11-13', 38583, '2027-02-11', 40743, 'ok', 30, 47, NULL),
(334, '2026-11-13', 38583, '2026-12-13', 39303, 'ok', 30, 48, NULL),
(335, '2026-11-13', 38583, '2026-12-13', 39303, 'ok', 30, 49, NULL),
(336, '2026-11-13', 38583, '2026-11-20', 38751, 'ok', 30, 50, NULL),
(337, '2026-11-13', 38583, '2026-12-13', 39303, 'ok', 30, 51, NULL),
(338, '2026-11-13', 38583, '2026-12-13', 39303, 'ok', 30, 52, NULL),
(339, '2026-11-13', 59990, '2026-11-20', 60158, 'ok', 31, 40, NULL),
(340, '2026-11-13', 59990, '2026-11-20', 60158, 'ok', 31, 41, NULL),
(341, '2026-11-13', 59990, '2026-12-13', 60710, 'ok', 31, 42, NULL),
(342, '2026-11-13', 59990, '2026-11-20', 60158, 'ok', 31, 43, NULL),
(343, '2026-11-13', 59990, '2026-12-13', 60710, 'ok', 31, 44, NULL),
(344, '2026-11-13', 59990, '2026-11-20', 60158, 'ok', 31, 45, NULL),
(345, '2026-11-13', 59990, '2026-12-13', 60710, 'ok', 31, 46, NULL),
(346, '2026-11-13', 59990, '2027-02-11', 62150, 'ok', 31, 47, NULL),
(347, '2026-11-13', 59990, '2026-12-13', 60710, 'ok', 31, 48, NULL),
(348, '2026-11-13', 59990, '2026-12-13', 60710, 'ok', 31, 49, NULL),
(349, '2026-11-13', 59990, '2026-11-20', 60158, 'ok', 31, 50, NULL),
(350, '2026-11-13', 59990, '2026-12-13', 60710, 'ok', 31, 51, NULL),
(351, '2026-11-13', 59990, '2026-12-13', 60710, 'ok', 31, 52, NULL),
(352, '2026-11-13', 61646, '2026-11-20', 61814, 'ok', 32, 40, NULL),
(353, '2026-11-13', 61646, '2026-11-20', 61814, 'ok', 32, 41, NULL),
(354, '2026-11-13', 61646, '2026-12-13', 62366, 'ok', 32, 42, NULL),
(355, '2026-11-13', 61646, '2026-11-20', 61814, 'ok', 32, 43, NULL),
(356, '2026-11-13', 61646, '2026-12-13', 62366, 'ok', 32, 44, NULL),
(357, '2026-11-13', 61646, '2026-11-20', 61814, 'ok', 32, 45, NULL),
(358, '2026-11-13', 61646, '2026-12-13', 62366, 'ok', 32, 46, NULL),
(359, '2026-11-13', 61646, '2027-02-11', 63806, 'ok', 32, 47, NULL),
(360, '2026-11-13', 61646, '2026-12-13', 62366, 'ok', 32, 48, NULL),
(361, '2026-11-13', 61646, '2026-12-13', 62366, 'ok', 32, 49, NULL),
(362, '2026-11-13', 61646, '2026-11-20', 61814, 'ok', 32, 50, NULL),
(363, '2026-11-13', 61646, '2026-12-13', 62366, 'ok', 32, 51, NULL),
(364, '2026-11-13', 61646, '2026-12-13', 62366, 'ok', 32, 52, NULL),
(365, '2026-11-13', 45327, '2026-11-20', 45495, 'ok', 33, 40, NULL),
(366, '2026-11-13', 45327, '2026-11-20', 45495, 'ok', 33, 41, NULL),
(367, '2026-11-13', 45327, '2026-12-13', 46047, 'ok', 33, 42, NULL),
(368, '2026-11-13', 45327, '2026-11-20', 45495, 'ok', 33, 43, NULL),
(369, '2026-11-13', 45327, '2026-12-13', 46047, 'ok', 33, 44, NULL),
(370, '2026-11-13', 45327, '2026-11-20', 45495, 'ok', 33, 45, NULL),
(371, '2026-11-13', 45327, '2026-12-13', 46047, 'ok', 33, 46, NULL),
(372, '2026-11-13', 45327, '2027-02-11', 47487, 'ok', 33, 47, NULL),
(373, '2026-11-13', 45327, '2026-12-13', 46047, 'ok', 33, 48, NULL),
(374, '2026-11-13', 45327, '2026-12-13', 46047, 'ok', 33, 49, NULL),
(375, '2026-11-13', 45327, '2026-11-20', 45495, 'ok', 33, 50, NULL),
(376, '2026-11-13', 45327, '2026-12-13', 46047, 'ok', 33, 51, NULL),
(377, '2026-11-13', 45327, '2026-12-13', 46047, 'ok', 33, 52, NULL),
(378, '2026-11-13', 41750, '2026-11-20', 41918, 'ok', 34, 40, NULL),
(379, '2026-11-13', 41750, '2026-11-20', 41918, 'ok', 34, 41, NULL),
(380, '2026-11-13', 41750, '2026-12-13', 42470, 'ok', 34, 42, NULL),
(381, '2026-11-13', 41750, '2026-11-20', 41918, 'ok', 34, 43, NULL),
(382, '2026-11-13', 41750, '2026-12-13', 42470, 'ok', 34, 44, NULL),
(383, '2026-11-13', 41750, '2026-11-20', 41918, 'ok', 34, 45, NULL),
(384, '2026-11-13', 41750, '2026-12-13', 42470, 'ok', 34, 46, NULL),
(385, '2026-11-13', 41750, '2027-02-11', 43910, 'ok', 34, 47, NULL),
(386, '2026-11-13', 41750, '2026-12-13', 42470, 'ok', 34, 48, NULL),
(387, '2026-11-13', 41750, '2026-12-13', 42470, 'ok', 34, 49, NULL),
(388, '2026-11-13', 41750, '2026-11-20', 41918, 'ok', 34, 50, NULL),
(389, '2026-11-13', 41750, '2026-12-13', 42470, 'ok', 34, 51, NULL),
(390, '2026-11-13', 41750, '2026-12-13', 42470, 'ok', 34, 52, NULL),
(391, '2026-11-13', 55287, '2026-11-20', 55455, 'ok', 35, 40, NULL),
(392, '2026-11-13', 55287, '2026-11-20', 55455, 'ok', 35, 41, NULL),
(393, '2026-11-13', 55287, '2026-12-13', 56007, 'ok', 35, 42, NULL),
(394, '2026-11-13', 55287, '2026-11-20', 55455, 'ok', 35, 43, NULL),
(395, '2026-11-13', 55287, '2026-12-13', 56007, 'ok', 35, 44, NULL),
(396, '2026-11-13', 55287, '2026-11-20', 55455, 'ok', 35, 45, NULL),
(397, '2026-11-13', 55287, '2026-12-13', 56007, 'ok', 35, 46, NULL),
(398, '2026-11-13', 55287, '2027-02-11', 57447, 'ok', 35, 47, NULL),
(399, '2026-11-13', 55287, '2026-12-13', 56007, 'ok', 35, 48, NULL),
(400, '2026-11-13', 55287, '2026-12-13', 56007, 'ok', 35, 49, NULL),
(401, '2026-11-13', 55287, '2026-11-20', 55455, 'ok', 35, 50, NULL),
(402, '2026-11-13', 55287, '2026-12-13', 56007, 'ok', 35, 51, NULL),
(403, '2026-11-13', 55287, '2026-12-13', 56007, 'ok', 35, 52, NULL),
(404, '2026-11-13', 43406, '2026-11-20', 43574, 'ok', 36, 40, NULL),
(405, '2026-11-13', 43406, '2026-11-20', 43574, 'ok', 36, 41, NULL),
(406, '2026-11-13', 43406, '2026-12-13', 44126, 'ok', 36, 42, NULL),
(407, '2026-11-13', 43406, '2026-11-20', 43574, 'ok', 36, 43, NULL),
(408, '2026-11-13', 43406, '2026-12-13', 44126, 'ok', 36, 44, NULL),
(409, '2026-11-13', 43406, '2026-11-20', 43574, 'ok', 36, 45, NULL),
(410, '2026-11-13', 43406, '2026-12-13', 44126, 'ok', 36, 46, NULL),
(411, '2026-11-13', 43406, '2027-02-11', 45566, 'ok', 36, 47, NULL),
(412, '2026-11-13', 43406, '2026-12-13', 44126, 'ok', 36, 48, NULL),
(413, '2026-11-13', 43406, '2026-12-13', 44126, 'ok', 36, 49, NULL),
(414, '2026-11-13', 43406, '2026-11-20', 43574, 'ok', 36, 50, NULL),
(415, '2026-11-13', 43406, '2026-12-13', 44126, 'ok', 36, 51, NULL),
(416, '2026-11-13', 43406, '2026-12-13', 44126, 'ok', 36, 52, NULL),
(417, '2026-11-13', 24926, '2026-11-20', 25094, 'ok', 37, 53, NULL),
(418, '2026-11-13', 24926, '2026-11-20', 25094, 'ok', 37, 54, NULL),
(419, '2026-11-13', 24926, '2026-12-13', 25646, 'ok', 37, 55, NULL),
(420, '2026-11-13', 24926, '2026-11-20', 25094, 'ok', 37, 56, NULL),
(421, '2026-11-13', 24926, '2026-12-13', 25646, 'ok', 37, 57, NULL),
(422, '2026-11-13', 24926, '2026-11-20', 25094, 'ok', 37, 58, NULL),
(423, '2026-11-13', 24926, '2026-12-13', 25646, 'ok', 37, 59, NULL),
(424, '2026-11-13', 24926, '2027-02-11', 27086, 'ok', 37, 60, NULL),
(425, '2026-11-13', 24926, '2026-12-13', 25646, 'ok', 37, 61, NULL),
(426, '2026-11-13', 24926, '2026-12-13', 25646, 'ok', 37, 62, NULL),
(427, '2026-11-13', 24926, '2026-11-20', 25094, 'ok', 37, 63, NULL),
(428, '2026-11-13', 24926, '2026-12-13', 25646, 'ok', 37, 64, NULL),
(429, '2026-11-13', 24926, '2026-12-13', 25646, 'ok', 37, 65, NULL),
(430, '2026-11-13', 24926, '2026-11-20', 25094, 'ok', 37, 66, NULL),
(431, '2026-11-13', 24134, '2026-11-20', 24302, 'ok', 38, 53, NULL),
(432, '2026-11-13', 24134, '2026-11-20', 24302, 'ok', 38, 54, NULL),
(433, '2026-11-13', 24134, '2026-12-13', 24854, 'ok', 38, 55, NULL),
(434, '2026-11-13', 24134, '2026-11-20', 24302, 'ok', 38, 56, NULL),
(435, '2026-11-13', 24134, '2026-12-13', 24854, 'ok', 38, 57, NULL),
(436, '2026-11-13', 24134, '2026-11-20', 24302, 'ok', 38, 58, NULL),
(437, '2026-11-13', 24134, '2026-12-13', 24854, 'ok', 38, 59, NULL),
(438, '2026-11-13', 24134, '2027-02-11', 26294, 'ok', 38, 60, NULL),
(439, '2026-11-13', 24134, '2026-12-13', 24854, 'ok', 38, 61, NULL),
(440, '2026-11-13', 24134, '2026-12-13', 24854, 'ok', 38, 62, NULL),
(441, '2026-11-13', 24134, '2026-11-20', 24302, 'ok', 38, 63, NULL),
(442, '2026-11-13', 24134, '2026-12-13', 24854, 'ok', 38, 64, NULL),
(443, '2026-11-13', 24134, '2026-12-13', 24854, 'ok', 38, 65, NULL),
(444, '2026-11-13', 24134, '2026-11-20', 24302, 'ok', 38, 66, NULL),
(445, '2026-11-13', 23366, '2026-11-20', 23534, 'ok', 39, 53, NULL),
(446, '2026-11-13', 23366, '2026-11-20', 23534, 'ok', 39, 54, NULL),
(447, '2026-11-13', 23366, '2026-12-13', 24086, 'ok', 39, 55, NULL),
(448, '2026-11-13', 23366, '2026-11-20', 23534, 'ok', 39, 56, NULL),
(449, '2026-11-13', 23366, '2026-12-13', 24086, 'ok', 39, 57, NULL),
(450, '2026-11-13', 23366, '2026-11-20', 23534, 'ok', 39, 58, NULL),
(451, '2026-11-13', 23366, '2026-12-13', 24086, 'ok', 39, 59, NULL),
(452, '2026-11-13', 23366, '2027-02-11', 25526, 'ok', 39, 60, NULL),
(453, '2026-11-13', 23366, '2026-12-13', 24086, 'ok', 39, 61, NULL),
(454, '2026-11-13', 23366, '2026-12-13', 24086, 'ok', 39, 62, NULL),
(455, '2026-11-13', 23366, '2026-11-20', 23534, 'ok', 39, 63, NULL),
(456, '2026-11-13', 23366, '2026-12-13', 24086, 'ok', 39, 64, NULL),
(457, '2026-11-13', 23366, '2026-12-13', 24086, 'ok', 39, 65, NULL),
(458, '2026-11-13', 23366, '2026-11-20', 23534, 'ok', 39, 66, NULL),
(459, '2026-11-13', 24686, '2026-11-20', 24854, 'ok', 40, 67, NULL),
(460, '2026-11-13', 24686, '2026-11-20', 24854, 'ok', 40, 68, NULL),
(461, '2026-11-13', 24686, '2026-12-13', 25406, 'ok', 40, 69, NULL),
(462, '2026-11-13', 24686, '2026-11-20', 24854, 'ok', 40, 70, NULL),
(463, '2026-11-13', 24686, '2026-12-13', 25406, 'ok', 40, 71, NULL),
(464, '2026-11-13', 24686, '2026-11-20', 24854, 'ok', 40, 72, NULL),
(465, '2026-11-13', 24686, '2026-12-13', 25406, 'ok', 40, 73, NULL),
(466, '2026-11-13', 24686, '2027-02-11', 26846, 'ok', 40, 74, NULL),
(467, '2026-11-13', 24686, '2026-12-13', 25406, 'ok', 40, 75, NULL),
(468, '2026-11-13', 24686, '2026-12-13', 25406, 'ok', 40, 76, NULL),
(469, '2026-11-13', 24686, '2026-11-20', 24854, 'ok', 40, 77, NULL),
(470, '2026-11-13', 24686, '2026-12-13', 25406, 'ok', 40, 78, NULL),
(471, '2026-11-13', 24686, '2026-12-13', 25406, 'ok', 40, 79, NULL),
(472, '2026-11-13', 24686, '2026-11-20', 24854, 'ok', 40, 80, NULL),
(473, '2026-11-13', 23942, '2026-11-20', 24110, 'ok', 41, 67, NULL),
(474, '2026-11-13', 23942, '2026-11-20', 24110, 'ok', 41, 68, NULL),
(475, '2026-11-13', 23942, '2026-12-13', 24662, 'ok', 41, 69, NULL),
(476, '2026-11-13', 23942, '2026-11-20', 24110, 'ok', 41, 70, NULL),
(477, '2026-11-13', 23942, '2026-12-13', 24662, 'ok', 41, 71, NULL),
(478, '2026-11-13', 23942, '2026-11-20', 24110, 'ok', 41, 72, NULL),
(479, '2026-11-13', 23942, '2026-12-13', 24662, 'ok', 41, 73, NULL),
(480, '2026-11-13', 23942, '2027-02-11', 26102, 'ok', 41, 74, NULL),
(481, '2026-11-13', 23942, '2026-12-13', 24662, 'ok', 41, 75, NULL),
(482, '2026-11-13', 23942, '2026-12-13', 24662, 'ok', 41, 76, NULL),
(483, '2026-11-13', 23942, '2026-11-20', 24110, 'ok', 41, 77, NULL),
(484, '2026-11-13', 23942, '2026-12-13', 24662, 'ok', 41, 78, NULL),
(485, '2026-11-13', 23942, '2026-12-13', 24662, 'ok', 41, 79, NULL),
(486, '2026-11-13', 23942, '2026-11-20', 24110, 'ok', 41, 80, NULL),
(487, '2026-11-13', 23246, '2026-11-20', 23414, 'ok', 42, 67, NULL),
(488, '2026-11-13', 23246, '2026-11-20', 23414, 'ok', 42, 68, NULL),
(489, '2026-11-13', 23246, '2026-12-13', 23966, 'ok', 42, 69, NULL),
(490, '2026-11-13', 23246, '2026-11-20', 23414, 'ok', 42, 70, NULL),
(491, '2026-11-13', 23246, '2026-12-13', 23966, 'ok', 42, 71, NULL),
(492, '2026-11-13', 23246, '2026-11-20', 23414, 'ok', 42, 72, NULL),
(493, '2026-11-13', 23246, '2026-12-13', 23966, 'ok', 42, 73, NULL),
(494, '2026-11-13', 23246, '2027-02-11', 25406, 'ok', 42, 74, NULL),
(495, '2026-11-13', 23246, '2026-12-13', 23966, 'ok', 42, 75, NULL),
(496, '2026-11-13', 23246, '2026-12-13', 23966, 'ok', 42, 76, NULL),
(497, '2026-11-13', 23246, '2026-11-20', 23414, 'ok', 42, 77, NULL),
(498, '2026-11-13', 23246, '2026-12-13', 23966, 'ok', 42, 78, NULL),
(499, '2026-11-13', 23246, '2026-12-13', 23966, 'ok', 42, 79, NULL),
(500, '2026-11-13', 23246, '2026-11-20', 23414, 'ok', 42, 80, NULL),
(501, '2026-11-13', 24566, '2026-11-20', 24734, 'ok', 43, 81, NULL),
(502, '2026-11-13', 24566, '2026-11-20', 24734, 'ok', 43, 82, NULL),
(503, '2026-11-13', 24566, '2026-12-13', 25286, 'ok', 43, 83, NULL),
(504, '2026-11-13', 24566, '2026-11-20', 24734, 'ok', 43, 84, NULL),
(505, '2026-11-13', 24566, '2026-12-13', 25286, 'ok', 43, 85, NULL),
(506, '2026-11-13', 24566, '2026-11-20', 24734, 'ok', 43, 86, NULL),
(507, '2026-11-13', 24566, '2026-12-13', 25286, 'ok', 43, 87, NULL),
(508, '2026-11-13', 24566, '2027-02-11', 26726, 'ok', 43, 88, NULL),
(509, '2026-11-13', 24566, '2026-12-13', 25286, 'ok', 43, 89, NULL),
(510, '2026-11-13', 24566, '2026-12-13', 25286, 'ok', 43, 90, NULL),
(511, '2026-11-13', 24566, '2026-11-20', 24734, 'ok', 43, 91, NULL),
(512, '2026-11-13', 24566, '2026-12-13', 25286, 'ok', 43, 92, NULL),
(513, '2026-11-13', 24566, '2026-12-13', 25286, 'ok', 43, 93, NULL),
(514, '2026-11-13', 24566, '2026-11-20', 24734, 'ok', 43, 94, NULL),
(515, '2026-11-13', 23822, '2026-11-20', 23990, 'ok', 44, 81, NULL),
(516, '2026-11-13', 23822, '2026-11-20', 23990, 'ok', 44, 82, NULL),
(517, '2026-11-13', 23822, '2026-12-13', 24542, 'ok', 44, 83, NULL),
(518, '2026-11-13', 23822, '2026-11-20', 23990, 'ok', 44, 84, NULL),
(519, '2026-11-13', 23822, '2026-12-13', 24542, 'ok', 44, 85, NULL),
(520, '2026-11-13', 23822, '2026-11-20', 23990, 'ok', 44, 86, NULL),
(521, '2026-11-13', 23822, '2026-12-13', 24542, 'ok', 44, 87, NULL),
(522, '2026-11-13', 23822, '2027-02-11', 25982, 'ok', 44, 88, NULL),
(523, '2026-11-13', 23822, '2026-12-13', 24542, 'ok', 44, 89, NULL),
(524, '2026-11-13', 23822, '2026-12-13', 24542, 'ok', 44, 90, NULL),
(525, '2026-11-13', 23822, '2026-11-20', 23990, 'ok', 44, 91, NULL),
(526, '2026-11-13', 23822, '2026-12-13', 24542, 'ok', 44, 92, NULL),
(527, '2026-11-13', 23822, '2026-12-13', 24542, 'ok', 44, 93, NULL),
(528, '2026-11-13', 23822, '2026-11-20', 23990, 'ok', 44, 94, NULL),
(529, '2026-11-13', 23126, '2026-11-20', 23294, 'ok', 45, 81, NULL),
(530, '2026-11-13', 23126, '2026-11-20', 23294, 'ok', 45, 82, NULL),
(531, '2026-11-13', 23126, '2026-12-13', 23846, 'ok', 45, 83, NULL),
(532, '2026-11-13', 23126, '2026-11-20', 23294, 'ok', 45, 84, NULL),
(533, '2026-11-13', 23126, '2026-12-13', 23846, 'ok', 45, 85, NULL),
(534, '2026-11-13', 23126, '2026-11-20', 23294, 'ok', 45, 86, NULL),
(535, '2026-11-13', 23126, '2026-12-13', 23846, 'ok', 45, 87, NULL),
(536, '2026-11-13', 23126, '2027-02-11', 25286, 'ok', 45, 88, NULL),
(537, '2026-11-13', 23126, '2026-12-13', 23846, 'ok', 45, 89, NULL),
(538, '2026-11-13', 23126, '2026-12-13', 23846, 'ok', 45, 90, NULL),
(539, '2026-11-13', 23126, '2026-11-20', 23294, 'ok', 45, 91, NULL),
(540, '2026-11-13', 23126, '2026-12-13', 23846, 'ok', 45, 92, NULL),
(541, '2026-11-13', 23126, '2026-12-13', 23846, 'ok', 45, 93, NULL),
(542, '2026-11-13', 23126, '2026-11-20', 23294, 'ok', 45, 94, NULL),
(543, '2026-11-13', 24446, '2026-11-20', 24614, 'ok', 46, 95, NULL),
(544, '2026-11-13', 24446, '2026-11-20', 24614, 'ok', 46, 96, NULL),
(545, '2026-11-13', 24446, '2026-12-13', 25166, 'ok', 46, 97, NULL),
(546, '2026-11-13', 24446, '2026-11-20', 24614, 'ok', 46, 98, NULL),
(547, '2026-11-13', 24446, '2026-12-13', 25166, 'ok', 46, 99, NULL),
(548, '2026-11-13', 24446, '2026-11-20', 24614, 'ok', 46, 100, NULL),
(549, '2026-11-13', 24446, '2026-12-13', 25166, 'ok', 46, 101, NULL),
(550, '2026-11-13', 24446, '2027-02-11', 26606, 'ok', 46, 102, NULL),
(551, '2026-11-13', 24446, '2026-12-13', 25166, 'ok', 46, 103, NULL),
(552, '2026-11-13', 24446, '2026-12-13', 25166, 'ok', 46, 104, NULL),
(553, '2026-11-13', 24446, '2026-11-20', 24614, 'ok', 46, 105, NULL),
(554, '2026-11-13', 24446, '2026-12-13', 25166, 'ok', 46, 106, NULL),
(555, '2026-11-13', 24446, '2026-12-13', 25166, 'ok', 46, 107, NULL),
(556, '2026-11-13', 24446, '2026-11-20', 24614, 'ok', 46, 108, NULL),
(557, '2026-11-13', 24446, '2026-12-13', 25166, 'ok', 46, 109, NULL),
(558, '2026-11-13', 24446, '2027-02-11', 26606, 'ok', 46, 110, NULL),
(559, '2026-11-13', 23750, '2026-11-20', 23918, 'ok', 47, 95, NULL),
(560, '2026-11-13', 23750, '2026-11-20', 23918, 'ok', 47, 96, NULL),
(561, '2026-11-13', 23750, '2026-12-13', 24470, 'ok', 47, 97, NULL),
(562, '2026-11-13', 23750, '2026-11-20', 23918, 'ok', 47, 98, NULL),
(563, '2026-11-13', 23750, '2026-12-13', 24470, 'ok', 47, 99, NULL),
(564, '2026-11-13', 23750, '2026-11-20', 23918, 'ok', 47, 100, NULL),
(565, '2026-11-13', 23750, '2026-12-13', 24470, 'ok', 47, 101, NULL),
(566, '2026-11-13', 23750, '2027-02-11', 25910, 'ok', 47, 102, NULL),
(567, '2026-11-13', 23750, '2026-12-13', 24470, 'ok', 47, 103, NULL),
(568, '2026-11-13', 23750, '2026-12-13', 24470, 'ok', 47, 104, NULL),
(569, '2026-11-13', 23750, '2026-11-20', 23918, 'ok', 47, 105, NULL),
(570, '2026-11-13', 23750, '2026-12-13', 24470, 'ok', 47, 106, NULL),
(571, '2026-11-13', 23750, '2026-12-13', 24470, 'ok', 47, 107, NULL),
(572, '2026-11-13', 23750, '2026-11-20', 23918, 'ok', 47, 108, NULL),
(573, '2026-11-13', 23750, '2026-12-13', 24470, 'ok', 47, 109, NULL),
(574, '2026-11-13', 23750, '2027-02-11', 25910, 'ok', 47, 110, NULL),
(575, '2026-11-13', 25046, '2026-11-13', 25214, 'ok', 48, 111, NULL),
(576, '2026-11-13', 25046, '2026-11-13', 25214, 'ok', 48, 112, NULL),
(577, '2026-11-13', 25046, '2026-11-13', 25546, 'ok', 48, 113, NULL),
(578, '2026-11-13', 25046, '2026-11-13', 26046, 'ok', 48, 114, NULL),
(579, '2026-11-13', 25046, '2026-11-13', 27046, 'ok', 48, 115, NULL),
(580, '2026-11-13', 25046, '2026-11-13', 29046, 'ok', 48, 116, NULL),
(581, '2026-11-13', 24302, '2026-11-13', 24470, 'ok', 49, 111, NULL),
(582, '2026-11-13', 24302, '2026-11-13', 24470, 'ok', 49, 112, NULL),
(583, '2026-11-13', 24302, '2026-11-13', 24802, 'ok', 49, 113, NULL),
(584, '2026-11-13', 24302, '2026-11-13', 25302, 'ok', 49, 114, NULL),
(585, '2026-11-13', 24302, '2026-11-13', 26302, 'ok', 49, 115, NULL),
(586, '2026-11-13', 24302, '2026-11-13', 28302, 'ok', 49, 116, NULL),
(587, '2026-11-13', 24974, '2026-11-20', 25142, 'ok', 50, 117, NULL),
(588, '2026-11-13', 24974, '2026-11-13', 25974, 'ok', 50, 118, NULL),
(589, '2026-11-13', 24974, '2026-11-13', 25974, 'ok', 50, 119, NULL),
(590, '2026-11-13', 24974, '2026-11-13', 26974, 'ok', 50, 120, NULL),
(591, '2026-11-13', 24974, '2027-02-11', 27134, 'ok', 50, 121, NULL),
(592, '2026-11-13', 24974, '2026-11-13', 28974, 'ok', 50, 122, NULL),
(593, '2026-11-13', 24230, '2026-11-20', 24398, 'ok', 51, 117, NULL),
(594, '2026-11-13', 24230, '2026-11-13', 25230, 'ok', 51, 118, NULL),
(595, '2026-11-13', 24230, '2026-11-13', 25230, 'ok', 51, 119, NULL),
(596, '2026-11-13', 24230, '2026-11-13', 26230, 'ok', 51, 120, NULL),
(597, '2026-11-13', 24230, '2027-02-11', 26390, 'ok', 51, 121, NULL),
(598, '2026-11-13', 24230, '2026-11-13', 28230, 'ok', 51, 122, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_task`
--

CREATE TABLE `maintenance_task` (
  `id` int(11) NOT NULL,
  `action` longtext NOT NULL,
  `frequency_days` int(11) DEFAULT NULL,
  `frequency_hours` int(11) DEFAULT NULL,
  `procedure_checklist` longtext DEFAULT NULL,
  `module_id` int(11) DEFAULT NULL,
  `part_id` int(11) DEFAULT NULL,
  `data_status` varchar(20) NOT NULL,
  `machine_model_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `maintenance_task`
--

INSERT INTO `maintenance_task` (`id`, `action`, `frequency_days`, `frequency_hours`, `procedure_checklist`, `module_id`, `part_id`, `data_status`, `machine_model_id`) VALUES
(40, 'vérifier le bruit', 7, 168, NULL, 55, NULL, 'confirmed', 3),
(41, 'vérifier la température', 7, 168, NULL, 55, NULL, 'confirmed', 3),
(42, 'vérifier le montage des boulons', 30, 720, NULL, 55, NULL, 'confirmed', 3),
(43, 'vérifier l\'état', 7, 168, NULL, 56, NULL, 'confirmed', 3),
(44, 'vérifier la tension', 30, 720, NULL, 56, NULL, 'confirmed', 3),
(45, 'vérifier le bruit', 7, 168, NULL, 51, NULL, 'confirmed', 3),
(46, 'vérifier le montage des boulons', 30, 720, NULL, 51, NULL, 'confirmed', 3),
(47, 'graissage des roulements', 90, 2160, NULL, 51, NULL, 'confirmed', 3),
(48, 'contrôle général', 30, 720, NULL, 56, NULL, 'confirmed', 3),
(49, 'vérifier le montage des boulons', 30, 720, NULL, 56, NULL, 'confirmed', 3),
(50, 'vérifier le bruit', 7, 168, NULL, 57, NULL, 'confirmed', 3),
(51, 'vérifier le montage', 30, 720, NULL, 57, NULL, 'confirmed', 3),
(52, 'vérifier que les galets tournent librement', 30, 720, NULL, 57, NULL, 'confirmed', 3),
(53, 'vérifier le bruit', 7, 168, NULL, 24, NULL, 'demo', 4),
(54, 'vérifier la température', 7, 168, NULL, 24, NULL, 'demo', 4),
(55, 'vérifier le montage des boulons', 30, 720, NULL, 24, NULL, 'demo', 4),
(56, 'vérifier l\'état', 7, 168, NULL, 26, NULL, 'demo', 4),
(57, 'vérifier la tension', 30, 720, NULL, 26, NULL, 'demo', 4),
(58, 'vérifier le bruit', 7, 168, NULL, 22, NULL, 'demo', 4),
(59, 'vérifier les boulons', 30, 720, NULL, 22, NULL, 'demo', 4),
(60, 'graissage', 90, 2160, NULL, 22, NULL, 'demo', 4),
(61, 'contrôle général', 30, 720, NULL, 26, NULL, 'demo', 4),
(62, 'vérifier les boulons', 30, 720, NULL, 26, NULL, 'demo', 4),
(63, 'vérifier le bruit', 7, 168, NULL, 27, NULL, 'demo', 4),
(64, 'vérifier le montage', 30, 720, NULL, 27, NULL, 'demo', 4),
(65, 'vérifier que les galets tournent librement', 30, 720, NULL, 27, NULL, 'demo', 4),
(66, 'vérifier l’état et la fixation du porteur de charge', 7, 168, NULL, 28, NULL, 'demo', 4),
(67, 'vérifier le bruit', 7, 168, NULL, 31, NULL, 'demo', 5),
(68, 'vérifier la température', 7, 168, NULL, 31, NULL, 'demo', 5),
(69, 'vérifier le montage des boulons', 30, 720, NULL, 31, NULL, 'demo', 5),
(70, 'vérifier l\'état', 7, 168, NULL, 33, NULL, 'demo', 5),
(71, 'vérifier la tension', 30, 720, NULL, 33, NULL, 'demo', 5),
(72, 'vérifier le bruit', 7, 168, NULL, 29, NULL, 'demo', 5),
(73, 'vérifier les boulons', 30, 720, NULL, 29, NULL, 'demo', 5),
(74, 'graissage', 90, 2160, NULL, 29, NULL, 'demo', 5),
(75, 'contrôle général', 30, 720, NULL, 33, NULL, 'demo', 5),
(76, 'vérifier les boulons', 30, 720, NULL, 33, NULL, 'demo', 5),
(77, 'vérifier le bruit', 7, 168, NULL, 34, NULL, 'demo', 5),
(78, 'vérifier le montage', 30, 720, NULL, 34, NULL, 'demo', 5),
(79, 'vérifier que les galets tournent librement', 30, 720, NULL, 34, NULL, 'demo', 5),
(80, 'vérifier l’état et la fixation du porteur de charge', 7, 168, NULL, 35, NULL, 'demo', 5),
(81, 'vérifier le bruit', 7, 168, NULL, 38, NULL, 'demo', 6),
(82, 'vérifier la température', 7, 168, NULL, 38, NULL, 'demo', 6),
(83, 'vérifier le montage des boulons', 30, 720, NULL, 38, NULL, 'demo', 6),
(84, 'vérifier l\'état', 7, 168, NULL, 40, NULL, 'demo', 6),
(85, 'vérifier la tension', 30, 720, NULL, 40, NULL, 'demo', 6),
(86, 'vérifier le bruit', 7, 168, NULL, 36, NULL, 'demo', 6),
(87, 'vérifier les boulons', 30, 720, NULL, 36, NULL, 'demo', 6),
(88, 'graissage', 90, 2160, NULL, 36, NULL, 'demo', 6),
(89, 'contrôle général', 30, 720, NULL, 40, NULL, 'demo', 6),
(90, 'vérifier les boulons', 30, 720, NULL, 40, NULL, 'demo', 6),
(91, 'vérifier le bruit', 7, 168, NULL, 41, NULL, 'demo', 6),
(92, 'vérifier le montage', 30, 720, NULL, 41, NULL, 'demo', 6),
(93, 'vérifier que les galets tournent librement', 30, 720, NULL, 41, NULL, 'demo', 6),
(94, 'vérifier l’état et la fixation du porteur de charge', 7, 168, NULL, 42, NULL, 'demo', 6),
(95, 'vérifier le bruit', 7, 168, NULL, 45, NULL, 'demo', 7),
(96, 'vérifier la température', 7, 168, NULL, 45, NULL, 'demo', 7),
(97, 'vérifier le montage des boulons', 30, 720, NULL, 45, NULL, 'demo', 7),
(98, 'vérifier l\'état', 7, 168, NULL, 47, NULL, 'demo', 7),
(99, 'vérifier la tension', 30, 720, NULL, 47, NULL, 'demo', 7),
(100, 'vérifier le bruit', 7, 168, NULL, 43, NULL, 'demo', 7),
(101, 'vérifier les boulons', 30, 720, NULL, 43, NULL, 'demo', 7),
(102, 'graissage', 90, 2160, NULL, 43, NULL, 'demo', 7),
(103, 'contrôle général', 30, 720, NULL, 47, NULL, 'demo', 7),
(104, 'vérifier les boulons', 30, 720, NULL, 47, NULL, 'demo', 7),
(105, 'vérifier le bruit', 7, 168, NULL, 48, NULL, 'demo', 7),
(106, 'vérifier le montage', 30, 720, NULL, 48, NULL, 'demo', 7),
(107, 'vérifier que les galets tournent librement', 30, 720, NULL, 48, NULL, 'demo', 7),
(108, 'vérifier l’état et la fixation du porteur de charge', 7, 168, NULL, 49, NULL, 'demo', 7),
(109, 'vérifier le ventilateur', 30, 720, NULL, 50, NULL, 'demo', 7),
(110, 'étalonner la sonde de température', 90, 2160, NULL, 50, NULL, 'demo', 7),
(111, 'contrôle de la pression', NULL, 168, NULL, 58, NULL, 'demo', 8),
(112, 'vérifier la garde de sécurité', NULL, 168, NULL, 61, NULL, 'demo', 8),
(113, 'remplacer le filtre à huile', NULL, 500, NULL, 58, NULL, 'demo', 8),
(114, 'inspecter les joints du vérin', NULL, 1000, NULL, 59, NULL, 'demo', 8),
(115, 'remplacer l\'huile hydraulique', NULL, 2000, NULL, 58, NULL, 'demo', 8),
(116, 'remplacer le kit de joints du vérin', NULL, 4000, NULL, 59, NULL, 'demo', 8),
(117, 'contrôler la purge de condensat', 7, 168, NULL, 64, NULL, 'demo', 9),
(118, 'inspecter la courroie', NULL, 1000, NULL, 62, NULL, 'demo', 9),
(119, 'remplacer le filtre à air', NULL, 1000, NULL, 63, NULL, 'demo', 9),
(120, 'remplacer le filtre à huile', NULL, 2000, NULL, 63, NULL, 'demo', 9),
(121, 'tester la soupape de sécurité', 90, 2160, NULL, 64, NULL, 'demo', 9),
(122, 'remplacer le séparateur air-huile', NULL, 4000, NULL, 63, NULL, 'demo', 9);

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
-- Table structure for table `notification`
--

CREATE TABLE `notification` (
  `id` int(11) NOT NULL,
  `message` longtext NOT NULL,
  `type` varchar(50) NOT NULL,
  `created_at` datetime NOT NULL,
  `read_at` datetime DEFAULT NULL,
  `recipient_id` int(11) NOT NULL,
  `fault_id` int(11) DEFAULT NULL,
  `intervention_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `part`
--

CREATE TABLE `part` (
  `id` int(11) NOT NULL,
  `part_number` varchar(255) DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `quantity` int(11) DEFAULT NULL,
  `module_id` int(11) NOT NULL,
  `data_status` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `part`
--

INSERT INTO `part` (`id`, `part_number`, `description`, `quantity`, `module_id`, `data_status`) VALUES
(142, 'DEMO-HLS-CA1100-M1-01', 'carter', 1, 22, 'demo'),
(143, 'DEMO-HLS-CA1100-M1-02', 'moyeu', 1, 22, 'demo'),
(144, 'DEMO-HLS-CA1100-M1-03', 'disque supérieur', 1, 22, 'demo'),
(145, 'DEMO-HLS-CA1100-M1-04', 'disque inférieur', 1, 22, 'demo'),
(146, 'DEMO-HLS-CA1100-M1-05', 'roulement', 2, 22, 'demo'),
(147, 'DEMO-HLS-CA1100-M1-06', 'arbre', 1, 22, 'demo'),
(148, 'DEMO-HLS-CA1100-M2-01', 'carter', 1, 23, 'demo'),
(149, 'DEMO-HLS-CA1100-M2-02', 'moyeu', 1, 23, 'demo'),
(150, 'DEMO-HLS-CA1100-M2-03', 'disque supérieur', 1, 23, 'demo'),
(151, 'DEMO-HLS-CA1100-M2-04', 'disque inférieur', 1, 23, 'demo'),
(152, 'DEMO-HLS-CA1100-M2-05', 'roulement', 2, 23, 'demo'),
(153, 'DEMO-HLS-CA1100-M2-06', 'arbre', 1, 23, 'demo'),
(154, 'DEMO-HLS-CA1100-M3-01', 'moteur-réducteur', 1, 24, 'demo'),
(155, 'DEMO-HLS-CA1100-M3-02', 'courroie', 1, 24, 'demo'),
(156, 'DEMO-HLS-CA1100-M3-03', 'rail de tension', 2, 24, 'demo'),
(157, 'DEMO-HLS-CA1100-M3-04', 'coulisse de rail', 1, 24, 'demo'),
(158, 'DEMO-HLS-CA1100-M4-01', 'poulie', 1, 25, 'demo'),
(159, 'DEMO-HLS-CA1100-M4-02', 'roulement', 2, 25, 'demo'),
(160, 'DEMO-HLS-CA1100-M4-03', 'plaque', 2, 25, 'demo'),
(161, 'DEMO-HLS-CA1100-M4-04', 'arbre', 1, 25, 'demo'),
(162, 'DEMO-HLS-CA1100-M5-01', 'élément de traction', 1, 26, 'demo'),
(163, 'DEMO-HLS-CA1100-M5-02', 'rail', 1, 26, 'demo'),
(164, 'DEMO-HLS-CA1100-M5-03', 'support', 1, 26, 'demo'),
(165, 'DEMO-HLS-CA1100-M6-01', 'galet', 2, 27, 'demo'),
(166, 'DEMO-HLS-CA1100-M6-02', 'flasque', 2, 27, 'demo'),
(167, 'DEMO-HLS-CA1100-M6-03', 'axe', 1, 27, 'demo'),
(168, 'DEMO-HLS-CA1100-M7-33', 'support de bobine', 1, 28, 'demo'),
(169, 'DEMO-HLS-CA1100-M7-34', 'axe de bobine', 1, 28, 'demo'),
(170, 'DEMO-HLS-CA1100-M7-35', 'verrou de sécurité', 1, 28, 'demo'),
(171, 'DEMO-HLS-PXL-CA1100-M1-01', 'carter', 1, 29, 'demo'),
(172, 'DEMO-HLS-PXL-CA1100-M1-02', 'moyeu', 1, 29, 'demo'),
(173, 'DEMO-HLS-PXL-CA1100-M1-03', 'disque supérieur', 1, 29, 'demo'),
(174, 'DEMO-HLS-PXL-CA1100-M1-04', 'disque inférieur', 1, 29, 'demo'),
(175, 'DEMO-HLS-PXL-CA1100-M1-05', 'roulement', 2, 29, 'demo'),
(176, 'DEMO-HLS-PXL-CA1100-M1-06', 'arbre', 1, 29, 'demo'),
(177, 'DEMO-HLS-PXL-CA1100-M2-01', 'carter', 1, 30, 'demo'),
(178, 'DEMO-HLS-PXL-CA1100-M2-02', 'moyeu', 1, 30, 'demo'),
(179, 'DEMO-HLS-PXL-CA1100-M2-03', 'disque supérieur', 1, 30, 'demo'),
(180, 'DEMO-HLS-PXL-CA1100-M2-04', 'disque inférieur', 1, 30, 'demo'),
(181, 'DEMO-HLS-PXL-CA1100-M2-05', 'roulement', 2, 30, 'demo'),
(182, 'DEMO-HLS-PXL-CA1100-M2-06', 'arbre', 1, 30, 'demo'),
(183, 'DEMO-HLS-PXL-CA1100-M3-01', 'moteur-réducteur', 1, 31, 'demo'),
(184, 'DEMO-HLS-PXL-CA1100-M3-02', 'courroie', 1, 31, 'demo'),
(185, 'DEMO-HLS-PXL-CA1100-M3-03', 'rail de tension', 2, 31, 'demo'),
(186, 'DEMO-HLS-PXL-CA1100-M3-04', 'coulisse de rail', 1, 31, 'demo'),
(187, 'DEMO-HLS-PXL-CA1100-M4-01', 'poulie', 1, 32, 'demo'),
(188, 'DEMO-HLS-PXL-CA1100-M4-02', 'roulement', 2, 32, 'demo'),
(189, 'DEMO-HLS-PXL-CA1100-M4-03', 'plaque', 2, 32, 'demo'),
(190, 'DEMO-HLS-PXL-CA1100-M4-04', 'arbre', 1, 32, 'demo'),
(191, 'DEMO-HLS-PXL-CA1100-M5-01', 'élément de traction', 1, 33, 'demo'),
(192, 'DEMO-HLS-PXL-CA1100-M5-02', 'rail', 1, 33, 'demo'),
(193, 'DEMO-HLS-PXL-CA1100-M5-03', 'support', 1, 33, 'demo'),
(194, 'DEMO-HLS-PXL-CA1100-M6-01', 'galet', 2, 34, 'demo'),
(195, 'DEMO-HLS-PXL-CA1100-M6-02', 'flasque', 2, 34, 'demo'),
(196, 'DEMO-HLS-PXL-CA1100-M6-03', 'axe', 1, 34, 'demo'),
(197, 'DEMO-HLS-PXL-CA1100-M7-33', 'porte-faisceau', 1, 35, 'demo'),
(198, 'DEMO-HLS-PXL-CA1100-M7-34', 'traverse renforcée', 1, 35, 'demo'),
(199, 'DEMO-HLS-PXL-CA1100-M7-35', 'verrou de sécurité', 1, 35, 'demo'),
(200, 'DEMO-HLS-PXL-CA1100-01-M1-01', 'carter', 1, 36, 'demo'),
(201, 'DEMO-HLS-PXL-CA1100-01-M1-02', 'moyeu', 1, 36, 'demo'),
(202, 'DEMO-HLS-PXL-CA1100-01-M1-03', 'disque supérieur', 1, 36, 'demo'),
(203, 'DEMO-HLS-PXL-CA1100-01-M1-04', 'disque inférieur', 1, 36, 'demo'),
(204, 'DEMO-HLS-PXL-CA1100-01-M1-05', 'roulement', 2, 36, 'demo'),
(205, 'DEMO-HLS-PXL-CA1100-01-M1-06', 'arbre', 1, 36, 'demo'),
(206, 'DEMO-HLS-PXL-CA1100-01-M2-01', 'carter', 1, 37, 'demo'),
(207, 'DEMO-HLS-PXL-CA1100-01-M2-02', 'moyeu', 1, 37, 'demo'),
(208, 'DEMO-HLS-PXL-CA1100-01-M2-03', 'disque supérieur', 1, 37, 'demo'),
(209, 'DEMO-HLS-PXL-CA1100-01-M2-04', 'disque inférieur', 1, 37, 'demo'),
(210, 'DEMO-HLS-PXL-CA1100-01-M2-05', 'roulement', 2, 37, 'demo'),
(211, 'DEMO-HLS-PXL-CA1100-01-M2-06', 'arbre', 1, 37, 'demo'),
(212, 'DEMO-HLS-PXL-CA1100-01-M3-01', 'moteur-réducteur', 1, 38, 'demo'),
(213, 'DEMO-HLS-PXL-CA1100-01-M3-02', 'courroie', 1, 38, 'demo'),
(214, 'DEMO-HLS-PXL-CA1100-01-M3-03', 'rail de tension', 2, 38, 'demo'),
(215, 'DEMO-HLS-PXL-CA1100-01-M3-04', 'coulisse de rail', 1, 38, 'demo'),
(216, 'DEMO-HLS-PXL-CA1100-01-M4-01', 'poulie', 1, 39, 'demo'),
(217, 'DEMO-HLS-PXL-CA1100-01-M4-02', 'roulement', 2, 39, 'demo'),
(218, 'DEMO-HLS-PXL-CA1100-01-M4-03', 'plaque', 2, 39, 'demo'),
(219, 'DEMO-HLS-PXL-CA1100-01-M4-04', 'arbre', 1, 39, 'demo'),
(220, 'DEMO-HLS-PXL-CA1100-01-M5-01', 'élément de traction', 1, 40, 'demo'),
(221, 'DEMO-HLS-PXL-CA1100-01-M5-02', 'rail', 1, 40, 'demo'),
(222, 'DEMO-HLS-PXL-CA1100-01-M5-03', 'support', 1, 40, 'demo'),
(223, 'DEMO-HLS-PXL-CA1100-01-M6-01', 'galet', 2, 41, 'demo'),
(224, 'DEMO-HLS-PXL-CA1100-01-M6-02', 'flasque', 2, 41, 'demo'),
(225, 'DEMO-HLS-PXL-CA1100-01-M6-03', 'axe', 1, 41, 'demo'),
(226, 'DEMO-HLS-PXL-CA1100-01-M7-33', 'porte-caisse', 1, 42, 'demo'),
(227, 'DEMO-HLS-PXL-CA1100-01-M7-34', 'griffe de maintien', 2, 42, 'demo'),
(228, 'DEMO-PPXXL-CA1100-M1-01', 'carter', 1, 43, 'demo'),
(229, 'DEMO-PPXXL-CA1100-M1-02', 'moyeu', 1, 43, 'demo'),
(230, 'DEMO-PPXXL-CA1100-M1-03', 'disque supérieur', 1, 43, 'demo'),
(231, 'DEMO-PPXXL-CA1100-M1-04', 'disque inférieur', 1, 43, 'demo'),
(232, 'DEMO-PPXXL-CA1100-M1-05', 'roulement', 2, 43, 'demo'),
(233, 'DEMO-PPXXL-CA1100-M1-06', 'arbre', 1, 43, 'demo'),
(234, 'DEMO-PPXXL-CA1100-M2-01', 'carter', 1, 44, 'demo'),
(235, 'DEMO-PPXXL-CA1100-M2-02', 'moyeu', 1, 44, 'demo'),
(236, 'DEMO-PPXXL-CA1100-M2-03', 'disque supérieur', 1, 44, 'demo'),
(237, 'DEMO-PPXXL-CA1100-M2-04', 'disque inférieur', 1, 44, 'demo'),
(238, 'DEMO-PPXXL-CA1100-M2-05', 'roulement', 2, 44, 'demo'),
(239, 'DEMO-PPXXL-CA1100-M2-06', 'arbre', 1, 44, 'demo'),
(240, 'DEMO-PPXXL-CA1100-M3-01', 'moteur-réducteur', 1, 45, 'demo'),
(241, 'DEMO-PPXXL-CA1100-M3-02', 'courroie', 1, 45, 'demo'),
(242, 'DEMO-PPXXL-CA1100-M3-03', 'rail de tension', 2, 45, 'demo'),
(243, 'DEMO-PPXXL-CA1100-M3-04', 'coulisse de rail', 1, 45, 'demo'),
(244, 'DEMO-PPXXL-CA1100-M4-01', 'poulie', 1, 46, 'demo'),
(245, 'DEMO-PPXXL-CA1100-M4-02', 'roulement', 2, 46, 'demo'),
(246, 'DEMO-PPXXL-CA1100-M4-03', 'plaque', 2, 46, 'demo'),
(247, 'DEMO-PPXXL-CA1100-M4-04', 'arbre', 1, 46, 'demo'),
(248, 'DEMO-PPXXL-CA1100-M5-01', 'élément de traction', 1, 47, 'demo'),
(249, 'DEMO-PPXXL-CA1100-M5-02', 'rail', 1, 47, 'demo'),
(250, 'DEMO-PPXXL-CA1100-M5-03', 'support', 1, 47, 'demo'),
(251, 'DEMO-PPXXL-CA1100-M6-01', 'galet', 2, 48, 'demo'),
(252, 'DEMO-PPXXL-CA1100-M6-02', 'flasque', 2, 48, 'demo'),
(253, 'DEMO-PPXXL-CA1100-M6-03', 'axe', 1, 48, 'demo'),
(254, 'DEMO-PPXXL-CA1100-M7-33', 'palonnier de peinture', 1, 49, 'demo'),
(255, 'DEMO-PPXXL-CA1100-M7-34', 'crochet résistant à la chaleur', 1, 49, 'demo'),
(256, 'DEMO-PPXXL-CA1100-M8-01', 'ventilateur', 1, 50, 'demo'),
(257, 'DEMO-PPXXL-CA1100-M8-02', 'sonde de température', 1, 50, 'demo'),
(258, 'DEMO-PPXXL-CA1100-M8-03', 'thermostat', 1, 50, 'demo'),
(259, 'GR-180-100 1100', 'housing', 1, 51, 'confirmed'),
(260, 'TF-108-100 1100', 'part', 1, 51, 'confirmed'),
(261, 'PL-600-100 1100', 'part', 1, 51, 'confirmed'),
(262, 'TF-203-100 1100', 'part', 1, 51, 'confirmed'),
(263, 'PL-600-317 1100', 'part', 1, 51, 'confirmed'),
(264, 'DIN 628 SKF - SKF 7206 BE', 'bearing', 2, 51, 'confirmed'),
(265, 'TF-103-100 1100', 'part', 1, 51, 'confirmed'),
(266, 'T2001', 'part', 1, 51, 'confirmed'),
(267, 'GR-90-100 1100', 'housing', 1, 52, 'confirmed'),
(268, 'TF-108-100 1100', 'part', 1, 52, 'confirmed'),
(269, 'PL-450-100 1100', 'part', 1, 52, 'confirmed'),
(270, 'TF-203-100 1100', 'part', 1, 52, 'confirmed'),
(271, 'PL-450-308 1100', 'part', 1, 52, 'confirmed'),
(272, 'DIN 628 SKF - SKF 7206 BE', 'bearing', 2, 52, 'confirmed'),
(273, 'TF-103-100 1100', 'part', 1, 52, 'confirmed'),
(274, 'T1001', 'part', 1, 52, 'confirmed'),
(275, 'GM-800 01 1100', 'module poulie', 1, 53, 'confirmed'),
(276, 'RT-300 1100', 'rail de tension', 2, 53, 'confirmed'),
(277, 'V-Belt 1800', 'belt', 1, 53, 'confirmed'),
(278, 'TK-550 120 01', 'coulisse de rail', 1, 53, 'confirmed'),
(279, 'GM-700 02 1100', 'module support moteur', 1, 53, 'confirmed'),
(280, 'GM-800-100 1100', 'part', 1, 54, 'confirmed'),
(281, 'TF-108-100 1100', 'part', 1, 54, 'confirmed'),
(282, 'PL-600-100 1100', 'part', 1, 54, 'confirmed'),
(283, 'TF-203-100 1100', 'part', 1, 54, 'confirmed'),
(284, 'DIN 628 SKF - SKF 7206 BE', 'bearing', 2, 54, 'confirmed'),
(285, 'T3001', 'part', 1, 54, 'confirmed'),
(286, 'PL-600-317 1100', 'part', 1, 54, 'confirmed'),
(287, 'PR 450 B17 11 40 001', 'pulley', 1, 54, 'confirmed'),
(288, 'TF-213-100 1100', 'part', 1, 54, 'confirmed'),
(289, 'GM-700-100-CM63', 'part', 1, 55, 'confirmed'),
(290, 'GM-700-200-CM63', 'part', 1, 55, 'confirmed'),
(291, 'TF-204-CM63', 'part', 1, 55, 'confirmed'),
(292, 'GM-700-400-UCF206', 'part', 1, 55, 'confirmed'),
(293, 'UCF206', 'bearing unit', 1, 55, 'confirmed'),
(294, 'GM-700-300-UCF206', 'part', 1, 55, 'confirmed'),
(295, 'MR-0.75-CM63-100', 'motor-reducer 0.75', 1, 55, 'confirmed'),
(296, 'TF-501-1100', 'shaft', 1, 55, 'confirmed'),
(297, 'PR 200 B17 11 40', 'pulley', 1, 55, 'confirmed'),
(298, 'T-200-1100', 'trolley = M7', NULL, 56, 'confirmed'),
(299, 'Corde D14', 'rope', NULL, 56, 'confirmed'),
(300, 'Croche 300', 'hook', NULL, 56, 'confirmed'),
(301, 'R-1952 1100', 'rail', NULL, 56, 'confirmed'),
(302, 'S-6001', 'support', NULL, 56, 'confirmed'),
(303, 'T-200-6200', 'wheel', 2, 57, 'confirmed'),
(304, 'T-200-337', 'plate', 2, 57, 'confirmed'),
(305, 'T-200-3210', 'part', 1, 57, 'confirmed'),
(306, 'DEMO-HP-2000X-M1-01', 'pompe', 1, 58, 'demo'),
(307, 'DEMO-HP-2000X-M1-02', 'réservoir', 1, 58, 'demo'),
(308, 'DEMO-HP-2000X-M1-03', 'filtre à huile', 1, 58, 'demo'),
(309, 'DEMO-HP-2000X-M1-04', 'joint de piston', 1, 58, 'demo'),
(310, 'DEMO-HP-2000X-M2-01', 'vérin', 1, 59, 'demo'),
(311, 'DEMO-HP-2000X-M2-02', 'kit de joints', 1, 59, 'demo'),
(312, 'DEMO-HP-2000X-M2-03', 'tige', 1, 59, 'demo'),
(313, 'DEMO-HP-2000X-M3-01', 'contacteur', 2, 60, 'demo'),
(314, 'DEMO-HP-2000X-M3-02', 'relais thermique', 1, 60, 'demo'),
(315, 'DEMO-HP-2000X-M3-03', 'capteur de pression', 1, 60, 'demo'),
(316, 'DEMO-HP-2000X-M4-01', 'colonne', 4, 61, 'demo'),
(317, 'DEMO-HP-2000X-M4-02', 'plateau', 1, 61, 'demo'),
(318, 'DEMO-HP-2000X-M4-03', 'garde de sécurité', 1, 61, 'demo'),
(319, 'DEMO-AirMax-300-M1-01', 'bloc à vis', 1, 62, 'demo'),
(320, 'DEMO-AirMax-300-M1-02', 'courroie', 1, 62, 'demo'),
(321, 'DEMO-AirMax-300-M1-03', 'roulement', 2, 62, 'demo'),
(322, 'DEMO-AirMax-300-M2-01', 'filtre à air', 1, 63, 'demo'),
(323, 'DEMO-AirMax-300-M2-02', 'filtre à huile', 1, 63, 'demo'),
(324, 'DEMO-AirMax-300-M2-03', 'séparateur air-huile', 1, 63, 'demo'),
(325, 'DEMO-AirMax-300-M3-01', 'réservoir', 1, 64, 'demo'),
(326, 'DEMO-AirMax-300-M3-02', 'purge automatique', 1, 64, 'demo'),
(327, 'DEMO-AirMax-300-M3-03', 'soupape de sécurité', 1, 64, 'demo'),
(328, 'DEMO-AirMax-300-M4-01', 'moteur', 1, 65, 'demo'),
(329, 'DEMO-AirMax-300-M4-02', 'pressostat', 1, 65, 'demo'),
(330, 'DEMO-AirMax-300-M4-03', 'contacteur', 1, 65, 'demo');

-- --------------------------------------------------------

--
-- Table structure for table `site`
--

CREATE TABLE `site` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `city` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `site`
--

INSERT INTO `site` (`id`, `name`, `city`) VALUES
(14, 'Sousse', 'Sousse'),
(15, 'Béja', 'Béja'),
(16, 'Jendouba', 'Jendouba'),
(17, 'Tunis', 'Tunis');

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `id` int(11) NOT NULL,
  `email` varchar(180) NOT NULL,
  `roles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`roles`)),
  `password` varchar(255) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `site_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`id`, `email`, `roles`, `password`, `full_name`, `site_id`) VALUES
(17, 'admin@roltechsystems.com', '[\"ROLE_ADMIN\"]', '$2y$13$gup3tPBQG1jekjh5UPU7fuSKCLUkcGoLfvY8JbL1T5r6ARVa14ib6', 'Ahmed Ben Salah', NULL),
(18, 'karim.technicien@roltechsystems.com', '[\"ROLE_TECHNICIEN\"]', '$2y$13$uQ32Y70CZttNxw.K81Cus.SQH1lnkMWn8vnCotO/ARcpLR0JC5jhi', 'Karim Trabelsi', 14),
(19, 'sami.technicien@roltechsystems.com', '[\"ROLE_TECHNICIEN\"]', '$2y$13$JrIqR9IOiiqs66r1E4eMiuIkx1AyHT2WytJEfX6DLdkeZ1n3U6vwi', 'Sami Gharbi', 15),
(20, 'malek.responsable@roltechsystems.com', '[\"ROLE_RESPONSABLE\"]', '$2y$13$j3Af61K6EH63kFzzv368te0hhr/87zvggJk0IS5G0uCQHNpxbRcoK', 'Malek Ben Sassi', 14);

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
  ADD KEY `IDX_9FD0DEA371CE806` (`reported_by_id`),
  ADD KEY `IDX_9FD0DEA3AFC2B591` (`module_id`),
  ADD KEY `IDX_9FD0DEA34CE34BEC` (`part_id`);

--
-- Indexes for table `intervention`
--
ALTER TABLE `intervention`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_D11814AB24171CD3` (`fault_id`),
  ADD KEY `IDX_D11814ABE6C5D496` (`technician_id`),
  ADD KEY `IDX_D11814ABA40BC2D5` (`schedule_id`),
  ADD KEY `IDX_D11814AB6E6F1246` (`assigned_by_id`);

--
-- Indexes for table `machine`
--
ALTER TABLE `machine`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_1505DF84F6BD1646` (`site_id`),
  ADD KEY `IDX_1505DF84B2127BFB` (`machine_model_id`);

--
-- Indexes for table `machine_model`
--
ALTER TABLE `machine_model`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_MACHINE_MODEL_CODE` (`code`);

--
-- Indexes for table `machine_module`
--
ALTER TABLE `machine_module`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_MACHINE_MODULE_MODEL_CODE` (`machine_model_id`,`code`),
  ADD KEY `IDX_C501896D727ACA70` (`parent_id`),
  ADD KEY `IDX_C501896DB2127BFB` (`machine_model_id`);

--
-- Indexes for table `maintenance_schedule`
--
ALTER TABLE `maintenance_schedule`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_444015D6F6B75B26` (`machine_id`),
  ADD KEY `IDX_444015D68DB60186` (`task_id`);

--
-- Indexes for table `maintenance_task`
--
ALTER TABLE `maintenance_task`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_9D6DBBE9AFC2B591` (`module_id`),
  ADD KEY `IDX_9D6DBBE94CE34BEC` (`part_id`),
  ADD KEY `IDX_9D6DBBE9B2127BFB` (`machine_model_id`);

--
-- Indexes for table `messenger_messages`
--
ALTER TABLE `messenger_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750` (`queue_name`,`available_at`,`delivered_at`,`id`);

--
-- Indexes for table `notification`
--
ALTER TABLE `notification`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_BF5476CAE92F8F78` (`recipient_id`),
  ADD KEY `IDX_BF5476CA24171CD3` (`fault_id`),
  ADD KEY `IDX_BF5476CA8EAE3863` (`intervention_id`);

--
-- Indexes for table `part`
--
ALTER TABLE `part`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_490F70C6AFC2B591` (`module_id`);

--
-- Indexes for table `site`
--
ALTER TABLE `site`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_IDENTIFIER_EMAIL` (`email`),
  ADD KEY `IDX_8D93D649F6BD1646` (`site_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `fault`
--
ALTER TABLE `fault`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `intervention`
--
ALTER TABLE `intervention`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `machine`
--
ALTER TABLE `machine`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `machine_model`
--
ALTER TABLE `machine_model`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `machine_module`
--
ALTER TABLE `machine_module`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT for table `maintenance_schedule`
--
ALTER TABLE `maintenance_schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=599;

--
-- AUTO_INCREMENT for table `maintenance_task`
--
ALTER TABLE `maintenance_task`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=123;

--
-- AUTO_INCREMENT for table `messenger_messages`
--
ALTER TABLE `messenger_messages`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notification`
--
ALTER TABLE `notification`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `part`
--
ALTER TABLE `part`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=331;

--
-- AUTO_INCREMENT for table `site`
--
ALTER TABLE `site`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `fault`
--
ALTER TABLE `fault`
  ADD CONSTRAINT `FK_9FD0DEA34CE34BEC` FOREIGN KEY (`part_id`) REFERENCES `part` (`id`),
  ADD CONSTRAINT `FK_9FD0DEA371CE806` FOREIGN KEY (`reported_by_id`) REFERENCES `user` (`id`),
  ADD CONSTRAINT `FK_9FD0DEA3AFC2B591` FOREIGN KEY (`module_id`) REFERENCES `machine_module` (`id`),
  ADD CONSTRAINT `FK_9FD0DEA3F6B75B26` FOREIGN KEY (`machine_id`) REFERENCES `machine` (`id`);

--
-- Constraints for table `intervention`
--
ALTER TABLE `intervention`
  ADD CONSTRAINT `FK_D11814AB24171CD3` FOREIGN KEY (`fault_id`) REFERENCES `fault` (`id`),
  ADD CONSTRAINT `FK_D11814AB6E6F1246` FOREIGN KEY (`assigned_by_id`) REFERENCES `user` (`id`),
  ADD CONSTRAINT `FK_D11814ABA40BC2D5` FOREIGN KEY (`schedule_id`) REFERENCES `maintenance_schedule` (`id`),
  ADD CONSTRAINT `FK_D11814ABE6C5D496` FOREIGN KEY (`technician_id`) REFERENCES `user` (`id`);

--
-- Constraints for table `machine`
--
ALTER TABLE `machine`
  ADD CONSTRAINT `FK_1505DF84B2127BFB` FOREIGN KEY (`machine_model_id`) REFERENCES `machine_model` (`id`),
  ADD CONSTRAINT `FK_1505DF84F6BD1646` FOREIGN KEY (`site_id`) REFERENCES `site` (`id`);

--
-- Constraints for table `machine_module`
--
ALTER TABLE `machine_module`
  ADD CONSTRAINT `FK_C501896D727ACA70` FOREIGN KEY (`parent_id`) REFERENCES `machine_module` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `FK_C501896DB2127BFB` FOREIGN KEY (`machine_model_id`) REFERENCES `machine_model` (`id`);

--
-- Constraints for table `maintenance_schedule`
--
ALTER TABLE `maintenance_schedule`
  ADD CONSTRAINT `FK_444015D68DB60186` FOREIGN KEY (`task_id`) REFERENCES `maintenance_task` (`id`),
  ADD CONSTRAINT `FK_444015D6F6B75B26` FOREIGN KEY (`machine_id`) REFERENCES `machine` (`id`);

--
-- Constraints for table `maintenance_task`
--
ALTER TABLE `maintenance_task`
  ADD CONSTRAINT `FK_9D6DBBE94CE34BEC` FOREIGN KEY (`part_id`) REFERENCES `part` (`id`),
  ADD CONSTRAINT `FK_9D6DBBE9AFC2B591` FOREIGN KEY (`module_id`) REFERENCES `machine_module` (`id`),
  ADD CONSTRAINT `FK_9D6DBBE9B2127BFB` FOREIGN KEY (`machine_model_id`) REFERENCES `machine_model` (`id`);

--
-- Constraints for table `notification`
--
ALTER TABLE `notification`
  ADD CONSTRAINT `FK_BF5476CA24171CD3` FOREIGN KEY (`fault_id`) REFERENCES `fault` (`id`),
  ADD CONSTRAINT `FK_BF5476CA8EAE3863` FOREIGN KEY (`intervention_id`) REFERENCES `intervention` (`id`),
  ADD CONSTRAINT `FK_BF5476CAE92F8F78` FOREIGN KEY (`recipient_id`) REFERENCES `user` (`id`);

--
-- Constraints for table `part`
--
ALTER TABLE `part`
  ADD CONSTRAINT `FK_490F70C6AFC2B591` FOREIGN KEY (`module_id`) REFERENCES `machine_module` (`id`);

--
-- Constraints for table `user`
--
ALTER TABLE `user`
  ADD CONSTRAINT `FK_8D93D649F6BD1646` FOREIGN KEY (`site_id`) REFERENCES `site` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
