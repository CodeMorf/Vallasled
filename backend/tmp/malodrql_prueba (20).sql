-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Oct 09, 2025 at 04:48 AM
-- Server version: 5.7.44-log
-- PHP Version: 8.3.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `malodrql_prueba`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`malodrql_prueba`@`localhost` PROCEDURE `sp_vendor_dashboard` (IN `p_proveedor_id` INT)   BEGIN
  -- vallas
  SELECT
    SUM(estado_valla='activa')        AS vallas_activas,
    SUM(estado_valla='inactiva')      AS vallas_inactivas,
    SUM(estado_valla='mantenimiento') AS vallas_mantenimiento,
    COUNT(*)                          AS vallas_total
  FROM vallas WHERE proveedor_id=p_proveedor_id;

  -- reservas
  SELECT
    SUM(CURDATE() BETWEEN r.fecha_inicio AND r.fecha_fin) AS reservas_activas,
    SUM(r.fecha_fin < CURDATE())                          AS reservas_vencidas
  FROM reservas r
  JOIN vallas v ON v.id=r.valla_id
  WHERE v.proveedor_id=p_proveedor_id;

  -- facturación
  SELECT
    COUNT(*) AS facturas_total,
    SUM(CASE WHEN f.estado='pagado' THEN f.monto ELSE 0 END)    AS total_pagado,
    SUM(CASE WHEN f.estado='pendiente' THEN f.monto ELSE 0 END) AS total_pendiente
  FROM facturas f
  JOIN vallas v ON v.id=f.valla_id
  WHERE v.proveedor_id=p_proveedor_id;

  -- licencias 30 días
  SELECT * FROM vw_vendor_licencias_30d WHERE proveedor_id=p_proveedor_id ORDER BY fecha_vencimiento ASC;

  -- mantenimientos próximos
  SELECT * FROM vw_vendor_mantenimientos_proximos WHERE proveedor_id=p_proveedor_id ORDER BY fecha_inicio ASC LIMIT 50;
END$$

CREATE DEFINER=`malodrql_prueba`@`localhost` PROCEDURE `sp_vendor_disponibilidad` (IN `p_proveedor_id` INT)   BEGIN
  SELECT r.valla_id, r.fecha_inicio AS inicio, r.fecha_fin AS fin, 'reserva' AS tipo
  FROM reservas r
  JOIN vallas v ON v.id=r.valla_id
  WHERE v.proveedor_id=p_proveedor_id AND r.estado IN ('confirmada','activa')
  UNION ALL
  SELECT p.valla_id, p.fecha_inicio, p.fecha_fin, p.motivo
  FROM periodos_no_disponibles p
  JOIN vallas v ON v.id=p.valla_id
  WHERE v.proveedor_id=p_proveedor_id
  ORDER BY valla_id, inicio;
END$$

--
-- Functions
--
CREATE DEFINER=`malodrql_prueba`@`localhost` FUNCTION `fn_vendor_comision_pct` (`p_valla_id` INT, `p_fecha` DATE) RETURNS DECIMAL(5,2)  BEGIN
  DECLARE v_pct DECIMAL(5,2);
  DECLARE v_prov INT;

  -- proveedor de la valla
  SELECT proveedor_id INTO v_prov FROM vallas WHERE id = p_valla_id LIMIT 1;

  -- regla específica por valla
  SELECT vc.comision_pct INTO v_pct
  FROM vendor_commissions vc
  WHERE vc.valla_id = p_valla_id
    AND vc.vigente_desde <= p_fecha
    AND (vc.vigente_hasta IS NULL OR vc.vigente_hasta >= p_fecha)
  ORDER BY vc.vigente_desde DESC
  LIMIT 1;

  IF v_pct IS NULL THEN
    -- regla por proveedor
    SELECT vc.comision_pct INTO v_pct
    FROM vendor_commissions vc
    WHERE vc.proveedor_id = v_prov AND vc.valla_id IS NULL
      AND vc.vigente_desde <= p_fecha
      AND (vc.vigente_hasta IS NULL OR vc.vigente_hasta >= p_fecha)
    ORDER BY vc.vigente_desde DESC
    LIMIT 1;
  END IF;

  IF v_pct IS NULL THEN
    -- fallback config_global.vendor_comision_pct
    SELECT CAST(valor AS DECIMAL(5,2)) INTO v_pct
    FROM config_global
    WHERE clave='vendor_comision_pct' AND activo=1
    ORDER BY id DESC
    LIMIT 1;
  END IF;

  IF v_pct IS NULL THEN
    SET v_pct = 10.00; -- último fallback
  END IF;

  RETURN v_pct;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `api_consumo`
--

CREATE TABLE `api_consumo` (
  `id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `fecha` datetime DEFAULT CURRENT_TIMESTAMP,
  `endpoint` varchar(128) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `api_tokens`
--

CREATE TABLE `api_tokens` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `correo` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `incremento_porcentaje` decimal(5,2) DEFAULT '0.00',
  `saldo` decimal(12,2) DEFAULT '0.00',
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  `fecha_vencimiento` datetime DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `api_tokens`
--

INSERT INTO `api_tokens` (`id`, `cliente_id`, `correo`, `token`, `incremento_porcentaje`, `saldo`, `fecha_creacion`, `fecha_vencimiento`, `activo`) VALUES
(1, 2, 'info@ship24go.com', '983fe885a582c3ab4eacd81251877b3cdaeba00a16b22b82905713c0f066e156', 0.00, 0.00, '2025-05-17 01:36:32', NULL, 0),
(2, 5, 'test@test.com', '1a39accc37aca80fdee17c2c763815e06706d0dd2ca798a483275f5dd2bebf4d', 0.00, 0.00, '2025-05-17 03:16:55', NULL, 1),
(3, 6, 'lorw@gmail.com', '90858da11ef33b1c484cdbddd8cd6375b1a29a1e0dbafc6049cdb37c6c695230', 0.00, 0.00, '2025-05-17 09:18:48', NULL, 1),
(6, 0, 'admin@vallasled.com', 'd0a2ef422dea4bd975c0a1bc916c20eed7d7ae4ca697e6669c81cfe3807cf14d', 0.00, 0.00, '2025-06-23 13:05:56', '2075-06-23 13:05:56', 1);

-- --------------------------------------------------------

--
-- Table structure for table `clientes`
--

CREATE TABLE `clientes` (
  `id` int(11) NOT NULL,
  `nombre` varchar(128) NOT NULL,
  `correo` varchar(128) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `comprobantes`
--

CREATE TABLE `comprobantes` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `tipo_ncf` varchar(5) DEFAULT NULL,
  `ncf` varchar(20) DEFAULT NULL,
  `monto` decimal(10,2) DEFAULT NULL,
  `fecha_emision` date DEFAULT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `estado` enum('generado','anulado') DEFAULT 'generado',
  `creado_por` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `rnc_cliente` varchar(15) DEFAULT NULL,
  `aplica_itbis` tinyint(1) DEFAULT '0',
  `factura_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `comprobantes_fiscales`
--

CREATE TABLE `comprobantes_fiscales` (
  `id` int(11) NOT NULL,
  `ncf` varchar(20) NOT NULL,
  `secuencia_id` int(11) NOT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `fecha_emision` date DEFAULT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `monto` decimal(12,2) DEFAULT NULL,
  `estado` varchar(25) DEFAULT 'emitido',
  `entregado_contador` tinyint(1) DEFAULT '0',
  `fecha_entrega` datetime DEFAULT NULL,
  `factura_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `configuracion`
--

CREATE TABLE `configuracion` (
  `id` int(11) NOT NULL,
  `clave` varchar(50) DEFAULT NULL,
  `valor` mediumtext
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `configuracion`
--

INSERT INTO `configuracion` (`id`, `clave`, `valor`) VALUES
(1, 'google_maps_api_key', 'AIzaSyAITLnhftd4NTBBDB4oujzoSbH69_oh6B8'),
(2, 'stripe_public_key', 'TU_CLAVE_PUBLICA_DE_PRUEBA'),
(3, 'stripe_private_key', 'TU_CLAVE_SECRETA_DE_PRUEBA'),
(4, 'debug_mode', '1'),
(6, 'site_title', 'Vallasled.com - Vallas Digitales y Publicidad Exterior en República Dominicana'),
(7, 'site_description', 'Conectamos tu marca con audiencias masivas en RD. Catálogo, mapa y disponibilidad.');

-- --------------------------------------------------------

--
-- Table structure for table `config_global`
--

CREATE TABLE `config_global` (
  `id` int(11) NOT NULL,
  `clave` varchar(64) DEFAULT NULL,
  `valor` text,
  `activo` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `config_global`
--

INSERT INTO `config_global` (`id`, `clave`, `valor`, `activo`) VALUES
(1, 'google_maps_api_key', 'AIzaSyCA6PtNzZRIvgY07c06H8RebWE5r0nmhCw', 1),
(2, 'stripe_public_key', 'pk_test_51NHXyALM97O7CJZacVsu9jRBXzxOUkWp4wTIwKfxpU56r1TILpJ4H4yl7CbD2ITpy2TycxMarxUdbZZxA0isTFtM00xEs8dfHw', 1),
(3, 'stripe_private_key', 'sk_test_51NHXyALM97O7CJZavBEFhQZfUsu3iTH02LGET6YCeaobpV66piWTvg3USvsKPyu6Hjxkztt2wtbaRGZkjBdvdaVf00X4W88G7r', 1),
(4, 'smtp_host', 'smtp-relay.brevo.com', 1),
(5, 'smtp_port', '587', 1),
(6, 'smtp_user', '', 1),
(7, 'smtp_pass', '', 1),
(8, 'smtp_secure', 'tls', 1),
(9, 'smtp_from_email', '', 1),
(10, 'smtp_from_nombre', 'VallaVendor', 1),
(11, 'stripe_webhook_secret', '', 0),
(12, 'vendor_comision_pct', '10.00', 1),
(13, 'stripe_currency', 'usd', 1),
(14, 'cron_key', 'https://auth.vallasled.com/admin/assets/logo.png', 1),
(15, 'logo_url', 'https://auth.vallasled.com/admin/assets/logo.png', 1),
(16, 'favicon_url', '/admin/website/uploads/favicon-128.png', 1),
(17, 'logo_align', 'center', 1),
(18, 'logo_width', '50', 1),
(19, 'logo_height', '36', 1),
(20, 'pixel_head_html', '', 1),
(21, 'pixel_body_html', '', 1),
(22, 'logo_2x_url', 'https://auth.vallasled.com/admin/assets/logo.png', 1),
(23, 'ga4_measurement_id', '', 1),
(24, 'google_ads_id', '', 1),
(25, 'facebook_pixel_id', '', 1),
(26, 'tiktok_pixel_id', '', 1),
(29, 'admin_email', 'dev@demo.com', 1),
(30, 'admin_pass_hash', '$2y$10$C694ZDWiCGs4D.KwJ33ZFOwqhQIM4fbbs3LLgtxDO4IPLxxoNbRZi', 1),
(31, 'openai_api_key', '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', 1),
(32, 'openai_model', 'gpt-4.1-mini', 1);

-- --------------------------------------------------------

--
-- Table structure for table `config_kv`
--

CREATE TABLE `config_kv` (
  `k` varchar(64) NOT NULL,
  `v` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `crm_clientes`
--

CREATE TABLE `crm_clientes` (
  `id` int(11) NOT NULL,
  `proveedor_id` int(11) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `empresa` varchar(150) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `creado` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `crm_clientes`
--

INSERT INTO `crm_clientes` (`id`, `proveedor_id`, `nombre`, `email`, `telefono`, `empresa`, `usuario_id`, `creado`) VALUES
(1, 3, 'fox pack', NULL, NULL, 'Fox Publicidad', 3, '2025-08-22 21:51:21'),
(2, 4, 'Morfe', 'info@ship24go.com', '8493623388', 'Ship24go', NULL, '2025-08-23 00:15:59'),
(3, 4, 'test', 'grupoohla@gmail.com', '5679877', 'test', NULL, '2025-08-23 00:20:49');

-- --------------------------------------------------------

--
-- Table structure for table `crm_licencias`
--

CREATE TABLE `crm_licencias` (
  `id` int(11) NOT NULL,
  `proveedor_id` int(11) NOT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `estado` enum('borrador','enviada','aprobada','rechazada','vencida') NOT NULL DEFAULT 'borrador',
  `ciudad` varchar(120) DEFAULT NULL,
  `entidad` varchar(180) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `lat` decimal(10,7) DEFAULT NULL,
  `lon` decimal(10,7) DEFAULT NULL,
  `ip_solicitante` varchar(45) DEFAULT NULL,
  `alto` decimal(10,2) DEFAULT NULL,
  `ancho` decimal(10,2) DEFAULT NULL,
  `documentos` json DEFAULT NULL,
  `tasas` decimal(10,2) DEFAULT NULL,
  `fecha_solicitud` date DEFAULT NULL,
  `fecha_emision` date DEFAULT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `aviso_30d_enviado` datetime DEFAULT NULL,
  `aviso_7d_enviado` datetime DEFAULT NULL,
  `aviso_1d_enviado` datetime DEFAULT NULL,
  `notas` text,
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `crm_licencias_ips`
--

CREATE TABLE `crm_licencias_ips` (
  `id` int(11) NOT NULL,
  `licencia_id` int(11) DEFAULT NULL,
  `proveedor_id` int(11) NOT NULL,
  `ip` varchar(64) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `cron_status`
--

CREATE TABLE `cron_status` (
  `id` int(11) NOT NULL,
  `task` varchar(64) NOT NULL,
  `proveedor_id` int(11) DEFAULT NULL,
  `dominio` varchar(255) DEFAULT NULL,
  `last_run_at` datetime DEFAULT NULL,
  `last_run_ok` tinyint(1) DEFAULT NULL,
  `last_msg` text,
  `last_ip` varchar(45) DEFAULT NULL,
  `count_sent` int(11) DEFAULT '0',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `cron_status`
--

INSERT INTO `cron_status` (`id`, `task`, `proveedor_id`, `dominio`, `last_run_at`, `last_run_ok`, `last_msg`, `last_ip`, `count_sent`, `updated_at`) VALUES
(1, 'notif_licencias', 4, NULL, '2025-08-23 17:19:13', 1, 'OK sent=0 fail=0', NULL, 0, '2025-08-23 21:19:13'),
(2, 'notif_licencias', 4, NULL, '2025-08-23 17:33:36', 1, 'OK sent=0 fail=0', NULL, 0, '2025-08-23 21:33:36');

-- --------------------------------------------------------

--
-- Table structure for table `datos_bancarios`
--

CREATE TABLE `datos_bancarios` (
  `id` int(11) NOT NULL,
  `banco` varchar(100) DEFAULT NULL,
  `numero_cuenta` varchar(50) DEFAULT NULL,
  `tipo_cuenta` varchar(50) DEFAULT NULL,
  `titular` varchar(100) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `datos_bancarios`
--

INSERT INTO `datos_bancarios` (`id`, `banco`, `numero_cuenta`, `tipo_cuenta`, `titular`, `activo`) VALUES
(1, 'Popular', '82993283', 'Ahorros', 'Ortiz', 0),
(2, 'Popular', '82993283', 'Corriente', 'Ortiz', 1),
(3, 'Testing', '234567', 'Ahorros', 'Testing', 1);

-- --------------------------------------------------------

--
-- Table structure for table `dominios_remotos`
--

CREATE TABLE `dominios_remotos` (
  `id` int(11) NOT NULL,
  `proveedor_id` int(11) DEFAULT NULL,
  `dominio` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  `activo` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `dominios_remotos`
--

INSERT INTO `dominios_remotos` (`id`, `proveedor_id`, `dominio`, `token`, `fecha_creacion`, `activo`) VALUES
(1, NULL, 'https://vallasled.com/', '1702a6f78cb9803c83bae5fdd368a14fd3b77dd224def909c83af91079065a19', '2025-05-17 01:19:41', 0);

-- --------------------------------------------------------

--
-- Table structure for table `empleados`
--

CREATE TABLE `empleados` (
  `id` int(11) NOT NULL,
  `nombre` varchar(128) NOT NULL,
  `contacto` varchar(128) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `email` varchar(128) DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `facturas`
--

CREATE TABLE `facturas` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `valla_id` int(11) DEFAULT NULL,
  `monto` decimal(10,2) NOT NULL,
  `precio_personalizado` decimal(10,2) DEFAULT NULL,
  `descuento` decimal(10,2) DEFAULT '0.00',
  `estado` enum('pendiente','pagado') DEFAULT 'pendiente',
  `metodo_pago` enum('stripe','transferencia') DEFAULT 'transferencia',
  `fecha_generada` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_generacion` datetime DEFAULT CURRENT_TIMESTAMP,
  `fecha_pago` datetime DEFAULT NULL,
  `stripe_link` varchar(255) DEFAULT NULL,
  `comision_pct` decimal(5,2) DEFAULT NULL,
  `comision_monto` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `facturas`
--

INSERT INTO `facturas` (`id`, `usuario_id`, `valla_id`, `monto`, `precio_personalizado`, `descuento`, `estado`, `metodo_pago`, `fecha_generada`, `fecha_generacion`, `fecha_pago`, `stripe_link`, `comision_pct`, `comision_monto`) VALUES
(3, 2, NULL, 90.00, NULL, 0.00, 'pagado', 'stripe', '2025-05-21 14:47:45', '2025-05-16 13:30:44', '2025-05-16 14:07:37', NULL, NULL, NULL),
(7, 8, 8, 90.00, 90.00, 0.00, 'pendiente', 'transferencia', '2025-05-21 14:51:09', '2025-05-21 14:51:09', NULL, NULL, NULL, NULL),
(8, 8, 8, 90.00, 90.00, 0.00, 'pendiente', 'transferencia', '2025-05-21 14:57:40', '2025-05-21 14:57:40', NULL, 'https://mi-pago-stripe.com/pay?f=8&monto=90.00', NULL, NULL);

--
-- Triggers `facturas`
--
DELIMITER $$
CREATE TRIGGER `trg_facturas_comm_ins` BEFORE INSERT ON `facturas` FOR EACH ROW BEGIN
  -- si no viene porcentaje, calcúlalo
  IF NEW.comision_pct IS NULL THEN
    SET NEW.comision_pct = fn_vendor_comision_pct(NEW.valla_id, CURDATE());
  END IF;
  -- calcula monto de comisión si falta
  IF NEW.comision_monto IS NULL AND NEW.comision_pct IS NOT NULL AND NEW.monto IS NOT NULL THEN
    SET NEW.comision_monto = ROUND(NEW.monto * NEW.comision_pct / 100, 2);
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_facturas_comm_upd` BEFORE UPDATE ON `facturas` FOR EACH ROW BEGIN
  IF NEW.comision_pct IS NULL THEN
    SET NEW.comision_pct = OLD.comision_pct;
  END IF;

  IF (NEW.monto <> OLD.monto) OR (NEW.comision_pct <> OLD.comision_pct) THEN
    SET NEW.comision_monto = ROUND(NEW.monto * NEW.comision_pct / 100, 2);
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `historial_acceso`
--

CREATE TABLE `historial_acceso` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `ip` varchar(50) DEFAULT NULL,
  `inicio` datetime DEFAULT NULL,
  `fin` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `keywords`
--

CREATE TABLE `keywords` (
  `id` bigint(20) NOT NULL,
  `keyword` varchar(191) NOT NULL,
  `normalized` varchar(191) NOT NULL,
  `intent` enum('informational','commercial','navigational','local') DEFAULT NULL,
  `volume` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `keywords`
--

INSERT INTO `keywords` (`id`, `keyword`, `normalized`, `intent`, `volume`) VALUES
(1, 'OOH', 'ooh', 'commercial', NULL),
(2, 'OOH 24 horas', 'ooh 24 horas', 'commercial', NULL),
(3, 'OOH 24 horas República Dominicana', 'ooh 24 horas republica dominicana', 'local', NULL),
(4, 'OOH 24 horas en República Dominicana', 'ooh 24 horas en republica dominicana', 'local', NULL),
(5, 'OOH 4k', 'ooh 4k', 'commercial', NULL),
(6, 'OOH 4k República Dominicana', 'ooh 4k republica dominicana', 'local', NULL),
(7, 'OOH 4k en República Dominicana', 'ooh 4k en republica dominicana', 'local', NULL),
(8, 'OOH Azua', 'ooh azua', 'local', NULL),
(9, 'OOH Baní', 'ooh bani', 'local', NULL),
(10, 'OOH Barahona', 'ooh barahona', 'local', NULL),
(11, 'OOH Bayahibe', 'ooh bayahibe', 'local', NULL),
(12, 'OOH Boca Chica', 'ooh boca chica', 'local', NULL),
(13, 'OOH Bonao', 'ooh bonao', 'local', NULL),
(14, 'OOH Bávaro', 'ooh bavaro', 'local', NULL),
(15, 'OOH Cabarete', 'ooh cabarete', 'local', NULL),
(16, 'OOH Cabrera', 'ooh cabrera', 'local', NULL),
(17, 'OOH Constanza', 'ooh constanza', 'local', NULL),
(18, 'OOH Dajabón', 'ooh dajabon', 'local', NULL),
(19, 'OOH Distrito Nacional', 'ooh distrito nacional', 'local', NULL),
(20, 'OOH El Seibo', 'ooh el seibo', 'local', NULL),
(21, 'OOH Haina', 'ooh haina', 'local', NULL),
(22, 'OOH Hato Mayor', 'ooh hato mayor', 'local', NULL),
(23, 'OOH Higüey', 'ooh higey', 'local', NULL),
(24, 'OOH Jarabacoa', 'ooh jarabacoa', 'local', NULL),
(25, 'OOH La Romana', 'ooh la romana', 'local', NULL),
(26, 'OOH La Vega', 'ooh la vega', 'local', NULL),
(27, 'OOH Las Terrenas', 'ooh las terrenas', 'local', NULL),
(28, 'OOH Los Alcarrizos', 'ooh los alcarrizos', 'local', NULL),
(29, 'OOH Maimón', 'ooh maimon', 'local', NULL),
(30, 'OOH Mao', 'ooh mao', 'local', NULL),
(31, 'OOH Moca', 'ooh moca', 'local', NULL),
(32, 'OOH Monte Cristi', 'ooh monte cristi', 'local', NULL),
(33, 'OOH Nagua', 'ooh nagua', 'local', NULL),
(34, 'OOH Puerto Plata', 'ooh puerto plata', 'local', NULL),
(35, 'OOH Punta Cana', 'ooh punta cana', 'local', NULL),
(36, 'OOH República Dominicana', 'ooh republica dominicana', 'local', NULL),
(37, 'OOH Samaná', 'ooh samana', 'local', NULL),
(38, 'OOH San Cristóbal', 'ooh san cristobal', 'local', NULL),
(39, 'OOH San Juan', 'ooh san juan', 'local', NULL),
(40, 'OOH San Pedro de Macorís', 'ooh san pedro de macoris', 'local', NULL),
(41, 'OOH Santiago', 'ooh santiago', 'local', NULL),
(42, 'OOH Santo Domingo', 'ooh santo domingo', 'local', NULL),
(43, 'OOH Santo Domingo Este', 'ooh santo domingo este', 'local', NULL),
(44, 'OOH Santo Domingo Norte', 'ooh santo domingo norte', 'local', NULL),
(45, 'OOH Santo Domingo Oeste', 'ooh santo domingo oeste', 'local', NULL),
(46, 'OOH Sosúa', 'ooh sosua', 'local', NULL),
(47, 'OOH Valverde', 'ooh valverde', 'local', NULL),
(48, 'OOH Villa Mella', 'ooh villa mella', 'local', NULL),
(49, 'OOH alcance', 'ooh alcance', 'commercial', NULL),
(50, 'OOH alcance República Dominicana', 'ooh alcance republica dominicana', 'local', NULL),
(51, 'OOH alcance en República Dominicana', 'ooh alcance en republica dominicana', 'local', NULL),
(52, 'OOH alta resolución', 'ooh alta resolucion', 'commercial', NULL),
(53, 'OOH alta resolución República Dominicana', 'ooh alta resolucion republica dominicana', 'local', NULL),
(54, 'OOH alta resolución en República Dominicana', 'ooh alta resolucion en republica dominicana', 'local', NULL),
(55, 'OOH audiencia', 'ooh audiencia', 'commercial', NULL),
(56, 'OOH audiencia República Dominicana', 'ooh audiencia republica dominicana', 'local', NULL),
(57, 'OOH audiencia en República Dominicana', 'ooh audiencia en republica dominicana', 'local', NULL),
(58, 'OOH baratas', 'ooh baratas', 'commercial', NULL),
(59, 'OOH baratas República Dominicana', 'ooh baratas republica dominicana', 'local', NULL),
(60, 'OOH baratas en República Dominicana', 'ooh baratas en republica dominicana', 'local', NULL),
(61, 'OOH calendario', 'ooh calendario', 'commercial', NULL),
(62, 'OOH calendario República Dominicana', 'ooh calendario republica dominicana', 'local', NULL),
(63, 'OOH calendario en República Dominicana', 'ooh calendario en republica dominicana', 'local', NULL),
(64, 'OOH catálogo', 'ooh catalogo', 'commercial', NULL),
(65, 'OOH catálogo República Dominicana', 'ooh catalogo republica dominicana', 'local', NULL),
(66, 'OOH catálogo en República Dominicana', 'ooh catalogo en republica dominicana', 'local', NULL),
(67, 'OOH cerca de mí', 'ooh cerca de mi', 'commercial', NULL),
(68, 'OOH cerca de mí República Dominicana', 'ooh cerca de mi republica dominicana', 'local', NULL),
(69, 'OOH cerca de mí en República Dominicana', 'ooh cerca de mi en republica dominicana', 'local', NULL),
(70, 'OOH cobertura', 'ooh cobertura', 'commercial', NULL),
(71, 'OOH cobertura República Dominicana', 'ooh cobertura republica dominicana', 'local', NULL),
(72, 'OOH cobertura en República Dominicana', 'ooh cobertura en republica dominicana', 'local', NULL),
(73, 'OOH con impresión', 'ooh con impresion', 'commercial', NULL),
(74, 'OOH con impresión República Dominicana', 'ooh con impresion republica dominicana', 'local', NULL),
(75, 'OOH con impresión en República Dominicana', 'ooh con impresion en republica dominicana', 'local', NULL),
(76, 'OOH con instalación', 'ooh con instalacion', 'commercial', NULL),
(77, 'OOH con instalación República Dominicana', 'ooh con instalacion republica dominicana', 'local', NULL),
(78, 'OOH con instalación en República Dominicana', 'ooh con instalacion en republica dominicana', 'local', NULL),
(79, 'OOH con mantenimiento', 'ooh con mantenimiento', 'commercial', NULL),
(80, 'OOH con mantenimiento República Dominicana', 'ooh con mantenimiento republica dominicana', 'local', NULL),
(81, 'OOH con mantenimiento en República Dominicana', 'ooh con mantenimiento en republica dominicana', 'local', NULL),
(82, 'OOH con permiso', 'ooh con permiso', 'commercial', NULL),
(83, 'OOH con permiso República Dominicana', 'ooh con permiso republica dominicana', 'local', NULL),
(84, 'OOH con permiso en República Dominicana', 'ooh con permiso en republica dominicana', 'local', NULL),
(85, 'OOH disponibles', 'ooh disponibles', 'commercial', NULL),
(86, 'OOH disponibles República Dominicana', 'ooh disponibles republica dominicana', 'local', NULL),
(87, 'OOH disponibles en República Dominicana', 'ooh disponibles en republica dominicana', 'local', NULL),
(88, 'OOH en Azua', 'ooh en azua', 'local', NULL),
(89, 'OOH en Baní', 'ooh en bani', 'local', NULL),
(90, 'OOH en Barahona', 'ooh en barahona', 'local', NULL),
(91, 'OOH en Bayahibe', 'ooh en bayahibe', 'local', NULL),
(92, 'OOH en Boca Chica', 'ooh en boca chica', 'local', NULL),
(93, 'OOH en Bonao', 'ooh en bonao', 'local', NULL),
(94, 'OOH en Bávaro', 'ooh en bavaro', 'local', NULL),
(95, 'OOH en Cabarete', 'ooh en cabarete', 'local', NULL),
(96, 'OOH en Cabrera', 'ooh en cabrera', 'local', NULL),
(97, 'OOH en Constanza', 'ooh en constanza', 'local', NULL),
(98, 'OOH en Dajabón', 'ooh en dajabon', 'local', NULL),
(99, 'OOH en Distrito Nacional', 'ooh en distrito nacional', 'local', NULL),
(100, 'OOH en El Seibo', 'ooh en el seibo', 'local', NULL),
(101, 'OOH en Haina', 'ooh en haina', 'local', NULL),
(102, 'OOH en Hato Mayor', 'ooh en hato mayor', 'local', NULL),
(103, 'OOH en Higüey', 'ooh en higey', 'local', NULL),
(104, 'OOH en Jarabacoa', 'ooh en jarabacoa', 'local', NULL),
(105, 'OOH en La Romana', 'ooh en la romana', 'local', NULL),
(106, 'OOH en La Vega', 'ooh en la vega', 'local', NULL),
(107, 'OOH en Las Terrenas', 'ooh en las terrenas', 'local', NULL),
(108, 'OOH en Los Alcarrizos', 'ooh en los alcarrizos', 'local', NULL),
(109, 'OOH en Maimón', 'ooh en maimon', 'local', NULL),
(110, 'OOH en Mao', 'ooh en mao', 'local', NULL),
(111, 'OOH en Moca', 'ooh en moca', 'local', NULL),
(112, 'OOH en Monte Cristi', 'ooh en monte cristi', 'local', NULL),
(113, 'OOH en Nagua', 'ooh en nagua', 'local', NULL),
(114, 'OOH en Puerto Plata', 'ooh en puerto plata', 'local', NULL),
(115, 'OOH en Punta Cana', 'ooh en punta cana', 'local', NULL),
(116, 'OOH en República Dominicana', 'ooh en republica dominicana', 'local', NULL),
(117, 'OOH en Samaná', 'ooh en samana', 'local', NULL),
(118, 'OOH en San Cristóbal', 'ooh en san cristobal', 'local', NULL),
(119, 'OOH en San Juan', 'ooh en san juan', 'local', NULL),
(120, 'OOH en San Pedro de Macorís', 'ooh en san pedro de macoris', 'local', NULL),
(121, 'OOH en Santiago', 'ooh en santiago', 'local', NULL),
(122, 'OOH en Santo Domingo', 'ooh en santo domingo', 'local', NULL),
(123, 'OOH en Santo Domingo Este', 'ooh en santo domingo este', 'local', NULL),
(124, 'OOH en Santo Domingo Norte', 'ooh en santo domingo norte', 'local', NULL),
(125, 'OOH en Santo Domingo Oeste', 'ooh en santo domingo oeste', 'local', NULL),
(126, 'OOH en Sosúa', 'ooh en sosua', 'local', NULL),
(127, 'OOH en Valverde', 'ooh en valverde', 'local', NULL),
(128, 'OOH en Villa Mella', 'ooh en villa mella', 'local', NULL),
(129, 'OOH hd', 'ooh hd', 'commercial', NULL),
(130, 'OOH hd República Dominicana', 'ooh hd republica dominicana', 'local', NULL),
(131, 'OOH hd en República Dominicana', 'ooh hd en republica dominicana', 'local', NULL),
(132, 'OOH impacto', 'ooh impacto', 'commercial', NULL),
(133, 'OOH impacto República Dominicana', 'ooh impacto republica dominicana', 'local', NULL),
(134, 'OOH impacto en República Dominicana', 'ooh impacto en republica dominicana', 'local', NULL),
(135, 'OOH mapa', 'ooh mapa', 'commercial', NULL),
(136, 'OOH mapa República Dominicana', 'ooh mapa republica dominicana', 'local', NULL),
(137, 'OOH mapa en República Dominicana', 'ooh mapa en republica dominicana', 'local', NULL),
(138, 'OOH ofertas', 'ooh ofertas', 'commercial', NULL),
(139, 'OOH ofertas República Dominicana', 'ooh ofertas republica dominicana', 'local', NULL),
(140, 'OOH ofertas en República Dominicana', 'ooh ofertas en republica dominicana', 'local', NULL),
(141, 'OOH paquetes', 'ooh paquetes', 'commercial', NULL),
(142, 'OOH paquetes República Dominicana', 'ooh paquetes republica dominicana', 'local', NULL),
(143, 'OOH paquetes en República Dominicana', 'ooh paquetes en republica dominicana', 'local', NULL),
(144, 'OOH para eventos', 'ooh para eventos', 'commercial', NULL),
(145, 'OOH para eventos República Dominicana', 'ooh para eventos republica dominicana', 'local', NULL),
(146, 'OOH para eventos en República Dominicana', 'ooh para eventos en republica dominicana', 'local', NULL),
(147, 'OOH para marcas', 'ooh para marcas', 'commercial', NULL),
(148, 'OOH para marcas República Dominicana', 'ooh para marcas republica dominicana', 'local', NULL),
(149, 'OOH para marcas en República Dominicana', 'ooh para marcas en republica dominicana', 'local', NULL),
(150, 'OOH premium', 'ooh premium', 'commercial', NULL),
(151, 'OOH premium República Dominicana', 'ooh premium republica dominicana', 'local', NULL),
(152, 'OOH premium en República Dominicana', 'ooh premium en republica dominicana', 'local', NULL),
(153, 'OOH programables', 'ooh programables', 'commercial', NULL),
(154, 'OOH programables República Dominicana', 'ooh programables republica dominicana', 'local', NULL),
(155, 'OOH programables en República Dominicana', 'ooh programables en republica dominicana', 'local', NULL),
(156, 'OOH proveedor', 'ooh proveedor', 'commercial', NULL),
(157, 'OOH proveedor República Dominicana', 'ooh proveedor republica dominicana', 'local', NULL),
(158, 'OOH proveedor en República Dominicana', 'ooh proveedor en republica dominicana', 'local', NULL),
(159, 'OOH proveedores', 'ooh proveedores', 'commercial', NULL),
(160, 'OOH proveedores República Dominicana', 'ooh proveedores republica dominicana', 'local', NULL),
(161, 'OOH proveedores en República Dominicana', 'ooh proveedores en republica dominicana', 'local', NULL),
(162, 'OOH tráfico', 'ooh trafico', 'commercial', NULL),
(163, 'OOH tráfico República Dominicana', 'ooh trafico republica dominicana', 'local', NULL),
(164, 'OOH tráfico en República Dominicana', 'ooh trafico en republica dominicana', 'local', NULL),
(165, 'OOH ubicaciones', 'ooh ubicaciones', 'commercial', NULL),
(166, 'OOH ubicaciones República Dominicana', 'ooh ubicaciones republica dominicana', 'local', NULL),
(167, 'OOH ubicaciones en República Dominicana', 'ooh ubicaciones en republica dominicana', 'local', NULL),
(168, 'agencia de vallas', 'agencia de vallas', 'commercial', NULL),
(169, 'agencia de vallas 24 horas', 'agencia de vallas 24 horas', 'commercial', NULL),
(170, 'agencia de vallas 24 horas República Dominicana', 'agencia de vallas 24 horas republica dominicana', 'local', NULL),
(171, 'agencia de vallas 24 horas en República Dominicana', 'agencia de vallas 24 horas en republica dominicana', 'local', NULL),
(172, 'agencia de vallas 4k', 'agencia de vallas 4k', 'commercial', NULL),
(173, 'agencia de vallas 4k República Dominicana', 'agencia de vallas 4k republica dominicana', 'local', NULL),
(174, 'agencia de vallas 4k en República Dominicana', 'agencia de vallas 4k en republica dominicana', 'local', NULL),
(175, 'agencia de vallas Azua', 'agencia de vallas azua', 'local', NULL),
(176, 'agencia de vallas Baní', 'agencia de vallas bani', 'local', NULL),
(177, 'agencia de vallas Barahona', 'agencia de vallas barahona', 'local', NULL),
(178, 'agencia de vallas Bayahibe', 'agencia de vallas bayahibe', 'local', NULL),
(179, 'agencia de vallas Boca Chica', 'agencia de vallas boca chica', 'local', NULL),
(180, 'agencia de vallas Bonao', 'agencia de vallas bonao', 'local', NULL),
(181, 'agencia de vallas Bávaro', 'agencia de vallas bavaro', 'local', NULL),
(182, 'agencia de vallas Cabarete', 'agencia de vallas cabarete', 'local', NULL),
(183, 'agencia de vallas Cabrera', 'agencia de vallas cabrera', 'local', NULL),
(184, 'agencia de vallas Constanza', 'agencia de vallas constanza', 'local', NULL),
(185, 'agencia de vallas Dajabón', 'agencia de vallas dajabon', 'local', NULL),
(186, 'agencia de vallas Distrito Nacional', 'agencia de vallas distrito nacional', 'local', NULL),
(187, 'agencia de vallas El Seibo', 'agencia de vallas el seibo', 'local', NULL),
(188, 'agencia de vallas Haina', 'agencia de vallas haina', 'local', NULL),
(189, 'agencia de vallas Hato Mayor', 'agencia de vallas hato mayor', 'local', NULL),
(190, 'agencia de vallas Higüey', 'agencia de vallas higey', 'local', NULL),
(191, 'agencia de vallas Jarabacoa', 'agencia de vallas jarabacoa', 'local', NULL),
(192, 'agencia de vallas La Romana', 'agencia de vallas la romana', 'local', NULL),
(193, 'agencia de vallas La Vega', 'agencia de vallas la vega', 'local', NULL),
(194, 'agencia de vallas Las Terrenas', 'agencia de vallas las terrenas', 'local', NULL),
(195, 'agencia de vallas Los Alcarrizos', 'agencia de vallas los alcarrizos', 'local', NULL),
(196, 'agencia de vallas Maimón', 'agencia de vallas maimon', 'local', NULL),
(197, 'agencia de vallas Mao', 'agencia de vallas mao', 'local', NULL),
(198, 'agencia de vallas Moca', 'agencia de vallas moca', 'local', NULL),
(199, 'agencia de vallas Monte Cristi', 'agencia de vallas monte cristi', 'local', NULL),
(200, 'agencia de vallas Nagua', 'agencia de vallas nagua', 'local', NULL),
(201, 'agencia de vallas Puerto Plata', 'agencia de vallas puerto plata', 'local', NULL),
(202, 'agencia de vallas Punta Cana', 'agencia de vallas punta cana', 'local', NULL),
(203, 'agencia de vallas República Dominicana', 'agencia de vallas republica dominicana', 'local', NULL),
(204, 'agencia de vallas Samaná', 'agencia de vallas samana', 'local', NULL),
(205, 'agencia de vallas San Cristóbal', 'agencia de vallas san cristobal', 'local', NULL),
(206, 'agencia de vallas San Juan', 'agencia de vallas san juan', 'local', NULL),
(207, 'agencia de vallas San Pedro de Macorís', 'agencia de vallas san pedro de macoris', 'local', NULL),
(208, 'agencia de vallas Santiago', 'agencia de vallas santiago', 'local', NULL),
(209, 'agencia de vallas Santo Domingo', 'agencia de vallas santo domingo', 'local', NULL),
(210, 'agencia de vallas Santo Domingo Este', 'agencia de vallas santo domingo este', 'local', NULL),
(211, 'agencia de vallas Santo Domingo Norte', 'agencia de vallas santo domingo norte', 'local', NULL),
(212, 'agencia de vallas Santo Domingo Oeste', 'agencia de vallas santo domingo oeste', 'local', NULL),
(213, 'agencia de vallas Sosúa', 'agencia de vallas sosua', 'local', NULL),
(214, 'agencia de vallas Valverde', 'agencia de vallas valverde', 'local', NULL),
(215, 'agencia de vallas Villa Mella', 'agencia de vallas villa mella', 'local', NULL),
(216, 'agencia de vallas alcance', 'agencia de vallas alcance', 'commercial', NULL),
(217, 'agencia de vallas alcance República Dominicana', 'agencia de vallas alcance republica dominicana', 'local', NULL),
(218, 'agencia de vallas alcance en República Dominicana', 'agencia de vallas alcance en republica dominicana', 'local', NULL),
(219, 'agencia de vallas alta resolución', 'agencia de vallas alta resolucion', 'commercial', NULL),
(220, 'agencia de vallas alta resolución República Dominicana', 'agencia de vallas alta resolucion republica dominicana', 'local', NULL),
(221, 'agencia de vallas alta resolución en República Dominicana', 'agencia de vallas alta resolucion en republica dominicana', 'local', NULL),
(222, 'agencia de vallas audiencia', 'agencia de vallas audiencia', 'commercial', NULL),
(223, 'agencia de vallas audiencia República Dominicana', 'agencia de vallas audiencia republica dominicana', 'local', NULL),
(224, 'agencia de vallas audiencia en República Dominicana', 'agencia de vallas audiencia en republica dominicana', 'local', NULL),
(225, 'agencia de vallas baratas', 'agencia de vallas baratas', 'commercial', NULL),
(226, 'agencia de vallas baratas República Dominicana', 'agencia de vallas baratas republica dominicana', 'local', NULL),
(227, 'agencia de vallas baratas en República Dominicana', 'agencia de vallas baratas en republica dominicana', 'local', NULL),
(228, 'agencia de vallas calendario', 'agencia de vallas calendario', 'commercial', NULL),
(229, 'agencia de vallas calendario República Dominicana', 'agencia de vallas calendario republica dominicana', 'local', NULL),
(230, 'agencia de vallas calendario en República Dominicana', 'agencia de vallas calendario en republica dominicana', 'local', NULL),
(231, 'agencia de vallas catálogo', 'agencia de vallas catalogo', 'commercial', NULL),
(232, 'agencia de vallas catálogo República Dominicana', 'agencia de vallas catalogo republica dominicana', 'local', NULL),
(233, 'agencia de vallas catálogo en República Dominicana', 'agencia de vallas catalogo en republica dominicana', 'local', NULL),
(234, 'agencia de vallas cerca de mí', 'agencia de vallas cerca de mi', 'commercial', NULL),
(235, 'agencia de vallas cerca de mí República Dominicana', 'agencia de vallas cerca de mi republica dominicana', 'local', NULL),
(236, 'agencia de vallas cerca de mí en República Dominicana', 'agencia de vallas cerca de mi en republica dominicana', 'local', NULL),
(237, 'agencia de vallas cobertura', 'agencia de vallas cobertura', 'commercial', NULL),
(238, 'agencia de vallas cobertura República Dominicana', 'agencia de vallas cobertura republica dominicana', 'local', NULL),
(239, 'agencia de vallas cobertura en República Dominicana', 'agencia de vallas cobertura en republica dominicana', 'local', NULL),
(240, 'agencia de vallas con impresión', 'agencia de vallas con impresion', 'commercial', NULL),
(241, 'agencia de vallas con impresión República Dominicana', 'agencia de vallas con impresion republica dominicana', 'local', NULL),
(242, 'agencia de vallas con impresión en República Dominicana', 'agencia de vallas con impresion en republica dominicana', 'local', NULL),
(243, 'agencia de vallas con instalación', 'agencia de vallas con instalacion', 'commercial', NULL),
(244, 'agencia de vallas con instalación República Dominicana', 'agencia de vallas con instalacion republica dominicana', 'local', NULL),
(245, 'agencia de vallas con instalación en República Dominicana', 'agencia de vallas con instalacion en republica dominicana', 'local', NULL),
(246, 'agencia de vallas con mantenimiento', 'agencia de vallas con mantenimiento', 'commercial', NULL),
(247, 'agencia de vallas con mantenimiento República Dominicana', 'agencia de vallas con mantenimiento republica dominicana', 'local', NULL),
(248, 'agencia de vallas con mantenimiento en República Dominicana', 'agencia de vallas con mantenimiento en republica dominicana', 'local', NULL),
(249, 'agencia de vallas con permiso', 'agencia de vallas con permiso', 'commercial', NULL),
(250, 'agencia de vallas con permiso República Dominicana', 'agencia de vallas con permiso republica dominicana', 'local', NULL),
(251, 'agencia de vallas con permiso en República Dominicana', 'agencia de vallas con permiso en republica dominicana', 'local', NULL),
(252, 'agencia de vallas disponibles', 'agencia de vallas disponibles', 'commercial', NULL),
(253, 'agencia de vallas disponibles República Dominicana', 'agencia de vallas disponibles republica dominicana', 'local', NULL),
(254, 'agencia de vallas disponibles en República Dominicana', 'agencia de vallas disponibles en republica dominicana', 'local', NULL),
(255, 'agencia de vallas en Azua', 'agencia de vallas en azua', 'local', NULL),
(256, 'agencia de vallas en Baní', 'agencia de vallas en bani', 'local', NULL),
(257, 'agencia de vallas en Barahona', 'agencia de vallas en barahona', 'local', NULL),
(258, 'agencia de vallas en Bayahibe', 'agencia de vallas en bayahibe', 'local', NULL),
(259, 'agencia de vallas en Boca Chica', 'agencia de vallas en boca chica', 'local', NULL),
(260, 'agencia de vallas en Bonao', 'agencia de vallas en bonao', 'local', NULL),
(261, 'agencia de vallas en Bávaro', 'agencia de vallas en bavaro', 'local', NULL),
(262, 'agencia de vallas en Cabarete', 'agencia de vallas en cabarete', 'local', NULL),
(263, 'agencia de vallas en Cabrera', 'agencia de vallas en cabrera', 'local', NULL),
(264, 'agencia de vallas en Constanza', 'agencia de vallas en constanza', 'local', NULL),
(265, 'agencia de vallas en Dajabón', 'agencia de vallas en dajabon', 'local', NULL),
(266, 'agencia de vallas en Distrito Nacional', 'agencia de vallas en distrito nacional', 'local', NULL),
(267, 'agencia de vallas en El Seibo', 'agencia de vallas en el seibo', 'local', NULL),
(268, 'agencia de vallas en Haina', 'agencia de vallas en haina', 'local', NULL),
(269, 'agencia de vallas en Hato Mayor', 'agencia de vallas en hato mayor', 'local', NULL),
(270, 'agencia de vallas en Higüey', 'agencia de vallas en higey', 'local', NULL),
(271, 'agencia de vallas en Jarabacoa', 'agencia de vallas en jarabacoa', 'local', NULL),
(272, 'agencia de vallas en La Romana', 'agencia de vallas en la romana', 'local', NULL),
(273, 'agencia de vallas en La Vega', 'agencia de vallas en la vega', 'local', NULL),
(274, 'agencia de vallas en Las Terrenas', 'agencia de vallas en las terrenas', 'local', NULL),
(275, 'agencia de vallas en Los Alcarrizos', 'agencia de vallas en los alcarrizos', 'local', NULL),
(276, 'agencia de vallas en Maimón', 'agencia de vallas en maimon', 'local', NULL),
(277, 'agencia de vallas en Mao', 'agencia de vallas en mao', 'local', NULL),
(278, 'agencia de vallas en Moca', 'agencia de vallas en moca', 'local', NULL),
(279, 'agencia de vallas en Monte Cristi', 'agencia de vallas en monte cristi', 'local', NULL),
(280, 'agencia de vallas en Nagua', 'agencia de vallas en nagua', 'local', NULL),
(281, 'agencia de vallas en Puerto Plata', 'agencia de vallas en puerto plata', 'local', NULL),
(282, 'agencia de vallas en Punta Cana', 'agencia de vallas en punta cana', 'local', NULL),
(283, 'agencia de vallas en República Dominicana', 'agencia de vallas en republica dominicana', 'local', NULL),
(284, 'agencia de vallas en Samaná', 'agencia de vallas en samana', 'local', NULL),
(285, 'agencia de vallas en San Cristóbal', 'agencia de vallas en san cristobal', 'local', NULL),
(286, 'agencia de vallas en San Juan', 'agencia de vallas en san juan', 'local', NULL),
(287, 'agencia de vallas en San Pedro de Macorís', 'agencia de vallas en san pedro de macoris', 'local', NULL),
(288, 'agencia de vallas en Santiago', 'agencia de vallas en santiago', 'local', NULL),
(289, 'agencia de vallas en Santo Domingo', 'agencia de vallas en santo domingo', 'local', NULL),
(290, 'agencia de vallas en Santo Domingo Este', 'agencia de vallas en santo domingo este', 'local', NULL),
(291, 'agencia de vallas en Santo Domingo Norte', 'agencia de vallas en santo domingo norte', 'local', NULL),
(292, 'agencia de vallas en Santo Domingo Oeste', 'agencia de vallas en santo domingo oeste', 'local', NULL),
(293, 'agencia de vallas en Sosúa', 'agencia de vallas en sosua', 'local', NULL),
(294, 'agencia de vallas en Valverde', 'agencia de vallas en valverde', 'local', NULL),
(295, 'agencia de vallas en Villa Mella', 'agencia de vallas en villa mella', 'local', NULL),
(296, 'agencia de vallas hd', 'agencia de vallas hd', 'commercial', NULL),
(297, 'agencia de vallas hd República Dominicana', 'agencia de vallas hd republica dominicana', 'local', NULL),
(298, 'agencia de vallas hd en República Dominicana', 'agencia de vallas hd en republica dominicana', 'local', NULL),
(299, 'agencia de vallas impacto', 'agencia de vallas impacto', 'commercial', NULL),
(300, 'agencia de vallas impacto República Dominicana', 'agencia de vallas impacto republica dominicana', 'local', NULL),
(301, 'agencia de vallas impacto en República Dominicana', 'agencia de vallas impacto en republica dominicana', 'local', NULL),
(302, 'agencia de vallas mapa', 'agencia de vallas mapa', 'commercial', NULL),
(303, 'agencia de vallas mapa República Dominicana', 'agencia de vallas mapa republica dominicana', 'local', NULL),
(304, 'agencia de vallas mapa en República Dominicana', 'agencia de vallas mapa en republica dominicana', 'local', NULL),
(305, 'agencia de vallas ofertas', 'agencia de vallas ofertas', 'commercial', NULL),
(306, 'agencia de vallas ofertas República Dominicana', 'agencia de vallas ofertas republica dominicana', 'local', NULL),
(307, 'agencia de vallas ofertas en República Dominicana', 'agencia de vallas ofertas en republica dominicana', 'local', NULL),
(308, 'agencia de vallas paquetes', 'agencia de vallas paquetes', 'commercial', NULL),
(309, 'agencia de vallas paquetes República Dominicana', 'agencia de vallas paquetes republica dominicana', 'local', NULL),
(310, 'agencia de vallas paquetes en República Dominicana', 'agencia de vallas paquetes en republica dominicana', 'local', NULL),
(311, 'agencia de vallas para eventos', 'agencia de vallas para eventos', 'commercial', NULL),
(312, 'agencia de vallas para eventos República Dominicana', 'agencia de vallas para eventos republica dominicana', 'local', NULL),
(313, 'agencia de vallas para eventos en República Dominicana', 'agencia de vallas para eventos en republica dominicana', 'local', NULL),
(314, 'agencia de vallas para marcas', 'agencia de vallas para marcas', 'commercial', NULL),
(315, 'agencia de vallas para marcas República Dominicana', 'agencia de vallas para marcas republica dominicana', 'local', NULL),
(316, 'agencia de vallas para marcas en República Dominicana', 'agencia de vallas para marcas en republica dominicana', 'local', NULL),
(317, 'agencia de vallas premium', 'agencia de vallas premium', 'commercial', NULL),
(318, 'agencia de vallas premium República Dominicana', 'agencia de vallas premium republica dominicana', 'local', NULL),
(319, 'agencia de vallas premium en República Dominicana', 'agencia de vallas premium en republica dominicana', 'local', NULL),
(320, 'agencia de vallas programables', 'agencia de vallas programables', 'commercial', NULL),
(321, 'agencia de vallas programables República Dominicana', 'agencia de vallas programables republica dominicana', 'local', NULL),
(322, 'agencia de vallas programables en República Dominicana', 'agencia de vallas programables en republica dominicana', 'local', NULL),
(323, 'agencia de vallas proveedor', 'agencia de vallas proveedor', 'commercial', NULL),
(324, 'agencia de vallas proveedor República Dominicana', 'agencia de vallas proveedor republica dominicana', 'local', NULL),
(325, 'agencia de vallas proveedor en República Dominicana', 'agencia de vallas proveedor en republica dominicana', 'local', NULL),
(326, 'agencia de vallas proveedores', 'agencia de vallas proveedores', 'commercial', NULL),
(327, 'agencia de vallas proveedores República Dominicana', 'agencia de vallas proveedores republica dominicana', 'local', NULL),
(328, 'agencia de vallas proveedores en República Dominicana', 'agencia de vallas proveedores en republica dominicana', 'local', NULL),
(329, 'agencia de vallas tráfico', 'agencia de vallas trafico', 'commercial', NULL),
(330, 'agencia de vallas tráfico República Dominicana', 'agencia de vallas trafico republica dominicana', 'local', NULL),
(331, 'agencia de vallas tráfico en República Dominicana', 'agencia de vallas trafico en republica dominicana', 'local', NULL),
(332, 'agencia de vallas ubicaciones', 'agencia de vallas ubicaciones', 'commercial', NULL),
(333, 'agencia de vallas ubicaciones República Dominicana', 'agencia de vallas ubicaciones republica dominicana', 'local', NULL),
(334, 'agencia de vallas ubicaciones en República Dominicana', 'agencia de vallas ubicaciones en republica dominicana', 'local', NULL),
(335, 'alquiler de vallas', 'alquiler de vallas', 'commercial', NULL),
(336, 'alquiler de vallas 24 horas', 'alquiler de vallas 24 horas', 'commercial', NULL),
(337, 'alquiler de vallas 24 horas República Dominicana', 'alquiler de vallas 24 horas republica dominicana', 'local', NULL),
(338, 'alquiler de vallas 24 horas en República Dominicana', 'alquiler de vallas 24 horas en republica dominicana', 'local', NULL),
(339, 'alquiler de vallas 4k', 'alquiler de vallas 4k', 'commercial', NULL),
(340, 'alquiler de vallas 4k República Dominicana', 'alquiler de vallas 4k republica dominicana', 'local', NULL),
(341, 'alquiler de vallas 4k en República Dominicana', 'alquiler de vallas 4k en republica dominicana', 'local', NULL),
(342, 'alquiler de vallas Azua', 'alquiler de vallas azua', 'local', NULL),
(343, 'alquiler de vallas Baní', 'alquiler de vallas bani', 'local', NULL),
(344, 'alquiler de vallas Barahona', 'alquiler de vallas barahona', 'local', NULL),
(345, 'alquiler de vallas Bayahibe', 'alquiler de vallas bayahibe', 'local', NULL),
(346, 'alquiler de vallas Boca Chica', 'alquiler de vallas boca chica', 'local', NULL),
(347, 'alquiler de vallas Bonao', 'alquiler de vallas bonao', 'local', NULL),
(348, 'alquiler de vallas Bávaro', 'alquiler de vallas bavaro', 'local', NULL),
(349, 'alquiler de vallas Cabarete', 'alquiler de vallas cabarete', 'local', NULL),
(350, 'alquiler de vallas Cabrera', 'alquiler de vallas cabrera', 'local', NULL),
(351, 'alquiler de vallas Constanza', 'alquiler de vallas constanza', 'local', NULL),
(352, 'alquiler de vallas Dajabón', 'alquiler de vallas dajabon', 'local', NULL),
(353, 'alquiler de vallas Distrito Nacional', 'alquiler de vallas distrito nacional', 'local', NULL),
(354, 'alquiler de vallas El Seibo', 'alquiler de vallas el seibo', 'local', NULL),
(355, 'alquiler de vallas Haina', 'alquiler de vallas haina', 'local', NULL),
(356, 'alquiler de vallas Hato Mayor', 'alquiler de vallas hato mayor', 'local', NULL),
(357, 'alquiler de vallas Higüey', 'alquiler de vallas higey', 'local', NULL),
(358, 'alquiler de vallas Jarabacoa', 'alquiler de vallas jarabacoa', 'local', NULL),
(359, 'alquiler de vallas La Romana', 'alquiler de vallas la romana', 'local', NULL),
(360, 'alquiler de vallas La Vega', 'alquiler de vallas la vega', 'local', NULL),
(361, 'alquiler de vallas Las Terrenas', 'alquiler de vallas las terrenas', 'local', NULL),
(362, 'alquiler de vallas Los Alcarrizos', 'alquiler de vallas los alcarrizos', 'local', NULL),
(363, 'alquiler de vallas Maimón', 'alquiler de vallas maimon', 'local', NULL),
(364, 'alquiler de vallas Mao', 'alquiler de vallas mao', 'local', NULL),
(365, 'alquiler de vallas Moca', 'alquiler de vallas moca', 'local', NULL),
(366, 'alquiler de vallas Monte Cristi', 'alquiler de vallas monte cristi', 'local', NULL),
(367, 'alquiler de vallas Nagua', 'alquiler de vallas nagua', 'local', NULL),
(368, 'alquiler de vallas Puerto Plata', 'alquiler de vallas puerto plata', 'local', NULL),
(369, 'alquiler de vallas Punta Cana', 'alquiler de vallas punta cana', 'local', NULL),
(370, 'alquiler de vallas República Dominicana', 'alquiler de vallas republica dominicana', 'local', NULL),
(371, 'alquiler de vallas Samaná', 'alquiler de vallas samana', 'local', NULL),
(372, 'alquiler de vallas San Cristóbal', 'alquiler de vallas san cristobal', 'local', NULL),
(373, 'alquiler de vallas San Juan', 'alquiler de vallas san juan', 'local', NULL),
(374, 'alquiler de vallas San Pedro de Macorís', 'alquiler de vallas san pedro de macoris', 'local', NULL),
(375, 'alquiler de vallas Santiago', 'alquiler de vallas santiago', 'local', NULL),
(376, 'alquiler de vallas Santo Domingo', 'alquiler de vallas santo domingo', 'local', NULL),
(377, 'alquiler de vallas Santo Domingo Este', 'alquiler de vallas santo domingo este', 'local', NULL),
(378, 'alquiler de vallas Santo Domingo Norte', 'alquiler de vallas santo domingo norte', 'local', NULL),
(379, 'alquiler de vallas Santo Domingo Oeste', 'alquiler de vallas santo domingo oeste', 'local', NULL),
(380, 'alquiler de vallas Sosúa', 'alquiler de vallas sosua', 'local', NULL),
(381, 'alquiler de vallas Valverde', 'alquiler de vallas valverde', 'local', NULL),
(382, 'alquiler de vallas Villa Mella', 'alquiler de vallas villa mella', 'local', NULL),
(383, 'alquiler de vallas alcance', 'alquiler de vallas alcance', 'commercial', NULL),
(384, 'alquiler de vallas alcance República Dominicana', 'alquiler de vallas alcance republica dominicana', 'local', NULL),
(385, 'alquiler de vallas alcance en República Dominicana', 'alquiler de vallas alcance en republica dominicana', 'local', NULL),
(386, 'alquiler de vallas alta resolución', 'alquiler de vallas alta resolucion', 'commercial', NULL),
(387, 'alquiler de vallas alta resolución República Dominicana', 'alquiler de vallas alta resolucion republica dominicana', 'local', NULL),
(388, 'alquiler de vallas alta resolución en República Dominicana', 'alquiler de vallas alta resolucion en republica dominicana', 'local', NULL),
(389, 'alquiler de vallas audiencia', 'alquiler de vallas audiencia', 'commercial', NULL),
(390, 'alquiler de vallas audiencia República Dominicana', 'alquiler de vallas audiencia republica dominicana', 'local', NULL),
(391, 'alquiler de vallas audiencia en República Dominicana', 'alquiler de vallas audiencia en republica dominicana', 'local', NULL),
(392, 'alquiler de vallas baratas', 'alquiler de vallas baratas', 'commercial', NULL),
(393, 'alquiler de vallas baratas República Dominicana', 'alquiler de vallas baratas republica dominicana', 'local', NULL),
(394, 'alquiler de vallas baratas en República Dominicana', 'alquiler de vallas baratas en republica dominicana', 'local', NULL),
(395, 'alquiler de vallas calendario', 'alquiler de vallas calendario', 'commercial', NULL),
(396, 'alquiler de vallas calendario República Dominicana', 'alquiler de vallas calendario republica dominicana', 'local', NULL),
(397, 'alquiler de vallas calendario en República Dominicana', 'alquiler de vallas calendario en republica dominicana', 'local', NULL),
(398, 'alquiler de vallas catálogo', 'alquiler de vallas catalogo', 'commercial', NULL),
(399, 'alquiler de vallas catálogo República Dominicana', 'alquiler de vallas catalogo republica dominicana', 'local', NULL),
(400, 'alquiler de vallas catálogo en República Dominicana', 'alquiler de vallas catalogo en republica dominicana', 'local', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `logos_hero`
--

CREATE TABLE `logos_hero` (
  `id` int(11) NOT NULL,
  `archivo` varchar(255) NOT NULL,
  `titulo` varchar(100) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `orden` int(11) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `logs_app`
--

CREATE TABLE `logs_app` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `level` enum('DEBUG','INFO','NOTICE','WARNING','ERROR','CRITICAL','ALERT','EMERGENCY') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ERROR',
  `tipo` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `mensaje` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `archivo` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `linea` int(10) UNSIGNED DEFAULT NULL,
  `url` varchar(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metodo` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip` varbinary(16) DEFAULT NULL,
  `user_agent` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `session_user` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_id` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contexto` json DEFAULT NULL,
  `is_handled` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `logs_app`
--

INSERT INTO `logs_app` (`id`, `created_at`, `level`, `tipo`, `mensaje`, `archivo`, `linea`, `url`, `metodo`, `ip`, `user_agent`, `session_user`, `request_id`, `contexto`, `is_handled`) VALUES
(388, '2025-10-08 18:04:45', 'WARNING', 'php_error', 'session_cache_limiter(): Session cache limiter cannot be changed when a session is active', '/www/wwwroot/vallasled.com/api/carritos/api.php', 6, 'https://vallasled.com/api/carritos/api.php?a=count', 'GET', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', '0000T3TRJXRabpqsHAH6o76g==', '{\"errno\": 2, \"linea\": 6, \"archivo\": \"/www/wwwroot/vallasled.com/api/carritos/api.php\"}', 0),
(389, '2025-10-08 18:04:45', 'WARNING', 'php_error', 'Undefined array key \"mostrar_precicio_cliente\"', '/www/wwwroot/vallasled.com/api/destacados/api.php', 220, 'https://vallasled.com/api/destacados/api.php?limit=12', 'GET', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', '0000T3TRJXtzyi0DddLcccJw==', '{\"errno\": 2, \"linea\": 220, \"archivo\": \"/www/wwwroot/vallasled.com/api/destacados/api.php\"}', 0),
(390, '2025-10-08 18:04:45', 'WARNING', 'php_error', 'Undefined array key \"mostrar_precicio_cliente\"', '/www/wwwroot/vallasled.com/api/destacados/api.php', 220, 'https://vallasled.com/api/destacados/api.php?limit=12', 'GET', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', '0000T3TRJXtzyi0DddLcccJw==', '{\"errno\": 2, \"linea\": 220, \"archivo\": \"/www/wwwroot/vallasled.com/api/destacados/api.php\"}', 0),
(391, '2025-10-08 18:04:45', 'WARNING', 'php_error', 'Undefined array key \"mostrar_precicio_cliente\"', '/www/wwwroot/vallasled.com/api/destacados/api.php', 220, 'https://vallasled.com/api/destacados/api.php?limit=12', 'GET', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', '0000T3TRJXtzyi0DddLcccJw==', '{\"errno\": 2, \"linea\": 220, \"archivo\": \"/www/wwwroot/vallasled.com/api/destacados/api.php\"}', 0),
(392, '2025-10-08 18:04:46', 'WARNING', 'php_error', 'session_cache_limiter(): Session cache limiter cannot be changed when a session is active', '/www/wwwroot/vallasled.com/api/carritos/api.php', 6, 'https://vallasled.com/api/carritos/api.php?a=count', 'GET', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', '0000T3TRJY01Q7kjiFRrd7cQ==', '{\"errno\": 2, \"linea\": 6, \"archivo\": \"/www/wwwroot/vallasled.com/api/carritos/api.php\"}', 0),
(393, '2025-10-08 18:40:06', 'WARNING', 'php_error', 'session_cache_limiter(): Session cache limiter cannot be changed when a session is active', '/www/wwwroot/vallasled.com/api/carritos/api.php', 6, 'https://vallasled.com/api/carritos/api.php?a=count', 'GET', 0x42f94fc9, 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; Googlebot/2.1; +http://www.google.com/bot.html) Chrome/140.0.7339.207 Safari/537.36', '', '0000T3TT6UfdZbO74FFncsMw==', '{\"errno\": 2, \"linea\": 6, \"archivo\": \"/www/wwwroot/vallasled.com/api/carritos/api.php\"}', 0),
(394, '2025-10-08 18:40:07', 'WARNING', 'php_error', 'Undefined array key \"mostrar_precicio_cliente\"', '/www/wwwroot/vallasled.com/api/destacados/api.php', 220, 'https://vallasled.com/api/destacados/api.php?limit=12', 'GET', 0x42f94fc9, 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; Googlebot/2.1; +http://www.google.com/bot.html) Chrome/140.0.7339.207 Safari/537.36', '', '0000T3TT6VMnliqlxay0AtZw==', '{\"errno\": 2, \"linea\": 220, \"archivo\": \"/www/wwwroot/vallasled.com/api/destacados/api.php\"}', 0),
(395, '2025-10-08 18:40:07', 'WARNING', 'php_error', 'Undefined array key \"mostrar_precicio_cliente\"', '/www/wwwroot/vallasled.com/api/destacados/api.php', 220, 'https://vallasled.com/api/destacados/api.php?limit=12', 'GET', 0x42f94fc9, 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; Googlebot/2.1; +http://www.google.com/bot.html) Chrome/140.0.7339.207 Safari/537.36', '', '0000T3TT6VMnliqlxay0AtZw==', '{\"errno\": 2, \"linea\": 220, \"archivo\": \"/www/wwwroot/vallasled.com/api/destacados/api.php\"}', 0),
(396, '2025-10-08 18:40:07', 'WARNING', 'php_error', 'Undefined array key \"mostrar_precicio_cliente\"', '/www/wwwroot/vallasled.com/api/destacados/api.php', 220, 'https://vallasled.com/api/destacados/api.php?limit=12', 'GET', 0x42f94fc9, 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; Googlebot/2.1; +http://www.google.com/bot.html) Chrome/140.0.7339.207 Safari/537.36', '', '0000T3TT6VMnliqlxay0AtZw==', '{\"errno\": 2, \"linea\": 220, \"archivo\": \"/www/wwwroot/vallasled.com/api/destacados/api.php\"}', 0),
(397, '2025-10-08 18:40:08', 'WARNING', 'php_error', 'Undefined array key \"mostrar_precicio_cliente\"', '/www/wwwroot/vallasled.com/api/destacados/api.php', 220, 'https://vallasled.com/api/destacados/api.php?limit=12', 'GET', 0x42f94fc8, 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.7339.207 Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', '', '0000T3TT6WeBLvcUcUJ6tA9g==', '{\"errno\": 2, \"linea\": 220, \"archivo\": \"/www/wwwroot/vallasled.com/api/destacados/api.php\"}', 0),
(398, '2025-10-08 18:40:08', 'WARNING', 'php_error', 'Undefined array key \"mostrar_precicio_cliente\"', '/www/wwwroot/vallasled.com/api/destacados/api.php', 220, 'https://vallasled.com/api/destacados/api.php?limit=12', 'GET', 0x42f94fc8, 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.7339.207 Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', '', '0000T3TT6WeBLvcUcUJ6tA9g==', '{\"errno\": 2, \"linea\": 220, \"archivo\": \"/www/wwwroot/vallasled.com/api/destacados/api.php\"}', 0),
(399, '2025-10-08 18:40:08', 'WARNING', 'php_error', 'Undefined array key \"mostrar_precicio_cliente\"', '/www/wwwroot/vallasled.com/api/destacados/api.php', 220, 'https://vallasled.com/api/destacados/api.php?limit=12', 'GET', 0x42f94fc8, 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.7339.207 Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', '', '0000T3TT6WeBLvcUcUJ6tA9g==', '{\"errno\": 2, \"linea\": 220, \"archivo\": \"/www/wwwroot/vallasled.com/api/destacados/api.php\"}', 0),
(400, '2025-10-08 18:40:09', 'WARNING', 'php_error', 'session_cache_limiter(): Session cache limiter cannot be changed when a session is active', '/www/wwwroot/vallasled.com/api/carritos/api.php', 6, 'https://vallasled.com/api/carritos/api.php?a=count', 'GET', 0x42f94fc9, 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.7339.207 Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', '', '0000T3TT6XA8veYdlMpcTsoQ==', '{\"errno\": 2, \"linea\": 6, \"archivo\": \"/www/wwwroot/vallasled.com/api/carritos/api.php\"}', 0),
(401, '2025-10-08 18:57:21', 'WARNING', 'php_error', 'session_cache_limiter(): Session cache limiter cannot be changed when a session is active', '/www/wwwroot/vallasled.com/api/carritos/api.php', 6, 'https://vallasled.com/api/carritos/api.php?a=count', 'GET', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', '0000T3TTZLjjKs5nAYnAeH0A==', '{\"errno\": 2, \"linea\": 6, \"archivo\": \"/www/wwwroot/vallasled.com/api/carritos/api.php\"}', 0),
(402, '2025-10-08 18:57:21', 'WARNING', 'php_error', 'Undefined array key \"mostrar_precicio_cliente\"', '/www/wwwroot/vallasled.com/api/destacados/api.php', 220, 'https://vallasled.com/api/destacados/api.php?limit=12', 'GET', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', '0000T3TTZL1PzInJEVQ6jGTw==', '{\"errno\": 2, \"linea\": 220, \"archivo\": \"/www/wwwroot/vallasled.com/api/destacados/api.php\"}', 0),
(403, '2025-10-08 18:57:21', 'WARNING', 'php_error', 'Undefined array key \"mostrar_precicio_cliente\"', '/www/wwwroot/vallasled.com/api/destacados/api.php', 220, 'https://vallasled.com/api/destacados/api.php?limit=12', 'GET', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', '0000T3TTZL1PzInJEVQ6jGTw==', '{\"errno\": 2, \"linea\": 220, \"archivo\": \"/www/wwwroot/vallasled.com/api/destacados/api.php\"}', 0),
(404, '2025-10-08 18:57:21', 'WARNING', 'php_error', 'Undefined array key \"mostrar_precicio_cliente\"', '/www/wwwroot/vallasled.com/api/destacados/api.php', 220, 'https://vallasled.com/api/destacados/api.php?limit=12', 'GET', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', '0000T3TTZL1PzInJEVQ6jGTw==', '{\"errno\": 2, \"linea\": 220, \"archivo\": \"/www/wwwroot/vallasled.com/api/destacados/api.php\"}', 0),
(405, '2025-10-08 18:57:21', 'WARNING', 'php_error', 'session_cache_limiter(): Session cache limiter cannot be changed when a session is active', '/www/wwwroot/vallasled.com/api/carritos/api.php', 6, 'https://vallasled.com/api/carritos/api.php?a=count', 'GET', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', '0000T3TTZLNAhdotWIM0uHjA==', '{\"errno\": 2, \"linea\": 6, \"archivo\": \"/www/wwwroot/vallasled.com/api/carritos/api.php\"}', 0),
(406, '2025-10-08 19:04:18', 'WARNING', 'php_error', 'session_cache_limiter(): Session cache limiter cannot be changed when a session is active', '/www/wwwroot/vallasled.com/api/carritos/api.php', 6, 'https://vallasled.com/api/carritos/api.php?a=count', 'GET', 0xba06373b, 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/141.0.7390.41 Mobile/15E148 Safari/604.1', '', '0000T3TUB61Ru7OXwHvSZMyQ==', '{\"errno\": 2, \"linea\": 6, \"archivo\": \"/www/wwwroot/vallasled.com/api/carritos/api.php\"}', 0),
(407, '2025-10-08 19:04:18', 'WARNING', 'php_error', 'Undefined array key \"mostrar_precicio_cliente\"', '/www/wwwroot/vallasled.com/api/destacados/api.php', 220, 'https://vallasled.com/api/destacados/api.php?limit=12', 'GET', 0xba06373b, 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/141.0.7390.41 Mobile/15E148 Safari/604.1', '', '0000T3TUB6vP0mHBtLgINvRA==', '{\"errno\": 2, \"linea\": 220, \"archivo\": \"/www/wwwroot/vallasled.com/api/destacados/api.php\"}', 0),
(408, '2025-10-08 19:04:18', 'WARNING', 'php_error', 'Undefined array key \"mostrar_precicio_cliente\"', '/www/wwwroot/vallasled.com/api/destacados/api.php', 220, 'https://vallasled.com/api/destacados/api.php?limit=12', 'GET', 0xba06373b, 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/141.0.7390.41 Mobile/15E148 Safari/604.1', '', '0000T3TUB6vP0mHBtLgINvRA==', '{\"errno\": 2, \"linea\": 220, \"archivo\": \"/www/wwwroot/vallasled.com/api/destacados/api.php\"}', 0),
(409, '2025-10-08 19:04:18', 'WARNING', 'php_error', 'Undefined array key \"mostrar_precicio_cliente\"', '/www/wwwroot/vallasled.com/api/destacados/api.php', 220, 'https://vallasled.com/api/destacados/api.php?limit=12', 'GET', 0xba06373b, 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/141.0.7390.41 Mobile/15E148 Safari/604.1', '', '0000T3TUB6vP0mHBtLgINvRA==', '{\"errno\": 2, \"linea\": 220, \"archivo\": \"/www/wwwroot/vallasled.com/api/destacados/api.php\"}', 0),
(410, '2025-10-08 19:46:42', 'WARNING', 'php_error', 'session_cache_limiter(): Session cache limiter cannot be changed when a session is active', '/www/wwwroot/vallasled.com/api/carritos/api.php', 6, 'https://vallasled.com/api/carritos/api.php?a=count', 'GET', 0x98a69cc7, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '', '0000T3TW9UZZkCZTcqvQjGqQ==', '{\"errno\": 2, \"linea\": 6, \"archivo\": \"/www/wwwroot/vallasled.com/api/carritos/api.php\"}', 0),
(411, '2025-10-08 19:46:42', 'WARNING', 'php_error', 'Undefined array key \"mostrar_precicio_cliente\"', '/www/wwwroot/vallasled.com/api/destacados/api.php', 220, 'https://vallasled.com/api/destacados/api.php?limit=12', 'GET', 0x98a69cc7, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '', '0000T3TW9UMnJeJukOAHHFJg==', '{\"errno\": 2, \"linea\": 220, \"archivo\": \"/www/wwwroot/vallasled.com/api/destacados/api.php\"}', 0),
(412, '2025-10-08 19:46:42', 'WARNING', 'php_error', 'Undefined array key \"mostrar_precicio_cliente\"', '/www/wwwroot/vallasled.com/api/destacados/api.php', 220, 'https://vallasled.com/api/destacados/api.php?limit=12', 'GET', 0x98a69cc7, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '', '0000T3TW9UMnJeJukOAHHFJg==', '{\"errno\": 2, \"linea\": 220, \"archivo\": \"/www/wwwroot/vallasled.com/api/destacados/api.php\"}', 0),
(413, '2025-10-08 19:46:42', 'WARNING', 'php_error', 'Undefined array key \"mostrar_precicio_cliente\"', '/www/wwwroot/vallasled.com/api/destacados/api.php', 220, 'https://vallasled.com/api/destacados/api.php?limit=12', 'GET', 0x98a69cc7, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '', '0000T3TW9UMnJeJukOAHHFJg==', '{\"errno\": 2, \"linea\": 220, \"archivo\": \"/www/wwwroot/vallasled.com/api/destacados/api.php\"}', 0),
(414, '2025-10-08 19:46:43', 'WARNING', 'php_error', 'session_cache_limiter(): Session cache limiter cannot be changed when a session is active', '/www/wwwroot/vallasled.com/api/carritos/api.php', 6, 'https://vallasled.com/api/carritos/api.php?a=count', 'GET', 0x98a69cc7, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '', '0000T3TW9V2jKyyQsgoiVM8Q==', '{\"errno\": 2, \"linea\": 6, \"archivo\": \"/www/wwwroot/vallasled.com/api/carritos/api.php\"}', 0),
(415, '2025-10-08 20:42:46', 'WARNING', 'php_error', 'session_cache_limiter(): Session cache limiter cannot be changed when a session is active', '/www/wwwroot/vallasled.com/api/carritos/api.php', 6, 'https://vallasled.com/api/carritos/api.php?a=count', 'GET', 0x98a69cc7, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '', '0000T3TYVATzAtUab5ZIARLw==', '{\"errno\": 2, \"linea\": 6, \"archivo\": \"/www/wwwroot/vallasled.com/api/carritos/api.php\"}', 0),
(416, '2025-10-08 20:42:46', 'WARNING', 'php_error', 'Undefined array key \"mostrar_precicio_cliente\"', '/www/wwwroot/vallasled.com/api/destacados/api.php', 220, 'https://vallasled.com/api/destacados/api.php?limit=12', 'GET', 0x98a69cc7, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '', '0000T3TYVAHmRGLaVuZweUfA==', '{\"errno\": 2, \"linea\": 220, \"archivo\": \"/www/wwwroot/vallasled.com/api/destacados/api.php\"}', 0),
(417, '2025-10-08 20:42:46', 'WARNING', 'php_error', 'Undefined array key \"mostrar_precicio_cliente\"', '/www/wwwroot/vallasled.com/api/destacados/api.php', 220, 'https://vallasled.com/api/destacados/api.php?limit=12', 'GET', 0x98a69cc7, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '', '0000T3TYVAHmRGLaVuZweUfA==', '{\"errno\": 2, \"linea\": 220, \"archivo\": \"/www/wwwroot/vallasled.com/api/destacados/api.php\"}', 0),
(418, '2025-10-08 20:42:46', 'WARNING', 'php_error', 'Undefined array key \"mostrar_precicio_cliente\"', '/www/wwwroot/vallasled.com/api/destacados/api.php', 220, 'https://vallasled.com/api/destacados/api.php?limit=12', 'GET', 0x98a69cc7, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '', '0000T3TYVAHmRGLaVuZweUfA==', '{\"errno\": 2, \"linea\": 220, \"archivo\": \"/www/wwwroot/vallasled.com/api/destacados/api.php\"}', 0),
(419, '2025-10-08 20:42:47', 'WARNING', 'php_error', 'session_cache_limiter(): Session cache limiter cannot be changed when a session is active', '/www/wwwroot/vallasled.com/api/carritos/api.php', 6, 'https://vallasled.com/api/carritos/api.php?a=count', 'GET', 0x98a69cc7, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '', '0000T3TYVBstBY6KOZlYq4ig==', '{\"errno\": 2, \"linea\": 6, \"archivo\": \"/www/wwwroot/vallasled.com/api/carritos/api.php\"}', 0),
(420, '2025-10-08 20:44:41', 'WARNING', 'php_error', 'session_cache_limiter(): Session cache limiter cannot be changed when a session is active', '/www/wwwroot/vallasled.com/api/carritos/api.php', 6, 'https://vallasled.com/api/carritos/api.php?a=count', 'GET', 0x94ff2cb5, 'Mozilla/5.0 (Linux; Android 15; 23129RA5FL Build/AQ3A.240829.003; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/141.0.7390.59 Mobile Safari/537.36 Instagram 401.0.0.48.79 Android (35/15; 440dpi; 1080x2400; Xiaomi/Redmi; 23129RA5FL; sapphire; qcom; es_US; 802602546; IABMV/1)', '', '0000T3TYYHmgZ9gPDf8U4MJw==', '{\"errno\": 2, \"linea\": 6, \"archivo\": \"/www/wwwroot/vallasled.com/api/carritos/api.php\"}', 0),
(421, '2025-10-08 20:44:41', 'WARNING', 'php_error', 'Undefined array key \"mostrar_precicio_cliente\"', '/www/wwwroot/vallasled.com/api/destacados/api.php', 220, 'https://vallasled.com/api/destacados/api.php?limit=12', 'GET', 0x94ff2cb5, 'Mozilla/5.0 (Linux; Android 15; 23129RA5FL Build/AQ3A.240829.003; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/141.0.7390.59 Mobile Safari/537.36 Instagram 401.0.0.48.79 Android (35/15; 440dpi; 1080x2400; Xiaomi/Redmi; 23129RA5FL; sapphire; qcom; es_US; 802602546; IABMV/1)', '', '0000T3TYYHwvPSZ29wTNBpyg==', '{\"errno\": 2, \"linea\": 220, \"archivo\": \"/www/wwwroot/vallasled.com/api/destacados/api.php\"}', 0),
(422, '2025-10-08 20:44:41', 'WARNING', 'php_error', 'Undefined array key \"mostrar_precicio_cliente\"', '/www/wwwroot/vallasled.com/api/destacados/api.php', 220, 'https://vallasled.com/api/destacados/api.php?limit=12', 'GET', 0x94ff2cb5, 'Mozilla/5.0 (Linux; Android 15; 23129RA5FL Build/AQ3A.240829.003; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/141.0.7390.59 Mobile Safari/537.36 Instagram 401.0.0.48.79 Android (35/15; 440dpi; 1080x2400; Xiaomi/Redmi; 23129RA5FL; sapphire; qcom; es_US; 802602546; IABMV/1)', '', '0000T3TYYHwvPSZ29wTNBpyg==', '{\"errno\": 2, \"linea\": 220, \"archivo\": \"/www/wwwroot/vallasled.com/api/destacados/api.php\"}', 0),
(423, '2025-10-08 20:44:41', 'WARNING', 'php_error', 'Undefined array key \"mostrar_precicio_cliente\"', '/www/wwwroot/vallasled.com/api/destacados/api.php', 220, 'https://vallasled.com/api/destacados/api.php?limit=12', 'GET', 0x94ff2cb5, 'Mozilla/5.0 (Linux; Android 15; 23129RA5FL Build/AQ3A.240829.003; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/141.0.7390.59 Mobile Safari/537.36 Instagram 401.0.0.48.79 Android (35/15; 440dpi; 1080x2400; Xiaomi/Redmi; 23129RA5FL; sapphire; qcom; es_US; 802602546; IABMV/1)', '', '0000T3TYYHwvPSZ29wTNBpyg==', '{\"errno\": 2, \"linea\": 220, \"archivo\": \"/www/wwwroot/vallasled.com/api/destacados/api.php\"}', 0);

-- --------------------------------------------------------

--
-- Table structure for table `logs_app_blob`
--

CREATE TABLE `logs_app_blob` (
  `log_id` bigint(20) UNSIGNED NOT NULL,
  `parte` tinyint(3) UNSIGNED NOT NULL DEFAULT '1',
  `payload` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `map_providers`
--

CREATE TABLE `map_providers` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(32) NOT NULL,
  `name` varchar(80) NOT NULL,
  `is_free` tinyint(1) NOT NULL DEFAULT '1',
  `requires_key` tinyint(1) NOT NULL DEFAULT '0',
  `site_url` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `map_providers`
--

INSERT INTO `map_providers` (`id`, `code`, `name`, `is_free`, `requires_key`, `site_url`) VALUES
(1, 'osm', 'OpenStreetMap', 1, 0, 'https://www.openstreetmap.org'),
(2, 'carto', 'CARTO Basemaps', 1, 0, 'https://carto.com/attributions'),
(3, 'wikimedia', 'Wikimedia Maps', 1, 0, 'https://foundation.wikimedia.org/wiki/Maps_Terms_of_Use'),
(4, 'opentopomap', 'OpenTopoMap', 1, 0, 'https://opentopomap.org/about'),
(5, 'osmfr_hot', 'OSM France HOT', 1, 0, 'https://www.openstreetmap.fr/tiles/'),
(6, 'google', 'Google Maps', 1, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `map_settings`
--

CREATE TABLE `map_settings` (
  `id` tinyint(3) UNSIGNED NOT NULL DEFAULT '1',
  `provider_code` varchar(32) NOT NULL,
  `style_code` varchar(48) NOT NULL,
  `lat` decimal(10,7) NOT NULL DEFAULT '18.4860580',
  `lng` decimal(10,7) NOT NULL DEFAULT '-69.9312120',
  `zoom` tinyint(3) UNSIGNED NOT NULL DEFAULT '12',
  `map_id` varchar(64) DEFAULT NULL,
  `style_url` varchar(255) DEFAULT NULL,
  `token` varchar(128) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `map_settings`
--

INSERT INTO `map_settings` (`id`, `provider_code`, `style_code`, `lat`, `lng`, `zoom`, `map_id`, `style_url`, `token`) VALUES
(1, 'osm', 'osm.standard', 18.4860580, -69.9312120, 12, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `map_styles`
--

CREATE TABLE `map_styles` (
  `id` int(10) UNSIGNED NOT NULL,
  `provider_code` varchar(32) NOT NULL,
  `style_code` varchar(48) NOT NULL,
  `style_name` varchar(80) NOT NULL,
  `tile_url` varchar(255) NOT NULL,
  `subdomains` varchar(32) DEFAULT NULL,
  `attribution_html` varchar(512) NOT NULL,
  `preview_image` varchar(255) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `is_free` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `map_styles`
--

INSERT INTO `map_styles` (`id`, `provider_code`, `style_code`, `style_name`, `tile_url`, `subdomains`, `attribution_html`, `preview_image`, `is_default`, `is_free`) VALUES
(1, 'osm', 'osm.standard', 'OSM Standard', 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', 'abc', '&copy; <a href=\"https://www.openstreetmap.org/copyright\">OSM</a> contributors', '/uploads/maps/osm_standard.png', 1, 1),
(2, 'carto', 'carto.positron', 'CARTO Positron (light)', 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', 'abcd', '&copy; OSM contributors &copy; <a href=\"https://carto.com/attributions\">CARTO</a>', '/uploads/maps/carto_positron.png', 0, 1),
(3, 'carto', 'carto.dark_matter', 'CARTO Dark Matter', 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', 'abcd', '&copy; OSM contributors &copy; <a href=\"https://carto.com/attributions\">CARTO</a>', '/uploads/maps/carto_dark.png', 0, 1),
(4, 'wikimedia', 'wikimedia.osm_intl', 'Wikimedia OSM Intl', 'https://maps.wikimedia.org/osm-intl/{z}/{x}/{y}.png', NULL, '&copy; OSM contributors &copy; Wikimedia', '/uploads/maps/wikimedia_intl.png', 0, 1),
(5, 'opentopomap', 'opentopo.topo', 'OpenTopoMap', 'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', 'abc', 'Map data: &copy; OSM contributors, SRTM | Map style: &copy; <a href=\"https://opentopomap.org\">OpenTopoMap</a>', '/uploads/maps/opentopo.png', 0, 1),
(6, 'osmfr_hot', 'osmfr.hot', 'OSM France HOT', 'https://{s}.tile.openstreetmap.fr/hot/{z}/{x}/{y}.png', 'abc', '&copy; OSM contributors | Tiles: OSM France', '/uploads/maps/osm_hot.png', 0, 1);

-- --------------------------------------------------------

--
-- Table structure for table `notas_credito`
--

CREATE TABLE `notas_credito` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `factura_id` int(11) DEFAULT NULL,
  `monto` decimal(10,2) NOT NULL,
  `motivo` varchar(255) DEFAULT NULL,
  `fecha` datetime DEFAULT CURRENT_TIMESTAMP,
  `estado` enum('pendiente','reembolsado','aplicado') DEFAULT 'pendiente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `notificaciones`
--

CREATE TABLE `notificaciones` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `tipo` enum('cancelacion','reserva','pago','otro') NOT NULL,
  `referencia_id` int(11) DEFAULT NULL,
  `mensaje` text NOT NULL,
  `leida` tinyint(1) DEFAULT '0',
  `estado` enum('pendiente','aceptada','rechazada') DEFAULT 'pendiente',
  `creada` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `notificaciones`
--

INSERT INTO `notificaciones` (`id`, `usuario_id`, `tipo`, `referencia_id`, `mensaje`, `leida`, `estado`, `creada`) VALUES
(1, 2, 'cancelacion', 2, 'Tu solicitud de cancelación de la factura #2 fue rechazada por el administrador.', 1, 'pendiente', '2025-05-16 16:50:47'),
(2, 2, 'cancelacion', 4, 'Tu solicitud de cancelación de la factura #4 fue rechazada por el administrador.', 1, 'pendiente', '2025-05-17 01:45:21'),
(3, 2, 'cancelacion', 2, 'Tu solicitud de cancelación de la factura #2 fue rechazada por el administrador.', 1, 'pendiente', '2025-05-17 20:28:14'),
(4, 2, 'cancelacion', 4, 'Tu solicitud de cancelación de la factura #4 fue rechazada por el administrador.', 1, 'pendiente', '2025-05-17 20:28:15');

-- --------------------------------------------------------

--
-- Table structure for table `notif_log`
--

CREATE TABLE `notif_log` (
  `id` bigint(20) NOT NULL,
  `proveedor_id` int(11) NOT NULL,
  `tipo` varchar(40) NOT NULL,
  `ref_id` bigint(20) NOT NULL,
  `canal` varchar(20) NOT NULL,
  `destino` varchar(190) NOT NULL,
  `ymd` int(11) NOT NULL,
  `status` varchar(20) NOT NULL,
  `msg` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `pages`
--

CREATE TABLE `pages` (
  `id` bigint(20) NOT NULL,
  `slug` varchar(191) NOT NULL,
  `title` varchar(191) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `page_keywords`
--

CREATE TABLE `page_keywords` (
  `page_id` bigint(20) NOT NULL,
  `keyword_id` bigint(20) NOT NULL,
  `score` tinyint(4) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `periodos_no_disponibles`
--

CREATE TABLE `periodos_no_disponibles` (
  `id` int(11) NOT NULL,
  `valla_id` int(11) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `motivo` varchar(255) DEFAULT 'No Disponible',
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `proveedores`
--

CREATE TABLE `proveedores` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `contacto` varchar(100) DEFAULT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `url_pdf` varchar(255) DEFAULT NULL,
  `estado` tinyint(1) DEFAULT '1',
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `proveedores`
--

INSERT INTO `proveedores` (`id`, `nombre`, `contacto`, `telefono`, `email`, `direccion`, `url_pdf`, `estado`, `creado`) VALUES
(1, 'Fox Publicidad', 'Vladimir Ortiz', '8493565448', 'infofoxpublicidad@gmail.com', 'avenida enriquilo 89', NULL, 1, '2025-06-24 00:09:15'),
(2, 'Proveedor TestSQL', 'Juan Prueba', '8099990000', 'prueba@proveedor.com', 'Test dirección', '', 0, '2025-06-24 00:37:23'),
(3, 'VALLAS PAUL', 'Noemi', '8492440575', '', 'Villa juana', NULL, 1, '2025-06-27 19:06:44'),
(4, 'Grupoohla srl', 'Jhon morfe', '8493623388', 'info@ship24go.com', 'Avenida enriquillo 89', '', 0, '2025-08-21 21:00:31'),
(5, 'Alfrit srl', 'jose antonio peralta', '8293673388', 'morfemorfe@gmail.yyy', 'C. Cesar Augusto Roque 37-33', '', 0, '2025-08-23 22:03:49'),
(6, 'whx70q', 't2d4bt', '704670968824', 'paouqua@mailbox.in.ua', 'w21ml1', 'https://redmak.com.tr/index.php?pejx14', 0, '2025-09-02 08:30:45'),
(7, 'Vallas Universal', 'Alexander Roberto Minaya', '8299042125', '', '', NULL, 1, '2025-09-15 12:38:10'),
(8, 'admin', '', '', 'admin@vallard.com', '', '', 1, '2025-09-19 23:59:50'),
(9, 'Captiva', 'Karla Compres', '89899999099', 'fkkffkdk@fkkfl.com', '', NULL, 1, '2025-10-06 16:52:48'),
(10, 'Colorin', '', '', '', '', NULL, 1, '2025-10-06 16:54:22');

-- --------------------------------------------------------

--
-- Table structure for table `provincias`
--

CREATE TABLE `provincias` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `provincias`
--

INSERT INTO `provincias` (`id`, `nombre`) VALUES
(2, 'Azua'),
(3, 'Bahoruco'),
(4, 'Barahona'),
(5, 'Dajabón'),
(1, 'Distrito Nacional'),
(6, 'Duarte'),
(8, 'El Seibo'),
(7, 'Elías Piña'),
(9, 'Espaillat'),
(10, 'Hato Mayor'),
(11, 'Hermanas Mirabal'),
(12, 'Independencia'),
(13, 'La Altagracia'),
(14, 'La Romana'),
(15, 'La Vega'),
(16, 'María Trinidad Sánchez'),
(17, 'Monseñor Nouel'),
(18, 'Monte Cristi'),
(19, 'Monte Plata'),
(20, 'Pedernales'),
(21, 'Peravia'),
(22, 'Puerto Plata'),
(23, 'Samaná'),
(24, 'San Cristóbal'),
(25, 'San José de Ocoa'),
(26, 'San Juan'),
(27, 'San Pedro de Macorís'),
(28, 'Sánchez Ramírez'),
(29, 'Santiago'),
(30, 'Santiago Rodríguez'),
(32, 'Santo Domingo'),
(31, 'Valverde');

-- --------------------------------------------------------

--
-- Table structure for table `recibos_transferencia`
--

CREATE TABLE `recibos_transferencia` (
  `id` int(11) NOT NULL,
  `factura_id` int(11) DEFAULT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `archivo` varchar(255) DEFAULT NULL,
  `fecha_subida` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `reservas`
--

CREATE TABLE `reservas` (
  `id` int(11) NOT NULL,
  `valla_id` int(11) DEFAULT NULL,
  `nombre_cliente` varchar(100) DEFAULT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `estado` enum('pendiente','confirmada','activa','cancelada','finalizada') DEFAULT 'confirmada',
  `orden` int(11) NOT NULL DEFAULT '1',
  `archivo_contenido` mediumtext,
  `usuario_id` int(11) DEFAULT NULL,
  `es_tercero` tinyint(1) DEFAULT '0',
  `factura_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `reservas`
--

INSERT INTO `reservas` (`id`, `valla_id`, `nombre_cliente`, `fecha_inicio`, `fecha_fin`, `estado`, `orden`, `archivo_contenido`, `usuario_id`, `es_tercero`, `factura_id`) VALUES
(1, 1, 'Empresa Demo', '2025-05-20', '2025-05-30', 'confirmada', 1, NULL, NULL, 0, NULL),
(2, 1, 'morfe@ship24.do', '2025-05-16', '2025-05-17', 'confirmada', 1, '', 2, 0, NULL),
(3, 1, 'morfe@ship24.do', '2025-06-19', '2025-07-18', 'confirmada', 1, NULL, 2, 0, 2),
(4, 2, 'vladi.ortiz@gmail.com', '2025-05-18', '2025-05-31', 'confirmada', 1, '../uploads/1747531296_Necesitas Asistencia vial 3-1.png', 7, 0, 5),
(5, 6, 'vladi.ortiz@gmail.com', '2025-05-17', '2025-05-31', 'confirmada', 1, '../uploads/1747531462_Mesa de trabajo 6_1@2x-1.png', 7, 0, 6),
(6, 90, 'fox pack', '2025-07-03', '2025-07-18', 'confirmada', 1, NULL, 3, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `roles_permisos`
--

CREATE TABLE `roles_permisos` (
  `id` int(11) NOT NULL,
  `rol` varchar(30) NOT NULL,
  `permiso` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `roles_permisos`
--

INSERT INTO `roles_permisos` (`id`, `rol`, `permiso`) VALUES
(1, 'admin', 'ver_todo'),
(2, 'admin', 'crear_clientes'),
(3, 'admin', 'editar_clientes'),
(4, 'admin', 'eliminar_clientes'),
(5, 'operador', 'ver_clientes'),
(6, 'operador', 'crear_clientes'),
(7, 'supervisor', 'ver_clientes'),
(8, 'supervisor', 'editar_clientes'),
(9, 'admin', 'menu:dashboard'),
(10, 'admin', 'menu:vallas'),
(11, 'admin', 'menu:reservas'),
(12, 'admin', 'menu:facturacion'),
(13, 'admin', 'menu:crm'),
(14, 'admin', 'menu:reportes'),
(15, 'admin', 'menu:mapa'),
(16, 'admin', 'menu:config'),
(17, 'supervisor', 'menu:vallas'),
(18, 'supervisor', 'menu:reservas'),
(19, 'supervisor', 'menu:reportes'),
(20, 'operador', 'menu:vallas'),
(21, 'operador', 'menu:reservas'),
(22, 'operador', 'menu:mapa'),
(23, 'gestor', 'menu:reportes'),
(24, 'gestor', 'menu:mapa'),
(25, 'visor', 'menu:reportes'),
(26, 'visor', 'menu:mapa');

-- --------------------------------------------------------

--
-- Table structure for table `secuencias_ncf`
--

CREATE TABLE `secuencias_ncf` (
  `id` int(11) NOT NULL,
  `tipo` varchar(3) NOT NULL,
  `descripcion` varchar(100) NOT NULL,
  `serie` varchar(2) NOT NULL,
  `desde` bigint(20) NOT NULL,
  `hasta` bigint(20) NOT NULL,
  `vigente` tinyint(1) DEFAULT '1',
  `fecha_inicio` date NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `resolucion` varchar(50) DEFAULT NULL,
  `localidad` varchar(100) DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `secuencias_ncf`
--

INSERT INTO `secuencias_ncf` (`id`, `tipo`, `descripcion`, `serie`, `desde`, `hasta`, `vigente`, `fecha_inicio`, `fecha_vencimiento`, `resolucion`, `localidad`, `fecha_creacion`) VALUES
(1, 'B01', 'Comprobante', '9', 1, 11, 1, '2025-05-17', '2025-09-24', '', NULL, '2025-05-17 11:14:39'),
(2, '01', 'NCF B01 00000001-00000011', 'B', 1, 11, 0, '2025-08-23', '2026-08-30', NULL, NULL, '2025-08-23 10:56:45');

-- --------------------------------------------------------

--
-- Table structure for table `solicitudes_cancelacion`
--

CREATE TABLE `solicitudes_cancelacion` (
  `id` int(11) NOT NULL,
  `factura_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `estado` enum('pendiente','aprobada','rechazada') DEFAULT 'pendiente',
  `motivo` text,
  `fecha_solicitud` datetime DEFAULT CURRENT_TIMESTAMP,
  `notificado_cliente` tinyint(1) DEFAULT '0',
  `notificado_admin` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `solicitudes_cancelacion`
--

INSERT INTO `solicitudes_cancelacion` (`id`, `factura_id`, `usuario_id`, `estado`, `motivo`, `fecha_solicitud`, `notificado_cliente`, `notificado_admin`) VALUES
(1, 1, 2, 'rechazada', 'test', '2025-05-16 10:24:12', 1, 1),
(2, 1, 2, 'rechazada', 'yghjk', '2025-05-16 10:33:18', 1, 1),
(3, 1, 2, 'rechazada', 'boj', '2025-05-16 10:43:31', 1, 1),
(4, 1, 2, 'rechazada', 'fhfgj', '2025-05-16 10:59:57', 1, 1),
(5, 1, 2, 'rechazada', 'zdgsd', '2025-05-16 11:00:44', 1, 1),
(6, 1, 2, 'rechazada', 'fghdfg', '2025-05-16 11:12:25', 1, 1),
(7, 2, 2, 'aprobada', 'gg', '2025-05-16 11:35:04', 1, 1),
(8, 4, 2, 'rechazada', 'ss', '2025-05-16 16:33:56', 1, 0),
(9, 2, 2, 'rechazada', 'a', '2025-05-16 16:48:51', 1, 0),
(10, 4, 2, 'rechazada', 'Kj', '2025-05-17 03:57:42', 0, 0),
(11, 2, 2, 'rechazada', 'Ok', '2025-05-17 03:58:17', 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `tipos_pago_licencia`
--

CREATE TABLE `tipos_pago_licencia` (
  `id` int(11) NOT NULL,
  `nombre` enum('Pago único','Pago mensual','Pago trimestral','Pago semestral','Pago anual','Pago con vencimiento flexible','Pago por suscripción','Pago anticipado','Pago a plazos','Pago por comisión','Pago por volumen','Pago según condiciones específicas (negociable)') NOT NULL,
  `monto_total` decimal(10,2) NOT NULL DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `usuario` varchar(100) DEFAULT NULL,
  `clave` varchar(100) DEFAULT NULL,
  `tipo` enum('admin','cliente','staff') DEFAULT 'cliente',
  `rol` varchar(30) DEFAULT 'operador',
  `activo` tinyint(1) DEFAULT '1',
  `tiene_tarjeta` tinyint(1) DEFAULT '0',
  `nombre_empresa` varchar(150) DEFAULT NULL,
  `cedula` varchar(15) DEFAULT NULL,
  `rnc` varchar(15) DEFAULT NULL,
  `pasaporte` varchar(20) DEFAULT NULL,
  `responsable` varchar(100) DEFAULT NULL,
  `direccion` text,
  `provincia` varchar(100) DEFAULT NULL,
  `municipio` varchar(100) DEFAULT NULL,
  `comprobante_fiscal` tinyint(1) DEFAULT '0',
  `stripe_customer_id` varchar(64) DEFAULT NULL,
  `stripe_payment_method` varchar(64) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `rol_vendor` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `usuarios`
--

INSERT INTO `usuarios` (`id`, `usuario`, `clave`, `tipo`, `rol`, `activo`, `tiene_tarjeta`, `nombre_empresa`, `cedula`, `rnc`, `pasaporte`, `responsable`, `direccion`, `provincia`, `municipio`, `comprobante_fiscal`, `stripe_customer_id`, `stripe_payment_method`, `email`, `rol_vendor`) VALUES
(1, 'grupoohla@gmail.es', '123123', 'cliente', 'operador', 1, 0, 'Ship24go', NULL, '839393838383', NULL, 'Jhon Morfe', 'Avenida Enriquillo 89, Santo Domingo, República Dominicana', 'Distrito Nacional', 'Santo Domingo de Guzman', 0, NULL, NULL, NULL, 0),
(2, 'morfe@ship24.do', '123123', 'cliente', 'operador', 1, 1, 'Ship24go', '40228370710', 'AK930393', '777', 'Jhon Morfe', 'Avenida Independencia 90, Santo Domingo, República Dominicana', 'Distrito Nacional', 'Santo Domingo de Guzman', 0, 'cus_SK7UJOhrSSzMPK', 'pm_1RPTY4LM97O7CJZacrLTGCfL', NULL, 0),
(3, 'admin@vallard.com', 'Valla1234@!', 'admin', 'operador', 1, 0, 'Fox Publicidad', NULL, NULL, NULL, 'Administrador del Sistema', 'Santo Domingo, República Dominicana', 'Distrito Nacional', 'Santo Domingo', 0, NULL, NULL, NULL, 0),
(4, 'tatyvera938@gmail.com', '123123', 'cliente', 'operador', 1, 0, 'Grupoohla S.r.l.', '83833838', NULL, NULL, 'Kdjdje', 'Callejon Bo. Fino, Santiago de los Caballeros, República Dominicana', 'Santiago', 'Puñal', 0, 'cus_SKIBlhl6d2pIFC', NULL, NULL, 0),
(5, 'test@test.com', '123123', 'cliente', 'operador', 1, 0, 'Ship24', '998888', NULL, NULL, 'test', 'Avenida Enriquillo 89, Santo Domingo, República Dominicana', 'Distrito Nacional', 'Santo Domingo de Guzman', 0, NULL, NULL, NULL, 0),
(6, 'lorena@gmail.com', '123123', 'cliente', 'operador', 1, 1, 'Loreb', '77656', NULL, NULL, 'Jhgh', 'Av Enriquillo 8, Santo Domingo, República Dominicana', 'Distrito Nacional', 'Santo Domingo de Guzman', 0, 'cus_SKQS8Lg1sTmfYT', 'pm_1RPld5LM97O7CJZaGGKi88ia', NULL, 0),
(7, 'vladi.ortiz@gmail.com', 'DomChi123', 'cliente', 'operador', 1, 0, 'Domchi SRL', NULL, '131556582', NULL, 'Vladimir Ortiz', 'Calle Barney N. Morgan 189, Santo Domingo, Dominican Republic', 'Distrito Nacional', 'Santo Domingo de Guzman', 1, NULL, NULL, NULL, 0),
(8, 'prueba01@gmail.com', '123123', 'cliente', 'operador', 1, 0, 'Gaia srl', '999888888', '', '', 'Jose miguel', 'Avenida España, Santo Domingo Este, República Dominicana', 'Santo Domingo', 'Santo Domingo Este', 0, NULL, NULL, NULL, 0),
(9, 'Jose', '$2y$10$9kQGpxv/9R6HsC1rcUCmgOTHoqjDc8JXFACvyWkVzGKqPan/rSDoy', 'staff', 'operador', 1, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 'jose@jose.com', 0),
(10, 'morfe09@gmail.com', '123123', 'cliente', 'operador', 1, 0, 'SHIP24GO', '000000000', NULL, NULL, 'TATIANA ZAMORA', 'Avenida Enriquillo 89, Santo Domingo, República Dominicana', 'Distrito Nacional', 'Santo Domingo de Guzman', 0, NULL, NULL, NULL, 0),
(11, 'Morfe@morfe.com', '123123', 'cliente', 'operador', 1, 0, 'Morfe srl', '0999999', NULL, NULL, 'Morfe srl', 'Avenida España, Santo Domingo Este, República Dominicana', 'Santo Domingo', 'Santo Domingo Este', 0, NULL, NULL, NULL, 0),
(12, 'prueba6@gmail.com', '123123', 'cliente', 'operador', 1, 0, 'Prueba', '000000', NULL, NULL, 'prueba', 'Avenida España, Santo Domingo Este, República Dominicana', 'Santo Domingo', 'Santo Domingo Este', 0, NULL, NULL, NULL, 0),
(13, 'ship24go.iuuii', '123123', 'cliente', 'operador', 1, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 'info@ship24go.iuuii', 0),
(14, 'Jhon Morfe', '123123', 'cliente', 'operador', 1, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 'grupo@gmail.com', 0),
(15, 'info@ship24go.com', '$2y$10$1sYV3dAMhhB5wpbkLb2ULOQXhqkvA79TLRCZpXTLgHKzSXGGERWca', 'cliente', 'operador', 1, 0, 'Ship24go srl', NULL, '40228370719', NULL, 'Jhon morfe', 'avenida enriquillo 89', 'Distrito Nacional', 'Santo Domingo', 0, NULL, NULL, 'info@ship24go.com', 1),
(16, 'morfemorfe@gmail.yyy', '$2y$10$CcvxL8cwYrKLxDP4RoUlwu8n9C1zw3f5/KYK7IgHXz1criUBrxANa', 'cliente', 'operador', 1, 0, 'Grupoohla srl', NULL, '8338339393', NULL, 'prueba', 'C. Cesar Augusto Roque 37-33', 'Distrito Nacional', 'Bella vista', 0, NULL, NULL, 'morfemorfe@gmail.yyy', 1),
(17, 'paouqua@mailbox.in.ua', '$2y$10$VLVwtvuGvs2bCEir1J/izO8F0EvPr16NHZPaCe.YbVvBE3S0eyEEy', 'cliente', 'operador', 1, 0, '* * * Bitcoin for free? Believe it: https://redmak.com.tr/index.php?pejx14 * * * hs=59b554ede3dd48dcb86cd75c0effc4ae* ххх*', NULL, NULL, 'wc042o', '4u1jpu', 'v0ps6l', 'Valverde', 'n0soiw', 0, NULL, NULL, 'paouqua@mailbox.in.ua', 1),
(18, 'demo@valla.com', '$2y$10$xfxum3wiHAT8Cc2twWD3fOafagwWMzmAYlzQf6/L5V2O5hpmBUM2K', 'admin', 'operador', 1, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 'demo@valla.com', 0),
(19, 'morfe@ship24.do', '$2y$10$uNlxF0hJK9iJyT7rSnr3GubRNydy7QVdzwUjUsJpCkDH2LwdVphjW', 'admin', 'operador', 1, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 'morfe@ship24.do', 0),
(20, 'info@ship24go.co', '$2y$10$yf1FltkvcplRcSajW8K99.OP0oYeA1f6jxicXeyZxM6IApFuFIkMG', 'admin', 'operador', 1, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 'info@ship24go.co', 0),
(21, 'dev@demo.com', '$2y$10$C694ZDWiCGs4D.KwJ33ZFOwqhQIM4fbbs3LLgtxDO4IPLxxoNbRZi', 'admin', 'operador', 1, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 'dev@demo.com', 0);

-- --------------------------------------------------------

--
-- Table structure for table `usuarios_permisos`
--

CREATE TABLE `usuarios_permisos` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `permiso` varchar(50) NOT NULL,
  `valor` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `usuarios_permisos`
--

INSERT INTO `usuarios_permisos` (`id`, `usuario_id`, `permiso`, `valor`) VALUES
(1, 9, 'menu:mapa', 1);

-- --------------------------------------------------------

--
-- Table structure for table `vallas`
--

CREATE TABLE `vallas` (
  `id` int(11) NOT NULL,
  `tipo` enum('led','impresa','movilled','vehiculo') DEFAULT 'led',
  `nombre` varchar(100) DEFAULT NULL,
  `provincia_id` int(11) DEFAULT NULL,
  `proveedor_id` int(11) DEFAULT NULL,
  `zona` varchar(100) DEFAULT NULL,
  `ubicacion` text,
  `lat` double DEFAULT NULL,
  `lng` double DEFAULT NULL,
  `url_stream_pantalla` text,
  `url_stream_trafico` text,
  `url_stream` text,
  `en_vivo` tinyint(1) NOT NULL DEFAULT '0',
  `mostrar_precio_cliente` tinyint(1) DEFAULT '1',
  `precio` decimal(10,2) DEFAULT '0.00',
  `estado` tinyint(1) NOT NULL DEFAULT '1',
  `disponible` tinyint(1) NOT NULL DEFAULT '1',
  `imagen` varchar(255) DEFAULT NULL,
  `imagen1` varchar(255) DEFAULT NULL,
  `imagen2` varchar(255) DEFAULT NULL,
  `imagen_previa` varchar(255) DEFAULT NULL,
  `audiencia_mensual` int(11) DEFAULT NULL,
  `frecuencia_dias` int(11) DEFAULT NULL,
  `spot_time_seg` int(11) DEFAULT NULL,
  `medida` varchar(80) DEFAULT NULL,
  `descripcion` text,
  `destacado_orden` int(11) DEFAULT NULL COMMENT 'Prioridad de destaque, 1 es más alto',
  `numero_licencia` varchar(100) DEFAULT NULL,
  `tipo_licencia` enum('Anual','Temporal','Permanente','Especial') DEFAULT 'Anual',
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  `fecha_vencimiento` datetime DEFAULT NULL,
  `estado_valla` enum('activa','inactiva') DEFAULT 'activa',
  `visible_publico` tinyint(1) NOT NULL DEFAULT '1',
  `mostrar_precios_market` tinyint(1) NOT NULL DEFAULT '1',
  `comentarios` text,
  `numero_contrato` varchar(100) DEFAULT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `url_documentacion` varchar(255) DEFAULT NULL,
  `precio_alquiler` decimal(10,2) DEFAULT NULL,
  `imagen_tercera` varchar(255) DEFAULT NULL,
  `imagen_cuarta` varchar(255) DEFAULT NULL,
  `responsable_id` int(11) DEFAULT NULL,
  `condiciones_mantenimiento` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `vallas`
--

INSERT INTO `vallas` (`id`, `tipo`, `nombre`, `provincia_id`, `proveedor_id`, `zona`, `ubicacion`, `lat`, `lng`, `url_stream_pantalla`, `url_stream_trafico`, `url_stream`, `en_vivo`, `mostrar_precio_cliente`, `precio`, `estado`, `disponible`, `imagen`, `imagen1`, `imagen2`, `imagen_previa`, `audiencia_mensual`, `frecuencia_dias`, `spot_time_seg`, `medida`, `descripcion`, `destacado_orden`, `numero_licencia`, `tipo_licencia`, `fecha_creacion`, `fecha_vencimiento`, `estado_valla`, `visible_publico`, `mostrar_precios_market`, `comentarios`, `numero_contrato`, `cliente_id`, `url_documentacion`, `precio_alquiler`, `imagen_tercera`, `imagen_cuarta`, `responsable_id`, `condiciones_mantenimiento`) VALUES
(17, 'led', 'PANTALLA HIGÜEY', 13, 1, 'Higuey', 'Parque, Las Tres Cruces, Higuey, Dominican Republic', 18.613051618464, -68.715703825149, 'https://courier.foxpack.us/pantallas_reproductor.html', 'https://courier.foxpack.us/pantallas_reproductor.html?camera=higuey_2', '<br /><b>Deprecated</b>:  htmlspecialchars(): Passing null to parameter #1 ($string) of type string is deprecated in <b>/www/wwwroot/auth.vallasled.com/admin/editar_valla.php</b> on line <b>98</b><br />', 0, 1, 53100.00, 1, 1, NULL, NULL, NULL, '1759722960_68e33dd058108_Screenshot_20251005_234527_Instagram.jpg', 700000, 400, 10, '3000 x 4000', 'Excelente Visual, frente a la Basílica y la Parada de Aptra', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(18, 'led', 'Pantalla Isleta Jumbo La Romana', 14, 1, '🟡 Zona Este', 'Bolulevar, Av. Libertad, La Romana, Dominican Republic', 18.419503, -68.965189, 'https://courier.foxpack.us/pantallas_reproductor.html?camera=romana_1', 'https://courier.foxpack.us/pantallas_reproductor.html?camera=romana_2', '<br /><b>Deprecated</b>:  htmlspecialchars(): Passing null to parameter #1 ($string) of type string is deprecated in <b>/www/wwwroot/auth.vallasled.com/admin/editar_valla.php</b> on line <b>98</b><br />', 0, 1, 25000.00, 1, 1, NULL, NULL, NULL, '1758329172_68cdf954a413a_Screenshot_20250919_204338_WhatsApp~2.jpg', 500000, 400, 10, '3000 x 2000 pixeles', NULL, NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(19, 'led', 'SANTIAGO 27 de Febrero', 29, 1, 'Zona Norte o Cibao', 'Av. 27 de Febrero Esq. Calle Constanza, Santiago de los Caballeros,', 19.459717, -70.691617, 'https://courier.foxpack.us/pantallas_reproductor.html?camera=santiago_1', 'https://courier.foxpack.us/pantallas_reproductor.html?camera=santiago_2', '<br /><b>Deprecated</b>:  htmlspecialchars(): Passing null to parameter #1 ($string) of type string is deprecated in <b>/www/wwwroot/auth.vallasled.com/admin/editar_valla.php</b> on line <b>98</b><br />', 0, 0, 0.00, 1, 1, NULL, NULL, NULL, NULL, 500000, 400, 10, '6000 x 3000 pixeles', 'Excelente Zona Frente al Sector Los Jardines Metropolitanos, Santiago de Los Caballeros Sector los Colegios,  Visual Central Desde la Calle Constanza y La Av. 27 de Febrero.', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(20, 'led', '🔵 Zona SANTIAGO: Av. Estrella Sadhala Esq. República de Argentina', 29, 1, NULL, 'Av. Estrella Sadhala Esq. República de Argentina Santiago de Los Caballeros', 19.452786, -70.684992, 'https://courier.foxpack.us/pantallas_reproductor.html?camera=digireal_1', 'https://courier.foxpack.us/pantallas_reproductor.html?camera=digireal_2', '<br /><b>Deprecated</b>:  htmlspecialchars(): Passing null to parameter #1 ($string) of type string is deprecated in <b>/www/wwwroot/auth.vallasled.com/admin/editar_valla.php</b> on line <b>98</b><br />', 0, 0, 0.00, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(21, 'led', 'Santiago Carretera de Gurabo', 29, 1, 'Zona Norte Cibao', 'Frente al Super Mercado Valerio, , Avenida Gregorio Luperón, Santiago de los Caballeros, Dominican Republic', 19.485456, -70.659254, 'https://courier.foxpack.us/pantallas_reproductor.html?camera=gurabo_1', 'https://courier.foxpack.us/pantallas_reproductor.html?camera=gurabo_2', '<br /><b>Deprecated</b>:  htmlspecialchars(): Passing null to parameter #1 ($string) of type string is deprecated in <b>/www/wwwroot/auth.vallasled.com/admin/editar_valla.php</b> on line <b>98</b><br />', 0, 0, 53100.00, 1, 1, NULL, NULL, NULL, '1759815069_68e4a59da66b6_IMG-20251007-WA0006.jpg', 500000, 400, 10, '3000 x 4000 pixeles', 'Impactante Pantalla led formato Vertical en Una de las mejores zonas de Alto Trafico con antesala a la Metrópolis de Santiago de los Caballeros', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(22, 'led', 'Pantalla San Pedro de Macoris', 27, 1, '🟢 Zona Este', 'Calle Luis Amiama Tío Esq José Hazim Azar, San Pedro de Macorís, Dominican Republic', 18.454878, -69.298816, 'https://courier.foxpack.us/pantallas_reproductor.html?camera=san_pedro_1', 'https://courier.foxpack.us/pantallas_reproductor.html?camera=Trafico_San_Pedro', '<br /><b>Deprecated</b>:  htmlspecialchars(): Passing null to parameter #1 ($string) of type string is deprecated in <b>/www/wwwroot/auth.vallasled.com/admin/editar_valla.php</b> on line <b>98</b><br />', 0, 0, 0.00, 1, 1, NULL, NULL, NULL, NULL, 400000, 400, 10, '3000x4000 pixeles', NULL, NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(23, 'led', 'Pantalla Av.Nuñez de Cacerez', 1, 1, 'Distrito Nacional', 'Avenida Núñez de Cáceres &amp; Calle Clara Pardo, Santo Domingo, Dominican Republic', 18.4757226, -69.9615675, 'https://courier.foxpack.us/pantallas_reproductor.html?camera=nunez', 'https://courier.foxpack.us/pantallas_reproductor.html?camera=Trafico_Nunez_de_Caceres', NULL, 0, 0, 45000.00, 1, 1, NULL, NULL, NULL, '1750912604_685cce5c9cf34_Diseño sin título (4).mp4', 1500000, 400, 10, '3000x3000', 'Excelente Ubicacion', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(24, 'led', 'Pantalla Defillo Esq Romulo', 1, NULL, NULL, 'Calle Doctor Fernando A. Defilló &amp; Avenida Rómulo Betancourt, Santo Domingo, Dominican Republic', 18.4539308, -69.9463264, 'https://courier.foxpack.us/pantallas_reproductor.html?camera=defillo', 'https://courier.foxpack.us/pantallas_reproductor.html?camera=defillo', NULL, 0, 0, 35000.00, 0, 1, NULL, NULL, NULL, NULL, 500000, 400, 10, '4000x2000', NULL, NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(25, 'led', 'Pantalla Zona Oriental / Hotel Golden', 32, 1, 'Santo Domingo Este', 'Golden House Hotel &amp; Convention Center, Autopista Coronel Rafael Tomás Fernández Domínguez, Santo Domingo Este, Dominican Republic', 18.486827341524748, -69.84069054145122, 'https://courier.foxpack.us/pantallas_reproductor.html?camera=golden_1', 'https://courier.foxpack.us/pantallas_reproductor.html?camera=golden_2', NULL, 0, 0, 70000.00, 1, 1, NULL, NULL, NULL, '1750788688_685aea50b774b_f096f4b6c8c710ccdced65b5a40c8234b676ca53.jpg', 1500000, 400, 10, '7000 x 3000', 'Valla led Premium', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(29, 'led', 'Pantalla Elias Piña', 7, 1, 'Zona Sur Profundo', 'Elias Piña, Wascar Comunicaciones', 18.8759736, -71.7001587, 'https://courier.foxpack.us/pantallas_reproductor.html?camera=elias_pina_1', NULL, 'https://www.youtube.com/watch?v=zE_yzuxFiMo&feature=youtu.be', 0, 0, 15000.00, 1, 1, NULL, NULL, NULL, '1750911990_685ccbf6ae3e3_20250624_180333.heic', 100000, 400, 10, '4000x3000', 'Excelente Ubicacion', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(30, 'impresa', 'Padre Castellano', 1, 3, 'Ensanche Espaillat', 'Av. Padre Castellanos & Calle Eduardo Brito, Santo Domingo, República Dominicana', 18.5029948, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1751056564', NULL, NULL, '1751056564_685f00b4a5bf0_ENSANCHE ESPAILLAT.png', NULL, NULL, NULL, 'Tamaño 40x20 Pies', 'Cara Norte', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(31, 'impresa', 'VALLA FIJA ENSANCHE ESPAILLAT', 1, 3, 'Ensanche Espaillat', 'Av. Padre Castellanos & Calle Eduardo Brito, Santo Domingo, República Dominicana', 18.5029948, -69, NULL, NULL, NULL, 0, 0, -0.01, 1, 1, '1751056883', NULL, NULL, '1751057654_685f04f638a40_ENSANCHE ESPAILLAT CARA SUR.png', NULL, NULL, NULL, 'Tamaño 40x20 Pies', 'Cara Sur', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(32, 'impresa', 'ELEVADO JOSEFA BREA', 1, 3, 'Distrito Nacional', 'C. Josefa Brea & C. París, Santo Domingo, República Dominicana', 18.4858419, -69, NULL, NULL, NULL, 0, 0, -0.02, 1, 1, '1751057142', NULL, NULL, '1751057553_685f04913721d_ELEVADO JOSEFA BREA.png', NULL, NULL, NULL, 'Tamaño Paris 50x20  Pies', 'Una cara', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(33, 'impresa', 'VALLA ELEVADO 27 DE FEBRERO ESQ. DUARTE', 1, 3, 'Distrito Nacional', 'Av Juan Pablo Duarte & Av. 27 De Febrero, San José de Ocoa, República Dominicana', 18.5467509, -70, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1751057447', NULL, NULL, '1751057510_685f0466a2eca_27 DE FEBRERO ESQ. DUARTE ELEVADO.png', NULL, NULL, NULL, 'Tamaño 50x16  Pies', 'Una cara', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(34, 'impresa', '27 De Febrero Esq. Duarte 2da cara', 1, 3, 'Distrito Nacional', 'Av Juan Pablo Duarte & Av. 27 De Febrero, San José de Ocoa, República Dominicana', 18.5467509, -70, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1751057959', NULL, NULL, '1751057959_685f0627e2e66_27 DE FEBRERO ESQ. DUARTE ELEVADO 2da cara.png', NULL, NULL, NULL, 'Tamaño 50x16 Pies', 'Segunda cara', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(35, 'impresa', 'San Martin elevado', 1, 3, 'Distrito Nacional', 'Av. San Martín no. 30, Santo Domingo, República Dominicana', 18.4796306, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1751058177', NULL, NULL, '1751058177_685f07010b756_AV. SAN MARTIN ELEVADO CARA UNO.png', NULL, NULL, NULL, 'Tamaño 50x20 Pies', 'Una cara', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(36, 'impresa', 'MAXIMO GOMEZ CARA NORTE', 1, 3, 'Maximo Gomez', 'Av. Máximo Gómez & Avenida 27 de Febrero, Santo Domingo, República Dominicana', 18.4763412, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1751058595', NULL, NULL, '1751058595_685f08a338192_AV. MAXIMO GOMEZ CARA NORTE.png', NULL, NULL, NULL, 'Tamaño 50x16 Pies', 'CARA NORTE', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(37, 'impresa', 'MAXIMO GOMEZ CARA SUR', 1, 3, 'Distrito Nacional', 'Av. Máximo Gómez & Av 27 de Febrero, Santo Domingo, República Dominicana', 18.4763412, -69, NULL, NULL, NULL, 0, 0, -0.02, 1, 1, '1751058757', NULL, NULL, '1751058757_685f094551009_AV. MAXIMO GOMEZ CARA SUR.png', NULL, NULL, NULL, 'Tamaño 50x16  Pies', 'Cara sur', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(38, 'impresa', 'AGORA MALL CARA NORTE', 1, 3, 'SERRALLES', 'Ágora Mall, Avenida John F. Kennedy, Santo Domingo, República Dominicana', 18.484034514773, -69, NULL, NULL, NULL, 0, 0, -0.02, 1, 1, '1751060160', NULL, NULL, '1751060160_685f0ec00169d_AGORA MALL CARA NORTE.png', NULL, NULL, NULL, 'Tamaño  20x40 Pies', 'Cara Norte', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(39, 'impresa', 'AGORA MALL CARA SUR', 1, 3, 'SERRALLES', 'Ágora Mall, Avenida John F. Kennedy, Santo Domingo, República Dominicana', 18.48404469013, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1751060271', NULL, NULL, '1751060271_685f0f2f1b0be_AGORA MALL CARA SUR.png', NULL, NULL, NULL, 'Tamaño 20x40  Pies', 'Cara Sur', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(40, 'impresa', 'EDIFICIO ALEGRIA AV. JHON F KENNEDY', 1, 3, 'Distrito Nacional', 'Av. John F. Kennedy no. 39, Santo Domingo, República Dominicana', 18.4808738, -69, NULL, NULL, NULL, 0, 0, -0.02, 1, 1, '1751063712', NULL, NULL, '1751063712_685f1ca058953_EDIFICIO ALEGRIA.png', NULL, NULL, NULL, 'Tamaño 40x20 Pies', 'Dirección: Oeste /Este', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(41, 'impresa', 'EDIFICIO ALEGRIA AV. JHON F KENNEDY ESTE /OESTE', 1, 3, 'Distrito Nacional', 'Av. John F. Kennedy no. 39, Santo Domingo, República Dominicana', 18.4808738, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, NULL, NULL, NULL, '1751064595_685f201300e67_EDIFICIO ALEGRIA DIRECCION ESTE OESTE.png', NULL, NULL, NULL, 'Tamaño  40x20 Pies', 'Dirección Este/Oeste', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(42, 'impresa', 'EDIFICIO ALERIA AV. JHON F KENNEDY ESTE /OESTE', 1, 3, 'Distrito Nacional', 'Av. John F. Kennedy no. 39, Santo Domingo, República Dominicana', 18.4808738, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, NULL, NULL, NULL, '1751063995_685f1dbbea147_EDIFICIO ALEGRIA DIRECCION ESTE OESTE.png', NULL, NULL, NULL, 'Tamaño  40x20 Pies', 'Dirección Este/Oeste', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(43, 'impresa', 'LA PRIVADA PRIMERA CARA', 1, 3, 'MIRADOR NORTE', 'Av 27 de Febrero & C. Teodoro Chasseriau, Santo Domingo, República Dominicana', 18.4516545, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, NULL, NULL, NULL, '1751064540_685f1fdc4ef03_AV. PRIVADA PRIMERA CARA.png', NULL, NULL, NULL, 'Tamaño 50x20  Pies', 'Primera cara', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(44, 'impresa', 'AV. PRIVADA SEGUNDA CARA', 1, 3, 'DISTRITO NACIONAL', 'Cerdo Centro, Avenida 27 de Febrero, Santo Domingo, República Dominicana', 18.4508692979, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, NULL, NULL, NULL, '1751065683_685f2453a3a2d_AV. PRIVADA SEGUNDA CARA.png', NULL, NULL, NULL, 'Tamaño 50x20 Pies', 'Segunda cara', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(45, 'impresa', 'AV. 27 DE FEBRERO ESQ. NUÑEZ PRIMERA CARA', 1, 3, 'Mirador Norte', 'Avenida Núñez de Cáceres, Mirador Norte, Santo Domingo, República Dominicana', 18.454659198088, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, NULL, NULL, NULL, '1751069855_685f349fafe9d_Captura de pantalla 2025-06-27 201428.png', NULL, NULL, NULL, 'Tamaño 36x20 Pies', 'CARA #1', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(46, 'led', 'Pantalla Puerto Plata', 22, 1, 'Norte', 'Puente peatonal llano de Pérez, Llano de Pérez, Puerto Plata, Dominican Republic', 19.788089, -70, 'https://courier.foxpack.us/pantallas_reproductor.html?camera=puerto_plata', 'https://courier.foxpack.us/pantallas_reproductor.html?camera=trafico_puerto_plata', NULL, 0, 0, 25000.00, 1, 1, NULL, NULL, NULL, NULL, 250000, 300, 10, '3000x1500', 'Pantalla Puente Peatonal Puerto Plats', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(47, 'impresa', 'AV.27 DE FEBRERO CASI ESQ. AV. PRIVADA', 1, 3, 'Distrito Nacional', 'Avenida 27 de Febrero 490, Santo Domingo, Dominican Republic', 18.451145, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1751489792', NULL, NULL, '1751489792_68659d00337bb_image_2025-07-02_165601698.png', NULL, NULL, NULL, 'TAMAÑO 50 X 20 PIES', 'CARA NO.1', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(48, 'impresa', 'AV.27 DE FEBRERO CASI ESQ. AV.PRIVADA', 1, 3, 'DISTRITO NACIONAL', 'Avenida 27 de Febrero 490, Santo Domingo, Dominican Republic', 18.451145, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1751490328', NULL, NULL, '1751490328_68659f184d11c_image_2025-07-02_170506613.png', NULL, NULL, NULL, '50X20 PIES', 'CARA NO.2', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(49, 'impresa', 'AV.27 DE FEBRERO ESQ. NUÑEZ DE CACERES, MIRADOR NORTE', 1, 3, 'DISTRITO NACIONAL', 'Av 27 de Febrero & Avenida Núñez de Cáceres, Santo Domingo, Dominican Republic', 18.4546398, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1751490841', NULL, NULL, '1751490841_6865a119b3fd7_image_2025-07-02_171351045.png', NULL, NULL, NULL, '36X20 PIES', 'CARA NO.1', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(50, 'impresa', 'AV. 27 DE FEBRERO ESQ. NUÑEZ DE CACERES, MIRADOR NORTE', 1, 3, 'DISTRITO NACIONAL', 'Av 27 de Febrero & Avenida Núñez de Cáceres, Santo Domingo, Dominican Republic', 18.4546398, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1751491153', NULL, NULL, '1751491153_6865a251a0d21_image_2025-07-02_171905157.png', NULL, NULL, NULL, '36X20 PIES', 'CARA NO. 2', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(51, 'impresa', 'AV. 27 DE FEBRERO. AL LADO DE LA Z101.', 1, 3, 'DISTRITO NACIONAL', 'Avenida 27 de Febrero 327, Santo Domingo, Dominican Republic', 18.4601346, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, NULL, NULL, NULL, '1751491388_6865a33c44ad2_image_2025-07-02_172303059.png', NULL, NULL, NULL, '40X20 PIES', 'CARA NO. 1', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(52, 'impresa', 'AV. 27 DE FEBRERO. AL LADO DE LA Z101.', 1, 3, 'DISTRITO NACIONAL', 'Avenida 27 de Febrero 327, Santo Domingo, Dominican Republic', 18.4601346, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, NULL, NULL, NULL, '1751491474_6865a39296b57_image_2025-07-02_172430375.png', NULL, NULL, NULL, '40X20 PIES', 'CARA NO. 2', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(53, 'impresa', 'AVENIDA MUSTAFA KEMAL CASI ESQ. 27 DE FEBRERO', 1, 3, 'DISTRITO NACIONAL', 'Calle Mustafá Kemal Ataturk, Santo Domingo, Dominican Republic', 18.470862, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, NULL, NULL, NULL, '1751492015_6865a5af370e9_image_2025-07-02_173155473.png', NULL, NULL, NULL, '50X20 PIES', 'CARA NO. 1', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(55, 'impresa', 'AV. ORTEGA Y GASSET ESQ. GUSTAVO MEJIA RICARD', 1, 3, 'DISTRITO NACIONAL', 'Avenida José Ortega y Gasset 28, Santo Domingo, Dominican Republic', 18.4766163, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, NULL, NULL, NULL, '1751492484_6865a78478cd5_image_2025-07-02_174102492.png', NULL, NULL, NULL, '50X20', 'CARA NO. 1', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(56, 'impresa', 'AV. ORTEGA Y GASSET ESQ. GUSTAVO MEJIA RICARD', 1, 3, 'DISTRITO NACIONAL', 'Avenida José Ortega y Gasset 28, Santo Domingo, Dominican Republic', 18.4766163, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1751492600', NULL, NULL, '1751492600_6865a7f805705_image_2025-07-02_174300992.png', NULL, NULL, NULL, '50X20', 'CARA NO. 2', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(57, 'impresa', 'AV. TIRDENTES ENSANCHE NACO', 1, 3, 'DISTRITO NACIONAL', 'Av. Tiradentes no.28, Ensanche Naco, Santo Domingo, Dominican Republic', 18.4747865, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1751492889', NULL, NULL, '1751492889_6865a9199aa84_image_2025-07-02_174751155.png', NULL, NULL, NULL, '50X20 PIES', 'CARA NO.1', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(58, 'impresa', 'AV. TIRDENTES ENSANCHE NACO', 1, 3, 'DISTRITO NACIONAL', 'Av. Tiradentes no.28, Ensanche Naco, Santo Domingo, Dominican Republic', 18.4747865, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1751492993', NULL, NULL, '1751492993_6865a9816cc43_image_2025-07-02_174944145.png', NULL, NULL, NULL, '50X20 PIES', 'CARA NO. 2', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(59, 'impresa', 'AV. LUPERON ESQ AV. ENRIQUILLO', 1, 3, 'DISTRITO NACIONAL', 'Av. Luperón & Av Enriquillo, Santo Domingo, Dominican Republic', 18.4423035, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, NULL, NULL, NULL, '1751493327_6865aacf8218a_image_2025-07-02_175518430.png', NULL, NULL, NULL, '50X20 PIES', '(1 CARA)', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(60, 'impresa', 'AV. LUPERON ESQ AV. ENRIQUILLO', 1, 3, 'DISTRITO NACIONAL', 'Av. Luperón & Av Enriquillo, Santo Domingo, Dominican Republic', 18.4423035, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, NULL, NULL, NULL, '1751493330_6865aad24744a_image_2025-07-02_175518430.png', NULL, NULL, NULL, '50X20 PIES', '(1 CARA)', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(61, 'impresa', 'AV. LUPERON ESQ AV. ENRIQUILLO', 1, 3, 'DISTRITO NACIONAL', 'Av. Luperón & Av Enriquillo, Santo Domingo, Dominican Republic', 18.4423035, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '50X20 PIES', '(1 CARA)', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(62, 'impresa', 'AV. LUPERON ESQ AV. ENRIQUILLO', 1, 3, 'DISTRITO NACIONAL', 'Av. Luperón & Av Enriquillo, Santo Domingo, Dominican Republic', 18.4423035, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1751493335', NULL, NULL, '1751493335_6865aad7c1d54_image_2025-07-02_175518430.png', NULL, NULL, NULL, '50X20 PIES', '(1 CARA)', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(63, 'impresa', 'AV. CAMINO CHIQUITO ARROYO HONDO', 1, 3, 'DISTRITO NACIONAL', 'Calle Camino Chiquito no. 68, Santo Domingo, Dominican Republic', 18.493803460887, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1751493571', NULL, NULL, '1751493571_6865abc3bfbeb_image_2025-07-02_175924396.png', NULL, NULL, NULL, '50X20 PIES', '(1 CARA)', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(65, 'impresa', 'AUTOPISTA DUARTE ELEVADO DEL KM. 9 1/2', 1, 3, 'DISTRITO NACIONAL', 'Autop. Juan Pablo Duarte 1/2, Dominican Republic', 18.4819064, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1751495248', NULL, NULL, '1751495248_6865b250dff92_image_2025-07-02_182706407.png', NULL, NULL, NULL, '50X20', 'CARA NO.1', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(66, 'impresa', 'AUTOPISTA DUARTE ELEVADO DEL KM.9 1/2', 1, 3, 'DISTRITO NACIONAL', 'Autop. Juan Pablo Duarte Km 9, Santo Domingo, Dominican Republic', 18.4791853, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1751495447', NULL, NULL, '1751495447_6865b317cbcc6_image_2025-07-02_183033708.png', NULL, NULL, NULL, '50X20 PIES', 'CARA NO.2', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(67, 'impresa', 'AV. JACOBO MAJLUTA, VILLA MELLA', 32, 3, 'SANTO DOMINGO NORTE', 'Av. Jacobo Majluta Azar, Santo Domingo, Dominican Republic', 18.5114576, -69, NULL, NULL, NULL, 0, 0, -0.01, 1, 1, '1751496431', NULL, NULL, '1751496431_6865b6efcc3ea_image_2025-07-02_184646920.png', NULL, NULL, NULL, '40X16 PIES', 'CARA NO.1', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(68, 'impresa', 'AV. JACOBO MAJLUTA, VILLA MELLA', 32, 3, 'SANTO DOMINGO NORTE', 'Av. Jacobo Majluta Azar, Santo Domingo, Dominican Republic', 18.5114576, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1751496519', NULL, NULL, '1751496519_6865b747c76fd_image_2025-07-02_184829230.png', NULL, NULL, NULL, '40X16 PIES', 'CARA NO.2', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(69, 'impresa', 'PUENTE DUARTE, CLUB CALERO', 32, 3, 'SANTO DOMINGO ESTE', 'Puente Juan Pablo Duarte, Rio Ozama, Santo Domingo, Dominican Republic', 18.486069745842, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1751496840', NULL, NULL, '1751496840_6865b888d331d_image_2025-07-02_185349147.png', NULL, NULL, NULL, '50X20 PIES', 'CARA NO.1', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(70, 'impresa', 'PUENTE DUARTE, CLUB CALERO', 32, 3, 'SANTO DOMING ESTE', 'Puente Juan Pablo Duarte, Rio Ozama, Santo Domingo, Dominican Republic', 18.485988343932, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1751496966', NULL, NULL, '1751496966_6865b90656a9a_image_2025-07-02_185554239.png', NULL, NULL, NULL, '50X20 PIES', 'CARA NO.2', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(71, 'impresa', 'AV. LAS AMERICAS ENSANCHE OZAMA CASI ESQ. SABANA LARGA.', 32, 3, NULL, 'Av. Las Américas & Avenida Sabana Larga, Ensanche Ozama, Santo Domingo Este, Dominican Republic', 18.4851045, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1751497405', NULL, NULL, '1751497405_6865babd177db_Screenshot 2025-07-02 190103.png', NULL, NULL, NULL, '50X2O PIES', 'CARA NO.1', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(72, 'impresa', 'AV. LAS AMERICAS ENSANCHE OZAMA CASI ESQ. SABANA LARGA.', 32, 3, 'SANTO DOMINGO ESTE', 'Av. Las Américas & Avenida Sabana Larga, Ensanche Ozama, Santo Domingo Este, Dominican Republic', 18.4851045, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1751497478', NULL, NULL, '1751497478_6865bb06b52d7_image_2025-07-02_190429400.png', NULL, NULL, NULL, '50X20 PIES', 'CARA NO.2', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(73, 'impresa', 'CARRETERA MELLA KM 8 1/2 FRENTE A CAMPO VERDE', 32, 3, 'SANTO DOMINGO ESTE', 'Carretera Mella no.399, Santo Domingo Este, Dominican Republic', 18.5079789, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1751497968', NULL, NULL, '1751497968_6865bcf085c7e_image_2025-07-02_191201047.png', NULL, NULL, NULL, '50X20 PIES', 'CARA NO.1', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(74, 'impresa', 'CARRETERA MELLA KM 8 1/2 FRENTE A CAMPO VERDE', 32, 3, 'SANTO DOMINGO ESTE', 'Carretera Mella no.399, Santo Domingo Este, Dominican Republic', 18.5079789, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1751498086', NULL, NULL, '1751498086_6865bd66bb72d_image_2025-07-02_191438484.png', NULL, NULL, NULL, '50X20 PIES', 'CARA NO.2', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(75, 'impresa', 'CARRETERA MELLA KM 8 1/2 FRENTE A CAMPO VERDE', 32, 3, 'SANTO DOMINGO ESTE', 'Carretera Mella no.399, Santo Domingo Este, Dominican Republic', 18.5079789, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1751498089', NULL, NULL, '1751498089_6865bd69a1abe_image_2025-07-02_191438484.png', NULL, NULL, NULL, '50X20 PIES', 'CARA NO.2', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(76, 'impresa', 'AUT. SAN ISIDRO ESQ AV, 1ERA MANZABA 3611,FRANCOINA S.', 32, 3, 'SANTO DOMINGO ESTE', 'C. 1ra & Autop. de San Isidro, Santo Domingo Este, Dominican Republic', 18.4879729, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1751498551', NULL, NULL, '1751498551_6865bf37a20c3_image_2025-07-02_192216973.png', NULL, NULL, NULL, '50X20 PIES', 'CARA NO.1', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(77, 'impresa', 'AUT. SAN ISIDRO ESQ AV, 1ERA MANZABA 3611,FRANCOINA S.', 32, 3, 'SANTO DOMINGO ESTE', 'C. 1ra & Autop. de San Isidro, Santo Domingo Este, Dominican Republic', 18.4879729, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1751498644', NULL, NULL, '1751498644_6865bf94872b4_image_2025-07-02_192356237.png', NULL, NULL, NULL, '50X20 PIES', 'CARA NO.2', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(78, 'impresa', 'AV. LAS AMERICAS ENSANCHE OZAMA, ESQ VENEZUELA', 32, 3, 'SANTO DOMINGO ESTE', 'Av. Las Américas & Avenida Venezuela, Ensanche Ozama, Santo Domingo Este, Dominican Republic', 18.4854507, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1751498839', NULL, NULL, '1751498839_6865c057d3125_image_2025-07-02_192707448.png', NULL, NULL, NULL, '50X20 PIES', 'CARA NO.1', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(79, 'impresa', 'AV. LAS AMERICAS ENSANCHE OZAMA, ESQ VENEZUELA', 32, 3, 'SANTO DOMINGO ESTE', 'Av. Las Américas & Avenida Venezuela, Ensanche Ozama, Santo Domingo Este, Dominican Republic', 18.4854507, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1751498923', NULL, NULL, '1751498923_6865c0abb5d92_image_2025-07-02_192828711.png', NULL, NULL, NULL, '50X20 PIES', 'CARA NO.2', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(80, 'impresa', 'CARRETERA MELLA CASI ESQ. SAN VICENTE DE PAUL, FRENTE AYUNTAMIENTO NUEVO-MEGA CENTRO', 32, 3, 'SANTO DOMINGO ESTE', 'Carretera Mella & Avenida San Vicente de Paúl, Santo Domingo Este, Dominican Republic', 18.5050193, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1751499133', NULL, NULL, '1751499133_6865c17d79c60_image_2025-07-02_193202047.png', NULL, NULL, NULL, '42X20 PIES', 'CARA NO.1', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(81, 'impresa', 'CARRETERA MELLA CASI ESQ. SAN VICENTE DE PAUL, FRENTE AYUNTAMIENTO NUEVO-MEGA CENTRO', 32, 3, 'SANTO DOMINGO ESTE', 'Carretera Mella & Avenida San Vicente de Paúl, Santo Domingo Este, Dominican Republic', 18.5050193, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1751499218', NULL, NULL, '1751499218_6865c1d221da4_image_2025-07-02_193329415.png', NULL, NULL, NULL, '42X20 PIES', 'CARA NO.2', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(82, 'impresa', 'CARRETERA MELLA KM. 9 ESQ. CHARLES DE GAULLE', 32, 3, 'SANTO DOMINGO ESTE', 'Carretera Mella & Avenida Charles de Gaulle, Santo Domingo Este, Dominican Republic', 18.5192271, -69.8346758, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1751499382', NULL, NULL, '1751499382_6865c2767a415_image_2025-07-02_193615337.png', NULL, NULL, NULL, '50X20 PIES', '(1 CARA)', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(83, 'impresa', 'CARRETERA MELLA NO. 431 KM. 9 1/2 ESQ. CHARLES DE GAULLE', 32, 3, 'SANTO DOMINGO ESTE', 'Carretera Mella 431, Santo Domingo Este, Dominican Republic', 18.519192670973, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1751499661', NULL, NULL, '1751499661_6865c38dbed74_image_2025-07-02_194054922.png', NULL, NULL, NULL, '40X20 PIES', 'CARA NO.1', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(84, 'impresa', 'CARRETERA MELLA NO. 431 KM. 9 1/2 ESQ. CHARLES DE GAULLE', 32, 3, 'SANTO DOMINGO ESTE', 'Carretera Mella 431, Santo Domingo Este, Dominican Republic', 18.518697259316, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1751499792', NULL, NULL, '1751499792_6865c4107e697_image_2025-07-02_194303006.png', NULL, NULL, NULL, '40X20 PIES', 'CARA NO.2', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(85, 'impresa', 'AV. LAS AMERICAS C/5TA. RES. MARBELLA KM 13 1/2 PROX AL HIPODROMO FERRETERIA QUILVIO', 32, 3, 'SANTO DOMIGO ESTE', 'Ferreteria Quilvio, Calle 5ta, Santo Domingo Este, Dominican Republic', 18.466265, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1751500296', NULL, NULL, '1751500296_6865c608d230a_image_2025-07-02_195128225.png', NULL, NULL, NULL, '50X20 PIES', 'CARA NO.1', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(87, 'impresa', 'AV. LAS AMERICAS C/5TA. RES. MARBELLA KM 13 1/2 PROX AL HIPODROMO FERRETERIA QUILVIO', 32, 3, 'SANTO DOMINGO ESTE', 'Ferreteria Quilvio, Calle 5ta, Santo Domingo Este, Dominican Republic', 18.466265, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1751500363', NULL, NULL, '1751500363_6865c64ba3c83_image_2025-07-02_195233272.png', NULL, NULL, NULL, '50X20 PIES', 'CARA NO.2', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(88, 'impresa', 'AUTOPISTA SAN ISIDRO, HOTEL GOLDEN', 32, 3, 'SANTO DOMINGO ESTE', 'Autop. de San Isidro, 11506 Santo Domingo Este, Dominican Republic', 18.485274664398, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 0, '1751501041', NULL, NULL, '1751501041_6865c8f1cbe41_image_2025-07-02_200344217.png', NULL, NULL, NULL, '50X16 PIES', 'CARA NO.1', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(90, 'impresa', 'AUTOPISTA SAN ISIDRO, HOTEL GOLDEN HOUSE', 32, 3, 'SANTO DOMINGO ESTE', 'Autop. de San Isidro, 11506 Santo Domingo Este, Dominican Republic', 18.48538659248157, -69.84316096032714, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1751501351', NULL, NULL, '1751501351_6865ca278dd33_image_2025-07-02_200903314.png', NULL, NULL, NULL, '50X16 PIES', 'CARA NO.2', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(91, 'led', 'Pantalla Caleta La Romana', 14, 1, 'Zona Este', 'Carretera La Caleta & Calle Principal Miramar, La Romana, Dominican Republic', 18.405148075808153, -68.99680912266712, 'https://courier.foxpack.us/pantallas_reproductor.html?camera=pantalla_caleta', 'https://courier.foxpack.us/pantallas_reproductor.html?camera=trafico_caleta', NULL, 0, 0, 15000.00, 1, 1, NULL, NULL, NULL, '1759813884_68e4a0fc53012_IMG-20250814-WA0043.jpg', 100000, 400, 10, '3000 x 2000 pixeles', 'Ubicada en única  ruta de  entrada y salida del Municipio de Caleta la Romana.', NULL, NULL, 'Anual', '2025-07-09 13:17:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(94, 'impresa', 'AV.RAFAEL TOMAS FERNANDEZ DOMINGUEZ CARRETERA DE SAN ISIDRO', 32, NULL, 'SANTO DOMINGO ESTE', 'Autopista de San Isidro, Urbanizacion Italia, Santo Domingo Este, Dominican Republic', 18.486058159446046, -69.8399852248535, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1753544758', NULL, NULL, '1753544758_6884f836f2cd9_image_2025-07-26_114552290.png', NULL, NULL, NULL, '50X20 PIES', 'CARA #1', NULL, NULL, 'Anual', '2025-07-26 10:45:58', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(95, 'impresa', 'AV.RAFAEL TOMAS FERNANDEZ DOMINGUEZ CARRETERA DE SAN ISIDRO', 32, 3, NULL, 'Autopista de San Isidro, Urbanizacion Italia, Santo Domingo Este, Dominican Republic', 18.486220963162, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, NULL, NULL, NULL, '1753544917_6884f8d50800a_image_2025-07-26_114832001.png', NULL, NULL, NULL, '50X20 PIES', 'CARA #2', NULL, NULL, 'Anual', '2025-07-26 10:48:37', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(96, 'impresa', 'AV. LAS AMERICAS LOS TRES OJOS', 32, 3, 'SANTO DOMINGO ESTE-OESTE', 'Dominican Republic, Santo Domingo Este, Avenida Las Américas, Av.las americas', 18.482260065964, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1753545742', NULL, NULL, '1753545742_6884fc0ed3acd_image_2025-07-26_120215982.png', NULL, NULL, NULL, '40X20 PIES', '1 CARA', NULL, NULL, 'Anual', '2025-07-26 11:02:22', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(97, 'impresa', 'AV. LAS AMERICAS LOS TRES OJOS', 32, 3, 'SANTO DOMINGO ESTE-OESTE', 'Dominican Republic, Santo Domingo Este, Avenida Las Américas, Av.las americas', 18.481341728006, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1753546123', NULL, NULL, '1753546123_6884fd8b2c465_image_2025-07-26_120835218.png', NULL, NULL, NULL, '40X20 PIES', '1 CARA', NULL, NULL, 'Anual', '2025-07-26 11:08:43', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(98, 'impresa', 'AUTOPISTA DUARTE KM.20, LA PENCA', 32, 3, 'SANTO DOMINGO ESTE', 'Destacamento P.N. Cruce de Cayacoa, Dominican Republic', 18.481830822132, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1753547015', NULL, NULL, '1753547015_68850107eab49_image_2025-07-26_122326928.png', NULL, NULL, NULL, '50X20', 'CARA #1', NULL, NULL, 'Anual', '2025-07-26 11:23:35', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(99, 'impresa', 'AUTOPISTA DUARTE KM.20 LA PENCA', 32, 3, 'SANTO DOMINGO ESTE', 'Destacamento P.N. Cruce de Cayacoa, Dominican Republic', 18.481958015686, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1753547168', NULL, NULL, '1753547168_688501a014409_image_2025-07-26_122551126.png', NULL, NULL, NULL, '50X20 PIES', 'CARA #2', NULL, NULL, 'Anual', '2025-07-26 11:26:08', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(100, 'impresa', 'AUTOPISTA DUARTTE KM.20, LA PENCA', 32, 3, 'SANTO DOMINGO ESTE', 'Bodega La Fortuna km 20 autopista duarte, Santo Domingo, Dominican Republic', 18.5448622, -70, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1753548101', NULL, NULL, '1753548101_688505458ee17_image_2025-07-26_124115876.png', NULL, NULL, NULL, '36x16 PIES', '1 CARA', NULL, NULL, 'Anual', '2025-07-26 11:41:41', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(101, 'impresa', 'AUT. LAS AMERICAS KM.22 NO.13 SANTA LUCIA', 32, 3, 'SANTO DOMINGO ESTE', 'Autop. Las Américas Km. 22, Santo Domingo, Dominican Republic', 18.45565419622, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1753549182', NULL, NULL, '1753549182_6885097ef05e3_image_2025-07-26_125934325.png', NULL, NULL, NULL, '50X20 PIES', NULL, NULL, NULL, 'Anual', '2025-07-26 11:59:42', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(102, 'impresa', 'AUTOVIA DEL ESTE JUAND DOLIO PROXIMO A GUAVABERRY', 27, 3, 'ESTE', 'Autovía del Este-Juan Dolio, Juan Dolio, Dominican Republic', 18.4348823, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1753550326', NULL, NULL, '1753550326_68850df67ee26_image_2025-07-26_131826144.png', NULL, NULL, NULL, '50X20 PIES', 'CARA #1', NULL, NULL, 'Anual', '2025-07-26 12:18:46', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(103, 'impresa', 'AUTOVIA DEL ESTE JUAND DOLIO PROXIMO A GUAVABERRY', 27, 3, 'ESTE', 'Autovía del Este-Juan Dolio, Juan Dolio, Dominican Republic', 18.4348823, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1753550445', NULL, NULL, '1753550445_68850e6d617ca_image_2025-07-26_132036880.png', NULL, NULL, NULL, '50X20 PIES', 'CARA #2', NULL, NULL, 'Anual', '2025-07-26 12:20:45', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(104, 'impresa', 'ENTRADA SAN PEDRO DE MACORIS, AUTOVIA DEL ESTE', 27, 3, 'ESTE', 'Entrada, Juan Dolio, San Pedro de Macoris, Dominican Republic', 18.4267437, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1753550772', NULL, NULL, '1753550772_68850fb4b15f2_image_2025-07-26_132600399.png', NULL, NULL, NULL, '36X20 PIES', '1 CARA', NULL, NULL, 'Anual', '2025-07-26 12:26:12', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(105, 'impresa', 'CARRETERA SAN PEDRO LA ROMANA KM.6', 14, 3, 'ESTE', 'Autovía del Este, La Romana, Dominican Republic', 18.4577609, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1753551366', NULL, NULL, '1753551366_688512065c8e8_image_2025-07-26_133522651.png', NULL, NULL, NULL, '36X16 PIES', 'CARA #1', NULL, NULL, 'Anual', '2025-07-26 12:36:06', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(106, 'impresa', 'CARRETERA SAN PEDRO LA ROMANA KM.6', 14, 3, 'ESTE', 'Autovía del Este, La Romana, Dominican Republic', 18.4577609, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1753551425', NULL, NULL, '1753551425_68851241eb926_image_2025-07-26_133658086.png', NULL, NULL, NULL, '32X12 PIES', NULL, NULL, NULL, 'Anual', '2025-07-26 12:37:05', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(107, 'impresa', 'AUTOVIA DEL ESTE SECCION LA ANTENA, CERCA DE LA CUEVA DE LAS MARAVILLAS', 14, 3, 'ESTE', 'Cueva de las Maravillas, Autovía del Este, La Caña, Dominican Republic', 18.449213081622, -69, NULL, NULL, NULL, 0, 1, 0.00, 1, 1, '1753551853', NULL, NULL, '1753551853_688513ed60422_image_2025-07-26_134400631.png', NULL, NULL, NULL, '30X15 PIES', '2 CARAS', NULL, NULL, 'Anual', '2025-07-26 12:44:13', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(108, 'impresa', 'CARRETERA SAN PEDRO ROMANA, LA ROMANA, CERCA DE LA AUTOVIA', 14, 3, 'ESTE', 'Autovía del Este, La Romana, Dominican Republic', 18.465535887687, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1753552956', NULL, NULL, '1753552956_6885183cc96e6_image_2025-07-26_135045260.png', NULL, NULL, NULL, '50X20 PIES', 'CARA #1', NULL, NULL, 'Anual', '2025-07-26 13:02:36', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(109, 'impresa', 'CARRETERA SAN PEDRO ROMANA, LA ROMANA, CERCA DE LA AUTOVIA', 14, 3, 'ESTE', 'Autovía del Este, La Romana, Dominican Republic', 18.465413770185, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1753553091', NULL, NULL, '1753553091_688518c3eae7e_image_2025-07-26_140442893.png', NULL, NULL, NULL, '50X20 PIES', 'CARA #2', NULL, NULL, 'Anual', '2025-07-26 13:04:51', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(110, 'impresa', 'AVENIDA LIBERTAD NO.767', 14, 3, 'ESTE', 'Avenida Libertad no.767, La Romana, Dominican Republic', 18.4177613, -68, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1753555033', NULL, NULL, '1753555033_68852059ebc53_image_2025-07-26_143706268.png', NULL, NULL, NULL, '40X16 PIES', '1 CARA', NULL, NULL, 'Anual', '2025-07-26 13:37:13', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(111, 'impresa', 'AUTOPISTA DUARTE EL PINO, LA VEGA', 15, 3, 'NORTE', 'Autopista Duarte, El Pino, Dominican Republic', 19.1454019, -70.4723097, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1753557445', NULL, NULL, '1753557445_688529c5a17b5_image_2025-07-26_151717141.png', NULL, NULL, NULL, '40X20 PIES', '1 CARA', NULL, NULL, 'Anual', '2025-07-26 14:17:25', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(112, 'impresa', 'AUTOPISTA DUARTE KM5 PONTON LA VEGA', 15, 3, 'NORTE', 'Autopista Duarte 5, La Vega, Dominican Republic', 19.2295405, -70, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1753557602', NULL, NULL, '1753557602_68852a62f0850_image_2025-07-26_151949053.png', NULL, NULL, NULL, '50 X20 PIES', '1 CARA', NULL, NULL, 'Anual', '2025-07-26 14:20:02', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(113, 'impresa', 'AUTOPISTA DUERTE PARAJE LOS PUENTES, LA VEGA', 15, 3, 'NORTE', 'Autopista Duarte, La Vega, Dominican Republic', 19.2387663, -70, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1753557797', NULL, NULL, '1753557797_68852b25ab746_image_2025-07-26_152306004.png', NULL, NULL, NULL, '50X20 PIES', 'CARA #1', NULL, NULL, 'Anual', '2025-07-26 14:23:17', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(114, 'impresa', 'AUTOPISTA DUERTE PARAJE LOS PUENTES, LA VEGA', 15, 3, 'NORTE', 'Autopista Duarte, La Vega, Dominican Republic', 19.2387663, -70, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1753557867', NULL, NULL, '1753557867_68852b6b13715_image_2025-07-26_152419561.png', NULL, NULL, NULL, '50X20 PIES', 'CARA #2', NULL, NULL, 'Anual', '2025-07-26 14:24:27', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(115, 'impresa', 'AUTOPISTA DUARTE LAS MARAS, LA VEGA PROXIMO A CARIBE TOURS', 15, 3, 'NORTE', 'Autopista Duarte, La Vega, Dominican Republic', 19.2387663, -70, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1753558502', NULL, NULL, '1753558502_68852de6e1f1a_image_2025-07-26_153455747.png', NULL, NULL, NULL, '50X20 PIES', '1 CARA', NULL, NULL, 'Anual', '2025-07-26 14:35:02', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(116, 'impresa', 'CARRETERA ROMANA HIGUEY KM1 1/2', 13, 3, 'ESTE', 'UASD Higüey, Avenida Altagracia, Higuey, Dominican Republic', 18.591604415253, -68, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1753558840', NULL, NULL, '1753558840_68852f384f3af_image_2025-07-26_154033718.png', NULL, NULL, NULL, '50X20 PIES', 'CARA #1', NULL, NULL, 'Anual', '2025-07-26 14:40:40', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(117, 'impresa', 'CARRETERA ROMANA HIGUEY KM1 1/2', 13, 3, 'ESTE', 'UASD Higüey, Avenida Altagracia, Higuey, Dominican Republic', 18.591624753159, -68, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1753558945', NULL, NULL, '1753558945_68852fa10e9bc_image_2025-07-26_154205701.png', NULL, NULL, NULL, '50X20 PIES', 'CARA #2', NULL, NULL, 'Anual', '2025-07-26 14:42:25', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(118, 'impresa', 'AUT.LAS AMERICAS KM.22 NO.13 SANTA LUCIA', 32, 3, 'ESTE/OESTE', 'Autopista Las Américas Km. 22, no.13, Santo Domingo, Dominican Republic', 18.456264817585, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1753559256', NULL, NULL, '1753559256_688530d89609c_image_2025-07-26_154652689.png', NULL, NULL, NULL, '50X20 PIES', 'CARA #1', NULL, NULL, 'Anual', '2025-07-26 14:47:36', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(119, 'impresa', 'AUT.LAS AMERICAS KM.22 NO.13 SANTA LUCIA', 32, 3, 'ESTE/OESTE', 'Autopista Las Américas Km. 22, no.13, Santo Domingo, Dominican Republic', 18.456285171593, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1753559440', NULL, NULL, '1753559440_68853190e8468_image_2025-07-26_155030631.png', NULL, NULL, NULL, '50X20 PIES', 'CARA #2', NULL, NULL, 'Anual', '2025-07-26 14:50:40', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(120, 'impresa', 'AUT.LAS AMERICAS KM.22 NO.13 SANTA LUCIA', 32, 3, 'ESTE/OESTE', 'Autopista Las Américas Km. 22, no.13, Santo Domingo, Dominican Republic', 18.455878090973, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1753559679', NULL, NULL, '1753559679_6885327f52346_image_2025-07-26_155421170.png', NULL, NULL, NULL, '50X20 PIES', 'CARA #1', NULL, NULL, 'Anual', '2025-07-26 14:54:39', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(121, 'impresa', 'AUT.LAS AMERICAS KM.22 NO.13 SANTA LUCIA', 32, 3, 'ESTE/OESTE', 'Autopista Las Américas Km. 22, no.13, Santo Domingo, Dominican Republic', 18.455939153128, -69, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1753559749', NULL, NULL, '1753559749_688532c5db85d_image_2025-07-26_155537278.png', NULL, NULL, NULL, '50X20 PIES', 'CARA #2', NULL, NULL, 'Anual', '2025-07-26 14:55:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(122, 'impresa', 'CALLE EL SOL ESQ. JUAN PABLO DUARTE, SANTIAGO', 29, 3, 'NORTE', 'Calle Del Sol & Avenida Juan Pablo Duarte, Santiago de los Caballeros, Dominican Republic', 19.450259, -70, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1753559974', NULL, NULL, '1753559974_688533a6ef937_image_2025-07-26_155926858.png', NULL, NULL, NULL, '50X20 PIES', '1 CARA', NULL, NULL, 'Anual', '2025-07-26 14:59:34', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(123, 'impresa', 'AUTOPISTA DUARTE KM5,SANTIAGO,EL EMBRUJO', 29, 3, 'NORTE', 'Banco Santa Cruz La Sirena El Embrujo, La Sirena, Autopista Juan Pablo Duarte, Santiago de los Caballeros, Dominican Republic', 19.442353881175, -70, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1753560261', NULL, NULL, '1753560261_688534c57ec4a_image_2025-07-26_160409231.png', NULL, NULL, NULL, '50X20 PIES', 'CARA #1', NULL, NULL, 'Anual', '2025-07-26 15:04:21', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(124, 'impresa', 'AUTOPISTA DUARTE KM5,SANTIAGO,EL EMBRUJO', 29, 3, 'NORTE', 'Banco Santa Cruz La Sirena El Embrujo, La Sirena, Autopista Juan Pablo Duarte, Santiago de los Caballeros, Dominican Republic', 19.441527922052, -70, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1753560399', NULL, NULL, '1753560399_6885354f7186f_image_2025-07-26_160624214.png', NULL, NULL, NULL, '50X20 PIES', 'CARA #2', NULL, NULL, 'Anual', '2025-07-26 15:06:39', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(125, 'impresa', 'AUT. DUARTE 11 1/2 SANTIAGO, EL PUÑAL', 29, 3, 'NORTE', 'Autopista Duarte 1/2, Santiago de los Caballeros, Dominican Republic', 19.4095785, -70, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1753560651', NULL, NULL, '1753560651_6885364b12971_image_2025-07-26_161044288.png', NULL, NULL, NULL, '50X20 PIES', 'CARA #1', NULL, NULL, 'Anual', '2025-07-26 15:10:51', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `vallas` (`id`, `tipo`, `nombre`, `provincia_id`, `proveedor_id`, `zona`, `ubicacion`, `lat`, `lng`, `url_stream_pantalla`, `url_stream_trafico`, `url_stream`, `en_vivo`, `mostrar_precio_cliente`, `precio`, `estado`, `disponible`, `imagen`, `imagen1`, `imagen2`, `imagen_previa`, `audiencia_mensual`, `frecuencia_dias`, `spot_time_seg`, `medida`, `descripcion`, `destacado_orden`, `numero_licencia`, `tipo_licencia`, `fecha_creacion`, `fecha_vencimiento`, `estado_valla`, `visible_publico`, `mostrar_precios_market`, `comentarios`, `numero_contrato`, `cliente_id`, `url_documentacion`, `precio_alquiler`, `imagen_tercera`, `imagen_cuarta`, `responsable_id`, `condiciones_mantenimiento`) VALUES
(126, 'impresa', 'AUT. DUARTE 11 1/2 SANTIAGO, EL PUÑAL', 29, 3, 'NORTE', 'Autopista Duarte 1/2, Santiago de los Caballeros, Dominican Republic', 19.4095785, -70, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1753560700', NULL, NULL, '1753560700_6885367c57b43_image_2025-07-26_161131773.png', NULL, NULL, NULL, '50X20 PIES', 'CARA #2', NULL, NULL, 'Anual', '2025-07-26 15:11:40', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(127, 'impresa', 'AUTOPISTA DUARTE KM 6 1/1, SANTIAGO', 29, 3, 'NORTE', 'Autopista Juan Pablo Duarte Km 6½, Santiago de los Caballeros, Dominican Republic', 19.419137984935, -70, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1753560829', NULL, NULL, '1753560829_688536fd6d054_image_2025-07-26_161333836.png', NULL, NULL, NULL, '50X20 PIES', 'CARA #1', NULL, NULL, 'Anual', '2025-07-26 15:13:49', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(128, 'impresa', 'AUTOPISTA DUARTE KM 6 1/2, SANTIAGO', 29, 3, 'NORTE', 'Autopista Juan Pablo Duarte Km 6½, Santiago de los Caballeros, Dominican Republic', 19.419218932838, -70.639997003705, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '50X20 PIES', 'CARA #2', NULL, NULL, 'Anual', '2025-07-26 15:14:42', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(129, 'impresa', 'AUTOPISTA DUARTE KM 6 1/2, SANTIAGO', 29, 3, 'NORTE', 'Autopista Juan Pablo Duarte Km 6½, Santiago de los Caballeros, Dominican Republic', 19.4190368, -70.6398468, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1753560884', NULL, NULL, '1753560884_688537344b79e_image_2025-07-26_161433764.png', NULL, NULL, NULL, '50X20 PIES', 'CARA #2', NULL, NULL, 'Anual', '2025-07-26 15:14:44', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(130, 'impresa', 'AUTOPISTA JOAQUIN BALAGUER, PARADA CHITO KM. 3 1/2', 29, 3, 'NORTE', 'Autopista Joaquín Balaguer 1/2, Santiago de los Caballeros, Dominican Republic', 19.4909161, -70, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1753561121', NULL, NULL, '1753561121_688538212e19c_image_2025-07-26_161834780.png', NULL, NULL, NULL, '50X16 PIES', 'CARA #1', NULL, NULL, 'Anual', '2025-07-26 15:18:41', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(131, 'impresa', 'AUTOPISTA JOAQUIN BALAGUER, PARADA CHITO KM. 3 1/2', 29, 3, 'NORTE', 'Autopista Joaquín Balaguer 1/2, Santiago de los Caballeros, Dominican Republic', 19.4907138196, -70, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1753561181', NULL, NULL, '1753561181_6885385d71609_image_2025-07-26_161932589.png', NULL, NULL, NULL, '50X16 PIES', 'CARA #2', NULL, NULL, 'Anual', '2025-07-26 15:19:41', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(132, 'impresa', 'CARRETERA RAMON CACERES KM.4 1/2 MOCA', 9, 3, 'NORTE', 'Autopista Ramón Cáceres 4, Moca, Dominican Republic', 19.3858419, -70, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1753561733', NULL, NULL, '1753561733_68853a85ed56e_image_2025-07-26_162804725.png', NULL, NULL, NULL, '36X12 PIES', 'CARA #2', NULL, NULL, 'Anual', '2025-07-26 15:28:53', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(133, 'impresa', 'CARRETERA RAMON CACERES KM. 4 1/2 MOCA', 9, 3, 'NORTE', 'Autopista Ramón Cáceres 4, Moca, Dominican Republic', 19.3858419, -70.5338301, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1753561815', NULL, NULL, '1753561815_68853ad79446b_image_2025-07-26_163006450.png', NULL, NULL, NULL, '36X12 PIES', 'CARA #1', NULL, NULL, 'Anual', '2025-07-26 15:30:15', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(134, 'impresa', 'AUT. 6 DE NOVIEMBRE KM. 14 CASA N0. 3 QUITA SUEÑOM HAINA', 24, NULL, 'SUR', 'Autopista 6 de Noviembre, Bajos de Haina, Dominican Republic', 18.4352648, -70, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, NULL, NULL, NULL, '1753562133_68853c1581433_image_2025-07-26_163522179.png', NULL, NULL, NULL, '50X20 PIES', 'CARA #1', NULL, NULL, 'Anual', '2025-07-26 15:35:33', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(135, 'impresa', 'AUT. 6 DE NOVIEMBRE KM. 14 CASA N0. 3 QUITA SUEÑOM HAINA', 24, 3, 'SUR', 'Autopista 6 de Noviembre, Bajos de Haina, Dominican Republic', 18.4352648, -70, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1753562197', NULL, NULL, '1753562197_68853c55f37b2_image_2025-07-26_163629351.png', NULL, NULL, NULL, '50X20 PIES', 'CARA #2', NULL, NULL, 'Anual', '2025-07-26 15:36:37', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(136, 'impresa', 'CARRETERA SANCHEZ, CASA NO. 08, SECCION PAYA, DE LA CIUDAD DE BANI, PROVINCIA PERAVIA', 21, 3, 'SUR', 'Carretera Sanchez, Paya, Dominican Republic', 18.2609386, -70, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1753562396', NULL, NULL, '1753562396_68853d1cf37bb_image_2025-07-26_163949053.png', NULL, NULL, NULL, '50X16 PIES', 'CARA #1', NULL, NULL, 'Anual', '2025-07-26 15:39:56', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(137, 'impresa', 'CARRETERA SANCHEZ, CASA NO. 08, SECCION PAYA, DE LA CIUDAD DE BANI, PROVINCIA PERAVIA', 21, 3, 'SUR', 'Carretera Sanchez, Paya, Dominican Republic', 18.2609386, -70, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1753562461', NULL, NULL, '1753562461_68853d5db4c21_image_2025-07-26_164048235.png', NULL, NULL, NULL, '50X16 PIES', 'CARA #2', NULL, NULL, 'Anual', '2025-07-26 15:41:01', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(138, 'impresa', 'CARRETERA SANCHEZ KM. 3 1/2 AZUA', 2, 3, 'SUR', 'Carretera Sanchez 1/2, Azua, Dominican Republic', 18.4536058, -70, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1753562607', NULL, NULL, '1753562607_68853def61048_image_2025-07-26_164303208.png', NULL, NULL, NULL, '50X16 PIES', 'CARA #1', NULL, NULL, 'Anual', '2025-07-26 15:43:27', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(139, 'impresa', 'CARRETERA SANCHEZ KM. 3 1/2 AZUA', 2, 3, 'SUR', 'Carretera Sanchez 1/2, Azua, Dominican Republic', 18.4536058, -70, NULL, NULL, NULL, 0, 0, -0.01, 1, 1, '1753562660', NULL, NULL, '1753562660_68853e247e9e3_image_2025-07-26_164413177.png', NULL, NULL, NULL, '50X16 PIES', 'CARA #2', NULL, NULL, 'Anual', '2025-07-26 15:44:20', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(140, 'impresa', 'C/GENERAL CABRAL ESQ. MONSEÑOR DE MERIÑO, SAN JUAN DE LA MAGUANA', 26, 3, 'SUR', 'Calle General Cabral & Calle Ramón Meriño, Las Matas de Farfán, San Juan, Dominican Republic', 18.8767498, -71, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1753563120', NULL, NULL, '1753563120_68853ff0a6236_image_2025-07-26_165150434.png', NULL, NULL, NULL, '36X12 PIES', '2 CARAS', NULL, NULL, 'Anual', '2025-07-26 15:52:00', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(141, 'impresa', 'AV. CASANDRA DAMIRON ESQ. GENERAL GASPAR POLANCO NO. 42, BARAHONA', 4, 3, 'SUR', 'Av. Casandra Damirón & Calle Gaspar Polanco, Barahona, Dominican Republic', 18.2092037, -71, NULL, NULL, NULL, 0, 0, 0.00, 1, 1, '1753563225', NULL, NULL, '1753563225_68854059c6864_image_2025-07-26_165335829.png', NULL, NULL, NULL, '36X12 PIES', '1 CARA', NULL, NULL, 'Anual', '2025-07-26 15:53:45', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(142, 'impresa', 'Puente Peatonal 2 Entrando a San Pedro de Macoris', 27, 7, 'Este', 'San Pedro de Macorís, Dominican Republic', 18.460946308697, -69, NULL, NULL, NULL, 0, 0, 65000.00, 1, 1, '1757940334', '1757940334_68c80a6eb447c_IMG-20250915-WA0006.jpg', NULL, '1757940334_68c80a6eb41b2_IMG-20250915-WA0007.jpg', 200000, NULL, NULL, '49 x 5 pies', 'Excelente espacio Publicitario Rompe tráfico en la Entrada del Puebll de SanPedro de Macoris, Impresion en Vinilo Rotulado Sobre Metal', NULL, NULL, 'Anual', '2025-09-15 07:45:34', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(143, 'impresa', 'Puente Peatonal 1 Entrando a San Pedro Macoris Cara1', 27, 7, 'ESTE', 'San Pedro de Macorís, Dominican Republic', 18.458364, -69.316533, NULL, NULL, NULL, 0, 1, 85000.00, 1, 1, '1759767451_68e3eb9b47867_Screenshot_20251006_121651_WhatsApp.jpg', NULL, NULL, '1759767451_68e3eb9b477ce_Screenshot_20251006_121651_WhatsApp.jpg', 400000, NULL, NULL, '52 x 5 Pies', 'Da la Bienvenida en Grande a Todos los Visitantes de la Ciudad de San Pedro de Macoris, Con esta Gigante Valla Rompe Trafico Fija tu marca como la Mas grande Opción a elegir por los Visitantes y Lugareños', NULL, NULL, 'Anual', '2025-09-16 16:34:13', NULL, 'activa', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `vallas_destacadas_pagos`
--

CREATE TABLE `vallas_destacadas_pagos` (
  `id` int(11) NOT NULL,
  `valla_id` int(11) NOT NULL,
  `proveedor_id` int(11) DEFAULT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `monto_pagado` decimal(10,2) DEFAULT '0.00',
  `fecha_pago` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `observacion` varchar(255) DEFAULT NULL,
  `orden` int(11) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `vallas_destacadas_pagos`
--

INSERT INTO `vallas_destacadas_pagos` (`id`, `valla_id`, `proveedor_id`, `cliente_id`, `fecha_inicio`, `fecha_fin`, `monto_pagado`, `fecha_pago`, `observacion`, `orden`) VALUES
(5, 90, NULL, NULL, '2025-07-03', '2025-07-13', 900.00, '2025-07-03 02:22:59', '', 1),
(6, 19, NULL, NULL, '2025-09-01', '2025-09-13', 0.00, '2025-09-19 18:19:49', '', 1),
(8, 140, 8, NULL, '2025-10-01', '2025-10-18', 90.00, '2025-10-05 13:38:04', 'ADS', 2),
(9, 141, NULL, NULL, '2025-10-05', '2025-11-02', 1.00, '2025-10-05 13:40:35', 'prueba', 1),
(10, 143, 1, NULL, '2025-10-01', '2025-10-19', 90.00, '2025-10-05 14:13:06', 'Prueba', 2),
(11, 17, 1, NULL, '2025-10-10', '2025-10-11', 1000.00, '2025-10-05 23:14:24', '', 1),
(12, 17, 1, NULL, '2025-10-06', '2025-10-07', 1000.00, '2025-10-05 23:14:56', '', 1);

-- --------------------------------------------------------

--
-- Table structure for table `vallas_licencias`
--

CREATE TABLE `vallas_licencias` (
  `id` int(11) NOT NULL,
  `valla_id` int(11) NOT NULL,
  `numero_licencia` varchar(100) NOT NULL,
  `tipo_licencia` enum('Anual','Temporal','Permanente','Especial','Licencia de Publicidad Exterior (INTRANT)','Permiso de Publicidad en Vías Interurbanas (INTRANT)','Permiso de Publicidad Municipal','Licencia para Publicidad en Zonas Costeras','Licencia para Publicidad Temporal','Permiso de Publicidad en Vehículos del Transporte Público','Permiso de Publicidad en Espacios Públicos Municipales') DEFAULT 'Anual',
  `tipo_permiso` enum('Publicidad Exterior','Instalación Temporal','Publicitaria Permanente','Publicidad Especial') DEFAULT 'Publicidad Exterior',
  `tipo_pago` enum('Pago único','Pago mensual','Pago trimestral','Pago semestral','Pago anual','Pago con vencimiento flexible','Pago por suscripción','Pago anticipado','Pago a plazos','Pago por comisión','Pago por volumen','Pago según condiciones específicas (negociable)') DEFAULT 'Pago único',
  `descripcion` text,
  `estado` enum('activo','inactivo') DEFAULT 'activo',
  `fecha_vencimiento` datetime DEFAULT NULL,
  `comentarios_estado` text,
  `cliente_id` int(11) DEFAULT NULL,
  `tipo_pago_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `valla_media`
--

CREATE TABLE `valla_media` (
  `id` int(11) NOT NULL,
  `valla_id` int(11) NOT NULL,
  `tipo` enum('foto','video','pdf') NOT NULL DEFAULT 'foto',
  `url` varchar(255) NOT NULL,
  `principal` tinyint(1) NOT NULL DEFAULT '0',
  `creado` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Triggers `valla_media`
--
DELIMITER $$
CREATE TRIGGER `valla_media_bi_uni` BEFORE INSERT ON `valla_media` FOR EACH ROW BEGIN
  IF NEW.principal = 1 THEN
    UPDATE valla_media SET principal=0 WHERE valla_id = NEW.valla_id;
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `valla_media_bu_uni` BEFORE UPDATE ON `valla_media` FOR EACH ROW BEGIN
  IF NEW.principal = 1 AND (OLD.principal IS NULL OR OLD.principal <> 1) THEN
    UPDATE valla_media SET principal=0 WHERE valla_id = NEW.valla_id AND id <> NEW.id;
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `valla_precios`
--

CREATE TABLE `valla_precios` (
  `id` int(11) NOT NULL,
  `valla_id` int(11) NOT NULL,
  `plan` enum('dia','mes','3m','12m','custom') NOT NULL,
  `meses` smallint(6) NOT NULL DEFAULT '0',
  `precio` decimal(12,2) NOT NULL DEFAULT '0.00',
  `moneda` enum('DOP','USD') NOT NULL DEFAULT 'DOP',
  `publicar` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `vendor_commissions`
--

CREATE TABLE `vendor_commissions` (
  `id` int(11) NOT NULL,
  `proveedor_id` int(11) NOT NULL,
  `valla_id` int(11) DEFAULT NULL,
  `comision_pct` decimal(5,2) NOT NULL,
  `vigente_desde` date NOT NULL,
  `vigente_hasta` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `vendor_commissions`
--

INSERT INTO `vendor_commissions` (`id`, `proveedor_id`, `valla_id`, `comision_pct`, `vigente_desde`, `vigente_hasta`) VALUES
(1, 1, NULL, 10.00, '2025-08-21', NULL);

--
-- Triggers `vendor_commissions`
--
DELIMITER $$
CREATE TRIGGER `trg_vendor_commissions_def` BEFORE INSERT ON `vendor_commissions` FOR EACH ROW BEGIN
  IF NEW.vigente_desde IS NULL THEN
    SET NEW.vigente_desde = CURDATE();
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `vendor_config`
--

CREATE TABLE `vendor_config` (
  `id` int(11) NOT NULL,
  `proveedor_id` int(11) NOT NULL,
  `nombre_empresa` varchar(200) DEFAULT NULL,
  `rnc` varchar(20) DEFAULT NULL,
  `plantilla` enum('consumo','credito','proforma','pos') DEFAULT 'credito',
  `logo_url` varchar(500) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `vencimiento_dias` int(11) NOT NULL DEFAULT '15',
  `itbis_pct` decimal(5,2) NOT NULL DEFAULT '18.00',
  `itbis_incluir` tinyint(1) NOT NULL DEFAULT '1',
  `ncf_serie` char(1) DEFAULT NULL,
  `ncf_tipo` char(2) DEFAULT NULL,
  `ncf_secuencia` int(10) UNSIGNED DEFAULT NULL,
  `ecf_habilitado` tinyint(1) NOT NULL DEFAULT '0',
  `ret_isr_pct` decimal(5,2) DEFAULT NULL,
  `ret_itbis_pct` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `vendor_config`
--

INSERT INTO `vendor_config` (`id`, `proveedor_id`, `nombre_empresa`, `rnc`, `plantilla`, `logo_url`, `updated_at`, `vencimiento_dias`, `itbis_pct`, `itbis_incluir`, `ncf_serie`, `ncf_tipo`, `ncf_secuencia`, `ecf_habilitado`, `ret_isr_pct`, `ret_itbis_pct`) VALUES
(1, 4, 'Ship24go srl', '8888888888', 'credito', '', '2025-08-23 15:51:29', 15, 18.00, 1, NULL, NULL, NULL, 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `vendor_equipo`
--

CREATE TABLE `vendor_equipo` (
  `id` int(11) NOT NULL,
  `proveedor_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `rol` enum('owner','gestor','operador','visor') NOT NULL DEFAULT 'operador',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `creado` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `vendor_members`
--

CREATE TABLE `vendor_members` (
  `id` int(11) NOT NULL,
  `proveedor_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `rol` enum('owner','admin','editor','viewer') NOT NULL DEFAULT 'editor',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `creado` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `vendor_membresias`
--

CREATE TABLE `vendor_membresias` (
  `id` int(11) NOT NULL,
  `proveedor_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date DEFAULT NULL,
  `estado` enum('pendiente','activa','expirada','cancelada') DEFAULT 'pendiente',
  `pago_metodo` enum('transferencia','stripe','gratis') DEFAULT 'gratis',
  `pago_referencia` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `vendor_membresias`
--

INSERT INTO `vendor_membresias` (`id`, `proveedor_id`, `plan_id`, `fecha_inicio`, `fecha_fin`, `estado`, `pago_metodo`, `pago_referencia`, `created_at`) VALUES
(1, 4, 4, '2025-08-21', '2026-08-21', 'pendiente', 'stripe', NULL, '2025-08-21 21:53:13'),
(2, 4, 4, '2025-08-21', '2026-08-21', 'pendiente', 'transferencia', NULL, '2025-08-21 21:53:23'),
(3, 4, 3, '2025-08-21', '2025-11-21', 'pendiente', 'stripe', NULL, '2025-08-21 22:14:36'),
(4, 4, 3, '2025-08-21', '2025-11-21', 'pendiente', 'transferencia', NULL, '2025-08-21 22:39:38'),
(5, 4, 3, '2025-08-21', '2025-11-21', 'cancelada', 'stripe', NULL, '2025-08-21 22:39:42'),
(6, 4, 2, '2025-08-22', '2025-09-22', 'pendiente', 'stripe', NULL, '2025-08-23 02:56:15'),
(7, 4, 1, '2025-08-22', NULL, 'activa', 'gratis', NULL, '2025-08-23 04:47:13'),
(8, 4, 5, '2025-08-23', NULL, 'activa', 'gratis', NULL, '2025-08-23 05:15:06'),
(9, 5, 5, '2025-08-23', NULL, 'activa', 'gratis', NULL, '2025-08-23 22:04:00'),
(10, 8, 1, '2025-10-06', NULL, 'activa', 'gratis', NULL, '2025-10-06 16:50:41'),
(11, 8, 2, '2025-10-06', '2025-11-06', 'pendiente', 'gratis', NULL, '2025-10-06 16:51:45');

-- --------------------------------------------------------

--
-- Table structure for table `vendor_notif_config`
--

CREATE TABLE `vendor_notif_config` (
  `proveedor_id` int(11) NOT NULL,
  `smtp_host` varchar(200) DEFAULT NULL,
  `smtp_port` int(11) DEFAULT NULL,
  `smtp_user` varchar(200) DEFAULT NULL,
  `smtp_pass` varchar(200) DEFAULT NULL,
  `smtp_secure` varchar(10) DEFAULT NULL,
  `from_email` varchar(200) DEFAULT NULL,
  `from_nombre` varchar(200) DEFAULT NULL,
  `enable_email` tinyint(1) NOT NULL DEFAULT '0',
  `green_instance` varchar(64) DEFAULT NULL,
  `green_token` varchar(128) DEFAULT NULL,
  `notify_phone` varchar(32) DEFAULT NULL,
  `notify_days_before` int(11) NOT NULL DEFAULT '30',
  `enable_whatsapp` tinyint(1) NOT NULL DEFAULT '0',
  `cron_key` varchar(128) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `vendor_notif_settings`
--

CREATE TABLE `vendor_notif_settings` (
  `proveedor_id` int(11) NOT NULL,
  `smtp_use_global` tinyint(1) NOT NULL DEFAULT '1',
  `smtp_host` varchar(200) DEFAULT NULL,
  `smtp_port` int(11) DEFAULT NULL,
  `smtp_user` varchar(200) DEFAULT NULL,
  `smtp_pass` varchar(200) DEFAULT NULL,
  `smtp_secure` enum('tls','ssl','none') NOT NULL DEFAULT 'tls',
  `smtp_from_email` varchar(200) DEFAULT NULL,
  `smtp_from_nombre` varchar(200) DEFAULT NULL,
  `greenapi_base` varchar(200) NOT NULL DEFAULT 'https://api.green-api.com',
  `greenapi_instance_id` varchar(80) DEFAULT NULL,
  `greenapi_token` varchar(120) DEFAULT NULL,
  `greenapi_phone` varchar(32) DEFAULT NULL,
  `notif_emails` json DEFAULT NULL,
  `notif_whatsapp` json DEFAULT NULL,
  `dias_antes_vencer` int(11) NOT NULL DEFAULT '30',
  `habilitar_email` tinyint(1) NOT NULL DEFAULT '1',
  `habilitar_whatsapp` tinyint(1) NOT NULL DEFAULT '0',
  `habilitar_cron` tinyint(1) NOT NULL DEFAULT '1',
  `actualizado` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `vendor_notif_settings`
--

INSERT INTO `vendor_notif_settings` (`proveedor_id`, `smtp_use_global`, `smtp_host`, `smtp_port`, `smtp_user`, `smtp_pass`, `smtp_secure`, `smtp_from_email`, `smtp_from_nombre`, `greenapi_base`, `greenapi_instance_id`, `greenapi_token`, `greenapi_phone`, `notif_emails`, `notif_whatsapp`, `dias_antes_vencer`, `habilitar_email`, `habilitar_whatsapp`, `habilitar_cron`, `actualizado`) VALUES
(4, 1, NULL, NULL, NULL, NULL, 'tls', NULL, NULL, 'https://api.green-api.com', NULL, NULL, NULL, '[\"info@ship24go.com\"]', '[]', 30, 1, 0, 1, '2025-08-23 21:32:45');

-- --------------------------------------------------------

--
-- Table structure for table `vendor_notif_templates`
--

CREATE TABLE `vendor_notif_templates` (
  `id` int(11) NOT NULL,
  `proveedor_id` int(11) NOT NULL,
  `nombre` varchar(64) NOT NULL,
  `asunto` varchar(200) NOT NULL,
  `body_html` mediumtext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `vendor_notif_templates`
--

INSERT INTO `vendor_notif_templates` (`id`, `proveedor_id`, `nombre`, `asunto`, `body_html`) VALUES
(1, 4, 'aviso_vencimiento', 'Aviso de vencimiento de licencia', '<p>Hola equipo,</p><p>La licencia <b>#{{licencia_id}}</b> de {{cliente_nombre}} en {{ciudad}} vence el <b>{{fecha_vencimiento}}</b>.</p><p>Estado: {{estado}}. Dirección: {{direccion}}</p><p>Panel: {{enlace_gestion}}</p>'),
(2, 4, 'recordatorio_pago', 'Recordatorio de pago de factura', '<p>Hola {{cliente_nombre}},</p><p>Tu factura <b>{{factura_numero}}</b> por <b>{{importe}}</b> vence el <b>{{fecha_vencimiento}}</b>.</p><p>Paga aquí: <a href=\"{{enlace_pago}}\">{{enlace_pago}}</a></p>');

-- --------------------------------------------------------

--
-- Table structure for table `vendor_pagos`
--

CREATE TABLE `vendor_pagos` (
  `id` int(11) NOT NULL,
  `membresia_id` int(11) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `moneda` varchar(10) NOT NULL DEFAULT 'USD',
  `metodo` enum('transferencia','stripe') NOT NULL,
  `referencia` varchar(191) DEFAULT NULL,
  `fecha` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `vendor_payout_accounts`
--

CREATE TABLE `vendor_payout_accounts` (
  `id` int(11) NOT NULL,
  `proveedor_id` int(11) NOT NULL,
  `tipo` enum('banco','stripe','paypal') NOT NULL,
  `config_json` json NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `creado` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `vendor_planes`
--

CREATE TABLE `vendor_planes` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text,
  `limite_vallas` int(11) DEFAULT '0',
  `tipo_facturacion` enum('gratis','mensual','trimestral','anual','comision') DEFAULT 'gratis',
  `precio` decimal(10,2) DEFAULT '0.00',
  `comision` decimal(5,2) DEFAULT '0.00',
  `estado` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `prueba_dias` int(11) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `vendor_planes`
--

INSERT INTO `vendor_planes` (`id`, `nombre`, `descripcion`, `limite_vallas`, `tipo_facturacion`, `precio`, `comision`, `estado`, `created_at`, `prueba_dias`) VALUES
(1, 'Gratis', 'Publica hasta 10 vallas, prueba limitada.', 10, 'gratis', 0.00, 0.00, 1, '2025-08-21 20:50:35', 0),
(2, 'Básico', 'Hasta 50 vallas, facturación mensual.', 50, 'mensual', 49.99, 0.00, 1, '2025-08-21 20:50:35', 0),
(3, 'Pro', 'Hasta 200 vallas, trimestral.', 200, 'trimestral', 129.99, 5.00, 1, '2025-08-21 20:50:35', 0),
(4, 'Premium', 'Vallas ilimitadas, contacto directo con clientes.', 0, 'anual', 399.99, 0.00, 1, '2025-08-21 20:50:35', 0),
(5, 'Comisión', 'Ilimitado, paga por comisión de cada alquiler.', 0, 'comision', 0.00, 15.00, 1, '2025-08-21 20:50:35', 0);

-- --------------------------------------------------------

--
-- Table structure for table `vendor_plan_features`
--

CREATE TABLE `vendor_plan_features` (
  `plan_id` int(11) NOT NULL,
  `access_crm` tinyint(1) NOT NULL DEFAULT '0',
  `access_facturacion` tinyint(1) NOT NULL DEFAULT '0',
  `access_mapa` tinyint(1) NOT NULL DEFAULT '1',
  `access_export` tinyint(1) NOT NULL DEFAULT '0',
  `soporte_ncf` tinyint(1) NOT NULL DEFAULT '0',
  `comision_model` enum('none','pct','flat','mixed') NOT NULL DEFAULT 'none',
  `comision_pct` decimal(5,2) NOT NULL DEFAULT '0.00',
  `comision_flat` decimal(10,2) NOT NULL DEFAULT '0.00',
  `factura_auto` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `vendor_plan_features`
--

INSERT INTO `vendor_plan_features` (`plan_id`, `access_crm`, `access_facturacion`, `access_mapa`, `access_export`, `soporte_ncf`, `comision_model`, `comision_pct`, `comision_flat`, `factura_auto`) VALUES
(1, 0, 0, 1, 0, 0, 'none', 0.00, 0.00, 0),
(2, 1, 1, 1, 0, 1, 'none', 0.00, 0.00, 1),
(3, 1, 1, 1, 0, 1, 'none', 0.00, 0.00, 1),
(4, 1, 1, 1, 1, 1, 'none', 0.00, 0.00, 1),
(5, 1, 0, 1, 0, 0, 'pct', 15.00, 0.00, 0);

-- --------------------------------------------------------

--
-- Table structure for table `vendor_roles_permisos`
--

CREATE TABLE `vendor_roles_permisos` (
  `id` int(11) NOT NULL,
  `proveedor_id` int(11) NOT NULL,
  `rol` varchar(30) NOT NULL,
  `permiso` varchar(50) NOT NULL,
  `valor` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `vendor_webhooks`
--

CREATE TABLE `vendor_webhooks` (
  `id` int(11) NOT NULL,
  `proveedor_id` int(11) NOT NULL,
  `evento` enum('reserva.creada','reserva.cancelada','pago.aprobado','pago.pendiente') NOT NULL,
  `url` varchar(255) NOT NULL,
  `secreto` varchar(64) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `creado` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_facturas_vendor`
-- (See below for the actual view)
--
CREATE TABLE `vw_facturas_vendor` (
`id` int(11)
,`usuario_id` int(11)
,`valla_id` int(11)
,`monto_total` decimal(10,2)
,`precio_personalizado` decimal(10,2)
,`descuento` decimal(10,2)
,`estado` enum('pendiente','pagado')
,`metodo_pago` enum('stripe','transferencia')
,`fecha_generada` datetime
,`fecha_generacion` datetime
,`fecha_pago` datetime
,`stripe_link` varchar(255)
,`comision_pct` decimal(5,2)
,`comision_monto` decimal(10,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_vallas_geo`
-- (See below for the actual view)
--
CREATE TABLE `vw_vallas_geo` (
`id` int(11)
,`proveedor_id` int(11)
,`nombre` varchar(100)
,`lat` double
,`lng` double
,`estado_valla` enum('activa','inactiva')
,`disponible` tinyint(1)
,`precio` decimal(10,2)
,`medida` varchar(80)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_vendor_features`
-- (See below for the actual view)
--
CREATE TABLE `vw_vendor_features` (
`proveedor_id` int(11)
,`access_crm` tinyint(1)
,`access_facturacion` tinyint(1)
,`access_mapa` tinyint(1)
,`access_export` tinyint(1)
,`soporte_ncf` tinyint(1)
,`comision_model` enum('none','pct','flat','mixed')
,`comision_pct` decimal(5,2)
,`comision_flat` decimal(10,2)
,`factura_auto` tinyint(1)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_vendor_licencias_30d`
-- (See below for the actual view)
--
CREATE TABLE `vw_vendor_licencias_30d` (
`id` int(11)
,`proveedor_id` int(11)
,`nombre` varchar(100)
,`numero_licencia` varchar(100)
,`fecha_vencimiento` datetime
,`dias_restantes` int(7)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_vendor_mantenimientos_proximos`
-- (See below for the actual view)
--
CREATE TABLE `vw_vendor_mantenimientos_proximos` (
`valla_id` int(11)
,`proveedor_id` int(11)
,`nombre` varchar(100)
,`fecha_inicio` date
,`fecha_fin` date
,`motivo` varchar(255)
);

-- --------------------------------------------------------

--
-- Table structure for table `web_analytics`
--

CREATE TABLE `web_analytics` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ymd` int(11) NOT NULL,
  `path` varchar(512) NOT NULL,
  `ip` varbinary(16) DEFAULT NULL,
  `ua` varchar(512) DEFAULT NULL,
  `ref` varchar(512) DEFAULT NULL,
  `u_campaign` varchar(64) DEFAULT NULL,
  `u_source` varchar(64) DEFAULT NULL,
  `u_medium` varchar(64) DEFAULT NULL,
  `u_term` varchar(64) DEFAULT NULL,
  `u_content` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `web_analytics`
--

INSERT INTO `web_analytics` (`id`, `ts`, `ymd`, `path`, `ip`, `ua`, `ref`, `u_campaign`, `u_source`, `u_medium`, `u_term`, `u_content`) VALUES
(1, '2025-09-25 22:51:41', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(2, '2025-09-25 22:51:51', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(3, '2025-09-25 22:52:29', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(4, '2025-09-25 22:52:56', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(5, '2025-09-25 22:59:47', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(6, '2025-09-25 23:01:09', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(7, '2025-09-25 23:01:27', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(8, '2025-09-25 23:03:03', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(9, '2025-09-25 23:07:21', 20250925, '/detalles-led/?id=23', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(10, '2025-09-25 23:11:44', 20250925, '/detalles-led/?id=23', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(11, '2025-09-25 23:17:42', 20250925, '/detalles-led/?id=23', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(12, '2025-09-25 23:19:46', 20250925, '/detalles-led/?id=23', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(13, '2025-09-25 23:25:08', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(14, '2025-09-25 23:25:25', 20250925, '/detalles-led/?id=91', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(15, '2025-09-25 23:25:36', 20250925, '/detalles-led/?id=91', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(16, '2025-09-25 23:25:58', 20250925, '/detalles-led/?id=23', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(17, '2025-09-25 23:27:03', 20250925, '/detalles-led/?id=23', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(18, '2025-09-25 23:30:41', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(19, '2025-09-25 23:30:51', 20250925, '/detalles-led/?id=91', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(20, '2025-09-25 23:32:12', 20250925, '/detalles-led/?id=23', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(21, '2025-09-25 23:32:21', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(22, '2025-09-25 23:32:32', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(23, '2025-09-25 23:32:45', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(24, '2025-09-25 23:32:55', 20250925, '/detalles-led/?id=46', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(25, '2025-09-25 23:36:48', 20250925, '/detalles-led/?id=91', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(26, '2025-09-25 23:40:44', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(27, '2025-09-25 23:41:25', 20250925, '/detalles-led/?id=23', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(28, '2025-09-25 23:42:03', 20250925, '/detalles-led/?id=91', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(29, '2025-09-25 23:44:29', 20250925, '/detalles-led/?id=46', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(30, '2025-09-25 23:44:50', 20250925, '/detalles-led/?id=46', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(31, '2025-09-26 00:02:12', 20250925, '/detalles-led/?id=23', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(32, '2025-09-26 00:07:46', 20250925, '/detalles-led/?id=23', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(33, '2025-09-26 00:14:42', 20250925, '/detalles-led/?id=23', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(34, '2025-09-26 00:15:00', 20250925, '/detalles-led/?id=23', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(35, '2025-09-26 00:15:09', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(36, '2025-09-26 00:22:00', 20250925, '/detalles-led/?id=46', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(37, '2025-09-26 00:22:09', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(38, '2025-09-26 00:22:51', 20250925, '/detalles-vallas/?id=142', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(39, '2025-09-26 00:23:50', 20250925, '/detalles-vallas/?id=143', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(40, '2025-09-26 00:25:35', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(41, '2025-09-26 00:25:49', 20250925, '/detalles-vallas/?id=143', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(42, '2025-09-26 00:26:23', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(43, '2025-09-26 00:29:05', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(44, '2025-09-26 00:29:08', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(45, '2025-09-26 00:29:11', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(46, '2025-09-26 00:29:25', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(47, '2025-09-26 00:29:27', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(48, '2025-09-26 00:29:58', 20250925, '/detalles-vallas/?id=142', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(49, '2025-09-26 00:33:36', 20250925, '/calendario/?id=142', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(50, '2025-09-26 00:34:23', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(51, '2025-09-26 00:34:37', 20250925, '/calendario/?id=7', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(52, '2025-09-26 00:35:18', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(53, '2025-09-26 00:36:56', 20250925, '/calendario/?id=142', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(54, '2025-09-26 00:37:06', 20250925, '/detalles-vallas/?id=142', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/calendario/?id=142', NULL, NULL, NULL, NULL, NULL),
(55, '2025-09-26 00:37:26', 20250925, '/calendario/?id=143', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(56, '2025-09-26 00:38:47', 20250925, '/calendario/?id=7', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(57, '2025-09-26 00:38:52', 20250925, '/calendario/?id=7', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(58, '2025-09-26 00:40:04', 20250925, '/calendario/?id=143', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(59, '2025-09-26 00:40:34', 20250925, '/calendario/?id=7', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(60, '2025-09-26 00:43:27', 20250925, '/calendario/?id=7', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(61, '2025-09-26 00:50:55', 20250925, '/calendario/?id=143', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(62, '2025-09-26 00:54:52', 20250925, '/calendario/?id=143', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(63, '2025-09-26 00:56:43', 20250925, '/calendario/?id=143', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(64, '2025-09-26 00:59:02', 20250925, '/calendario/?id=143', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(65, '2025-09-26 01:01:35', 20250925, '/calendario/?id=143', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(66, '2025-09-26 01:04:56', 20250925, '/calendario/?id=143', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(67, '2025-09-26 01:06:14', 20250925, '/detalles-vallas/?id=143', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/calendario/?id=143', NULL, NULL, NULL, NULL, NULL),
(68, '2025-09-26 01:11:35', 20250925, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(69, '2025-09-26 01:12:21', 20250925, '/calendario/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/calendario/?id=7', NULL, NULL, NULL, NULL, NULL),
(70, '2025-09-26 01:12:35', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(71, '2025-09-26 01:16:06', 20250925, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(72, '2025-09-26 01:16:33', 20250925, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(73, '2025-09-26 01:17:20', 20250925, '/detalles-vallas/?id=142', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(74, '2025-09-26 01:17:41', 20250925, '/detalles-led/?id=46', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(75, '2025-09-26 01:17:44', 20250925, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/detalles-led/?id=46', NULL, NULL, NULL, NULL, NULL),
(76, '2025-09-26 01:17:51', 20250925, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(77, '2025-09-26 01:17:52', 20250925, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(78, '2025-09-26 01:17:54', 20250925, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(79, '2025-09-26 01:20:51', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(80, '2025-09-26 01:21:42', 20250925, '/detalles-led/?id=91', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(81, '2025-09-26 01:21:45', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/detalles-led/?id=91', NULL, NULL, NULL, NULL, NULL),
(82, '2025-09-26 01:22:43', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(83, '2025-09-26 01:22:46', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(84, '2025-09-26 01:30:00', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/detalles-led/?id=91', NULL, NULL, NULL, NULL, NULL),
(85, '2025-09-26 01:31:10', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(86, '2025-09-26 01:33:19', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(87, '2025-09-26 01:34:13', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/detalles-led/?id=91', NULL, NULL, NULL, NULL, NULL),
(88, '2025-09-26 01:35:16', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/detalles-led/?id=91', NULL, NULL, NULL, NULL, NULL),
(89, '2025-09-26 01:36:56', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/detalles-led/?id=91', NULL, NULL, NULL, NULL, NULL),
(90, '2025-09-26 01:39:01', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/detalles-led/?id=91', NULL, NULL, NULL, NULL, NULL),
(91, '2025-09-26 01:39:05', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(92, '2025-09-26 01:39:07', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(93, '2025-09-26 01:39:48', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/detalles-led/?id=91', NULL, NULL, NULL, NULL, NULL),
(94, '2025-09-26 01:39:50', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/detalles-led/?id=91', NULL, NULL, NULL, NULL, NULL),
(95, '2025-09-26 01:39:52', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/detalles-led/?id=91', NULL, NULL, NULL, NULL, NULL),
(96, '2025-09-26 01:40:11', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(97, '2025-09-26 01:40:42', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/detalles-led/?id=91', NULL, NULL, NULL, NULL, NULL),
(98, '2025-09-26 01:41:11', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/detalles-led/?id=91', NULL, NULL, NULL, NULL, NULL),
(99, '2025-09-26 01:41:34', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(100, '2025-09-26 01:42:45', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(101, '2025-09-26 01:43:02', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(102, '2025-09-26 01:45:34', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(103, '2025-09-26 01:46:17', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(104, '2025-09-26 01:47:00', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(105, '2025-09-26 01:47:22', 20250925, '/detalles-led/?id=91', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(106, '2025-09-26 01:48:46', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/detalles-led/?id=91', NULL, NULL, NULL, NULL, NULL),
(107, '2025-09-26 01:51:31', 20250925, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(108, '2025-09-26 01:51:35', 20250925, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(109, '2025-09-26 01:52:45', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/detalles-led/?id=91', NULL, NULL, NULL, NULL, NULL),
(110, '2025-09-26 01:53:47', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/detalles-led/?id=91', NULL, NULL, NULL, NULL, NULL),
(111, '2025-09-26 01:54:29', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/detalles-led/?id=91', NULL, NULL, NULL, NULL, NULL),
(112, '2025-09-26 02:12:45', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/detalles-led/?id=91', NULL, NULL, NULL, NULL, NULL),
(113, '2025-09-26 02:12:48', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(114, '2025-09-26 02:12:50', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(115, '2025-09-26 02:13:06', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(116, '2025-09-26 02:19:52', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(117, '2025-09-26 02:21:13', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(118, '2025-09-26 02:21:39', 20250925, '/', 0x310ccd62, 'SeobilityBot (SEO Tool; https://www.seobility.net/sites/bot.html)', '', NULL, NULL, NULL, NULL, NULL),
(119, '2025-09-26 02:21:40', 20250925, '/', 0x310ccd62, 'SeobilityBot (SEO Tool; https://www.seobility.net/sites/bot.html)', '', NULL, NULL, NULL, NULL, NULL),
(120, '2025-09-26 02:21:41', 20250925, '/', 0x310ccd62, 'SeobilityBot (SEO Tool; https://www.seobility.net/sites/bot.html)', '', NULL, NULL, NULL, NULL, NULL),
(121, '2025-09-26 02:21:44', 20250925, '/', 0xa237d1c7, 'SeobilityBot (SEO Tool; https://www.seobility.net/sites/bot.html)', '', NULL, NULL, NULL, NULL, NULL),
(122, '2025-09-26 02:26:17', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(123, '2025-09-26 02:29:02', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(124, '2025-09-26 02:31:58', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(125, '2025-09-26 02:33:17', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(126, '2025-09-26 02:35:39', 20250925, '/', 0x310ccd62, 'SeobilityBot (SEO Tool; https://www.seobility.net/sites/bot.html)', '', NULL, NULL, NULL, NULL, NULL),
(127, '2025-09-26 02:35:39', 20250925, '/', 0x310ccd62, 'SeobilityBot (SEO Tool; https://www.seobility.net/sites/bot.html)', '', NULL, NULL, NULL, NULL, NULL),
(128, '2025-09-26 02:35:52', 20250925, '/', 0xa237d987, 'SeobilityBot (SEO Tool; https://www.seobility.net/sites/bot.html)', '', NULL, NULL, NULL, NULL, NULL),
(129, '2025-09-26 02:35:53', 20250925, '/', 0x310ccd62, 'SeobilityBot (SEO Tool; https://www.seobility.net/sites/bot.html)', '', NULL, NULL, NULL, NULL, NULL),
(130, '2025-09-26 02:35:54', 20250925, '/', 0x310ccd62, 'SeobilityBot (SEO Tool; https://www.seobility.net/sites/bot.html)', '', NULL, NULL, NULL, NULL, NULL),
(131, '2025-09-26 02:36:49', 20250925, '/', 0xa237d987, 'SeobilityBot (SEO Tool; https://www.seobility.net/sites/bot.html)', '', NULL, NULL, NULL, NULL, NULL),
(132, '2025-09-26 02:36:50', 20250925, '/', 0xa237d987, 'SeobilityBot (SEO Tool; https://www.seobility.net/sites/bot.html)', '', NULL, NULL, NULL, NULL, NULL),
(133, '2025-09-26 02:38:31', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(134, '2025-09-26 02:38:59', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(135, '2025-09-26 02:40:50', 20250925, '/', 0x74cbf495, 'SeobilityBot (SEO Tool; https://www.seobility.net/sites/bot.html)', '', NULL, NULL, NULL, NULL, NULL),
(136, '2025-09-26 02:42:54', 20250925, '/tipos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(137, '2025-09-26 02:46:49', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(138, '2025-09-26 02:47:05', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(139, '2025-09-26 02:48:45', 20250925, '/tipos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(140, '2025-09-26 02:49:55', 20250925, '/tipos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(141, '2025-09-26 02:50:22', 20250925, '/tipos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(142, '2025-09-26 02:51:18', 20250925, '/tipos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(143, '2025-09-26 02:52:43', 20250925, '/tipos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(144, '2025-09-26 02:53:15', 20250925, '/tipos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(145, '2025-09-26 02:53:33', 20250925, '/tipos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(146, '2025-09-26 02:54:49', 20250925, '/tipos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(147, '2025-09-26 02:55:29', 20250925, '/tipos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(148, '2025-09-26 02:57:39', 20250925, '/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(149, '2025-09-26 03:01:12', 20250925, '/tipos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(150, '2025-09-26 03:04:57', 20250925, '/?tipo=led', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(151, '2025-09-26 03:05:02', 20250925, '/?tipo=led', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(152, '2025-09-26 03:06:38', 20250925, '/tipos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(153, '2025-09-26 03:06:43', 20250925, '/tipos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(154, '2025-09-26 03:06:47', 20250925, '/tipos/buscar.php?tipo=led?tipo=led', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/tipos/', NULL, NULL, NULL, NULL, NULL),
(155, '2025-09-26 03:07:03', 20250925, '/tipos/buscar.php?tipo=impresa?tipo=impresa', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/tipos/', NULL, NULL, NULL, NULL, NULL),
(156, '2025-09-26 03:08:48', 20250925, '/tipos/buscar.php?tipo=vehiculo?tipo=vehiculo', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/tipos/', NULL, NULL, NULL, NULL, NULL),
(157, '2025-09-26 03:13:26', 20250925, '/es/alquiler-de-vallas-led/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(158, '2025-09-26 03:21:26', 20250925, '/es/alquiler-de-vallas-led/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(159, '2025-09-26 03:21:42', 20250925, '/es/alquiler-de-vallas-led/buscar.php?q=&tipo=led?q=&tipo=led', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/es/alquiler-de-vallas-led/', NULL, NULL, NULL, NULL, NULL),
(160, '2025-09-26 03:24:38', 20250925, '/tipos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/tipos/', NULL, NULL, NULL, NULL, NULL),
(161, '2025-09-26 03:26:48', 20250925, '/', 0xa237b6fe, 'SeobilityBot (SEO Tool; https://www.seobility.net/sites/bot.html)', '', NULL, NULL, NULL, NULL, NULL),
(162, '2025-09-26 03:31:30', 20250925, '/tipos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/tipos/', NULL, NULL, NULL, NULL, NULL),
(163, '2025-09-26 03:36:04', 20250925, '/es/catalogo/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(164, '2025-09-26 03:44:46', 20250925, '/es/catalogo/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(165, '2025-09-26 03:47:22', 20250925, '/es/catalogo/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/es/catalogo/', NULL, NULL, NULL, NULL, NULL),
(166, '2025-09-26 03:47:24', 20250925, '/es/catalogo/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/es/catalogo/', NULL, NULL, NULL, NULL, NULL),
(167, '2025-09-26 03:53:59', 20250925, '/es/catalogo/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/es/catalogo/', NULL, NULL, NULL, NULL, NULL),
(168, '2025-09-26 03:54:13', 20250925, '/detalles-vallas/?id=142', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/es/catalogo/', NULL, NULL, NULL, NULL, NULL),
(169, '2025-09-26 03:58:13', 20250925, '/es/catalogo/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(170, '2025-09-26 03:58:22', 20250925, '/es/catalogo/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/es/catalogo/', NULL, NULL, NULL, NULL, NULL),
(171, '2025-09-26 04:05:15', 20250926, '/es/catalogo/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(172, '2025-09-26 04:05:31', 20250926, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(173, '2025-09-26 04:05:42', 20250926, '/?tipo=impresa', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(174, '2025-09-26 04:15:17', 20250926, '/es/mapas/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(175, '2025-09-26 04:19:40', 20250926, '/es/mapas/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(176, '2025-09-26 04:19:58', 20250926, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(177, '2025-09-26 04:20:15', 20250926, '/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(178, '2025-09-26 04:20:17', 20250926, '/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(179, '2025-09-26 04:21:32', 20250926, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(180, '2025-09-26 04:29:26', 20250926, '/es/marketing/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(181, '2025-09-26 04:32:33', 20250926, '/es/marketing/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(182, '2025-09-26 04:33:14', 20250926, '/es/marketing/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(183, '2025-09-26 04:38:22', 20250926, '/es/marketing/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(184, '2025-09-26 04:39:10', 20250926, '/?tipo=mochila', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(185, '2025-09-26 04:41:15', 20250926, '/es/marketing/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(186, '2025-09-26 04:41:51', 20250926, '/es/catalogo/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/es/marketing/', NULL, NULL, NULL, NULL, NULL),
(187, '2025-09-26 04:49:00', 20250926, '/es/blog/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(188, '2025-09-26 04:55:18', 20250926, '/es/marketing/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(189, '2025-09-26 04:55:32', 20250926, '/es/blog/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(190, '2025-09-26 04:55:38', 20250926, '/es/blog/post_detalle.php?slug=zonas-turisticas', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/es/blog/', NULL, NULL, NULL, NULL, NULL),
(191, '2025-09-26 04:56:22', 20250926, '/es/blog/post_detalle.php?slug=razones-vallas-led', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/es/blog/', NULL, NULL, NULL, NULL, NULL),
(192, '2025-09-26 05:04:34', 20250926, '/es/blog/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(193, '2025-09-26 05:04:38', 20250926, '/es/blog/post_detalle.php?slug=roi-exterior', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/es/blog/', NULL, NULL, NULL, NULL, NULL),
(194, '2025-09-26 05:04:50', 20250926, '/es/blog/post_detalle.php?slug=movil-vehiculos', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/es/blog/', NULL, NULL, NULL, NULL, NULL),
(195, '2025-09-26 05:05:16', 20250926, '/es/blog/post_detalle.php?slug=razones-vallas-led', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/es/blog/', NULL, NULL, NULL, NULL, NULL),
(196, '2025-09-26 05:08:56', 20250926, '/es/blog/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(197, '2025-09-26 05:11:08', 20250926, '/es/blog/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(198, '2025-09-26 05:11:28', 20250926, '/es/blog/post_detalle.php?slug=estrategia-360', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/es/blog/', NULL, NULL, NULL, NULL, NULL),
(199, '2025-09-26 05:11:49', 20250926, '/es/blog/post_detalle.php?slug=estrategia-360', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/es/blog/', NULL, NULL, NULL, NULL, NULL),
(200, '2025-09-26 05:14:27', 20250926, '/es/blog/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(201, '2025-09-26 05:14:39', 20250926, '/es/blog/post_detalle.php?slug=errores-comunes', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/es/blog/', NULL, NULL, NULL, NULL, NULL),
(202, '2025-09-26 05:16:58', 20250926, '/es/blog/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(203, '2025-09-26 05:19:47', 20250926, '/es/blog/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(204, '2025-09-26 05:23:51', 20250926, '/es/blog/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(205, '2025-09-26 05:24:04', 20250926, '/es/blog/post_detalle.php?slug=errores-comunes', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/es/blog/', NULL, NULL, NULL, NULL, NULL),
(206, '2025-09-26 05:24:20', 20250926, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(207, '2025-09-26 05:31:34', 20250926, '/es/analisis/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(208, '2025-09-26 05:31:57', 20250926, '/es/analisis/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/es/analisis/', NULL, NULL, NULL, NULL, NULL),
(209, '2025-09-26 05:32:00', 20250926, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(210, '2025-09-26 05:44:40', 20250926, '/es/privacidad/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(211, '2025-09-26 05:46:09', 20250926, '/es/condiciones/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(212, '2025-09-26 05:48:02', 20250926, '/login/moviles/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(213, '2025-09-26 05:49:12', 20250926, '/login/moviles/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(214, '2025-09-26 05:50:20', 20250926, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(215, '2025-09-26 05:52:10', 20250926, '/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(216, '2025-09-26 05:52:17', 20250926, '/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(217, '2025-09-26 05:52:50', 20250926, '/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', '', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `web_analytics` (`id`, `ts`, `ymd`, `path`, `ip`, `ua`, `ref`, `u_campaign`, `u_source`, `u_medium`, `u_term`, `u_content`) VALUES
(218, '2025-09-26 05:53:40', 20250926, '/calendario/?id=17', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(219, '2025-09-26 05:53:44', 20250926, '/detalles-led/?id=17', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(220, '2025-09-26 05:53:53', 20250926, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/detalles-led/?id=17', NULL, NULL, NULL, NULL, NULL),
(221, '2025-09-26 05:53:56', 20250926, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(222, '2025-09-26 05:53:57', 20250926, '/carritos/', 0x42f95384, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36 (compatible; Google-Read-Aloud; +https://support.google.com/webmasters/answer/1061943)', '', NULL, NULL, NULL, NULL, NULL),
(223, '2025-09-26 05:53:57', 20250926, '/carritos/', 0x42f95383, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36 (compatible; Google-Read-Aloud; +https://support.google.com/webmasters/answer/1061943)', '', NULL, NULL, NULL, NULL, NULL),
(224, '2025-09-26 05:53:57', 20250926, '/carritos/', 0x42f95384, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36 (compatible; Google-Read-Aloud; +https://support.google.com/webmasters/answer/1061943)', '', NULL, NULL, NULL, NULL, NULL),
(225, '2025-09-26 05:54:55', 20250926, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(226, '2025-09-26 05:54:57', 20250926, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(227, '2025-09-26 05:55:01', 20250926, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(228, '2025-09-26 05:55:03', 20250926, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(229, '2025-09-26 05:55:04', 20250926, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(230, '2025-09-26 05:55:35', 20250926, '/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(231, '2025-09-26 05:55:43', 20250926, '/calendario/?id=7', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(232, '2025-09-26 05:55:46', 20250926, '/calendario/index.php', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/calendario/?id=7', NULL, NULL, NULL, NULL, NULL),
(233, '2025-09-26 05:55:48', 20250926, '/calendario/index.php', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/calendario/index.php', NULL, NULL, NULL, NULL, NULL),
(234, '2025-09-26 05:56:05', 20250926, '/es/privacidad/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(235, '2025-09-26 05:56:09', 20250926, '/es/privacidad/index.php', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/es/privacidad/', NULL, NULL, NULL, NULL, NULL),
(236, '2025-09-26 05:56:12', 20250926, '/es/privacidad/index.php', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/es/privacidad/index.php', NULL, NULL, NULL, NULL, NULL),
(237, '2025-09-26 05:57:48', 20250926, '/calendario/?id=7', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(238, '2025-09-26 05:57:49', 20250926, '/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/calendario/?id=7', NULL, NULL, NULL, NULL, NULL),
(239, '2025-09-26 05:58:06', 20250926, '/es/privacidad/index.php', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/es/privacidad/index.php', NULL, NULL, NULL, NULL, NULL),
(240, '2025-09-26 05:58:11', 20250926, '/es/privacidad/index.php', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/es/privacidad/index.php', NULL, NULL, NULL, NULL, NULL),
(241, '2025-09-26 05:58:13', 20250926, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/es/privacidad/index.php', NULL, NULL, NULL, NULL, NULL),
(242, '2025-09-26 06:08:45', 20250926, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/es/privacidad/index.php', NULL, NULL, NULL, NULL, NULL),
(243, '2025-09-26 06:08:53', 20250926, '/es/alquiler-de-vallas-led/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(244, '2025-09-26 06:08:57', 20250926, '/es/catalogo/?tipo=movilled', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(245, '2025-09-26 06:09:01', 20250926, '/es/marketing/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(246, '2025-09-26 06:09:05', 20250926, '/es/blog/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(247, '2025-09-26 06:09:09', 20250926, '/es/blog/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(248, '2025-09-26 06:09:13', 20250926, '/es/analisis/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(249, '2025-09-26 06:09:17', 20250926, '/es/mapas/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(250, '2025-09-26 06:09:21', 20250926, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(251, '2025-09-26 06:09:25', 20250926, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/es/privacidad/index.php', NULL, NULL, NULL, NULL, NULL),
(252, '2025-09-26 06:12:10', 20250926, '/', 0x310ccd62, 'SeobilityBot (SEO Tool; https://www.seobility.net/sites/bot.html)', '', NULL, NULL, NULL, NULL, NULL),
(253, '2025-09-26 06:13:01', 20250926, '/', 0xcdd21fd0, 'Hello from Palo Alto Networks, find out more about our scans in https://docs-cortex.paloaltonetworks.com/r/1/Cortex-Xpanse/Scanning-activity', '', NULL, NULL, NULL, NULL, NULL),
(254, '2025-09-26 06:14:12', 20250926, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/es/privacidad/index.php', NULL, NULL, NULL, NULL, NULL),
(255, '2025-09-26 06:14:12', 20250926, '/app.js?v=1758424400', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(256, '2025-09-26 06:14:32', 20250926, '/favicon.ico', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/login/moviles/', NULL, NULL, NULL, NULL, NULL),
(257, '2025-09-26 06:15:29', 20250926, '/favicon.ico', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/error/404.html', NULL, NULL, NULL, NULL, NULL),
(258, '2025-09-26 06:15:42', 20250926, '/s', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(259, '2025-09-26 06:15:43', 20250926, '/app.js?v=1758424400', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/s', NULL, NULL, NULL, NULL, NULL),
(260, '2025-09-26 06:22:03', 20250926, '/favicon.ico', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/s', NULL, NULL, NULL, NULL, NULL),
(261, '2025-09-26 06:22:05', 20250926, '/s?warm=1', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/s', NULL, NULL, NULL, NULL, NULL),
(262, '2025-09-26 06:22:06', 20250926, '/app.js?v=1758424400', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/s?warm=1', NULL, NULL, NULL, NULL, NULL),
(263, '2025-09-26 06:22:50', 20250926, '/?warm=1', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(264, '2025-09-26 06:23:14', 20250926, '/es/alquiler-de-vallas-led/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/?warm=1', NULL, NULL, NULL, NULL, NULL),
(265, '2025-09-26 06:23:14', 20250926, '/es/alquiler-de-vallas-led/app.js?v=1758424400', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/es/alquiler-de-vallas-led/', NULL, NULL, NULL, NULL, NULL),
(266, '2025-09-26 06:23:25', 20250926, '/es/blog/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/?warm=1', NULL, NULL, NULL, NULL, NULL),
(267, '2025-09-26 06:23:25', 20250926, '/es/blog/app.js?v=1758424400', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/es/blog/', NULL, NULL, NULL, NULL, NULL),
(268, '2025-09-26 06:23:38', 20250926, '/es/blog/post_detalle.php?slug=zonas-turisticas', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/es/blog/', NULL, NULL, NULL, NULL, NULL),
(269, '2025-09-26 06:23:43', 20250926, '/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/es/blog/', NULL, NULL, NULL, NULL, NULL),
(270, '2025-09-26 06:40:12', 20250926, '/es/alquiler-de-vallas-led/buscar.php?q=&tipo=led?q=&tipo=led', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/es/alquiler-de-vallas-led/', NULL, NULL, NULL, NULL, NULL),
(271, '2025-09-26 06:40:36', 20250926, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(272, '2025-09-26 06:49:13', 20250926, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(273, '2025-09-26 06:52:24', 20250926, '/es/condiciones/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(274, '2025-09-26 06:53:38', 20250926, '/es/privacidad/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(275, '2025-09-26 06:54:44', 20250926, '/es/catalogo/?tipo=mochila', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(276, '2025-09-26 06:55:21', 20250926, '/detalles-led/?id=46&amp;warm=1', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/detalles-led/?id=46', NULL, NULL, NULL, NULL, NULL),
(277, '2025-09-26 06:59:59', 20250926, '/detalles-vallas/?id=143', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(278, '2025-09-26 07:01:20', 20250926, '/es/mapas/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(279, '2025-09-26 07:05:21', 20250926, '/?tipo=mochila', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(280, '2025-09-26 07:06:21', 20250926, '/?warm=1', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(281, '2025-09-26 07:07:22', 20250926, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/es/privacidad/', NULL, NULL, NULL, NULL, NULL),
(282, '2025-09-26 07:07:34', 20250926, '/detalles-vallas/?id=142', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(283, '2025-09-26 07:15:41', 20250926, '/detalles-vallas/?id=18', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(284, '2025-09-26 07:22:08', 20250926, '/detalles-vallas/?id=18', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(285, '2025-09-26 07:22:11', 20250926, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/detalles-vallas/?id=18', NULL, NULL, NULL, NULL, NULL),
(286, '2025-09-26 07:28:19', 20250926, '/es/condiciones/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(287, '2025-09-26 07:28:23', 20250926, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/es/condiciones/', NULL, NULL, NULL, NULL, NULL),
(288, '2025-09-26 07:28:36', 20250926, '/detalles-led/?id=21', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(289, '2025-09-26 07:28:42', 20250926, '/?tipo=mochila', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(290, '2025-09-26 07:37:09', 20250926, '/es/marketing/', 0xacee6599, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(291, '2025-09-26 07:37:12', 20250926, '/es/mapas/', 0xacee6599, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(292, '2025-09-26 07:37:24', 20250926, '/?canary=hmgcvwjzic', 0xacee6599, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(293, '2025-09-26 07:37:25', 20250926, '/', 0xacee6599, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(294, '2025-09-26 13:23:00', 20250926, '/detalles-led/?id=22&amp;warm=1', 0xbe50f671, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/detalles-led/?id=22', NULL, NULL, NULL, NULL, NULL),
(295, '2025-09-26 13:26:16', 20250926, '/calendario/?id=7', 0xbe50f671, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(296, '2025-09-26 13:27:58', 20250926, '/carritos/', 0xbe50f671, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(297, '2025-09-26 14:54:40', 20250926, '/detalles-led/?id=18&amp;warm=1', 0x6c534559, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/detalles-led/?id=18', NULL, NULL, NULL, NULL, NULL),
(298, '2025-09-26 14:59:06', 20250926, '/calendario/?id=24', 0x6c534559, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(299, '2025-09-26 15:00:47', 20250926, '/es/catalogo/?tipo=mochila', 0x6c534559, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(300, '2025-09-27 09:00:03', 20250927, '/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(301, '2025-09-27 19:21:34', 20250927, '/detalles-led/?id=29', 0xb334b9b8, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(302, '2025-09-27 19:22:01', 20250927, '/calendario/?id=29', 0xb334b9b8, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(303, '2025-09-28 20:19:09', 20250928, '/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(304, '2025-10-02 05:01:56', 20251002, '/', 0x36d7e1ba, 'Mozilla/5.0 (Windows; U; MSIE 9.0; Windows NT 6.3; .NET CLR 2.1.28607; Win64; x64)', 'https://www.google.com/', NULL, NULL, NULL, NULL, NULL),
(305, '2025-10-02 09:41:02', 20251002, '/?warm=1', 0x6704fa29, 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(306, '2025-10-03 06:49:04', 20251003, '/', 0x2294445e, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4240.193 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(307, '2025-10-04 15:10:57', 20251004, '/?warm=1', 0x6704fbb2, 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(308, '2025-10-04 16:10:49', 20251004, '/', 0xc9e5a2fb, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(309, '2025-10-04 16:16:06', 20251004, '/es/catalogo/?tipo=movilled', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(310, '2025-10-04 16:16:13', 20251004, '/es/marketing/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(311, '2025-10-04 16:32:14', 20251004, '/detalles-led/?id=91', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(312, '2025-10-04 16:32:18', 20251004, '/calendario/?id=91', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(313, '2025-10-04 16:32:25', 20251004, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(314, '2025-10-05 16:36:59', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(315, '2025-10-05 16:59:35', 20251005, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(316, '2025-10-05 17:31:14', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(317, '2025-10-05 17:41:50', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(318, '2025-10-05 18:03:52', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(319, '2025-10-05 18:05:28', 20251005, '/es/mapas/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(320, '2025-10-05 18:06:39', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(321, '2025-10-05 18:08:11', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(322, '2025-10-05 18:09:37', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(323, '2025-10-05 18:23:56', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(324, '2025-10-05 19:27:10', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(325, '2025-10-05 19:28:26', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(326, '2025-10-05 19:33:22', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(327, '2025-10-05 19:37:53', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(328, '2025-10-05 19:48:31', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(329, '2025-10-05 19:51:38', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(330, '2025-10-05 19:52:14', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(331, '2025-10-05 19:54:49', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(332, '2025-10-05 20:05:28', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(333, '2025-10-05 20:08:28', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(334, '2025-10-05 20:27:55', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(335, '2025-10-05 20:35:36', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(336, '2025-10-05 20:36:14', 20251005, '/calendario/?id=19', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(337, '2025-10-05 20:54:14', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(338, '2025-10-05 20:56:09', 20251005, '/calendario/?id=19', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(339, '2025-10-05 20:56:49', 20251005, '/calendario/?id=90', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(340, '2025-10-05 21:01:17', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(341, '2025-10-05 21:07:22', 20251005, '/destacadas/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(342, '2025-10-05 21:07:43', 20251005, '/detalles-vallas/?id=140', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(343, '2025-10-05 21:07:53', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/detalles-vallas/?id=140', NULL, NULL, NULL, NULL, NULL),
(344, '2025-10-05 21:09:09', 20251005, '/detalles-vallas/?id=90', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(345, '2025-10-05 21:17:49', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(346, '2025-10-05 21:18:19', 20251005, '/destacadas/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(347, '2025-10-05 21:22:20', 20251005, '/detalles-vallas/?id=141', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(348, '2025-10-05 21:24:10', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(349, '2025-10-05 21:29:00', 20251005, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(350, '2025-10-05 21:35:54', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(351, '2025-10-05 21:36:26', 20251005, '/detalles-vallas/?id=140', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(352, '2025-10-05 21:38:49', 20251005, '/calendario/?id=141&amp;warm=1', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/calendario/?id=141', NULL, NULL, NULL, NULL, NULL),
(353, '2025-10-05 21:38:53', 20251005, '/calendario/?id=141', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(354, '2025-10-05 21:50:02', 20251005, '/destacadas/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(355, '2025-10-05 21:50:25', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(356, '2025-10-05 22:01:24', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(357, '2025-10-05 22:02:13', 20251005, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(358, '2025-10-05 22:03:58', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(359, '2025-10-05 22:04:27', 20251005, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(360, '2025-10-05 22:04:38', 20251005, '/detalles-led/?id=46', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(361, '2025-10-05 22:08:49', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/detalles-led/?id=46', NULL, NULL, NULL, NULL, NULL),
(362, '2025-10-05 22:23:30', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/detalles-led/?id=46', NULL, NULL, NULL, NULL, NULL),
(363, '2025-10-05 22:27:48', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/detalles-led/?id=46', NULL, NULL, NULL, NULL, NULL),
(364, '2025-10-05 22:28:02', 20251005, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(365, '2025-10-05 22:35:38', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(366, '2025-10-05 22:36:09', 20251005, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(367, '2025-10-05 22:40:22', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(368, '2025-10-05 22:40:50', 20251005, '/calendario/?id=91', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(369, '2025-10-05 22:43:39', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(370, '2025-10-05 22:43:55', 20251005, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(371, '2025-10-05 22:56:45', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(372, '2025-10-05 22:57:03', 20251005, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(373, '2025-10-05 22:57:59', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(374, '2025-10-05 22:59:22', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(375, '2025-10-05 23:00:16', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(376, '2025-10-05 23:03:18', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(377, '2025-10-05 23:04:06', 20251005, '/es/analisis/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(378, '2025-10-05 23:04:17', 20251005, '/detalles-led/?id=46', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(379, '2025-10-05 23:04:43', 20251005, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(380, '2025-10-05 23:08:57', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(381, '2025-10-05 23:10:06', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(382, '2025-10-05 23:10:22', 20251005, '/detalles-vallas/?id=140', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(383, '2025-10-05 23:10:38', 20251005, '/es/alquiler-de-vallas-led/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/detalles-vallas/?id=140', NULL, NULL, NULL, NULL, NULL),
(384, '2025-10-05 23:10:46', 20251005, '/es/alquiler-de-vallas-led/buscar.php', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/es/alquiler-de-vallas-led/', NULL, NULL, NULL, NULL, NULL),
(385, '2025-10-05 23:19:45', 20251005, '/es/alquiler-de-vallas-led/buscar.php', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/es/alquiler-de-vallas-led/', NULL, NULL, NULL, NULL, NULL),
(386, '2025-10-05 23:20:13', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(387, '2025-10-05 23:24:59', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/es/alquiler-de-vallas-led/buscar.php', NULL, NULL, NULL, NULL, NULL),
(388, '2025-10-05 23:27:17', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/es/alquiler-de-vallas-led/buscar.php', NULL, NULL, NULL, NULL, NULL),
(389, '2025-10-05 23:35:48', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(390, '2025-10-05 23:36:46', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(391, '2025-10-05 23:37:28', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(392, '2025-10-05 23:38:26', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(393, '2025-10-05 23:40:08', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(394, '2025-10-05 23:45:16', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(395, '2025-10-05 23:55:34', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(396, '2025-10-05 23:55:59', 20251005, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(397, '2025-10-06 00:59:51', 20251005, '/es/alquiler-de-vallas-led/', 0xbea7e3ee, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(398, '2025-10-06 00:59:58', 20251005, '/es/blog/', 0xbea7e3ee, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(399, '2025-10-06 01:24:31', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(400, '2025-10-06 01:24:50', 20251005, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(401, '2025-10-06 01:33:53', 20251005, '/es/mapas/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(402, '2025-10-06 01:36:30', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(403, '2025-10-06 01:36:46', 20251005, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(404, '2025-10-06 01:48:47', 20251005, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(405, '2025-10-06 01:48:52', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(406, '2025-10-06 01:56:07', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(407, '2025-10-06 01:56:21', 20251005, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(408, '2025-10-06 01:57:15', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(409, '2025-10-06 01:57:23', 20251005, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(410, '2025-10-06 02:03:52', 20251005, '/detalles-led/?id=46', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(411, '2025-10-06 02:05:49', 20251005, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(412, '2025-10-06 02:06:02', 20251005, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(413, '2025-10-06 03:44:05', 20251005, '/detalles-led/?id=91', 0xbea7e3a6, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(414, '2025-10-06 03:49:46', 20251005, '/detalles-led/?id=17&amp;warm=1', 0x98a68613, 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0.1 Mobile/15E148 Safari/604.1', 'https://demo.vallasled.com/detalles-led/?id=17', NULL, NULL, NULL, NULL, NULL),
(415, '2025-10-06 16:07:43', 20251006, '/detalles-vallas/?id=142', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/?q=Puente+Peatonal+2', NULL, NULL, NULL, NULL, NULL),
(416, '2025-10-06 16:08:01', 20251006, '/?q=Puente+Peatonal+2', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(417, '2025-10-06 16:08:20', 20251006, '/calendario/?id=142', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/?q=Puente+Peatonal+2', NULL, NULL, NULL, NULL, NULL),
(418, '2025-10-06 16:47:10', 20251006, '/calendario/?id=46', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(419, '2025-10-06 16:47:45', 20251006, '/es/analisis/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(420, '2025-10-06 16:47:55', 20251006, '/es/marketing/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/es/analisis/', NULL, NULL, NULL, NULL, NULL),
(421, '2025-10-06 16:48:04', 20251006, '/es/alquiler-de-vallas-led/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/es/marketing/', NULL, NULL, NULL, NULL, NULL),
(422, '2025-10-06 16:55:41', 20251006, '/es/mapas/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(423, '2025-10-06 19:54:46', 20251006, '/detalles-led/?id=18&amp;warm=1', 0xb3356096, 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/141.0.7390.41 Mobile/15E148 Safari/604.1', 'https://demo.vallasled.com/detalles-led/?id=18', NULL, NULL, NULL, NULL, NULL),
(424, '2025-10-06 22:09:00', 20251006, '/?provincia=13', 0x98a6a312, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `web_analytics` (`id`, `ts`, `ymd`, `path`, `ip`, `ua`, `ref`, `u_campaign`, `u_source`, `u_medium`, `u_term`, `u_content`) VALUES
(425, '2025-10-06 22:09:07', 20251006, '/detalles-vallas/?id=117', 0x98a6a312, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/?provincia=13', NULL, NULL, NULL, NULL, NULL),
(426, '2025-10-06 22:11:13', 20251006, '/?tipo=led&provincia=13', 0x98a6a312, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(427, '2025-10-06 22:11:29', 20251006, '/?tipo=led', 0x98a6a312, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(428, '2025-10-07 04:53:46', 20251007, '/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(429, '2025-10-07 05:01:06', 20251007, '/detalles-led/?id=17', 0xb525d4d1, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(430, '2025-10-07 05:12:34', 20251007, '/detalles-led/?id=91', 0xb525d4d1, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/?q=Caleta', NULL, NULL, NULL, NULL, NULL),
(431, '2025-10-07 05:12:58', 20251007, '/?q=Caleta&amp;warm=1', 0x4a7dd242, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36 (compatible; Google-Read-Aloud; +https://support.google.com/webmasters/answer/1061943)', 'https://demo.vallasled.com/?q=Caleta', NULL, NULL, NULL, NULL, NULL),
(432, '2025-10-07 05:20:27', 20251007, '/?q=Gurabo&tipo=impresa', 0xb525d4d1, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(433, '2025-10-07 05:31:44', 20251007, '/?q=Gurabo&tipo=led', 0xb525d4d1, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(434, '2025-10-07 15:59:05', 20251007, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(435, '2025-10-07 15:59:27', 20251007, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(436, '2025-10-07 16:07:09', 20251007, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(437, '2025-10-07 16:07:37', 20251007, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(438, '2025-10-07 16:07:37', 20251007, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(439, '2025-10-07 16:07:39', 20251007, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(440, '2025-10-07 16:07:39', 20251007, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(441, '2025-10-07 16:07:42', 20251007, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(442, '2025-10-07 16:13:18', 20251007, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(443, '2025-10-07 16:13:21', 20251007, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(444, '2025-10-07 16:13:22', 20251007, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(445, '2025-10-07 16:13:31', 20251007, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(446, '2025-10-07 16:16:17', 20251007, '/?provincia=13', 0x9467c744, 'Mozilla/5.0 (Linux; Android 13; POCO X5 5G Build/TKQ1.221114.001) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.6312.118 Mobile Safari/537.36 XiaoMi/MiuiBrowser/14.43.0-gn', '', NULL, NULL, NULL, NULL, NULL),
(447, '2025-10-07 16:27:22', 20251007, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(448, '2025-10-07 16:27:26', 20251007, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(449, '2025-10-07 16:27:51', 20251007, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(450, '2025-10-07 16:27:54', 20251007, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(451, '2025-10-07 16:27:54', 20251007, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(452, '2025-10-07 16:27:56', 20251007, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(453, '2025-10-07 16:29:06', 20251007, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(454, '2025-10-07 16:29:16', 20251007, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(455, '2025-10-07 16:29:19', 20251007, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(456, '2025-10-07 16:29:19', 20251007, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(457, '2025-10-07 16:29:21', 20251007, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(458, '2025-10-07 16:29:55', 20251007, '/calendario/?id=143', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(459, '2025-10-07 16:29:59', 20251007, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(460, '2025-10-07 16:38:02', 20251007, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(461, '2025-10-07 16:51:33', 20251007, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(462, '2025-10-07 16:51:36', 20251007, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(463, '2025-10-07 16:51:59', 20251007, '/calendario/?id=17', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(464, '2025-10-07 16:52:03', 20251007, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/calendario/?id=17', NULL, NULL, NULL, NULL, NULL),
(465, '2025-10-07 16:54:24', 20251007, '/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(466, '2025-10-07 16:54:56', 20251007, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', 'https://demo.vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(467, '2025-10-07 16:55:10', 20251007, '/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(468, '2025-10-07 17:23:17', 20251007, '/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(469, '2025-10-07 17:38:54', 20251007, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/calendario/?id=17', NULL, NULL, NULL, NULL, NULL),
(470, '2025-10-07 17:54:57', 20251007, '/', 0x42f95382, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36 (compatible; Google-Read-Aloud; +https://support.google.com/webmasters/answer/1061943)', '', NULL, NULL, NULL, NULL, NULL),
(471, '2025-10-07 18:48:10', 20251007, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/calendario/?id=17', NULL, NULL, NULL, NULL, NULL),
(472, '2025-10-07 19:53:19', 20251007, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(473, '2025-10-07 20:21:27', 20251007, '/', 0xaccb1507, 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.129 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(474, '2025-10-07 20:21:27', 20251007, '/', 0xaccb1507, 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.129 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(475, '2025-10-07 20:58:29', 20251007, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(476, '2025-10-07 21:05:12', 20251007, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/calendario/?id=17', NULL, NULL, NULL, NULL, NULL),
(477, '2025-10-07 21:33:16', 20251007, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(478, '2025-10-07 21:56:07', 20251007, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://demo.vallasled.com/calendario/?id=17', NULL, NULL, NULL, NULL, NULL),
(479, '2025-10-07 22:43:08', 20251007, '/', 0xc3d34d8e, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(480, '2025-10-07 22:43:18', 20251007, '/', 0xc3d34d8e, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(481, '2025-10-07 23:01:12', 20251007, '/', 0x93b98416, 'Hello from Palo Alto Networks, find out more about our scans in https://docs-cortex.paloaltonetworks.com/r/1/Cortex-Xpanse/Scanning-activity', '', NULL, NULL, NULL, NULL, NULL),
(482, '2025-10-08 00:51:02', 20251007, '/', 0xa76356f5, 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(483, '2025-10-08 00:51:02', 20251007, '/', 0xa76356f5, 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(484, '2025-10-08 03:27:21', 20251007, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(485, '2025-10-08 03:46:01', 20251007, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(486, '2025-10-08 04:08:11', 20251008, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(487, '2025-10-08 04:28:40', 20251008, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(488, '2025-10-08 16:44:19', 20251008, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(489, '2025-10-08 16:44:23', 20251008, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(490, '2025-10-08 16:45:01', 20251008, '/', 0x9467c744, 'WhatsApp/2.23.20.0', '', NULL, NULL, NULL, NULL, NULL),
(491, '2025-10-08 16:45:32', 20251008, '/carritos/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://vallasled.com/', NULL, NULL, NULL, NULL, NULL),
(492, '2025-10-08 16:45:36', 20251008, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(493, '2025-10-08 16:46:48', 20251008, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(494, '2025-10-08 16:47:04', 20251008, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://vallasled.com/carritos/', NULL, NULL, NULL, NULL, NULL),
(495, '2025-10-08 16:47:12', 20251008, '/', 0x98a69cc7, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(496, '2025-10-08 17:31:14', 20251008, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(497, '2025-10-08 17:51:05', 20251008, '/', 0x74cbc540, 'SeobilityBot (SEO Tool; https://www.seobility.net/sites/bot.html)', '', NULL, NULL, NULL, NULL, NULL),
(498, '2025-10-08 17:51:25', 20251008, '/', 0xa237b6fe, 'SeobilityBot (SEO Tool; https://www.seobility.net/sites/bot.html)', '', NULL, NULL, NULL, NULL, NULL),
(499, '2025-10-08 17:52:12', 20251008, '/', 0x9467c744, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(500, '2025-10-08 18:04:43', 20251008, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(501, '2025-10-08 18:18:06', 20251008, '/', 0x42f94fc9, 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.7339.207 Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', '', NULL, NULL, NULL, NULL, NULL),
(502, '2025-10-08 18:39:56', 20251008, '/', 0x42f94fc9, 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/99.0.4844.84 Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', '', NULL, NULL, NULL, NULL, NULL),
(503, '2025-10-08 18:39:57', 20251008, '/', 0x42f94fca, 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', '', NULL, NULL, NULL, NULL, NULL),
(504, '2025-10-08 18:57:19', 20251008, '/', 0x9467c744, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(505, '2025-10-08 19:01:52', 20251008, '/', 0x4ad0a881, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:93.0) Gecko/20100101 Firefox/93.0', '', NULL, NULL, NULL, NULL, NULL),
(506, '2025-10-08 19:04:18', 20251008, '/', 0xba06373b, 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/141.0.7390.41 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL),
(507, '2025-10-08 19:11:04', 20251008, '/', 0xce5118e3, '', '', NULL, NULL, NULL, NULL, NULL),
(508, '2025-10-08 19:11:06', 20251008, '/', 0xce5118e3, 'Mozilla/5.0 (Linux; Android 6.0; HTC One M9 Build/MRA413617) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0.2645.98 Mobile Safari/537.3', '', NULL, NULL, NULL, NULL, NULL),
(509, '2025-10-08 19:11:14', 20251008, '/?rest_route=/wp/v2/users/', 0xce5118e3, 'Go-http-client/1.1', '', NULL, NULL, NULL, NULL, NULL),
(510, '2025-10-08 19:11:38', 20251008, '/', 0xc3d34d8c, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(511, '2025-10-08 19:12:07', 20251008, '/', 0xc3d34d8e, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(512, '2025-10-08 19:12:34', 20251008, '/', 0x6bacc34a, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(513, '2025-10-08 19:12:38', 20251008, '/', 0x68a4ade8, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(514, '2025-10-08 19:12:41', 20251008, '/', 0x6bacc34a, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(515, '2025-10-08 19:12:47', 20251008, '/', 0x80c00c6b, 'Mozilla/5.0 (compatible; UGAResearchAgent/1.0; Please visit: NISLabUGA.github.io)', '', NULL, NULL, NULL, NULL, NULL),
(516, '2025-10-08 19:17:25', 20251008, '/', 0x9246b920, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3', '', NULL, NULL, NULL, NULL, NULL),
(517, '2025-10-08 19:30:32', 20251008, '/', 0xcda92717, 'Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/106.0.0.0 Safari/537.36', 'http://vallasled.com', NULL, NULL, NULL, NULL, NULL),
(518, '2025-10-08 19:33:27', 20251008, '/', 0x602926ca, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:139.0) Gecko/20100101 Firefox/139.0', '', NULL, NULL, NULL, NULL, NULL),
(519, '2025-10-08 19:35:57', 20251008, '/', 0x6704fab5, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(520, '2025-10-08 19:35:58', 20251008, '/', 0x68a4ad6a, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(521, '2025-10-08 19:36:03', 20251008, '/', 0x6704fab5, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(522, '2025-10-08 19:46:42', 20251008, '/', 0x98a69cc7, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(523, '2025-10-08 20:42:46', 20251008, '/', 0x98a69cc7, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '', NULL, NULL, NULL, NULL, NULL),
(524, '2025-10-08 20:44:38', 20251008, '/?fbclid=PAZXh0bgNhZW0CMTEAAadyVg-tTTnDuVQMdsgghilrQ-OU_5wv20k1w4iAtAQqj9x_bExLvfEmfji9YQ_aem_8uQH3FOIh_gmz6M30GNjHQ', 0x94ff2cb5, 'Mozilla/5.0 (Linux; Android 15; 23129RA5FL Build/AQ3A.240829.003; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/141.0.7390.59 Mobile Safari/537.36 Instagram 401.0.0.48.79 Android (35/15; 440dpi; 1080x2400; Xiaomi/Redmi; 23129RA5FL; sapphire; qcom; es_US; 802602546; IABMV/1)', 'https://l.instagram.com/', NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `web_setting`
--

CREATE TABLE `web_setting` (
  `id` int(10) UNSIGNED NOT NULL,
  `clave` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor` varchar(1024) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `web_setting`
--

INSERT INTO `web_setting` (`id`, `clave`, `valor`, `created_at`, `updated_at`) VALUES
(1, 'theme_primary_color', '#007bff', '2025-09-20 18:57:24', '2025-09-20 23:58:49'),
(2, 'theme_secondary_color', '#131925', '2025-09-20 18:57:24', '2025-09-20 23:54:40'),
(3, 'theme_hero_bg', '', '2025-09-20 18:57:24', '2025-09-20 18:57:24'),
(5, 'company_name', 'Vallasled.com', '2025-09-20 19:08:57', '2025-09-20 19:08:57'),
(6, 'company_address', 'Santo Domingo, RD', '2025-09-20 19:08:57', '2025-09-20 19:08:57'),
(7, 'company_phone', '18493565448', '2025-09-20 19:08:57', '2025-10-05 23:52:47'),
(8, 'company_rnc', '', '2025-09-20 19:08:57', '2025-09-20 19:08:57'),
(9, 'support_whatsapp', '18493565448', '2025-09-20 19:08:57', '2025-10-05 23:52:19'),
(10, 'support_email', 'soporte@vallasled.com', '2025-09-20 19:08:57', '2025-09-20 19:08:57'),
(11, 'footer_bg_color', '#0b1220', '2025-09-20 19:08:57', '2025-09-20 19:08:57'),
(12, 'footer_text_color', '#edf8f1', '2025-09-20 19:08:57', '2025-09-24 19:47:56'),
(13, 'footer_link_color', '#f6fff5', '2025-09-20 19:08:57', '2025-09-24 19:28:40'),
(14, 'footer_brand_by', 'by Vallasled', '2025-09-20 19:08:57', '2025-09-20 19:08:57'),
(15, 'legal_terms_url', '/terminos', '2025-09-20 19:08:57', '2025-09-20 19:08:57'),
(16, 'legal_privacy_url', '/privacidad', '2025-09-20 19:08:57', '2025-09-20 19:08:57'),
(17, 'logo_url', 'https://auth.vallasled.com/admin/assets/logo.png', '2025-09-20 19:18:03', '2025-09-24 18:33:06'),
(18, 'favicon_url', 'https://auth.vallasled.com/admin/assets/logo.png', '2025-09-20 19:18:03', '2025-09-24 18:32:45'),
(19, 'vendor_register_url', 'https://auth.vallasled.com/vendor/auth/register.php', '2025-09-20 19:18:03', '2025-09-20 19:18:03'),
(20, 'vendor_login_url', 'https://auth.vallasled.com/vendor/auth/login.php', '2025-09-20 19:18:03', '2025-09-20 19:18:03'),
(55, 'log_enabled', '1', '2025-09-20 20:38:36', '2025-09-20 20:38:36'),
(56, 'log_level', 'WARNING', '2025-09-20 20:38:36', '2025-09-20 20:38:36'),
(57, 'log_retention_days', '30', '2025-09-20 20:38:36', '2025-09-20 20:38:36'),
(63, 'wa_tpl_valla', 'Interesado en esta valla:\r\nID {{id}} x{{qty}}\r\n{{nombre}}\r\n{{ubicacion}}\r\n{{medida}}\r\nPrecio: RD$ {{precio}}', '2025-09-20 23:17:06', '2025-09-20 23:17:06'),
(64, 'wa_tpl_item', '- ID {{id}} x{{qty}} · {{nombre}}', '2025-09-20 23:17:06', '2025-09-20 23:17:06'),
(65, 'wa_tpl_cart', 'Interesado en vallas:\r\n{{items}}\r\nTotal: RD$ {{total}}', '2025-09-20 23:17:06', '2025-09-20 23:17:06'),
(66, 'wa_tpl_sep', '\n', '2025-09-20 23:17:06', '2025-09-20 23:17:06'),
(67, 'hero_title', 'Título de ejemplo', '2025-09-20 23:40:29', '2025-09-20 23:40:29'),
(68, 'hero_subtitle', 'Conectamos tu marca con audiencias masivas en República Dominicana usando vallas digitales y datos.', '2025-09-20 23:40:29', '2025-10-05 17:56:20'),
(69, 'hero_cta_text', 'Explorar Mapa de Valla', '2025-09-20 23:40:29', '2025-10-05 18:09:18'),
(70, 'hero_cta_url', '/es/mapas/', '2025-09-20 23:40:29', '2025-10-05 18:05:53'),
(71, 'hero_text_color', '#374151', '2025-09-21 01:23:00', '2025-09-21 01:23:00'),
(72, 'logo_width_px', '120', '2025-09-21 01:23:00', '2025-09-21 01:23:00'),
(73, 'logo_height_px', '40', '2025-09-21 01:23:00', '2025-09-21 01:23:00'),
(74, 'border_radius_px', '2', '2025-09-21 01:23:00', '2025-09-21 18:16:05'),
(75, 'auth_base_url', 'https://auth.vallasled.com', '2025-09-21 02:36:23', '2025-09-21 02:36:23'),
(76, 'asset_version', '1758424400', '2025-09-21 02:50:31', '2025-09-21 03:13:20'),
(83, 'home_banner_enabled', '1', '2025-09-25 03:40:06', '2025-09-25 03:40:06'),
(84, 'home_banner_mode', 'video', '2025-09-25 03:40:06', '2025-09-25 03:40:06'),
(85, 'home_banner_image_url', 'https://auth.vallasled.com/uploads/hero.jpg', '2025-09-25 03:40:06', '2025-09-25 03:40:06'),
(86, 'home_banner_video_urls', '[\"https://demo.vallasled.com/video/step1.mp4\",\"https://demo.vallasled.com/video/step2.mp4\",\"https://demo.vallasled.com/video/step3.mp4\"]', '2025-09-25 03:40:06', '2025-09-25 03:40:06'),
(87, 'home_banner_video_poster', '', '2025-09-25 03:40:06', '2025-09-25 03:42:33'),
(88, 'home_banner_height', '360', '2025-09-25 03:40:06', '2025-09-25 03:40:06'),
(95, 'site_name', 'Vallasled.com', '2025-09-26 02:26:09', '2025-09-26 02:26:09'),
(96, 'site_description', 'Reserva vallas LED y estáticas en Santo Domingo, Punta Cana y todo RD. Mapas, precios y disponibilidad.', '2025-09-26 02:26:09', '2025-09-26 02:26:09'),
(97, 'site_url', 'https://www.vallasled.com', '2025-09-26 02:26:09', '2025-09-26 02:26:09'),
(98, 'site_logo_url', 'https://auth.vallasled.com/admin/assets/logo.png', '2025-09-26 02:26:09', '2025-09-26 02:26:09'),
(99, 'site_locale', 'es_DO', '2025-09-26 02:26:09', '2025-09-26 02:26:09'),
(100, 'site_twitter', '@vallasled', '2025-09-26 02:26:09', '2025-09-26 02:26:09'),
(101, 'hero_title_top', 'Plataforma Inteligente de', '2025-10-05 17:56:20', '2025-10-05 17:56:20'),
(102, 'hero_title_bottom', 'Publicidad Exterior', '2025-10-05 17:56:20', '2025-10-05 17:56:20'),
(111, 'wa_tpl_personal', 'Hola {{nombre}}, soy de {{empresa}}.\nVi su interés en publicidad exterior en {{ciudad}}.\n¿Le comparto opciones y precios? {{asunto}}', '2025-10-07 16:27:00', '2025-10-07 16:27:00');

-- --------------------------------------------------------

--
-- Table structure for table `zonas_canon`
--

CREATE TABLE `zonas_canon` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `normalized` varchar(120) NOT NULL,
  `synonyms` json DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `zonas_canon`
--

INSERT INTO `zonas_canon` (`id`, `nombre`, `normalized`, `synonyms`, `activo`) VALUES
(1, 'Zona Este', 'zona este', '[\"🟡 zona este\", \"zona oriental\", \"este\"]', 1),
(2, 'Zona Norte o Cibao', 'zona norte o cibao', '[\"zona norte cibao\", \"norte\", \"cibao\", \"zona norte\"]', 1),
(3, 'Zona Sur', 'zona sur', '[\"sur\", \"zona sur profundo\"]', 1),
(4, 'Distrito Nacional', 'distrito nacional', '[\"dn\"]', 1),
(5, 'Santo Domingo', 'santo domingo', '[\"santo domingo o\", \"sdo\"]', 1),
(6, 'Santo Domingo Este', 'santo domingo este', '[\"sde\", \"santo domingo oriental\"]', 1),
(7, 'Santo Domingo Norte', 'santo domingo norte', '[\"sdn\"]', 1),
(8, 'Santo Domingo Oeste', 'santo domingo oeste', '[\"sdoe\"]', 1),
(9, 'Santiago', 'santiago', '[\"zona santiago\"]', 1),
(10, 'La Romana', 'la romana', '[\"romana\"]', 1),
(11, 'La Altagracia', 'la altagracia', '[\"higüey\", \"higuey\", \"higuey centro\"]', 1),
(12, 'Puerto Plata', 'puerto plata', '[\"pp\"]', 1),
(13, 'La Vega', 'la vega', '[\"vega\"]', 1),
(14, 'San Pedro de Macorís', 'san pedro de macoris', '[\"san pedro\", \"spm\"]', 1);

-- --------------------------------------------------------

--
-- Structure for view `vw_facturas_vendor`
--
DROP TABLE IF EXISTS `vw_facturas_vendor`;

CREATE ALGORITHM=UNDEFINED DEFINER=`malodrql_prueba`@`localhost` SQL SECURITY DEFINER VIEW `vw_facturas_vendor`  AS SELECT `f`.`id` AS `id`, `f`.`usuario_id` AS `usuario_id`, `f`.`valla_id` AS `valla_id`, `f`.`monto` AS `monto_total`, `f`.`precio_personalizado` AS `precio_personalizado`, `f`.`descuento` AS `descuento`, `f`.`estado` AS `estado`, `f`.`metodo_pago` AS `metodo_pago`, `f`.`fecha_generada` AS `fecha_generada`, `f`.`fecha_generacion` AS `fecha_generacion`, `f`.`fecha_pago` AS `fecha_pago`, `f`.`stripe_link` AS `stripe_link`, `f`.`comision_pct` AS `comision_pct`, `f`.`comision_monto` AS `comision_monto` FROM `facturas` AS `f` ;

-- --------------------------------------------------------

--
-- Structure for view `vw_vallas_geo`
--
DROP TABLE IF EXISTS `vw_vallas_geo`;

CREATE ALGORITHM=UNDEFINED DEFINER=`malodrql_prueba`@`localhost` SQL SECURITY DEFINER VIEW `vw_vallas_geo`  AS SELECT `vallas`.`id` AS `id`, `vallas`.`proveedor_id` AS `proveedor_id`, `vallas`.`nombre` AS `nombre`, `vallas`.`lat` AS `lat`, `vallas`.`lng` AS `lng`, `vallas`.`estado_valla` AS `estado_valla`, `vallas`.`disponible` AS `disponible`, `vallas`.`precio` AS `precio`, `vallas`.`medida` AS `medida` FROM `vallas` WHERE ((`vallas`.`lat` is not null) AND (`vallas`.`lng` is not null)) ;

-- --------------------------------------------------------

--
-- Structure for view `vw_vendor_features`
--
DROP TABLE IF EXISTS `vw_vendor_features`;

CREATE ALGORITHM=UNDEFINED DEFINER=`malodrql_prueba`@`localhost` SQL SECURITY DEFINER VIEW `vw_vendor_features`  AS SELECT `vm`.`proveedor_id` AS `proveedor_id`, `f`.`access_crm` AS `access_crm`, `f`.`access_facturacion` AS `access_facturacion`, `f`.`access_mapa` AS `access_mapa`, `f`.`access_export` AS `access_export`, `f`.`soporte_ncf` AS `soporte_ncf`, `f`.`comision_model` AS `comision_model`, `f`.`comision_pct` AS `comision_pct`, `f`.`comision_flat` AS `comision_flat`, `f`.`factura_auto` AS `factura_auto` FROM (((`vendor_membresias` `vm` join `vendor_planes` `p` on((`p`.`id` = `vm`.`plan_id`))) left join `vendor_plan_features` `f` on((`f`.`plan_id` = `p`.`id`))) join (select `vendor_membresias`.`proveedor_id` AS `proveedor_id`,max(`vendor_membresias`.`id`) AS `max_id` from `vendor_membresias` where (`vendor_membresias`.`estado` = 'activa') group by `vendor_membresias`.`proveedor_id`) `t` on(((`t`.`proveedor_id` = `vm`.`proveedor_id`) and (`t`.`max_id` = `vm`.`id`)))) ;

-- --------------------------------------------------------

--
-- Structure for view `vw_vendor_licencias_30d`
--
DROP TABLE IF EXISTS `vw_vendor_licencias_30d`;

CREATE ALGORITHM=UNDEFINED DEFINER=`malodrql_prueba`@`localhost` SQL SECURITY DEFINER VIEW `vw_vendor_licencias_30d`  AS SELECT `vallas`.`id` AS `id`, `vallas`.`proveedor_id` AS `proveedor_id`, `vallas`.`nombre` AS `nombre`, `vallas`.`numero_licencia` AS `numero_licencia`, `vallas`.`fecha_vencimiento` AS `fecha_vencimiento`, (to_days(`vallas`.`fecha_vencimiento`) - to_days(curdate())) AS `dias_restantes` FROM `vallas` WHERE ((`vallas`.`fecha_vencimiento` is not null) AND (`vallas`.`fecha_vencimiento` between curdate() and (curdate() + interval 30 day))) ;

-- --------------------------------------------------------

--
-- Structure for view `vw_vendor_mantenimientos_proximos`
--
DROP TABLE IF EXISTS `vw_vendor_mantenimientos_proximos`;

CREATE ALGORITHM=UNDEFINED DEFINER=`malodrql_prueba`@`localhost` SQL SECURITY DEFINER VIEW `vw_vendor_mantenimientos_proximos`  AS SELECT `v`.`id` AS `valla_id`, `v`.`proveedor_id` AS `proveedor_id`, `v`.`nombre` AS `nombre`, `p`.`fecha_inicio` AS `fecha_inicio`, `p`.`fecha_fin` AS `fecha_fin`, `p`.`motivo` AS `motivo` FROM (`periodos_no_disponibles` `p` join `vallas` `v` on((`v`.`id` = `p`.`valla_id`))) WHERE (`p`.`fecha_inicio` >= curdate()) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `api_consumo`
--
ALTER TABLE `api_consumo`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `api_tokens`
--
ALTER TABLE `api_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `correo` (`correo`);

--
-- Indexes for table `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `comprobantes`
--
ALTER TABLE `comprobantes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ncf` (`ncf`),
  ADD KEY `factura_id` (`factura_id`);

--
-- Indexes for table `comprobantes_fiscales`
--
ALTER TABLE `comprobantes_fiscales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `secuencia_id` (`secuencia_id`),
  ADD KEY `factura_id` (`factura_id`);

--
-- Indexes for table `configuracion`
--
ALTER TABLE `configuracion`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `clave` (`clave`),
  ADD UNIQUE KEY `u_clave` (`clave`);

--
-- Indexes for table `config_global`
--
ALTER TABLE `config_global`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `clave` (`clave`),
  ADD UNIQUE KEY `clave_2` (`clave`),
  ADD UNIQUE KEY `u_clave_activo` (`clave`,`activo`);

--
-- Indexes for table `config_kv`
--
ALTER TABLE `config_kv`
  ADD PRIMARY KEY (`k`);

--
-- Indexes for table `crm_clientes`
--
ALTER TABLE `crm_clientes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_prov_email` (`proveedor_id`,`email`);

--
-- Indexes for table `crm_licencias`
--
ALTER TABLE `crm_licencias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `proveedor_id` (`proveedor_id`),
  ADD KEY `estado` (`estado`),
  ADD KEY `fecha_vencimiento` (`fecha_vencimiento`);

--
-- Indexes for table `crm_licencias_ips`
--
ALTER TABLE `crm_licencias_ips`
  ADD PRIMARY KEY (`id`),
  ADD KEY `licencia_id` (`licencia_id`),
  ADD KEY `proveedor_id` (`proveedor_id`);

--
-- Indexes for table `cron_status`
--
ALTER TABLE `cron_status`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_task_prov` (`task`,`proveedor_id`);

--
-- Indexes for table `datos_bancarios`
--
ALTER TABLE `datos_bancarios`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `dominios_remotos`
--
ALTER TABLE `dominios_remotos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_dominios_remotos_dominio` (`dominio`);

--
-- Indexes for table `empleados`
--
ALTER TABLE `empleados`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `facturas`
--
ALTER TABLE `facturas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_facturas_valla_estado` (`valla_id`,`estado`);

--
-- Indexes for table `historial_acceso`
--
ALTER TABLE `historial_acceso`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `keywords`
--
ALTER TABLE `keywords`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_norm` (`normalized`);
ALTER TABLE `keywords` ADD FULLTEXT KEY `ft_kw` (`keyword`,`normalized`);

--
-- Indexes for table `logos_hero`
--
ALTER TABLE `logos_hero`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `logs_app`
--
ALTER TABLE `logs_app`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_level` (`level`),
  ADD KEY `idx_tipo` (`tipo`),
  ADD KEY `idx_request` (`request_id`);
ALTER TABLE `logs_app` ADD FULLTEXT KEY `ft_mensaje` (`mensaje`);

--
-- Indexes for table `logs_app_blob`
--
ALTER TABLE `logs_app_blob`
  ADD PRIMARY KEY (`log_id`,`parte`);

--
-- Indexes for table `map_providers`
--
ALTER TABLE `map_providers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `map_settings`
--
ALTER TABLE `map_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `map_styles`
--
ALTER TABLE `map_styles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_style` (`provider_code`,`style_code`);

--
-- Indexes for table `notas_credito`
--
ALTER TABLE `notas_credito`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notif_log`
--
ALTER TABLE `notif_log`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq1` (`proveedor_id`,`tipo`,`ref_id`,`canal`,`destino`,`ymd`),
  ADD KEY `proveedor_id` (`proveedor_id`),
  ADD KEY `tipo` (`tipo`),
  ADD KEY `ref_id` (`ref_id`);

--
-- Indexes for table `pages`
--
ALTER TABLE `pages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `page_keywords`
--
ALTER TABLE `page_keywords`
  ADD PRIMARY KEY (`page_id`,`keyword_id`),
  ADD KEY `k_page` (`page_id`),
  ADD KEY `k_kw` (`keyword_id`);

--
-- Indexes for table `periodos_no_disponibles`
--
ALTER TABLE `periodos_no_disponibles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `valla_id` (`valla_id`),
  ADD KEY `idx_pnd_valla_fechas` (`valla_id`,`fecha_inicio`,`fecha_fin`);

--
-- Indexes for table `proveedores`
--
ALTER TABLE `proveedores`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `provincias`
--
ALTER TABLE `provincias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_provincias_nombre` (`nombre`);

--
-- Indexes for table `recibos_transferencia`
--
ALTER TABLE `recibos_transferencia`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `reservas`
--
ALTER TABLE `reservas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `valla_id` (`valla_id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `idx_reservas_valla_fechas` (`valla_id`,`fecha_inicio`,`fecha_fin`);

--
-- Indexes for table `roles_permisos`
--
ALTER TABLE `roles_permisos`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `secuencias_ncf`
--
ALTER TABLE `secuencias_ncf`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `solicitudes_cancelacion`
--
ALTER TABLE `solicitudes_cancelacion`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tipos_pago_licencia`
--
ALTER TABLE `tipos_pago_licencia`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_usuarios_rol_vendor` (`rol_vendor`);

--
-- Indexes for table `usuarios_permisos`
--
ALTER TABLE `usuarios_permisos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usuario_id` (`usuario_id`,`permiso`);

--
-- Indexes for table `vallas`
--
ALTER TABLE `vallas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_vallas_prov_estado` (`proveedor_id`,`estado`),
  ADD KEY `idx_vallas_prov` (`proveedor_id`),
  ADD KEY `idx_vallas_geo` (`lat`,`lng`),
  ADD KEY `idx_vallas_proveedor_estado` (`proveedor_id`,`estado_valla`,`disponible`),
  ADD KEY `idx_vallas_publico_estado` (`visible_publico`,`estado`),
  ADD KEY `idx_vallas_provincia` (`provincia_id`),
  ADD KEY `idx_vallas_zona` (`zona`(64));

--
-- Indexes for table `vallas_destacadas_pagos`
--
ALTER TABLE `vallas_destacadas_pagos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `valla_id` (`valla_id`),
  ADD KEY `cliente_id` (`cliente_id`),
  ADD KEY `idx_valla_id` (`valla_id`),
  ADD KEY `idx_cliente_id` (`cliente_id`),
  ADD KEY `idx_proveedor_id` (`proveedor_id`),
  ADD KEY `idx_destacadas_rango` (`fecha_inicio`,`fecha_fin`);

--
-- Indexes for table `vallas_licencias`
--
ALTER TABLE `vallas_licencias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_numero_licencia` (`numero_licencia`),
  ADD KEY `fk_valla_id` (`valla_id`),
  ADD KEY `fk_tipo_pago_licencia` (`tipo_pago_id`);

--
-- Indexes for table `valla_media`
--
ALTER TABLE `valla_media`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_valla` (`valla_id`),
  ADD KEY `idx_principal` (`valla_id`,`principal`),
  ADD KEY `idx_valla_media_valla` (`valla_id`),
  ADD KEY `idx_valla_media_principal` (`principal`);

--
-- Indexes for table `valla_precios`
--
ALTER TABLE `valla_precios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_plan` (`valla_id`,`plan`,`meses`),
  ADD KEY `valla_idx` (`valla_id`);

--
-- Indexes for table `vendor_commissions`
--
ALTER TABLE `vendor_commissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_prov` (`proveedor_id`),
  ADD KEY `idx_valla` (`valla_id`);

--
-- Indexes for table `vendor_config`
--
ALTER TABLE `vendor_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `proveedor_id` (`proveedor_id`);

--
-- Indexes for table `vendor_equipo`
--
ALTER TABLE `vendor_equipo`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_vendor_usuario` (`proveedor_id`,`usuario_id`),
  ADD KEY `idx_vendor` (`proveedor_id`),
  ADD KEY `idx_usuario` (`usuario_id`);

--
-- Indexes for table `vendor_members`
--
ALTER TABLE `vendor_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_vendor_user` (`proveedor_id`,`usuario_id`),
  ADD KEY `idx_vendor` (`proveedor_id`),
  ADD KEY `idx_user` (`usuario_id`);

--
-- Indexes for table `vendor_membresias`
--
ALTER TABLE `vendor_membresias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `proveedor_id` (`proveedor_id`),
  ADD KEY `plan_id` (`plan_id`);

--
-- Indexes for table `vendor_notif_config`
--
ALTER TABLE `vendor_notif_config`
  ADD PRIMARY KEY (`proveedor_id`);

--
-- Indexes for table `vendor_notif_settings`
--
ALTER TABLE `vendor_notif_settings`
  ADD PRIMARY KEY (`proveedor_id`);

--
-- Indexes for table `vendor_notif_templates`
--
ALTER TABLE `vendor_notif_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_tpl` (`proveedor_id`,`nombre`);

--
-- Indexes for table `vendor_pagos`
--
ALTER TABLE `vendor_pagos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `membresia_id` (`membresia_id`);

--
-- Indexes for table `vendor_payout_accounts`
--
ALTER TABLE `vendor_payout_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_prov` (`proveedor_id`),
  ADD KEY `idx_tipo` (`tipo`);

--
-- Indexes for table `vendor_planes`
--
ALTER TABLE `vendor_planes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `vendor_plan_features`
--
ALTER TABLE `vendor_plan_features`
  ADD PRIMARY KEY (`plan_id`);

--
-- Indexes for table `vendor_roles_permisos`
--
ALTER TABLE `vendor_roles_permisos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_vendor_rol_perm` (`proveedor_id`,`rol`,`permiso`),
  ADD KEY `idx_perm` (`permiso`);

--
-- Indexes for table `vendor_webhooks`
--
ALTER TABLE `vendor_webhooks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_prov` (`proveedor_id`),
  ADD KEY `idx_evento` (`evento`);

--
-- Indexes for table `web_analytics`
--
ALTER TABLE `web_analytics`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ymd_idx` (`ymd`),
  ADD KEY `path_idx` (`path`(120));

--
-- Indexes for table `web_setting`
--
ALTER TABLE `web_setting`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_clave` (`clave`);

--
-- Indexes for table `zonas_canon`
--
ALTER TABLE `zonas_canon`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_zona_normalized` (`normalized`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `api_consumo`
--
ALTER TABLE `api_consumo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `api_tokens`
--
ALTER TABLE `api_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `comprobantes`
--
ALTER TABLE `comprobantes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `comprobantes_fiscales`
--
ALTER TABLE `comprobantes_fiscales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `configuracion`
--
ALTER TABLE `configuracion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `config_global`
--
ALTER TABLE `config_global`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `crm_clientes`
--
ALTER TABLE `crm_clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `crm_licencias`
--
ALTER TABLE `crm_licencias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crm_licencias_ips`
--
ALTER TABLE `crm_licencias_ips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cron_status`
--
ALTER TABLE `cron_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `datos_bancarios`
--
ALTER TABLE `datos_bancarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `dominios_remotos`
--
ALTER TABLE `dominios_remotos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `empleados`
--
ALTER TABLE `empleados`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `facturas`
--
ALTER TABLE `facturas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `historial_acceso`
--
ALTER TABLE `historial_acceso`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `keywords`
--
ALTER TABLE `keywords`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=401;

--
-- AUTO_INCREMENT for table `logos_hero`
--
ALTER TABLE `logos_hero`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `logs_app`
--
ALTER TABLE `logs_app`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=424;

--
-- AUTO_INCREMENT for table `map_providers`
--
ALTER TABLE `map_providers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `map_styles`
--
ALTER TABLE `map_styles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `notas_credito`
--
ALTER TABLE `notas_credito`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notificaciones`
--
ALTER TABLE `notificaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `notif_log`
--
ALTER TABLE `notif_log`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pages`
--
ALTER TABLE `pages`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `periodos_no_disponibles`
--
ALTER TABLE `periodos_no_disponibles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `proveedores`
--
ALTER TABLE `proveedores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `provincias`
--
ALTER TABLE `provincias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `recibos_transferencia`
--
ALTER TABLE `recibos_transferencia`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reservas`
--
ALTER TABLE `reservas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `roles_permisos`
--
ALTER TABLE `roles_permisos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `secuencias_ncf`
--
ALTER TABLE `secuencias_ncf`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `solicitudes_cancelacion`
--
ALTER TABLE `solicitudes_cancelacion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `tipos_pago_licencia`
--
ALTER TABLE `tipos_pago_licencia`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `usuarios_permisos`
--
ALTER TABLE `usuarios_permisos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `vallas`
--
ALTER TABLE `vallas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=145;

--
-- AUTO_INCREMENT for table `vallas_destacadas_pagos`
--
ALTER TABLE `vallas_destacadas_pagos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `vallas_licencias`
--
ALTER TABLE `vallas_licencias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `valla_media`
--
ALTER TABLE `valla_media`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `valla_precios`
--
ALTER TABLE `valla_precios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vendor_commissions`
--
ALTER TABLE `vendor_commissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `vendor_config`
--
ALTER TABLE `vendor_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `vendor_equipo`
--
ALTER TABLE `vendor_equipo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vendor_members`
--
ALTER TABLE `vendor_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vendor_membresias`
--
ALTER TABLE `vendor_membresias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `vendor_notif_templates`
--
ALTER TABLE `vendor_notif_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `vendor_pagos`
--
ALTER TABLE `vendor_pagos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vendor_payout_accounts`
--
ALTER TABLE `vendor_payout_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vendor_planes`
--
ALTER TABLE `vendor_planes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `vendor_roles_permisos`
--
ALTER TABLE `vendor_roles_permisos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vendor_webhooks`
--
ALTER TABLE `vendor_webhooks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `web_analytics`
--
ALTER TABLE `web_analytics`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=525;

--
-- AUTO_INCREMENT for table `web_setting`
--
ALTER TABLE `web_setting`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=112;

--
-- AUTO_INCREMENT for table `zonas_canon`
--
ALTER TABLE `zonas_canon`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `comprobantes_fiscales`
--
ALTER TABLE `comprobantes_fiscales`
  ADD CONSTRAINT `comprobantes_fiscales_ibfk_1` FOREIGN KEY (`secuencia_id`) REFERENCES `secuencias_ncf` (`id`);

--
-- Constraints for table `crm_clientes`
--
ALTER TABLE `crm_clientes`
  ADD CONSTRAINT `fk_crm_prov` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `logs_app_blob`
--
ALTER TABLE `logs_app_blob`
  ADD CONSTRAINT `fk_logs_blob` FOREIGN KEY (`log_id`) REFERENCES `logs_app` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `page_keywords`
--
ALTER TABLE `page_keywords`
  ADD CONSTRAINT `fk_pk_kw` FOREIGN KEY (`keyword_id`) REFERENCES `keywords` (`id`),
  ADD CONSTRAINT `fk_pk_page` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`);

--
-- Constraints for table `vallas`
--
ALTER TABLE `vallas`
  ADD CONSTRAINT `fk_provincia` FOREIGN KEY (`provincia_id`) REFERENCES `provincias` (`id`),
  ADD CONSTRAINT `fk_valla_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `vallas_licencias`
--
ALTER TABLE `vallas_licencias`
  ADD CONSTRAINT `fk_tipo_pago_licencia` FOREIGN KEY (`tipo_pago_id`) REFERENCES `tipos_pago_licencia` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_valla_id` FOREIGN KEY (`valla_id`) REFERENCES `vallas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vallas_licencias_ibfk_1` FOREIGN KEY (`valla_id`) REFERENCES `vallas` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `valla_media`
--
ALTER TABLE `valla_media`
  ADD CONSTRAINT `fk_valla_media_valla` FOREIGN KEY (`valla_id`) REFERENCES `vallas` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `valla_precios`
--
ALTER TABLE `valla_precios`
  ADD CONSTRAINT `fk_vp_valla` FOREIGN KEY (`valla_id`) REFERENCES `vallas` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vendor_membresias`
--
ALTER TABLE `vendor_membresias`
  ADD CONSTRAINT `vendor_membresias_ibfk_1` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`),
  ADD CONSTRAINT `vendor_membresias_ibfk_2` FOREIGN KEY (`plan_id`) REFERENCES `vendor_planes` (`id`);

--
-- Constraints for table `vendor_pagos`
--
ALTER TABLE `vendor_pagos`
  ADD CONSTRAINT `vendor_pagos_ibfk_1` FOREIGN KEY (`membresia_id`) REFERENCES `vendor_membresias` (`id`);

--
-- Constraints for table `vendor_plan_features`
--
ALTER TABLE `vendor_plan_features`
  ADD CONSTRAINT `vendor_plan_features_ibfk_1` FOREIGN KEY (`plan_id`) REFERENCES `vendor_planes` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
