-- Operaciones de agenda con bloqueo y lectura actual para evitar carreras.
-- Se invocan fuera de otra transacción: administran su propio COMMIT/ROLLBACK.
DELIMITER $$
CREATE PROCEDURE guardar_servicio_reservado(
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
END$$

CREATE PROCEDURE reprogramar_fecha_cita(IN p_cita BIGINT UNSIGNED, IN p_fecha DATE, IN p_usuario BIGINT UNSIGNED, IN p_motivo TEXT)
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
END$$
DELIMITER ;
