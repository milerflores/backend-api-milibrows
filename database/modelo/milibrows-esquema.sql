-- MiLibrows: estructura sin datos personales. Versión 20/11/2026.

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `asignaciones_insumos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_empleado` bigint unsigned NOT NULL,
  `id_insumo` bigint unsigned NOT NULL,
  `cantidad_entregada` int unsigned NOT NULL,
  `fecha_entrega` datetime NOT NULL,
  `fecha_agotamiento` datetime DEFAULT NULL,
  `estado` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en_uso',
  PRIMARY KEY (`id`),
  KEY `asignaciones_insumos_id_empleado_foreign` (`id_empleado`),
  KEY `asignaciones_insumos_id_insumo_foreign` (`id_insumo`),
  CONSTRAINT `asignaciones_insumos_id_empleado_foreign` FOREIGN KEY (`id_empleado`) REFERENCES `empleados` (`id`),
  CONSTRAINT `asignaciones_insumos_id_insumo_foreign` FOREIGN KEY (`id_insumo`) REFERENCES `insumos` (`id`),
  CONSTRAINT `asignaciones_cantidad_positiva` CHECK ((`cantidad_entregada` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 */ /*!50003 TRIGGER `asignaciones_validar_actualizar` BEFORE UPDATE ON `asignaciones_insumos` FOR EACH ROW BEGIN
    IF EXISTS (SELECT 1 FROM movimientos_inventario WHERE id_asignacion = OLD.id AND (id_insumo <> NEW.id_insumo OR tipo_movimiento <> 'salida' OR variacion_cantidad <> -CAST(NEW.cantidad_entregada AS SIGNED))) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La asignacion no puede contradecir su movimiento de salida.';
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categorias_inventario` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `citas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_cliente` bigint unsigned NOT NULL,
  `id_usuario_creador` bigint unsigned NOT NULL,
  `fecha_finalizacion` datetime DEFAULT NULL,
  `origen` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `numero_revision` int unsigned NOT NULL DEFAULT '1',
  `motivo_cancelacion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `fecha` date NOT NULL,
  `estado` enum('confirmada','completada','cancelada','no_asistio') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'confirmada',
  `fecha_creacion` timestamp NULL DEFAULT NULL,
  `fecha_actualizacion` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `citas_id_cliente_foreign` (`id_cliente`),
  KEY `citas_id_usuario_creador_foreign` (`id_usuario_creador`),
  KEY `citas_fecha_estado` (`fecha`,`estado`),
  CONSTRAINT `citas_id_cliente_foreign` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id`),
  CONSTRAINT `citas_id_usuario_creador_foreign` FOREIGN KEY (`id_usuario_creador`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `citas_revision_positiva` CHECK ((`numero_revision` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 */ /*!50003 TRIGGER `citas_validar_actualizacion` BEFORE UPDATE ON `citas` FOR EACH ROW BEGIN
    DECLARE v_conflicto BIGINT UNSIGNED DEFAULT NULL;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_conflicto = NULL;
    IF NEW.id_cliente<>OLD.id_cliente AND EXISTS(SELECT 1 FROM cobros WHERE id_cita=OLD.id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Una cita con cobros conserva su cliente para mantener los puntos vinculados.';
    END IF;
    IF (NEW.numero_revision < OLD.numero_revision) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El numero de revision de una cita no puede retroceder.';
    END IF;
    IF NEW.estado = 'completada' AND (EXISTS (SELECT 1 FROM servicios_cita WHERE id_cita = OLD.id AND estado_servicio IN ('pendiente','en_proceso')) OR NOT EXISTS (SELECT 1 FROM servicios_cita WHERE id_cita = OLD.id AND estado_servicio = 'realizado')) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Para completar la cita deben resolverse todos sus servicios y realizarse al menos uno.';
    END IF;
    IF NEW.estado = 'confirmada' AND (NEW.fecha <> OLD.fecha OR NEW.estado <> OLD.estado) THEN
        SELECT propio.id INTO v_conflicto FROM servicios_cita propio
        JOIN servicios_cita otro ON otro.id_empleado = propio.id_empleado AND otro.id_cita <> OLD.id
        JOIN citas c ON c.id = otro.id_cita
        WHERE propio.id_cita = OLD.id AND c.fecha = NEW.fecha AND c.estado = 'confirmada'
          AND propio.estado_servicio IN ('pendiente','en_proceso','realizado') AND otro.estado_servicio IN ('pendiente','en_proceso','realizado')
          AND TIME_TO_SEC(otro.hora_inicio) < TIME_TO_SEC(propio.hora_inicio) + propio.duracion_minutos * 60
          AND TIME_TO_SEC(otro.hora_inicio) + otro.duracion_minutos * 60 > TIME_TO_SEC(propio.hora_inicio)
        LIMIT 1;
        IF v_conflicto IS NOT NULL THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La nueva fecha de la cita produce un conflicto de horarios.';
        END IF;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `clientes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_persona` bigint unsigned NOT NULL,
  `preferencias` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `fecha_creacion` timestamp NULL DEFAULT NULL,
  `fecha_actualizacion` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `clientes_id_persona_unique` (`id_persona`),
  CONSTRAINT `clientes_id_persona_foreign` FOREIGN KEY (`id_persona`) REFERENCES `personas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cobros` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_cita` bigint unsigned NOT NULL,
  `id_metodo_pago` bigint unsigned NOT NULL,
  `id_usuario_creador` bigint unsigned NOT NULL,
  `importe` decimal(12,2) NOT NULL,
  `fecha_cobro` datetime NOT NULL,
  `referencia_pago` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'registrado',
  PRIMARY KEY (`id`),
  KEY `cobros_id_metodo_pago_foreign` (`id_metodo_pago`),
  KEY `cobros_id_usuario_creador_foreign` (`id_usuario_creador`),
  KEY `cobros_id_cita_index` (`id_cita`),
  CONSTRAINT `cobros_id_cita_foreign` FOREIGN KEY (`id_cita`) REFERENCES `citas` (`id`),
  CONSTRAINT `cobros_id_metodo_pago_foreign` FOREIGN KEY (`id_metodo_pago`) REFERENCES `metodos_pago` (`id`),
  CONSTRAINT `cobros_id_usuario_creador_foreign` FOREIGN KEY (`id_usuario_creador`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `cobros_importe_positivo` CHECK ((`importe` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 */ /*!50003 TRIGGER `cobros_con_puntos_inmutables` BEFORE UPDATE ON `cobros` FOR EACH ROW BEGIN
    IF (NEW.id_cita<>OLD.id_cita OR NEW.importe<>OLD.importe) AND EXISTS(SELECT 1 FROM movimientos_puntos WHERE id_cobro=OLD.id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El cobro tiene puntos emitidos; anule el pago en lugar de modificar su origen o importe.';
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `codigos_restablecimiento` (
  `correo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_creacion` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`correo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `configuracion_estudio` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nombre_estudio` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `canal_notificacion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `anticipacion_recordatorio_min` int unsigned NOT NULL DEFAULT '1440',
  `monto_por_punto` decimal(12,2) NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `configuracion_monto_por_punto_positivo` CHECK ((`monto_por_punto` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `descuentos_manuales_cita` (
  `id_servicio_cita` bigint unsigned NOT NULL,
  `id_motivo_descuento` bigint unsigned DEFAULT NULL,
  `id_usuario` bigint unsigned NOT NULL,
  `monto` decimal(12,2) NOT NULL,
  `nota` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `fecha_creacion` timestamp NOT NULL,
  PRIMARY KEY (`id_servicio_cita`),
  KEY `descuentos_manuales_motivo_foreign` (`id_motivo_descuento`),
  KEY `descuentos_manuales_usuario_foreign` (`id_usuario`),
  CONSTRAINT `descuentos_manuales_motivo_foreign` FOREIGN KEY (`id_motivo_descuento`) REFERENCES `motivos_descuento` (`id`),
  CONSTRAINT `descuentos_manuales_servicio_foreign` FOREIGN KEY (`id_servicio_cita`) REFERENCES `servicios_cita` (`id`),
  CONSTRAINT `descuentos_manuales_usuario_foreign` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `descuentos_manuales_monto_valido` CHECK ((`monto` >= 0)),
  CONSTRAINT `descuentos_manuales_motivo_o_nota` CHECK (((`id_motivo_descuento` is not null) or ((`nota` is not null) and (char_length(trim(`nota`)) > 0))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `empleados` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` bigint unsigned NOT NULL,
  `cargo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `admite_reservas` tinyint(1) NOT NULL DEFAULT '0',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `fecha_creacion` timestamp NULL DEFAULT NULL,
  `fecha_actualizacion` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `empleados_id_usuario_unique` (`id_usuario`),
  CONSTRAINT `empleados_id_usuario_foreign` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `historial_cita` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_cita` bigint unsigned NOT NULL,
  `id_usuario_responsable` bigint unsigned NOT NULL,
  `numero_revision` int unsigned NOT NULL,
  `tipo_cambio` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `detalle_cambios` json NOT NULL,
  `motivo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `fecha_creacion` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `eventos_cita_id_cita_revision_unique` (`id_cita`,`numero_revision`),
  UNIQUE KEY `historial_identidad_revision_unica` (`id`,`id_cita`,`numero_revision`),
  KEY `eventos_cita_id_usuario_responsable_foreign` (`id_usuario_responsable`),
  CONSTRAINT `eventos_cita_id_cita_foreign` FOREIGN KEY (`id_cita`) REFERENCES `citas` (`id`),
  CONSTRAINT `eventos_cita_id_usuario_responsable_foreign` FOREIGN KEY (`id_usuario_responsable`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `historial_revision_positiva` CHECK ((`numero_revision` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 */ /*!50003 TRIGGER `historial_cita_validar_insertar` BEFORE INSERT ON `historial_cita` FOR EACH ROW BEGIN
    IF NOT EXISTS (SELECT 1 FROM citas WHERE id = NEW.id_cita AND numero_revision = NEW.numero_revision) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La revision del historial debe coincidir con la revision actual de la cita.';
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `horarios_estudio` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_estudio` bigint unsigned NOT NULL,
  `dia_semana` tinyint unsigned NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  PRIMARY KEY (`id`),
  KEY `horarios_estudio_id_estudio_foreign` (`id_estudio`),
  CONSTRAINT `horarios_estudio_id_estudio_foreign` FOREIGN KEY (`id_estudio`) REFERENCES `configuracion_estudio` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `insumos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_categoria` bigint unsigned NOT NULL,
  `nombre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `existencias_actuales` int unsigned NOT NULL DEFAULT '0',
  `existencias_minimas` int unsigned NOT NULL DEFAULT '0',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `fecha_creacion` timestamp NULL DEFAULT NULL,
  `fecha_actualizacion` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `insumos_id_categoria_foreign` (`id_categoria`),
  CONSTRAINT `insumos_id_categoria_foreign` FOREIGN KEY (`id_categoria`) REFERENCES `categorias_inventario` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jornadas_empleados` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_empleado` bigint unsigned NOT NULL,
  `dia_semana` tinyint unsigned NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jornadas_empleados_id_empleado_foreign` (`id_empleado`),
  CONSTRAINT `jornadas_empleados_id_empleado_foreign` FOREIGN KEY (`id_empleado`) REFERENCES `empleados` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `metodos_pago` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `metodos_pago_codigo_unique` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `motivos_descuento` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  CONSTRAINT `motivos_descuento_activo_valido` CHECK ((`activo` in (0,1)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `movimientos_inventario` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_insumo` bigint unsigned NOT NULL,
  `id_usuario_creador` bigint unsigned NOT NULL,
  `id_asignacion` bigint unsigned DEFAULT NULL,
  `tipo_movimiento` enum('entrada','salida','devolucion','ajuste') COLLATE utf8mb4_unicode_ci NOT NULL,
  `variacion_cantidad` int NOT NULL,
  `existencias_antes` int NOT NULL,
  `existencias_despues` int NOT NULL,
  `motivo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_creacion` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `movimientos_inventario_id_asignacion_unique` (`id_asignacion`),
  KEY `movimientos_inventario_id_insumo_foreign` (`id_insumo`),
  KEY `movimientos_inventario_id_usuario_creador_foreign` (`id_usuario_creador`),
  CONSTRAINT `movimientos_inventario_id_asignacion_foreign` FOREIGN KEY (`id_asignacion`) REFERENCES `asignaciones_insumos` (`id`),
  CONSTRAINT `movimientos_inventario_id_insumo_foreign` FOREIGN KEY (`id_insumo`) REFERENCES `insumos` (`id`),
  CONSTRAINT `movimientos_inventario_id_usuario_creador_foreign` FOREIGN KEY (`id_usuario_creador`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `movimientos_inventario_saldos_validos` CHECK (((`existencias_antes` >= 0) and (`existencias_despues` >= 0) and (`existencias_despues` = (`existencias_antes` + `variacion_cantidad`)))),
  CONSTRAINT `movimientos_inventario_signo_valido` CHECK ((((`tipo_movimiento` in (_utf8mb4'entrada',_utf8mb4'devolucion')) and (`variacion_cantidad` > 0)) or ((`tipo_movimiento` = _utf8mb4'salida') and (`variacion_cantidad` < 0)) or ((`tipo_movimiento` = _utf8mb4'ajuste') and (`variacion_cantidad` <> 0))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 */ /*!50003 TRIGGER `inventario_validar_insertar` BEFORE INSERT ON `movimientos_inventario` FOR EACH ROW BEGIN
    CALL validar_movimiento_asignado(NEW.id_asignacion, NEW.id_insumo, NEW.tipo_movimiento, NEW.variacion_cantidad);
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 */ /*!50003 TRIGGER `inventario_validar_actualizar` BEFORE UPDATE ON `movimientos_inventario` FOR EACH ROW BEGIN
    CALL validar_movimiento_asignado(NEW.id_asignacion, NEW.id_insumo, NEW.tipo_movimiento, NEW.variacion_cantidad);
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `movimientos_puntos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_cliente` bigint unsigned NOT NULL,
  `id_cobro` bigint unsigned DEFAULT NULL,
  `id_usuario_creador` bigint unsigned NOT NULL,
  `tipo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `puntos` int NOT NULL,
  `motivo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `fecha_creacion` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `movimientos_puntos_cobro_origen_unico` (`id_cobro`,((case when (`tipo` in (_utf8mb4'ganado',_utf8mb4'reversion')) then `tipo` else NULL end))),
  KEY `movimientos_puntos_cliente_fecha` (`id_cliente`,`fecha_creacion`),
  KEY `movimientos_puntos_id_usuario_creador_foreign` (`id_usuario_creador`),
  CONSTRAINT `movimientos_puntos_id_cliente_foreign` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id`),
  CONSTRAINT `movimientos_puntos_id_cobro_foreign` FOREIGN KEY (`id_cobro`) REFERENCES `cobros` (`id`),
  CONSTRAINT `movimientos_puntos_id_usuario_creador_foreign` FOREIGN KEY (`id_usuario_creador`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `movimientos_puntos_tipo_y_signo_validos` CHECK ((((`tipo` = _utf8mb4'ganado') and (`id_cobro` is not null) and (`puntos` >= 0)) or ((`tipo` = _utf8mb4'reversion') and (`id_cobro` is not null) and (`puntos` <= 0)) or ((`tipo` = _utf8mb4'ajuste') and (`puntos` <> 0) and (`motivo` is not null) and (char_length(trim(`motivo`)) > 0))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 */ /*!50003 TRIGGER `puntos_validar_cliente` BEFORE INSERT ON `movimientos_puntos` FOR EACH ROW BEGIN
    IF NEW.id_cobro IS NOT NULL AND NOT EXISTS (SELECT 1 FROM cobros co JOIN citas c ON c.id = co.id_cita WHERE co.id = NEW.id_cobro AND c.id_cliente = NEW.id_cliente AND (NEW.tipo<>'ganado' OR co.estado='registrado') AND (NEW.tipo<>'reversion' OR co.estado='anulado')) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El cliente o estado de los puntos no corresponde al cobro.';
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 */ /*!50003 TRIGGER `movimientos_puntos_no_actualizar` BEFORE UPDATE ON `movimientos_puntos` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Los movimientos de puntos no se editan. Registre un movimiento de ajuste.' */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 */ /*!50003 TRIGGER `movimientos_puntos_no_eliminar` BEFORE DELETE ON `movimientos_puntos` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Los movimientos de puntos no se eliminan. Registre una reversion o ajuste.' */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `niveles_fidelizacion` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `orden` tinyint unsigned NOT NULL,
  `puntos_minimos` int unsigned NOT NULL,
  `porcentaje_descuento` decimal(5,2) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `niveles_fidelizacion_codigo_unique` (`codigo`),
  UNIQUE KEY `niveles_fidelizacion_orden_unique` (`orden`),
  CONSTRAINT `niveles_activo_valido` CHECK ((`activo` in (0,1))),
  CONSTRAINT `niveles_estructura_valida` CHECK ((((`codigo` = _utf8mb4'glow') and (`orden` = 1) and (`puntos_minimos` = 0) and (`activo` = 1)) or ((`codigo` = _utf8mb4'golden') and (`orden` = 2) and ((`puntos_minimos` > 0) or ((`activo` = 0) and (`puntos_minimos` = 0)))) or ((`codigo` = _utf8mb4'vip') and (`orden` = 3) and ((`puntos_minimos` > 0) or ((`activo` = 0) and (`puntos_minimos` = 0)))))),
  CONSTRAINT `niveles_porcentaje_valido` CHECK ((`porcentaje_descuento` between 0 and 100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 */ /*!50003 TRIGGER `niveles_no_eliminar_glow` BEFORE DELETE ON `niveles_fidelizacion` FOR EACH ROW BEGIN
    IF OLD.codigo = 'glow' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Glow es el nivel inicial y no se puede eliminar.';
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notas_cita` (
  `id_cita` bigint unsigned NOT NULL,
  `descripcion_tratamiento` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id_cita`),
  CONSTRAINT `notas_cita_id_cita_foreign` FOREIGN KEY (`id_cita`) REFERENCES `citas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notificaciones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_cita` bigint unsigned NOT NULL,
  `id_historial_cita` bigint unsigned DEFAULT NULL,
  `revision_cita` int unsigned NOT NULL,
  `clave_deduplicacion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `canal` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_programada` datetime NOT NULL,
  `fecha_envio` datetime DEFAULT NULL,
  `estado` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
  PRIMARY KEY (`id`),
  UNIQUE KEY `notificaciones_clave_deduplicacion_unique` (`clave_deduplicacion`),
  KEY `notificaciones_id_cita_foreign` (`id_cita`),
  KEY `notificaciones_id_evento_cita_foreign` (`id_historial_cita`),
  KEY `notificaciones_estado_fecha_programada_index` (`estado`,`fecha_programada`),
  KEY `notificaciones_historial_cita_foreign` (`id_historial_cita`,`id_cita`,`revision_cita`),
  CONSTRAINT `notificaciones_historial_cita_foreign` FOREIGN KEY (`id_historial_cita`, `id_cita`, `revision_cita`) REFERENCES `historial_cita` (`id`, `id_cita`, `numero_revision`),
  CONSTRAINT `notificaciones_id_cita_foreign` FOREIGN KEY (`id_cita`) REFERENCES `citas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 */ /*!50003 TRIGGER `notificaciones_validar_insertar` BEFORE INSERT ON `notificaciones` FOR EACH ROW BEGIN
    CALL validar_vinculo_notificacion(NEW.id_cita, NEW.id_historial_cita, NEW.revision_cita);
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 */ /*!50003 TRIGGER `notificaciones_validar_actualizar` BEFORE UPDATE ON `notificaciones` FOR EACH ROW BEGIN
    CALL validar_vinculo_notificacion(NEW.id_cita, NEW.id_historial_cita, NEW.revision_cita);
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `dni` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `correo_contacto` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellido_paterno` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellido_materno` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT NULL,
  `fecha_actualizacion` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personas_dni_unique` (`dni`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_codigo_unique` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles_usuario` (
  `id_usuario` bigint unsigned NOT NULL,
  `id_rol` bigint unsigned NOT NULL,
  PRIMARY KEY (`id_usuario`,`id_rol`),
  KEY `roles_usuario_id_rol_foreign` (`id_rol`),
  CONSTRAINT `roles_usuario_id_rol_foreign` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id`),
  CONSTRAINT `roles_usuario_id_usuario_foreign` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `servicios` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `precio` decimal(12,2) NOT NULL,
  `duracion_minutos` smallint unsigned NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `imagen_ruta` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `visible_al_publico` tinyint(1) NOT NULL DEFAULT '1',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `fecha_creacion` timestamp NULL DEFAULT NULL,
  `fecha_actualizacion` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `servicios_cita` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_cita` bigint unsigned NOT NULL,
  `id_servicio` bigint unsigned NOT NULL,
  `id_nivel` bigint unsigned DEFAULT NULL,
  `id_empleado` bigint unsigned NOT NULL,
  `nombre_servicio_acordado` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `precio_lista` decimal(12,2) NOT NULL,
  `monto_descuento_automatico` decimal(12,2) NOT NULL DEFAULT '0.00',
  `precio_acordado` decimal(12,2) NOT NULL,
  `duracion_minutos` smallint unsigned NOT NULL,
  `motivo_estado_servicio` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `hora_inicio` time NOT NULL,
  `fecha_inicio_real` datetime DEFAULT NULL,
  `fecha_finalizacion` datetime DEFAULT NULL,
  `estado_servicio` enum('pendiente','en_proceso','realizado','cancelado','no_realizado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
  PRIMARY KEY (`id`),
  KEY `servicios_cita_id_servicio_foreign` (`id_servicio`),
  KEY `servicios_cita_id_nivel_foreign` (`id_nivel`),
  KEY `servicios_cita_cita_index` (`id_cita`),
  KEY `servicios_cita_empleado_horario` (`id_empleado`,`hora_inicio`,`estado_servicio`,`id_cita`),
  CONSTRAINT `servicios_cita_empleado_foreign` FOREIGN KEY (`id_empleado`) REFERENCES `empleados` (`id`),
  CONSTRAINT `servicios_cita_id_cita_foreign` FOREIGN KEY (`id_cita`) REFERENCES `citas` (`id`),
  CONSTRAINT `servicios_cita_id_nivel_foreign` FOREIGN KEY (`id_nivel`) REFERENCES `niveles_fidelizacion` (`id`),
  CONSTRAINT `servicios_cita_id_servicio_foreign` FOREIGN KEY (`id_servicio`) REFERENCES `servicios` (`id`),
  CONSTRAINT `servicios_cita_fechas_reales_validas` CHECK (((`fecha_finalizacion` is null) or (`fecha_inicio_real` is null) or (`fecha_finalizacion` >= `fecha_inicio_real`))),
  CONSTRAINT `servicios_cita_horario_valido` CHECK (((`duracion_minutos` > 0) and (time_to_sec(`hora_inicio`) >= 0) and ((time_to_sec(`hora_inicio`) + (`duracion_minutos` * 60)) <= 86400))),
  CONSTRAINT `servicios_cita_precios_validos` CHECK (((`precio_lista` >= 0) and (`monto_descuento_automatico` >= 0) and (`monto_descuento_automatico` <= `precio_lista`) and (`precio_acordado` >= 0) and (`precio_acordado` <= (`precio_lista` - `monto_descuento_automatico`))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 */ /*!50003 TRIGGER `servicios_cita_validar_insertar` BEFORE INSERT ON `servicios_cita` FOR EACH ROW BEGIN
    CALL validar_horario_servicio(COALESCE(NEW.id, 0), NEW.id_cita, NEW.id_servicio, NEW.id_empleado, NEW.hora_inicio, NEW.duracion_minutos, NEW.estado_servicio);
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 */ /*!50003 TRIGGER `servicios_cita_validar_actualizar` BEFORE UPDATE ON `servicios_cita` FOR EACH ROW BEGIN
    IF EXISTS(SELECT 1 FROM cobros WHERE id_cita=OLD.id_cita AND estado='registrado') AND
       (NEW.precio_lista<>OLD.precio_lista OR NEW.precio_acordado<>OLD.precio_acordado OR NEW.monto_descuento_automatico<>OLD.monto_descuento_automatico OR NOT(NEW.id_nivel<=>OLD.id_nivel) OR NEW.estado_servicio<>OLD.estado_servicio OR NEW.id_cita<>OLD.id_cita) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Los servicios cobrados conservan sus precios, descuentos y resultados.';
    END IF;
    IF NEW.id_cita<>OLD.id_cita OR NEW.id_servicio<>OLD.id_servicio OR NEW.id_empleado<>OLD.id_empleado OR NEW.hora_inicio<>OLD.hora_inicio OR NEW.duracion_minutos<>OLD.duracion_minutos OR NEW.estado_servicio<>OLD.estado_servicio THEN
        CALL validar_horario_servicio(OLD.id, NEW.id_cita, NEW.id_servicio, NEW.id_empleado, NEW.hora_inicio, NEW.duracion_minutos, NEW.estado_servicio);
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `servicios_por_empleado` (
  `id_empleado` bigint unsigned NOT NULL,
  `id_servicio` bigint unsigned NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id_empleado`,`id_servicio`),
  KEY `servicios_por_empleado_id_servicio_foreign` (`id_servicio`),
  CONSTRAINT `servicios_por_empleado_id_empleado_foreign` FOREIGN KEY (`id_empleado`) REFERENCES `empleados` (`id`),
  CONSTRAINT `servicios_por_empleado_id_servicio_foreign` FOREIGN KEY (`id_servicio`) REFERENCES `servicios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuarios` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_persona` bigint unsigned NOT NULL,
  `correo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_verificacion_correo` timestamp NULL DEFAULT NULL,
  `contrasena` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `version_sesion` int unsigned NOT NULL DEFAULT '1',
  `secreto_segundo_factor` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `codigos_respaldo_mfa` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `fecha_confirmacion_mfa` timestamp NULL DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `fecha_creacion` timestamp NULL DEFAULT NULL,
  `fecha_actualizacion` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`correo`),
  UNIQUE KEY `users_id_persona_unique` (`id_persona`),
  CONSTRAINT `usuarios_id_persona_foreign` FOREIGN KEY (`id_persona`) REFERENCES `personas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE PROCEDURE `anular_cobro_con_puntos`(IN p_cobro BIGINT UNSIGNED, IN p_usuario BIGINT UNSIGNED, IN p_motivo TEXT)
BEGIN
    DECLARE v_cita BIGINT UNSIGNED;
    DECLARE v_cliente BIGINT UNSIGNED;
    DECLARE v_estado VARCHAR(255);
    DECLARE v_puntos INT DEFAULT 0;
    DECLARE v_origen BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_pagado_restante DECIMAL(12,2);
    DECLARE v_tasa_actual DECIMAL(12,2);
    DECLARE v_neto INT;
    DECLARE v_ajuste INT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
    SELECT id_cita INTO v_cita FROM cobros WHERE id = p_cobro;
    START TRANSACTION;
    SELECT id_cliente INTO v_cliente FROM citas WHERE id = v_cita FOR UPDATE;
    SELECT estado INTO v_estado FROM cobros WHERE id = p_cobro FOR UPDATE;
    IF v_estado IS NULL OR v_estado <> 'registrado' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El cobro no existe o ya fue anulado.';
    END IF;
    UPDATE cobros SET estado = 'anulado' WHERE id = p_cobro;
    SELECT MAX(id), COALESCE(SUM(puntos),0) INTO v_origen, v_puntos FROM movimientos_puntos WHERE id_cobro = p_cobro AND tipo = 'ganado';
    IF v_origen IS NOT NULL THEN
        INSERT INTO movimientos_puntos(id_cliente,id_cobro,id_usuario_creador,tipo,puntos,motivo,fecha_creacion) VALUES(v_cliente,p_cobro,p_usuario,'reversion',-v_puntos,p_motivo,NOW());
        SELECT COALESCE(SUM(importe),0) INTO v_pagado_restante FROM cobros WHERE id_cita=v_cita AND estado='registrado' FOR UPDATE;
        SELECT monto_por_punto INTO v_tasa_actual FROM configuracion_estudio WHERE id=1 FOR SHARE;
        SELECT COALESCE(SUM(m.puntos),0) INTO v_neto FROM movimientos_puntos m JOIN cobros co ON co.id=m.id_cobro WHERE co.id_cita=v_cita FOR SHARE;
        SET v_ajuste=FLOOR(v_pagado_restante/v_tasa_actual)-v_neto;
        IF v_ajuste<>0 THEN
            INSERT INTO movimientos_puntos(id_cliente,id_cobro,id_usuario_creador,tipo,puntos,motivo,fecha_creacion)
            VALUES(v_cliente,p_cobro,p_usuario,'ajuste',v_ajuste,'Reajuste del redondeo acumulado al anular un pago parcial',NOW());
        END IF;
    END IF;
    COMMIT;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE PROCEDURE `guardar_servicio_reservado`(
    IN p_id BIGINT UNSIGNED, IN p_cita BIGINT UNSIGNED, IN p_servicio BIGINT UNSIGNED,
    IN p_empleado BIGINT UNSIGNED, IN p_hora TIME, IN p_duracion INT UNSIGNED,
    IN p_nombre VARCHAR(255), IN p_lista DECIMAL(12,2), IN p_automatico DECIMAL(12,2),
    IN p_nivel BIGINT UNSIGNED, IN p_acordado DECIMAL(12,2)
)
BEGIN
    DECLARE v_fecha DATE;
    DECLARE v_estado VARCHAR(255);
    DECLARE v_id BIGINT UNSIGNED;
    DECLARE v_empleado BIGINT UNSIGNED;
    DECLARE v_conflicto BIGINT UNSIGNED DEFAULT NULL;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_conflicto = NULL;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
    SET TRANSACTION ISOLATION LEVEL READ COMMITTED;
    START TRANSACTION;
    SELECT fecha, estado INTO v_fecha, v_estado FROM citas WHERE id = p_cita FOR UPDATE;
    SELECT id INTO v_empleado FROM empleados WHERE id = p_empleado FOR UPDATE;
    IF v_fecha IS NULL OR v_empleado IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No existe la cita o el empleado.';
    END IF;
    IF COALESCE(p_id,0) <> 0 AND NOT EXISTS(SELECT 1 FROM servicios_cita WHERE id=p_id AND id_cita=p_cita) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El servicio reservado no pertenece a la cita.';
    END IF;
    IF v_estado = 'confirmada' THEN
        SELECT s.id INTO v_conflicto FROM servicios_cita s JOIN citas c ON c.id=s.id_cita
        WHERE s.id<>COALESCE(p_id,0) AND s.id_empleado=p_empleado AND c.fecha=v_fecha AND c.estado='confirmada'
          AND s.estado_servicio IN ('pendiente','en_proceso','realizado')
          AND TIME_TO_SEC(s.hora_inicio)<TIME_TO_SEC(p_hora)+p_duracion*60
          AND TIME_TO_SEC(s.hora_inicio)+s.duracion_minutos*60>TIME_TO_SEC(p_hora)
        LIMIT 1 FOR UPDATE;
        IF v_conflicto IS NOT NULL THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El empleado tiene otro servicio en el horario solicitado.';
        END IF;
    END IF;
    IF COALESCE(p_id,0)=0 THEN
        INSERT INTO servicios_cita(id_cita,id_servicio,id_empleado,hora_inicio,duracion_minutos,nombre_servicio_acordado,precio_lista,monto_descuento_automatico,id_nivel,precio_acordado)
        VALUES(p_cita,p_servicio,p_empleado,p_hora,p_duracion,p_nombre,p_lista,p_automatico,p_nivel,p_acordado);
        SET v_id=LAST_INSERT_ID();
    ELSE
        UPDATE servicios_cita SET id_servicio=p_servicio,id_empleado=p_empleado,hora_inicio=p_hora,duracion_minutos=p_duracion,nombre_servicio_acordado=p_nombre,precio_lista=p_lista,monto_descuento_automatico=p_automatico,id_nivel=p_nivel,precio_acordado=p_acordado WHERE id=p_id;
        SET v_id=p_id;
    END IF;
    COMMIT;
    SELECT v_id AS id_servicio_cita;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE PROCEDURE `registrar_cobro_parcial`(IN p_cita BIGINT UNSIGNED, IN p_metodo BIGINT UNSIGNED, IN p_usuario BIGINT UNSIGNED, IN p_importe DECIMAL(12,2), IN p_referencia VARCHAR(255))
BEGIN
    DECLARE v_cliente BIGINT UNSIGNED;
    DECLARE v_estado VARCHAR(255);
    DECLARE v_pagado DECIMAL(12,2) DEFAULT 0;
    DECLARE v_total DECIMAL(12,2) DEFAULT 0;
    DECLARE v_tasa DECIMAL(12,2);
    DECLARE v_puntos INT;
    DECLARE v_cobro BIGINT UNSIGNED;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
    START TRANSACTION;
    SELECT id_cliente, estado INTO v_cliente, v_estado FROM citas WHERE id = p_cita FOR UPDATE;
    SELECT COALESCE(SUM(importe),0) INTO v_pagado FROM cobros WHERE id_cita = p_cita AND estado = 'registrado' FOR UPDATE;
    SELECT COALESCE(SUM(precio_acordado),0) INTO v_total FROM servicios_cita WHERE id_cita = p_cita AND estado_servicio = 'realizado' FOR SHARE;
    IF v_cliente IS NULL OR v_estado <> 'completada' OR p_importe <= 0 OR v_pagado+p_importe > v_total THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El pago excede el saldo o la cita no tiene una atencion completada.';
    END IF;
    IF NOT EXISTS(SELECT 1 FROM metodos_pago WHERE id = p_metodo AND activo = 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El metodo de pago esta inactivo.';
    END IF;
    SELECT monto_por_punto INTO v_tasa FROM configuracion_estudio WHERE id = 1 FOR SHARE;
    INSERT INTO cobros(id_cita,id_metodo_pago,id_usuario_creador,importe,fecha_cobro,referencia_pago,estado) VALUES(p_cita,p_metodo,p_usuario,p_importe,NOW(),p_referencia,'registrado');
    SET v_cobro = LAST_INSERT_ID();
    SET v_puntos = FLOOR((v_pagado+p_importe)/v_tasa)-FLOOR(v_pagado/v_tasa);
    INSERT INTO movimientos_puntos(id_cliente,id_cobro,id_usuario_creador,tipo,puntos,fecha_creacion) VALUES(v_cliente,v_cobro,p_usuario,'ganado',v_puntos,NOW());
    COMMIT;
    SELECT v_cobro AS id_cobro, v_puntos AS puntos;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE PROCEDURE `registrar_entrega_insumo`(IN p_empleado BIGINT UNSIGNED, IN p_insumo BIGINT UNSIGNED, IN p_cantidad INT UNSIGNED, IN p_usuario BIGINT UNSIGNED)
BEGIN
    DECLARE v_stock BIGINT;
    DECLARE v_asignacion BIGINT UNSIGNED;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
    START TRANSACTION;
    IF p_cantidad = 0 OR NOT EXISTS (SELECT 1 FROM usuarios u JOIN roles_usuario ru ON ru.id_usuario = u.id JOIN roles r ON r.id = ru.id_rol WHERE u.id = p_usuario AND u.activo = 1 AND r.codigo = 'administracion') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La entrega requiere cantidad positiva y un administrador activo.';
    END IF;
    SELECT existencias_actuales INTO v_stock FROM insumos WHERE id = p_insumo AND activo = 1 FOR UPDATE;
    IF v_stock IS NULL OR v_stock < p_cantidad THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No hay existencias suficientes para la entrega.';
    END IF;
    INSERT INTO asignaciones_insumos(id_empleado,id_insumo,cantidad_entregada,fecha_entrega,estado) VALUES(p_empleado,p_insumo,p_cantidad,NOW(),'en_uso');
    SET v_asignacion = LAST_INSERT_ID();
    INSERT INTO movimientos_inventario(id_insumo,id_usuario_creador,id_asignacion,tipo_movimiento,variacion_cantidad,existencias_antes,existencias_despues,motivo,fecha_creacion)
    VALUES(p_insumo,p_usuario,v_asignacion,'salida',-CAST(p_cantidad AS SIGNED),v_stock,v_stock-p_cantidad,'Entrega a empleado',NOW());
    UPDATE insumos SET existencias_actuales = v_stock-p_cantidad, fecha_actualizacion = NOW() WHERE id = p_insumo;
    COMMIT;
    SELECT v_asignacion AS id_asignacion;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE PROCEDURE `reprogramar_fecha_cita`(IN p_cita BIGINT UNSIGNED, IN p_fecha DATE, IN p_usuario BIGINT UNSIGNED, IN p_motivo TEXT)
BEGIN
    DECLARE v_fin INT DEFAULT 0;
    DECLARE v_empleado BIGINT UNSIGNED;
    DECLARE v_bloqueo BIGINT UNSIGNED;
    DECLARE v_conflicto BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_fecha DATE;
    DECLARE v_revision INT UNSIGNED;
    DECLARE v_estado VARCHAR(255);
    DECLARE profesionales CURSOR FOR SELECT DISTINCT id_empleado FROM servicios_cita WHERE id_cita=p_cita ORDER BY id_empleado;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_fin=1;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
    SET TRANSACTION ISOLATION LEVEL READ COMMITTED;
    START TRANSACTION;
    SELECT fecha,numero_revision,estado INTO v_fecha,v_revision,v_estado FROM citas WHERE id=p_cita FOR UPDATE;
    IF v_fecha IS NULL OR v_estado<>'confirmada' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Solo puede reprogramarse una cita confirmada.';
    END IF;
    OPEN profesionales;
    bucle: LOOP
        FETCH profesionales INTO v_empleado;
        IF v_fin=1 THEN LEAVE bucle; END IF;
        SELECT id INTO v_bloqueo FROM empleados WHERE id=v_empleado FOR UPDATE;
    END LOOP;
    CLOSE profesionales;
    SET v_fin=0;
    SELECT propio.id INTO v_conflicto FROM servicios_cita propio
    JOIN servicios_cita otro ON otro.id_empleado=propio.id_empleado AND otro.id_cita<>p_cita
    JOIN citas c ON c.id=otro.id_cita
    WHERE propio.id_cita=p_cita AND c.fecha=p_fecha AND c.estado='confirmada'
      AND propio.estado_servicio IN ('pendiente','en_proceso','realizado') AND otro.estado_servicio IN ('pendiente','en_proceso','realizado')
      AND TIME_TO_SEC(otro.hora_inicio)<TIME_TO_SEC(propio.hora_inicio)+propio.duracion_minutos*60
      AND TIME_TO_SEC(otro.hora_inicio)+otro.duracion_minutos*60>TIME_TO_SEC(propio.hora_inicio)
    LIMIT 1 FOR UPDATE;
    IF v_conflicto IS NOT NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La nueva fecha produce un conflicto de horarios.';
    END IF;
    UPDATE citas SET fecha=p_fecha,numero_revision=v_revision+1,fecha_actualizacion=NOW() WHERE id=p_cita;
    INSERT INTO historial_cita(id_cita,id_usuario_responsable,numero_revision,tipo_cambio,detalle_cambios,motivo,fecha_creacion)
    VALUES(p_cita,p_usuario,v_revision+1,'reprogramacion',JSON_OBJECT('fecha_anterior',v_fecha,'fecha_nueva',p_fecha),p_motivo,NOW());
    COMMIT;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE PROCEDURE `validar_horario_servicio`(
    IN p_id BIGINT UNSIGNED, IN p_cita BIGINT UNSIGNED, IN p_servicio BIGINT UNSIGNED,
    IN p_empleado BIGINT UNSIGNED, IN p_hora TIME, IN p_duracion INT, IN p_estado VARCHAR(255)
)
BEGIN
    DECLARE v_fecha DATE;
    DECLARE v_estado VARCHAR(255);
    DECLARE v_empleado BIGINT UNSIGNED;
    DECLARE v_conflicto BIGINT UNSIGNED DEFAULT NULL;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_conflicto = NULL;
    SELECT fecha, estado INTO v_fecha, v_estado FROM citas WHERE id = p_cita FOR SHARE;
    SELECT id INTO v_empleado FROM empleados WHERE id = p_empleado FOR UPDATE;
    IF v_estado = 'confirmada' AND p_estado IN ('pendiente','en_proceso','realizado') AND NOT EXISTS (SELECT 1 FROM servicios_por_empleado WHERE id_empleado = p_empleado AND id_servicio = p_servicio AND activo = 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El empleado no esta habilitado para el servicio.';
    END IF;
    IF v_estado = 'confirmada' AND p_estado IN ('pendiente','en_proceso','realizado') THEN
        SELECT s.id INTO v_conflicto FROM servicios_cita s JOIN citas c ON c.id = s.id_cita
        WHERE s.id <> p_id AND s.id_empleado = p_empleado AND c.fecha = v_fecha AND c.estado = 'confirmada'
          AND s.estado_servicio IN ('pendiente','en_proceso','realizado')
          AND TIME_TO_SEC(s.hora_inicio) < TIME_TO_SEC(p_hora) + p_duracion * 60
          AND TIME_TO_SEC(s.hora_inicio) + s.duracion_minutos * 60 > TIME_TO_SEC(p_hora)
        LIMIT 1;
        IF v_conflicto IS NOT NULL THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El empleado tiene otro servicio en el intervalo reservado.';
        END IF;
    END IF;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE PROCEDURE `validar_movimiento_asignado`(IN p_asignacion BIGINT UNSIGNED, IN p_insumo BIGINT UNSIGNED, IN p_tipo VARCHAR(255), IN p_cantidad INT)
BEGIN
    IF p_asignacion IS NOT NULL AND NOT EXISTS (SELECT 1 FROM asignaciones_insumos WHERE id = p_asignacion AND id_insumo = p_insumo AND p_tipo = 'salida' AND cantidad_entregada = -p_cantidad) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La salida debe corresponder al insumo y cantidad de la asignacion.';
    END IF;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE PROCEDURE `validar_vinculo_notificacion`(IN p_cita BIGINT UNSIGNED, IN p_historial BIGINT UNSIGNED, IN p_revision INT UNSIGNED)
BEGIN
    IF p_revision = 0 OR NOT EXISTS (SELECT 1 FROM citas WHERE id = p_cita AND numero_revision >= p_revision) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La revision de la notificacion no corresponde a la cita.';
    END IF;
    IF p_historial IS NOT NULL AND NOT EXISTS (SELECT 1 FROM historial_cita WHERE id = p_historial AND id_cita = p_cita AND numero_revision = p_revision) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El historial de la notificacion pertenece a otra cita o revision.';
    END IF;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

