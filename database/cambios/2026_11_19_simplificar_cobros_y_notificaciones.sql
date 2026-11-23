-- Cambio exclusivo del esquema MySQL de MiliBrows.
-- Aplicar con respaldo previo. El backend se adapta en una etapa posterior.
-- MySQL confirma cada DDL de forma independiente.

ALTER TABLE `cobros`
    DROP FOREIGN KEY `cobros_id_turno_caja_foreign`,
    DROP INDEX `cobros_id_turno_caja_foreign`,
    DROP INDEX `cobros_clave_idempotencia_unique`,
    DROP COLUMN `id_turno_caja`,
    DROP COLUMN `clave_idempotencia`;

DROP TABLE `turnos_caja`;
DROP TABLE `cajas`;
DROP TABLE `intentos_notificacion`;
