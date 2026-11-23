-- Cambio exclusivo de base de datos. Aplicar con respaldo previo.
-- Conserva las asignaciones, movimientos y sus cantidades.

ALTER TABLE `movimientos_inventario`
    DROP INDEX `movimientos_inventario_clave_idempotencia_unique`,
    DROP COLUMN `clave_idempotencia`;

ALTER TABLE `asignaciones_insumos`
    DROP FOREIGN KEY `asignaciones_insumos_id_autor_agotamiento_foreign`,
    DROP FOREIGN KEY `asignaciones_insumos_id_autor_entrega_foreign`,
    DROP INDEX `asignaciones_insumos_id_autor_agotamiento_foreign`,
    DROP INDEX `asignaciones_insumos_id_autor_entrega_foreign`,
    DROP COLUMN `id_autor_agotamiento`,
    DROP COLUMN `id_autor_entrega`;
