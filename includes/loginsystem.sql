-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Creato il: Apr 22, 2025 alle 23:26
-- Versione del server: 10.4.32-MariaDB
-- Versione PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `loginsystem`
--

-- --------------------------------------------------------

--
-- Struttura della tabella `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dump dei dati per la tabella `admin`
--

INSERT INTO `admin` (`id`, `username`, `password`) VALUES
(1, 'admin', '$2y$10$7f2zoplHeqacPS3H1Y29ieDhUFVKHI7wpcDjsPuQETdfzp7/sWE56'),
(2, 'picco', '$2y$10$fGGHL7X7choS7FOKZQ1JJ.EWjP4Pd9lxeVl.TpsOUWWAGlQaOpGhq');

-- --------------------------------------------------------

--
-- Struttura della tabella `auto`
--

CREATE TABLE `auto` (
  `id` int(11) NOT NULL,
  `id_socio` int(11) NOT NULL,
  `marca` varchar(100) NOT NULL,
  `modello` varchar(100) NOT NULL,
  `targa` varchar(20) NOT NULL,
  `numero_telaio` varchar(100) DEFAULT NULL,
  `colore` varchar(50) DEFAULT NULL,
  `cilindrata` int(11) DEFAULT NULL,
  `tipo_carburante` varchar(50) DEFAULT NULL,
  `anno_immatricolazione` year(4) DEFAULT NULL,
  `has_certificazione_asi` tinyint(1) NOT NULL DEFAULT 0,
  `targa_oro` tinyint(1) NOT NULL DEFAULT 0,
  `note` text DEFAULT NULL,
  `foto` text DEFAULT NULL,
  `data_inserimento` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `banned_emails`
--

CREATE TABLE `banned_emails` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `reason` text DEFAULT NULL,
  `banned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `banned_ips`
--

CREATE TABLE `banned_ips` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `reason` text DEFAULT NULL,
  `banned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `iscrizioni_manifestazioni`
--

CREATE TABLE `iscrizioni_manifestazioni` (
  `id` int(11) NOT NULL,
  `id_manifestazione` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `numero_partecipanti` int(11) NOT NULL DEFAULT 1,
  `data_iscrizione` timestamp NOT NULL DEFAULT current_timestamp(),
  `stato_pagamento` varchar(50) NOT NULL DEFAULT 'In attesa',
  `otp_codice` varchar(255) DEFAULT NULL,
  `otp_expires` timestamp NULL DEFAULT NULL,
  `otp_confirmed` tinyint(1) NOT NULL DEFAULT 0,
  `car_marca` varchar(100) NOT NULL,
  `car_modello` varchar(100) NOT NULL,
  `car_targa` varchar(20) NOT NULL,
  `id_auto_socio` int(11) DEFAULT NULL,
  `note_iscrizione` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `email_attempted` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempt_timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `success` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `manifestazioni`
--

CREATE TABLE `manifestazioni` (
  `id` int(11) NOT NULL,
  `titolo` varchar(255) NOT NULL,
  `data_inizio` datetime NOT NULL,
  `data_creazione` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_chiusura_iscrizioni` datetime NOT NULL,
  `programma` text DEFAULT NULL,
  `luogo_ritrovo` varchar(255) DEFAULT NULL,
  `quota_pranzo` decimal(10,2) DEFAULT NULL,
  `note` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `soci`
--

CREATE TABLE `soci` (
  `id` int(11) NOT NULL,
  `codice_fiscale` varchar(16) NOT NULL,
  `nome` varchar(100) DEFAULT NULL,
  `cognome` varchar(100) DEFAULT NULL,
  `tessera_club_numero` varchar(50) DEFAULT NULL,
  `tessera_club_scadenza` date DEFAULT NULL,
  `has_tessera_asi` tinyint(1) NOT NULL DEFAULT 0,
  `tessera_asi_numero` varchar(50) DEFAULT NULL,
  `data_iscrizione_club` timestamp NOT NULL DEFAULT current_timestamp(),
  `note` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `fname` varchar(255) DEFAULT NULL,
  `lname` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(300) DEFAULT NULL,
  `contactno` varchar(11) DEFAULT NULL,
  `reset_token_hash` varchar(255) DEFAULT NULL,
  `reset_token_expires_at` datetime DEFAULT NULL,
  `posting_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `activation_token` varchar(255) DEFAULT NULL,
  `activation_token_expires` timestamp NULL DEFAULT NULL,
  `reset_password_token` varchar(255) DEFAULT NULL,
  `reset_password_token_expires` timestamp NULL DEFAULT NULL,
  `id_socio` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dump dei dati per la tabella `users`
--

INSERT INTO `users` (`id`, `fname`, `lname`, `email`, `password`, `contactno`, `reset_token_hash`, `reset_token_expires_at`, `posting_date`, `is_active`, `activation_token`, `activation_token_expires`, `reset_password_token`, `reset_password_token_expires`, `id_socio`) VALUES
(5, 'Cristian', 'Masiero', 'gay@gay.it', '$2y$10$26qj.8RLqDKA6bNXYW/3peCDn0kuBfskjVMtWkxZt.KrOSlyjF3CS', '6666666661', NULL, NULL, '2025-04-22 09:36:08', 1, NULL, NULL, NULL, NULL, NULL),
(6, 'Mario', 'Rossi', 'nemeli9395@asaption.com', '$2y$10$sLEhR3EGEghfwgIs32siGObw/gQN3rTnyfqBGFOLu7bVFbh9NDrCK', '9999999999', NULL, NULL, '2025-04-22 12:50:20', 1, NULL, NULL, NULL, NULL, NULL),
(7, 'Alessandro', 'De Zuani', 'alessandrodezuani@gmail.com', '$2y$10$YHRk8fQ322eiIEreCbzQk.lt58FsParqr.ChO57dBkBLuWCMSv/ha', '1234567890', NULL, NULL, '2025-04-22 19:37:29', 0, NULL, NULL, NULL, NULL, NULL);

--
-- Indici per le tabelle scaricate
--

--
-- Indici per le tabelle `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `auto`
--
ALTER TABLE `auto`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_telaio` (`numero_telaio`),
  ADD KEY `idx_targa` (`targa`),
  ADD KEY `idx_id_socio` (`id_socio`);

--
-- Indici per le tabelle `banned_emails`
--
ALTER TABLE `banned_emails`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indici per le tabelle `banned_ips`
--
ALTER TABLE `banned_ips`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ip_address` (`ip_address`);

--
-- Indici per le tabelle `iscrizioni_manifestazioni`
--
ALTER TABLE `iscrizioni_manifestazioni`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_manifestazione_user` (`id_manifestazione`,`id_user`),
  ADD KEY `idx_id_manifestazione` (`id_manifestazione`),
  ADD KEY `idx_id_user` (`id_user`),
  ADD KEY `fk_iscrizioni_auto` (`id_auto_socio`);

--
-- Indici per le tabelle `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_ip_address` (`ip_address`);

--
-- Indici per le tabelle `manifestazioni`
--
ALTER TABLE `manifestazioni`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `soci`
--
ALTER TABLE `soci`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codice_fiscale` (`codice_fiscale`);

--
-- Indici per le tabelle `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_id_socio` (`id_socio`);

--
-- AUTO_INCREMENT per le tabelle scaricate
--

--
-- AUTO_INCREMENT per la tabella `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT per la tabella `auto`
--
ALTER TABLE `auto`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `banned_emails`
--
ALTER TABLE `banned_emails`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `banned_ips`
--
ALTER TABLE `banned_ips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `iscrizioni_manifestazioni`
--
ALTER TABLE `iscrizioni_manifestazioni`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `manifestazioni`
--
ALTER TABLE `manifestazioni`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `soci`
--
ALTER TABLE `soci`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Limiti per le tabelle scaricate
--

--
-- Limiti per la tabella `auto`
--
ALTER TABLE `auto`
  ADD CONSTRAINT `fk_auto_socio` FOREIGN KEY (`id_socio`) REFERENCES `soci` (`id`) ON UPDATE CASCADE;

--
-- Limiti per la tabella `iscrizioni_manifestazioni`
--
ALTER TABLE `iscrizioni_manifestazioni`
  ADD CONSTRAINT `fk_iscrizioni_auto_socio` FOREIGN KEY (`id_auto_socio`) REFERENCES `auto` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_iscrizioni_manifestazione` FOREIGN KEY (`id_manifestazione`) REFERENCES `manifestazioni` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_iscrizioni_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limiti per la tabella `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD CONSTRAINT `fk_login_attempts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Limiti per la tabella `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_user_socio` FOREIGN KEY (`id_socio`) REFERENCES `soci` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
