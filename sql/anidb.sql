-- phpMyAdmin SQL Dump
-- version 4.5.1
-- http://www.phpmyadmin.net
--
-- Host: 127.0.0.1
-- Gegenereerd op: 10 apr 2016 om 23:06
-- Serverversie: 10.1.10-MariaDB
-- PHP-versie: 5.6.15

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `anidb`
--

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `config`
--

CREATE TABLE `config` (
  `name` varchar(50) NOT NULL,
  `val_int` int(11) DEFAULT NULL,
  `val_str` varchar(1024) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Gegevens worden geëxporteerd voor tabel `config`
--

INSERT INTO `config` (`name`, `val_int`, `val_str`) VALUES
('kisscookie', NULL, '__cfduid=dabea1e7372d727626f48cc18ada3ad641460238515; cf_clearance=c9598abccee90292902cc4d8ba1cc20a720fd825-1460315196-86400; idtz=141.105.11.28-570296570; ASP.NET_SessionId=2vd1w53obw3cofinyfutcyi3'),
('last_title_update', 0, NULL);

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `title`
--

CREATE TABLE `title` (
  `id` int(10) UNSIGNED NOT NULL,
  `lang` char(10) NOT NULL,
  `type` enum('primary','synonym','short','official') NOT NULL,
  `value` varchar(255) NOT NULL,
  `anime_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Gegevens worden geëxporteerd voor tabel `title`
--

INSERT INTO `title` (`id`, `lang`, `type`, `value`, `anime_id`) VALUES
(1, 'x-other', 'synonym', '❄', 4726);

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `urlcache`
--

CREATE TABLE `urlcache` (
  `anime_id` int(11) NOT NULL,
  `episode_num` int(11) NOT NULL,
  `resolution` int(11) NOT NULL,
  `value` varchar(1024) NOT NULL,
  `last_updated` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Indexen voor geëxporteerde tabellen
--

--
-- Indexen voor tabel `config`
--
ALTER TABLE `config`
  ADD PRIMARY KEY (`name`);

--
-- Indexen voor tabel `title`
--
ALTER TABLE `title`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lang_index` (`lang`),
  ADD KEY `type_index` (`type`),
  ADD KEY `value_index` (`value`);

--
-- Indexen voor tabel `urlcache`
--
ALTER TABLE `urlcache`
  ADD PRIMARY KEY (`anime_id`,`episode_num`,`resolution`);

--
-- AUTO_INCREMENT voor geëxporteerde tabellen
--

--
-- AUTO_INCREMENT voor een tabel `title`
--
ALTER TABLE `title`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=194031;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
