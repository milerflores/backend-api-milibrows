-- Esquema exclusivamente de base de datos. Requiere respaldo y validación
-- de que las cantidades sean enteras y quepan en los nuevos tipos.
ALTER TABLE `insumos`
    DROP FOREIGN KEY `insumos_id_unidad_medida_foreign`,
    DROP INDEX `insumos_id_unidad_medida_foreign`,
    DROP COLUMN `id_unidad_medida`,
    MODIFY COLUMN `existencias_actuales` INT UNSIGNED NOT NULL DEFAULT 0,
    MODIFY COLUMN `existencias_minimas` INT UNSIGNED NOT NULL DEFAULT 0;

ALTER TABLE `asignaciones_insumos`
    MODIFY COLUMN `cantidad_entregada` INT UNSIGNED NOT NULL;

ALTER TABLE `movimientos_inventario`
    MODIFY COLUMN `variacion_cantidad` INT NOT NULL,
    MODIFY COLUMN `existencias_antes` INT NOT NULL,
    MODIFY COLUMN `existencias_despues` INT NOT NULL;

DROP TABLE `unidades_medida`;
