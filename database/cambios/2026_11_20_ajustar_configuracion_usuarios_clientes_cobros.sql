-- Cambio exclusivo del esquema MySQL, con respaldo previo.
ALTER TABLE `configuracion_estudio` DROP COLUMN `zona_horaria`;
ALTER TABLE `usuarios` DROP COLUMN `token_recordatorio`;
ALTER TABLE `clientes` ADD COLUMN `activo` TINYINT(1) NOT NULL DEFAULT 1 AFTER `preferencias`;
ALTER TABLE `cobros` DROP COLUMN `referencia`;
