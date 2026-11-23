-- Integridad del modelo de reservas con múltiples profesionales y pagos parciales.
-- Este archivo lo ejecuta la migración 2026_11_20_000200.
ALTER TABLE citas ADD CONSTRAINT citas_revision_positiva CHECK (numero_revision > 0);
ALTER TABLE historial_cita ADD CONSTRAINT historial_revision_positiva CHECK (numero_revision > 0);
ALTER TABLE asignaciones_insumos ADD CONSTRAINT asignaciones_cantidad_positiva CHECK (cantidad_entregada > 0);

DELIMITER $$
CREATE PROCEDURE validar_horario_servicio(
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
END$$

CREATE TRIGGER servicios_cita_validar_insertar BEFORE INSERT ON servicios_cita FOR EACH ROW
BEGIN
    CALL validar_horario_servicio(COALESCE(NEW.id, 0), NEW.id_cita, NEW.id_servicio, NEW.id_empleado, NEW.hora_inicio, NEW.duracion_minutos, NEW.estado_servicio);
END$$

CREATE TRIGGER servicios_cita_validar_actualizar BEFORE UPDATE ON servicios_cita FOR EACH ROW
BEGIN
    IF EXISTS(SELECT 1 FROM cobros WHERE id_cita=OLD.id_cita AND estado='registrado') AND
       (NEW.precio_lista<>OLD.precio_lista OR NEW.precio_acordado<>OLD.precio_acordado OR NEW.monto_descuento_automatico<>OLD.monto_descuento_automatico OR NOT(NEW.id_nivel<=>OLD.id_nivel) OR NEW.estado_servicio<>OLD.estado_servicio OR NEW.id_cita<>OLD.id_cita) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Los servicios cobrados conservan sus precios, descuentos y resultados.';
    END IF;
    IF NEW.id_cita<>OLD.id_cita OR NEW.id_servicio<>OLD.id_servicio OR NEW.id_empleado<>OLD.id_empleado OR NEW.hora_inicio<>OLD.hora_inicio OR NEW.duracion_minutos<>OLD.duracion_minutos OR NEW.estado_servicio<>OLD.estado_servicio THEN
        CALL validar_horario_servicio(OLD.id, NEW.id_cita, NEW.id_servicio, NEW.id_empleado, NEW.hora_inicio, NEW.duracion_minutos, NEW.estado_servicio);
    END IF;
END$$

CREATE TRIGGER citas_validar_actualizacion BEFORE UPDATE ON citas FOR EACH ROW
BEGIN
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
END$$

CREATE TRIGGER historial_cita_validar_insertar BEFORE INSERT ON historial_cita FOR EACH ROW
BEGIN
    IF NOT EXISTS (SELECT 1 FROM citas WHERE id = NEW.id_cita AND numero_revision = NEW.numero_revision) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La revision del historial debe coincidir con la revision actual de la cita.';
    END IF;
END$$

CREATE PROCEDURE validar_vinculo_notificacion(IN p_cita BIGINT UNSIGNED, IN p_historial BIGINT UNSIGNED, IN p_revision INT UNSIGNED)
BEGIN
    IF p_revision = 0 OR NOT EXISTS (SELECT 1 FROM citas WHERE id = p_cita AND numero_revision >= p_revision) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La revision de la notificacion no corresponde a la cita.';
    END IF;
    IF p_historial IS NOT NULL AND NOT EXISTS (SELECT 1 FROM historial_cita WHERE id = p_historial AND id_cita = p_cita AND numero_revision = p_revision) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El historial de la notificacion pertenece a otra cita o revision.';
    END IF;
END$$
CREATE TRIGGER notificaciones_validar_insertar BEFORE INSERT ON notificaciones FOR EACH ROW
BEGIN
    CALL validar_vinculo_notificacion(NEW.id_cita, NEW.id_historial_cita, NEW.revision_cita);
END$$
CREATE TRIGGER notificaciones_validar_actualizar BEFORE UPDATE ON notificaciones FOR EACH ROW
BEGIN
    CALL validar_vinculo_notificacion(NEW.id_cita, NEW.id_historial_cita, NEW.revision_cita);
END$$

CREATE TRIGGER puntos_validar_cliente BEFORE INSERT ON movimientos_puntos FOR EACH ROW
BEGIN
    IF NEW.id_cobro IS NOT NULL AND NOT EXISTS (SELECT 1 FROM cobros co JOIN citas c ON c.id = co.id_cita WHERE co.id = NEW.id_cobro AND c.id_cliente = NEW.id_cliente AND (NEW.tipo<>'ganado' OR co.estado='registrado') AND (NEW.tipo<>'reversion' OR co.estado='anulado')) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El cliente o estado de los puntos no corresponde al cobro.';
    END IF;
END$$

CREATE PROCEDURE validar_movimiento_asignado(IN p_asignacion BIGINT UNSIGNED, IN p_insumo BIGINT UNSIGNED, IN p_tipo VARCHAR(255), IN p_cantidad INT)
BEGIN
    IF p_asignacion IS NOT NULL AND NOT EXISTS (SELECT 1 FROM asignaciones_insumos WHERE id = p_asignacion AND id_insumo = p_insumo AND p_tipo = 'salida' AND cantidad_entregada = -p_cantidad) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La salida debe corresponder al insumo y cantidad de la asignacion.';
    END IF;
END$$
CREATE TRIGGER inventario_validar_insertar BEFORE INSERT ON movimientos_inventario FOR EACH ROW
BEGIN
    CALL validar_movimiento_asignado(NEW.id_asignacion, NEW.id_insumo, NEW.tipo_movimiento, NEW.variacion_cantidad);
END$$
CREATE TRIGGER inventario_validar_actualizar BEFORE UPDATE ON movimientos_inventario FOR EACH ROW
BEGIN
    CALL validar_movimiento_asignado(NEW.id_asignacion, NEW.id_insumo, NEW.tipo_movimiento, NEW.variacion_cantidad);
END$$
CREATE TRIGGER asignaciones_validar_actualizar BEFORE UPDATE ON asignaciones_insumos FOR EACH ROW
BEGIN
    IF EXISTS (SELECT 1 FROM movimientos_inventario WHERE id_asignacion = OLD.id AND (id_insumo <> NEW.id_insumo OR tipo_movimiento <> 'salida' OR variacion_cantidad <> -CAST(NEW.cantidad_entregada AS SIGNED))) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La asignacion no puede contradecir su movimiento de salida.';
    END IF;
END$$

-- Operación atómica que evita asignaciones sin salida y registra al responsable
-- únicamente en movimientos_inventario, como establece el modelo acordado.
CREATE PROCEDURE registrar_entrega_insumo(IN p_empleado BIGINT UNSIGNED, IN p_insumo BIGINT UNSIGNED, IN p_cantidad INT UNSIGNED, IN p_usuario BIGINT UNSIGNED)
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
END$$

-- Cobro parcial y puntos forman una única transacción. Se usa la diferencia
-- de los redondeos del acumulado anterior y posterior, no el de cada importe.
CREATE PROCEDURE registrar_cobro_parcial(IN p_cita BIGINT UNSIGNED, IN p_metodo BIGINT UNSIGNED, IN p_usuario BIGINT UNSIGNED, IN p_importe DECIMAL(12,2), IN p_referencia VARCHAR(255))
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
END$$

CREATE PROCEDURE anular_cobro_con_puntos(IN p_cobro BIGINT UNSIGNED, IN p_usuario BIGINT UNSIGNED, IN p_motivo TEXT)
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
END$$
DELIMITER ;
