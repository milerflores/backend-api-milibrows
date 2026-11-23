-- Los movimientos de puntos ya emitidos impiden reasignar o cambiar
-- el importe de su cobro. Las correcciones se realizan mediante anulación.
DELIMITER $$
CREATE TRIGGER cobros_con_puntos_inmutables BEFORE UPDATE ON cobros FOR EACH ROW
BEGIN
    IF (NEW.id_cita<>OLD.id_cita OR NEW.importe<>OLD.importe) AND EXISTS(SELECT 1 FROM movimientos_puntos WHERE id_cobro=OLD.id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El cobro tiene puntos emitidos; anule el pago en lugar de modificar su origen o importe.';
    END IF;
END$$
DELIMITER ;
