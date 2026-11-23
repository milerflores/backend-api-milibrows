-- Fidelización por puntos. S/ 1 por punto; Glow 5%; Golden y VIP pendientes de configuración.
-- Conservar los precios históricos: precio_lista = precio_acordado,
-- descuento automático cero e id_nivel NULL en las citas anteriores.
-- Los sellos no se convierten a puntos ni se puntúan cobros retroactivamente.

ALTER TABLE `configuracion_estudio`
    ADD COLUMN `monto_por_punto` DECIMAL(12,2) NULL;
UPDATE `configuracion_estudio` SET `monto_por_punto` = 1.00;
ALTER TABLE `configuracion_estudio`
    MODIFY COLUMN `monto_por_punto` DECIMAL(12,2) NOT NULL,
    ADD CONSTRAINT `configuracion_monto_por_punto_positivo` CHECK (`monto_por_punto` > 0);

ALTER TABLE `niveles_fidelizacion`
    ADD COLUMN `nombre` VARCHAR(255) NULL AFTER `codigo`,
    ADD COLUMN `puntos_minimos` INT UNSIGNED NULL AFTER `orden`,
    ADD COLUMN `porcentaje_descuento` DECIMAL(5,2) NULL AFTER `puntos_minimos`,
    ADD COLUMN `activo` TINYINT(1) NOT NULL DEFAULT 1 AFTER `porcentaje_descuento`;

UPDATE `niveles_fidelizacion`
SET `codigo` = CASE `orden` WHEN 1 THEN 'glow' WHEN 2 THEN 'golden' WHEN 3 THEN 'vip' END,
    `nombre` = CASE `orden` WHEN 1 THEN 'Glow' WHEN 2 THEN 'Golden' WHEN 3 THEN 'VIP' END,
    `puntos_minimos` = CASE `orden` WHEN 1 THEN 0 WHEN 2 THEN 0 WHEN 3 THEN 0 END,
    `porcentaje_descuento` = CASE `orden` WHEN 1 THEN 5.00 WHEN 2 THEN 15.00 WHEN 3 THEN 25.00 END,
    `activo` = CASE `orden` WHEN 1 THEN 1 ELSE 0 END;

ALTER TABLE `niveles_fidelizacion`
    MODIFY COLUMN `nombre` VARCHAR(255) NOT NULL,
    MODIFY COLUMN `puntos_minimos` INT UNSIGNED NOT NULL,
    MODIFY COLUMN `porcentaje_descuento` DECIMAL(5,2) NOT NULL,
    DROP COLUMN `metrica`,
    DROP COLUMN `umbral`,
    DROP COLUMN `descripcion_beneficio`,
    ADD CONSTRAINT `niveles_porcentaje_valido` CHECK (`porcentaje_descuento` BETWEEN 0 AND 100),
    ADD CONSTRAINT `niveles_activo_valido` CHECK (`activo` IN (0, 1)),
    ADD CONSTRAINT `niveles_estructura_valida` CHECK (
        (`codigo` = 'glow' AND `orden` = 1 AND `puntos_minimos` = 0 AND `activo` = 1)
        OR (`codigo` = 'golden' AND `orden` = 2 AND (`puntos_minimos` > 0 OR (`activo` = 0 AND `puntos_minimos` = 0)))
        OR (`codigo` = 'vip' AND `orden` = 3 AND (`puntos_minimos` > 0 OR (`activo` = 0 AND `puntos_minimos` = 0)))
    );

ALTER TABLE `servicios_cita`
    ADD COLUMN `precio_lista` DECIMAL(12,2) NULL AFTER `nombre_servicio_acordado`,
    ADD COLUMN `monto_descuento_automatico` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `precio_lista`,
    ADD COLUMN `id_nivel` BIGINT UNSIGNED NULL AFTER `monto_descuento_automatico`;
UPDATE `servicios_cita` SET `precio_lista` = `precio_acordado`;
ALTER TABLE `servicios_cita`
    MODIFY COLUMN `precio_lista` DECIMAL(12,2) NOT NULL,
    ADD CONSTRAINT `servicios_cita_id_nivel_foreign` FOREIGN KEY (`id_nivel`) REFERENCES `niveles_fidelizacion` (`id`),
    ADD CONSTRAINT `servicios_cita_precios_validos` CHECK (
        `precio_lista` >= 0 AND `monto_descuento_automatico` >= 0
        AND `monto_descuento_automatico` <= `precio_lista`
        AND `precio_acordado` >= 0
        AND `precio_acordado` <= `precio_lista` - `monto_descuento_automatico`
    );

CREATE TABLE `movimientos_puntos` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_cliente` BIGINT UNSIGNED NOT NULL,
    `id_cobro` BIGINT UNSIGNED NULL,
    `id_usuario_creador` BIGINT UNSIGNED NOT NULL,
    `tipo` VARCHAR(255) NOT NULL,
    `puntos` INT NOT NULL,
    `motivo` TEXT NULL,
    `fecha_creacion` TIMESTAMP NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `movimientos_puntos_cliente_fecha` (`id_cliente`, `fecha_creacion`),
    UNIQUE INDEX `movimientos_puntos_cobro_origen_unico` (`id_cobro`, (CASE WHEN `tipo` IN ('ganado', 'reversion') THEN `tipo` ELSE NULL END)),
    CONSTRAINT `movimientos_puntos_id_cliente_foreign` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id`),
    CONSTRAINT `movimientos_puntos_id_cobro_foreign` FOREIGN KEY (`id_cobro`) REFERENCES `cobros` (`id`),
    CONSTRAINT `movimientos_puntos_id_usuario_creador_foreign` FOREIGN KEY (`id_usuario_creador`) REFERENCES `usuarios` (`id`),
    CONSTRAINT `movimientos_puntos_tipo_y_signo_validos` CHECK (
        (`tipo` = 'ganado' AND `id_cobro` IS NOT NULL AND `puntos` >= 0)
        OR (`tipo` = 'reversion' AND `id_cobro` IS NOT NULL AND `puntos` <= 0)
        OR (`tipo` = 'ajuste' AND `puntos` <> 0 AND `motivo` IS NOT NULL AND CHAR_LENGTH(TRIM(`motivo`)) > 0)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `motivos_descuento` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre` VARCHAR(255) NOT NULL,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    CONSTRAINT `motivos_descuento_activo_valido` CHECK (`activo` IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `descuentos_manuales_cita` (
    `id_servicio_cita` BIGINT UNSIGNED NOT NULL,
    `monto` DECIMAL(12,2) NOT NULL,
    `id_motivo_descuento` BIGINT UNSIGNED NULL,
    `nota` TEXT NULL,
    `id_usuario` BIGINT UNSIGNED NOT NULL,
    `fecha_creacion` TIMESTAMP NOT NULL,
    PRIMARY KEY (`id_servicio_cita`),
    CONSTRAINT `descuentos_manuales_servicio_foreign` FOREIGN KEY (`id_servicio_cita`) REFERENCES `servicios_cita` (`id`),
    CONSTRAINT `descuentos_manuales_motivo_foreign` FOREIGN KEY (`id_motivo_descuento`) REFERENCES `motivos_descuento` (`id`),
    CONSTRAINT `descuentos_manuales_usuario_foreign` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`),
    CONSTRAINT `descuentos_manuales_monto_valido` CHECK (`monto` >= 0),
    CONSTRAINT `descuentos_manuales_motivo_o_nota` CHECK (
        `id_motivo_descuento` IS NOT NULL OR (`nota` IS NOT NULL AND CHAR_LENGTH(TRIM(`nota`)) > 0)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historial de puntos inmutable: toda corrección requiere un movimiento nuevo.
CREATE TRIGGER `movimientos_puntos_no_actualizar`
BEFORE UPDATE ON `movimientos_puntos` FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Los movimientos de puntos no se editan. Registre un movimiento de ajuste.';

CREATE TRIGGER `movimientos_puntos_no_eliminar`
BEFORE DELETE ON `movimientos_puntos` FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Los movimientos de puntos no se eliminan. Registre una reversion o ajuste.';

DELIMITER $$
CREATE TRIGGER `niveles_no_eliminar_glow`
BEFORE DELETE ON `niveles_fidelizacion` FOR EACH ROW
BEGIN
    IF OLD.codigo = 'glow' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Glow es el nivel inicial y no se puede eliminar.';
    END IF;
END$$
DELIMITER ;

DROP TABLE `servicios_por_programa`;
DROP TABLE `sellos_obtenidos`;
DROP TABLE `recompensas_sellos`;
DROP TABLE `programas_sellos`;
