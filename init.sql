-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: db:3306
-- Creato il: Apr 28, 2025 alle 09:45
-- Versione del server: 10.6.21-MariaDB-ubu2004
-- Versione PHP: 8.2.8

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
  `foto1` varchar(255) DEFAULT NULL COMMENT 'Nome del file della prima foto',
  `foto2` varchar(255) DEFAULT NULL COMMENT 'Nome del file della seconda foto',
  `data_inserimento` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dump dei dati per la tabella `auto`
--

INSERT INTO `auto` (`id`, `id_socio`, `marca`, `modello`, `targa`, `numero_telaio`, `colore`, `cilindrata`, `tipo_carburante`, `anno_immatricolazione`, `has_certificazione_asi`, `targa_oro`, `note`, `foto1`, `foto2`, `data_inserimento`) VALUES
(1, 1, 'Fiat', '500 F', 'AA123BB', 'F110D-1234567', 'Blu', 499, 'Benzina', '1968', 0, 0, 'Auto restaurata nel 2022.', NULL, NULL, '2025-04-23 08:10:00'),
(2, 1, 'Alfa Romeo', 'Giulia 1600 Super', 'CC456DD', 'AR10526-8901234', 'Rosso Alfa', 1570, 'Benzina', '1972', 1, 0, 'Auto originale, in ottime condizioni.', NULL, NULL, '2025-04-23 08:11:00'),
(3, 2, 'Volkswagen', 'Maggiolino 1300', 'EE789FF', '1102587410', 'Giallo brillante', 1285, 'Benzina', '1970', 1, 1, 'Certificata ASI Targa Oro.', NULL, NULL, '2025-04-23 08:12:00'),
(4, 3, 'Lancia', 'Fulvia Coupé 1.3 S', 'GG012HH', '818630-001578', 'Verde Derby', 1298, 'Benzina', '1974', 1, 0, 'Auto utilizzata regolarmente per raduni. Revisionata di recente.', NULL, NULL, '2025-04-23 08:13:00'),
(5, 4, 'Porsche', '911 T 2.2 (E-Series)', 'XX342GH', '771638', 'Grigio Scuro', 2190, 'Benzina', '1971', 0, 0, 'Modello \"T\" con motore 2.2, importazione USA.', 'auto_4_1745831666_f1d8534e6708305a.jpg', 'auto_4_1745831666_1c7a5300f96c70f2.jpg', '2025-04-23 10:35:37');

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

--
-- Dump dei dati per la tabella `banned_emails`
--

INSERT INTO `banned_emails` (`id`, `email`, `reason`, `banned_at`) VALUES
(1, 'spam-user-123@example.com', 'Associata ad account di spam', '2025-04-19 14:00:00'),
(2, 'tempmail@disposable.net', 'Utilizzo di email temporanea non consentito', '2025-04-22 07:00:00'),
(3, 'problematic.user@mail.com', 'Violazione dei termini di servizio', '2025-04-23 08:50:00'),
(4, 'gigio@gigio.it', 'test01', '2025-04-23 09:44:41');

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

--
-- Dump dei dati per la tabella `banned_ips`
--

INSERT INTO `banned_ips` (`id`, `ip_address`, `reason`, `banned_at`) VALUES
(1, '192.168.1.105', 'Tentativi di accesso falliti ripetuti', '2025-04-20 12:30:00'),
(2, '203.0.113.55', 'Registrazioni massive sospette', '2025-04-21 06:15:00'),
(3, '2001:0db8:85a3:0000:0000:8a2e:0370:7334', 'Attività anomala rilevata', '2025-04-23 08:45:00');

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

--
-- Dump dei dati per la tabella `iscrizioni_manifestazioni`
--

INSERT INTO `iscrizioni_manifestazioni` (`id`, `id_manifestazione`, `id_user`, `numero_partecipanti`, `data_iscrizione`, `stato_pagamento`, `otp_codice`, `otp_expires`, `otp_confirmed`, `car_marca`, `car_modello`, `car_targa`, `id_auto_socio`, `note_iscrizione`) VALUES
(2, 1, 6, 1, '2025-04-23 08:29:53', 'In attesa', NULL, NULL, 1, 'Alfa Romeo', 'Giulia 1600 Super', 'CC456DD', 2, 'porvaTest1'),
(3, 2, 8, 1, '2025-04-26 10:34:51', 'In attesa', NULL, NULL, 1, 'Fiat', '127', 'AA567BB', NULL, 'Mi piacciono le fiat');

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

--
-- Dump dei dati per la tabella `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `user_id`, `email_attempted`, `ip_address`, `attempt_timestamp`, `success`) VALUES
(1, 7, 'alessandrodezuani@gmail.com', '127.0.0.1', '2025-04-23 08:52:16', 1),
(2, 7, 'alessandrodezuani@gmail.com', '127.0.0.1', '2025-04-23 10:05:20', 1),
(3, 6, 'nemeli9395@asaption.com', '127.0.0.1', '2025-04-23 10:21:37', 1),
(4, 7, 'alessandrodezuani@gmail.com', '127.0.0.1', '2025-04-23 10:23:46', 1),
(5, 7, 'alessandrodezuani@gmail.com', '127.0.0.1', '2025-04-23 10:24:32', 1),
(6, 7, 'alessandrodezuani@gmail.com', '172.18.0.1', '2025-04-26 10:29:13', 1),
(7, 8, 'andreasanto.morabito@allievi.itsdigitalacademy.com', '172.18.0.1', '2025-04-26 10:34:01', 1),
(8, 9, 'aledezuani@outlook.it', '172.18.0.1', '2025-04-26 10:53:50', 1),
(9, 9, 'aledezuani@outlook.it', '192.168.65.1', '2025-04-28 06:39:01', 1),
(10, 7, 'alessandrodezuani@gmail.com', '192.168.65.1', '2025-04-28 06:39:12', 1),
(11, 7, 'alessandrodezuani@gmail.com', '192.168.65.1', '2025-04-28 09:33:23', 1),
(12, NULL, 'alessandrodezuani@gmail.com', '192.168.65.1', '2025-04-28 09:34:00', 0);

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

--
-- Dump dei dati per la tabella `manifestazioni`
--

INSERT INTO `manifestazioni` (`id`, `titolo`, `data_inizio`, `data_creazione`, `data_chiusura_iscrizioni`, `programma`, `luogo_ritrovo`, `quota_pranzo`, `note`) VALUES
(1, 'Raduno di Primavera: Visita a Villa Contarini', '2025-06-08 09:00:00', '2025-04-10 08:00:00', '2025-06-03 18:00:00', 'Ore 9:00 Ritrovo e registrazione in Piazza del Popolo, Arqua Petrarca.\r\nOre 10:00 Partenza in sfilata per Villa Rossi.\r\nOre 11:00 Visita guidata a Villa Rossi e ai suoi giardini storici.\r\nOre 13:00 Pranzo conviviale presso il ristorante \"Il Giardino Antico\".\r\nOre 15:30 Saluti e termine manifestazione.', 'Piazzola sul Brenta (PD)', 20.00, 'Manifestazione aperta a tutti i soci e ai non soci con auto d\'epoca.'),
(2, 'Tour Panoramico dei Colli + Azienda Vinicola', '2025-07-06 09:30:00', '2025-04-20 09:30:00', '2025-07-01 18:00:00', 'Ore 9:30 Ritrovo e caffè di benvenuto presso la piazza centrale di Cinto Euganeo.\nOre 10:30 Partenza per un percorso panoramico tra i Colli Euganei.\nOre 12:00 Arrivo e visita guidata all\'azienda vinicola \"Cantina Storica\".\nOre 13:30 Degustazione e pranzo leggero in cantina.\nOre 15:00 Pomeriggio libero o rientro.', 'Piazza Principale, Cinto Euganeo (PD)', 40.00, 'Evento con percorso stradale misto, adatto a tutte le auto d\'epoca. Quota include degustazione vini.'),
(3, 'Mostra Scambio e Raduno Autunnale', '2025-09-21 10:00:00', '2025-04-23 07:00:00', '2025-09-16 18:00:00', 'Ore 10:00 Apertura area espositiva e mostra scambio in Fiera.\nOre 11:00 Arrivo e esposizione delle auto dei soci e partecipanti.\nOre 13:00 Pranzo a buffet presso l\'area ristorazione della fiera.\nOre 15:00 Premiazioni e saluti.', 'Area Fiera, Cittadella (PD)', 30.00, 'Grande evento con mostra scambio e raduno aperto a tutti i tipi di auto d\'epoca. Possibilità di esporre la propria auto prenotando uno spazio.');

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

--
-- Dump dei dati per la tabella `soci`
--

INSERT INTO `soci` (`id`, `codice_fiscale`, `nome`, `cognome`, `tessera_club_numero`, `tessera_club_scadenza`, `has_tessera_asi`, `tessera_asi_numero`, `data_iscrizione_club`, `note`) VALUES
(1, 'RDSMRA70A01H501Z', 'Mario', 'Rossi', '2025-001', '2025-12-31', 1, 'ASI-12345', '2025-04-23 08:00:00', 'Socio fondatore, collezionista Fiat e Alfa Romeo.'),
(2, 'BNCLRA75B41L219S', 'Laura', 'Bianchi', '2025-002', '2025-12-31', 0, NULL, '2025-04-23 08:05:00', 'Appassionata di auto tedesche, prima iscrizione.'),
(3, 'VRDGNN65C15G224U', 'Giovanni', 'Verdi', '2025-003', '2025-12-31', 1, 'ASI-67890', '2024-05-15 07:30:00', 'Socio attivo, partecipa spesso ai raduni con la sua Lancia.'),
(4, 'HZGRMF36S42E553E', 'Alessandro', 'De Zuani', NULL, NULL, 0, NULL, '2025-04-23 10:35:37', NULL);

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
  `new_password_hash` varchar(255) DEFAULT NULL,
  `contactno` varchar(11) DEFAULT NULL,
  `reset_token_hash` varchar(255) DEFAULT NULL,
  `reset_token_expires_at` datetime DEFAULT NULL,
  `change_password_token_hash` varchar(255) DEFAULT NULL,
  `change_password_token_expires_at` datetime DEFAULT NULL,
  `posting_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `activation_token` varchar(255) DEFAULT NULL,
  `activation_token_expires` timestamp NULL DEFAULT NULL,
  `reset_password_token` varchar(255) DEFAULT NULL,
  `reset_password_token_expires` timestamp NULL DEFAULT NULL,
  `id_socio` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dump dei dati per la tabella `users`
--

INSERT INTO `users` (`id`, `fname`, `lname`, `email`, `password`, `new_password_hash`, `contactno`, `reset_token_hash`, `reset_token_expires_at`, `change_password_token_hash`, `change_password_token_expires_at`, `posting_date`, `is_active`, `activation_token`, `activation_token_expires`, `reset_password_token`, `reset_password_token_expires`, `id_socio`) VALUES
(5, 'Cristian', 'Masiero', 'cristian@null.null', '$2y$10$26qj.8RLqDKA6bNXYW/3peCDn0kuBfskjVMtWkxZt.KrOSlyjF3CS', NULL, '6666666661', NULL, NULL, NULL, NULL, '2025-04-22 09:36:08', 0, NULL, NULL, NULL, NULL, NULL),
(6, 'Mario', 'Rossi', 'nemeli9395@asaption.com', '$2y$10$O7Al03K4lxCHDDcd/1FIvu4wNSp9z01w8swQZc6GBUZX2iFIWcHHK', NULL, '9999999999', NULL, NULL, NULL, NULL, '2025-04-22 12:50:20', 1, NULL, NULL, NULL, NULL, 1),
(7, 'Alessandro', 'De Zuani', 'alessandrodezuani@gmail.com', '$2y$10$YHRk8fQ322eiIEreCbzQk.lt58FsParqr.ChO57dBkBLuWCMSv/ha', NULL, '1234567890', NULL, NULL, NULL, NULL, '2025-04-22 19:37:29', 1, NULL, NULL, NULL, NULL, 4),
(8, 'Andrea', 'Morabito', 'andreasanto.morabito@allievi.itsdigitalacademy.com', '$2y$10$44MFnQrbnoTkdlQrsYMUG.tT8hxdljyYOYohlj/XY832tOMgeqdgS', NULL, '6666666666', NULL, NULL, NULL, NULL, '2025-04-26 10:32:25', 1, NULL, NULL, NULL, NULL, NULL),
(9, 'Ale', 'Dezzy', 'aledezuani@outlook.it', '$2y$10$azudXP78fRfRGB8KRpyB7Oe/SEYy9INtHa6uv01P0tdwmAYFwFuse', NULL, '7898778979', NULL, NULL, NULL, NULL, '2025-04-26 10:52:16', 1, NULL, NULL, NULL, NULL, NULL);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT per la tabella `banned_emails`
--
ALTER TABLE `banned_emails`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT per la tabella `banned_ips`
--
ALTER TABLE `banned_ips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT per la tabella `iscrizioni_manifestazioni`
--
ALTER TABLE `iscrizioni_manifestazioni`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT per la tabella `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT per la tabella `manifestazioni`
--
ALTER TABLE `manifestazioni`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT per la tabella `soci`
--
ALTER TABLE `soci`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT per la tabella `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

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
