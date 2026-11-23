// Regenerar: node scripts/generar-openapi.cjs (sin dependencias npm).
const fs = require('node:fs');
const path = require('node:path');
const ref = n => ({$ref:`#/components/schemas/${n}`});
const str = (extra={}) => ({type:'string',...extra});
const integer = (extra={}) => ({type:'integer',minimum:1,...extra});
const bool = {type:'boolean'};
const nullable = s => ({anyOf:[s,{type:'null'}]});
const arr = s => ({type:'array',items:s});
const obj = (properties,required=[]) => ({type:'object',properties,...(required.length?{required}:{})});
const date = str({format:'date-time'}), day = str({format:'date'}), hora = str({pattern:'^\\d{2}:\\d{2}$',examples:['10:00']});
const email = str({format:'email',maxLength:255});
const text = str({maxLength:2000}), name = str({maxLength:255}), shortName = str({maxLength:100});
const money = str({pattern:'^\\d+\\.\\d{2}$',examples:['60.00']});
const amount = {anyOf:[{type:'number',minimum:0,maximum:9999999999.99},str({pattern:'^\\d+(\\.\\d{1,2})?$'})]};
const ids = {type:'array',items:integer(),minItems:1,maxItems:20,uniqueItems:true};
const role = str({enum:['administracion','recepcion','estilista','cliente']});
const status = str({enum:['confirmada','completada','cancelada','no_asistio']});
const estadoServicio = str({enum:['pendiente','en_proceso','realizado','cancelado','no_realizado']});
const password = str({minLength:10,writeOnly:true,description:'Debe incluir mayúsculas, minúsculas y números.'});
const stamp = {id:integer(),fecha_creacion:date,fecha_actualizacion:date};
const schemas = {
 Error:obj({mensaje:str(),errores:{type:'object',additionalProperties:arr(str())}},['mensaje']),
 Mensaje:obj({mensaje:str()},['mensaje']),
 Meta:obj({pagina:integer(),total:integer({minimum:0}),ultima_pagina:integer()}),
 Persona:obj({...stamp,dni:nullable(str({pattern:'^\\d{8}$'})),nombre:shortName,apellido_paterno:shortName,apellido_materno:nullable(shortName),nombre_completo:name,telefono:nullable(str({maxLength:30})),correo_contacto:nullable(email)}),
 Perfil:obj({id:integer(),correo:email,activo:bool,roles:arr(role),persona:nullable(ref('Persona')),id_cliente:nullable(integer()),id_empleado:nullable(integer()),segundo_factor_configurado:bool}),
 Credenciales:obj({correo:email,contrasena:str({writeOnly:true})},['correo','contrasena']),
 Registro:obj({nombre:shortName,apellido_paterno:shortName,apellido_materno:nullable(shortName),dni:str({pattern:'^\\d{8}$'}),telefono:nullable(str({maxLength:30})),correo:email,contrasena:password,contrasena_confirmacion:password},['nombre','apellido_paterno','dni','correo','contrasena','contrasena_confirmacion']),
 Restablecimiento:obj({correo:email,token:str(),contrasena:password,contrasena_confirmacion:password},['correo','token','contrasena','contrasena_confirmacion']),
 TokenSolicitud:obj({correo:email,contrasena:str({writeOnly:true}),nombre_dispositivo:str({maxLength:100})},['correo','contrasena','nombre_dispositivo']),
 Token:obj({token:str(),tipo:str({const:'Bearer'}),perfil:ref('Perfil')}),
 Servicio:obj({...stamp,nombre:name,descripcion:nullable(str({maxLength:5000})),imagen_url:nullable(str({format:'uri-reference'})),precio:money,duracion_minutos:integer({maximum:720}),activo:bool,visible_al_publico:bool}),
 ServicioSolicitud:obj({nombre:name,descripcion:nullable(str({maxLength:5000})),eliminar_imagen:bool,precio:amount,duracion_minutos:integer({maximum:720}),activo:bool,visible_al_publico:bool},['nombre','precio','duracion_minutos']),
 Disponibilidad:obj({id_empleado:integer(),nombre_completo:name,duracion_minutos:integer(),intervalos:arr(obj({hora_inicio:hora,hora_fin:hora,fecha_inicio:date,fecha_fin:date}))}),
 ServicioReserva:obj({id_servicio:integer(),id_empleado:integer(),hora_inicio:hora},['id_servicio','id_empleado','hora_inicio']),
 Reserva:obj({fecha:day,servicios:{type:'array',items:ref('ServicioReserva'),minItems:1,maxItems:20},id_cliente:integer({description:'Solo Administración o Recepción. Obligatorio para reservas del personal; prohibido para clientes.'})},['fecha','servicios']),
 ReprogramacionServicio:obj({id_servicio_cita:integer(),id_empleado:integer(),hora_inicio:hora},['id_servicio_cita']),
 Reprogramacion:obj({fecha:day,servicios:arr(ref('ReprogramacionServicio')),motivo:nullable(text)},['fecha']),
 ResultadoServicio:obj({id_servicio_cita:integer(),estado_servicio:str({enum:['realizado','cancelado','no_realizado']}),motivo_estado_servicio:nullable(text)},['id_servicio_cita','estado_servicio']),
 DescuentoManual:obj({monto:money,id_motivo_descuento:nullable(integer()),motivo:nullable(name),nota:nullable(text),fecha_creacion:date}),
 DescuentoManualSolicitud:obj({monto:amount,id_motivo_descuento:nullable(integer()),nota:nullable(text)},['monto']),
 ServicioCita:obj({id:integer(),id_cita:integer(),id_servicio:integer(),id_empleado:integer(),estilista:nullable(name),id_nivel:nullable(integer()),nombre_servicio_acordado:name,precio_lista:money,monto_descuento_automatico:money,precio_acordado:money,duracion_minutos:integer(),hora_inicio:hora,fecha_inicio_real:nullable(date),fecha_finalizacion:nullable(date),estado_servicio:estadoServicio,motivo_estado_servicio:nullable(text),descuento_manual:nullable(ref('DescuentoManual'))}),
 Nota:obj({descripcion_tratamiento:nullable(str({maxLength:5000})),observaciones:nullable(str({maxLength:5000}))}),
 HistorialCita:obj({id:integer(),id_cita:integer(),id_usuario_responsable:integer(),numero_revision:integer(),tipo_cambio:str(),detalle_cambios:{type:'object'},motivo:nullable(text),fecha_creacion:date}),
 CobroResumen:obj({id:integer(),id_metodo_pago:integer(),metodo:nullable(name),importe:money,fecha_cobro:date,referencia_pago:nullable(name),estado:str({enum:['registrado','anulado']})}),
 Cita:obj({id:integer(),id_cliente:integer(),fecha:day,fecha_inicio:nullable(date),fecha_fin:nullable(date),fecha_finalizacion:nullable(date),origen:str(),estado:status,numero_revision:integer(),motivo_cancelacion:nullable(text),servicios:arr(ref('ServicioCita')),cobros:arr(ref('CobroResumen')),total_realizado:money,total_pagado:money,saldo_pendiente:money,cliente:ref('Persona'),nota:nullable(ref('Nota')),historial:arr(ref('HistorialCita'))}),
 HistorialServicio:obj({id:integer(),id_cita:integer(),id_servicio:integer(),id_empleado:integer(),nombre_servicio_acordado:name,precio_lista:money,monto_descuento_automatico:money,precio_acordado:money,duracion_minutos:integer(),hora_inicio:str(),estado_servicio:str({const:'realizado'}),cita:obj({id:integer(),id_cliente:integer(),fecha:day,estado:status,fecha_finalizacion:nullable(date)})}),
 Cliente:obj({...stamp,id_persona:integer(),preferencias:nullable(text),activo:bool,persona:ref('Persona')}),
 ClienteSolicitud:obj({dni:nullable(str({pattern:'^\\d{8}$'})),nombre:shortName,apellido_paterno:shortName,apellido_materno:nullable(shortName),telefono:nullable(str({maxLength:30})),correo_contacto:nullable(email),preferencias:nullable(text),activo:bool},['nombre','apellido_paterno']),
 Contacto:obj({nombre:shortName,apellido_paterno:shortName,apellido_materno:nullable(shortName),telefono:nullable(str({maxLength:30})),correo_contacto:nullable(email),preferencias:nullable(text)}),
 Tramo:obj({dia_semana:integer({maximum:7,description:'ISO: lunes=1; domingo=7.'}),hora_inicio:hora,hora_fin:hora},['dia_semana','hora_inicio','hora_fin']),
 Horario:obj({id:integer(),id_estudio:integer(),dia_semana:integer({maximum:7}),hora_inicio:str({examples:['10:00:00']}),hora_fin:str({examples:['22:00:00']})}),
 Jornada:obj({id:integer(),id_empleado:integer(),dia_semana:integer({maximum:7}),hora_inicio:str({examples:['10:00:00']}),hora_fin:str({examples:['22:00:00']})}),
 Empleado:obj({id:integer(),cargo:name,activo:bool,admite_reservas:bool,cuenta:ref('Perfil'),jornada:arr(ref('Jornada')),servicios:arr(ref('Servicio')),asignaciones:arr(ref('Asignacion'))}),
 EmpleadoSolicitud:obj({nombre:shortName,apellido_paterno:shortName,apellido_materno:nullable(shortName),dni:nullable(str({pattern:'^\\d{8}$'})),telefono:nullable(str({maxLength:30})),correo:email,rol:str({enum:['administracion','recepcion','estilista']}),cargo:name,admite_reservas:bool,contrasena:password},['nombre','apellido_paterno','correo','rol','cargo','contrasena']),
 EmpleadoActualizacion:obj({nombre:shortName,apellido_paterno:shortName,apellido_materno:nullable(shortName),telefono:nullable(str({maxLength:30})),correo_contacto:nullable(email),cargo:name,activo:bool,admite_reservas:bool}),
 CuentaActualizacion:obj({correo:email,rol:str({enum:['administracion','recepcion','estilista']}),activo:bool}),
 MetodoPago:obj({id:integer(),codigo:str({enum:['efectivo','yape','plin','tarjeta','transferencia']}),nombre:name,activo:bool}),
 Cobro:obj({id:integer(),id_cita:integer(),id_metodo_pago:integer(),id_usuario_creador:integer(),importe:money,fecha_cobro:date,estado:str({enum:['registrado','anulado']}),referencia_pago:nullable(name),metodo:ref('MetodoPago')}),
 CobroSolicitud:obj({id_cita:integer(),id_metodo_pago:integer(),importe:amount,referencia_pago:nullable(name)},['id_cita','id_metodo_pago','importe']),
 Ingresos:obj({total:money,cantidad:integer({minimum:0}),por_metodo:arr(obj({id_metodo_pago:integer(),total:money,cantidad:integer({minimum:0})})),por_fecha:arr(obj({fecha:day,total:money}))}),
 CategoriaInventario:obj({id:integer(),nombre:name}),
 Insumo:obj({...stamp,id_categoria:integer(),nombre:name,existencias_actuales:integer({minimum:0}),existencias_minimas:integer({minimum:0}),activo:bool,categoria:ref('CategoriaInventario')}),
 InsumoSolicitud:obj({id_categoria:integer(),nombre:name,existencias_minimas:integer({minimum:0}),activo:bool},['nombre','id_categoria']),
 Movimiento:obj({id:integer(),id_insumo:integer(),id_usuario_creador:integer(),id_asignacion:nullable(integer()),tipo_movimiento:str({enum:['entrada','salida','devolucion','ajuste']}),variacion_cantidad:{type:'integer'},existencias_antes:integer({minimum:0}),existencias_despues:integer({minimum:0}),motivo:text,fecha_creacion:date}),
 MovimientoSolicitud:obj({id_insumo:integer(),tipo_movimiento:str({enum:['entrada','salida','devolucion','ajuste']}),variacion_cantidad:{type:'integer'},motivo:text},['id_insumo','tipo_movimiento','variacion_cantidad','motivo']),
 Asignacion:obj({id:integer(),id_empleado:integer(),id_insumo:integer(),cantidad_entregada:integer(),fecha_entrega:date,estado:str({enum:['en_uso','agotado']}),fecha_agotamiento:nullable(date),insumo:ref('Insumo'),empleado:obj({id:integer(),nombre_completo:name})}),
 AsignacionSolicitud:obj({id_empleado:integer(),id_insumo:integer(),cantidad_entregada:integer()},['id_empleado','id_insumo','cantidad_entregada']),
 Nivel:obj({id:integer(),codigo:str({enum:['glow','golden','vip']}),nombre:name,orden:integer(),puntos_minimos:integer({minimum:0}),porcentaje_descuento:money,activo:bool}),
 NivelSolicitud:obj({codigo:str({enum:['glow','golden','vip']}),nombre:name,puntos_minimos:integer({minimum:0}),porcentaje_descuento:amount,activo:bool},['codigo','nombre','puntos_minimos','porcentaje_descuento','activo']),
 MotivoDescuento:obj({id:integer(),nombre:name,activo:bool}),
 MotivoDescuentoSolicitud:obj({nombre:name,activo:bool},['nombre']),
 MovimientoPuntos:obj({id:integer(),id_cliente:integer(),id_cobro:nullable(integer()),id_usuario_creador:integer(),tipo:str({enum:['ganado','reversion','ajuste']}),puntos:{type:'integer'},motivo:nullable(text),fecha_creacion:date}),
 AjustePuntosSolicitud:obj({id_cliente:integer(),puntos:{type:'integer'},motivo:text},['id_cliente','puntos','motivo']),
 Fidelizacion:obj({puntos:{type:'integer'},monto_por_punto:money,nivel:ref('Nivel'),siguiente_nivel:nullable(obj({id:integer(),codigo:str(),nombre:name,puntos_minimos:integer({minimum:0}),porcentaje_descuento:money,puntos_faltantes:integer({minimum:0})})),servicios_realizados:integer({minimum:0}),visitas:integer({minimum:0}),monto_pagado:money,movimientos:arr(ref('MovimientoPuntos'))}),
 ConfiguracionEstudio:obj({id:integer(),nombre_estudio:name,canal_notificacion:nullable(str({enum:['correo']})),anticipacion_recordatorio_min:integer({maximum:10080}),monto_por_punto:money}),
 Notificaciones:obj({canal_notificacion:nullable(str({enum:['correo']})),anticipacion_recordatorio_min:integer({maximum:10080})},['canal_notificacion','anticipacion_recordatorio_min']),
 Resumen:obj({citas:integer({minimum:0}),estados:arr(obj({estado:status,cantidad:integer({minimum:0})})),servicios_realizados:integer({minimum:0}),visitas:integer({minimum:0}),ingresos:money,alertas_reposicion:arr(ref('Insumo'))}),
};
const paths = {};
const shared = [{sesionWeb:[]},{tokenMovil:[]}], web = [{sesionWeb:[]}];
const permisos = {
 publico:'Público.',
 cliente:'Cliente; registros propios.',
 compartido:'Cliente: citas propias. Estilista: citas con servicios asignados. Administración y Recepción: agenda general.',
 admin:'Administración, sesión web con MFA configurado y verificado en la sesión actual.',
 recepcion:'Administración con MFA o Recepción, mediante sesión web.',
 personal:'Administración, Recepción o Estilista. MFA obligatorio para Administración y para quien lo haya confirmado.',
};
function response(s,description='Operación correcta') { return {description,content:{'application/json':{schema:s}}}; }
function wrapped(s,extra={}) { return obj({datos:s,...extra},['datos']); }
function op(method,pathUrl,summary,options={}) {
    const {request,result=ref('Mensaje'),list=false,code=200,access='admin',session=true,description='',query=[],extra={}}=options;
    const success = code===204 ? {description:'Operación correcta sin cuerpo.'} : response(list ? wrapped(arr(result),{meta:ref('Meta'),...extra}) : wrapped(result,extra));
    const responses = {[code]:success};
    for(const [n,d] of Object.entries({'401':'Sesión o token ausente, inválido o expirado.','403':'Rol, propietario, acceso activo, MFA o tipo de cliente no autorizado.','404':'Registro inexistente.','409':'Conflicto de estado, intervalo ocupado, duplicado o stock insuficiente.','419':'CSRF inválido o expirado en una petición web.','422':'Validación de los datos.','429':'Demasiados intentos.','500':'Error interno sin detalles sensibles.'})) responses[n]=response(ref('Error'),d);
    let parameters = [];
    for (const m of pathUrl.matchAll(/\{([^}]+)\}/g)) {
        parameters.push({name:m[1],in:'path',required:true,schema:integer()});
    }
    parameters.push(...query.map(([name,schema,desc])=>({name,in:'query',required:name==='fecha'||name==='ids_servicios[]',schema,description:desc||'',...(name==='ids_servicios[]'?{style:'form',explode:true}:{})})));
    if(session && !['get','head'].includes(method)) parameters.push({name:'X-XSRF-TOKEN',in:'header',required:false,schema:str(),description:'Valor vigente de la cookie XSRF-TOKEN para escrituras por sesión web.'});
    if(list) parameters.push({name:'pagina',in:'query',schema:integer(),description:'Página de 30 registros.'});
    const value={operationId:method+'_'+pathUrl.replace(/[{}]/g,'').replace(/[^a-z0-9]/g,'_'),summary,tags:[pathUrl.split('/')[1]],description:(permisos[access]||access)+(description?' '+description:''),security:access==='publico'?[]:(session?web:shared),'x-permisos':permisos[access]||access,responses};
    if(parameters.length)value.parameters=parameters;
    if(request)value.requestBody={required:true,content:{'application/json':{schema:request}}};
    paths[pathUrl]??={}; paths[pathUrl][method]=value; return value;
}
const partial = s => {const p=structuredClone(s);delete p.required;return p;};
const dates=[['fecha_desde',day],['fecha_hasta',day]];
const citasQuery=[...dates,['estado',status],['id_empleado',integer()],['id_cliente',integer()]];
const msg=description=>response(ref('Mensaje'),description);

op('get','/acceso/cookie-csrf','Obtener cookies CSRF y de sesión',{code:204,access:'publico',description:'Sanctum: enviar cookies con credentials e incluir X-XSRF-TOKEN en escrituras web posteriores.'});
const login=op('post','/acceso/iniciar-sesion','Iniciar sesión por cookie',{access:'publico',request:ref('Credenciales'),description:'Regenera sesión. Permite configurar/verificar MFA; el panel permanece bloqueado hasta verificarlo.'});
login.responses['200']=response(obj({datos:ref('Perfil'),requiere_segundo_factor:bool},['datos','requiere_segundo_factor']));
op('post','/acceso/cerrar-sesion','Cerrar e invalidar la sesión web',{access:'Cuenta con sesión web; permite cerrar aunque falte MFA.',code:204});
op('post','/acceso/registro','Registrar cliente y vincular ficha libre por DNI',{access:'publico',request:ref('Registro'),result:ref('Perfil'),code:201});
op('post','/acceso/solicitar-restablecimiento','Solicitar enlace de un solo uso',{access:'publico',request:obj({correo:email},['correo'])}).responses['200']=msg('Respuesta uniforme para evitar enumeración de correos.');
op('post','/acceso/restablecer-contrasena','Restablecer contraseña y revocar accesos previos',{access:'publico',request:ref('Restablecimiento')}).responses['200']=msg('Contraseña restablecida.');
op('post','/acceso/token-movil','Emitir token personal para cliente activo',{access:'publico',request:ref('TokenSolicitud'),result:ref('Token'),code:201});
op('delete','/acceso/token-movil','Revocar el token móvil actual',{access:'cliente',session:false,code:204}).security=[{tokenMovil:[]}];
op('post','/acceso/segundo-factor/activar','Preparar secreto TOTP y códigos cifrados',{access:'personal',request:obj({contrasena:str({writeOnly:true})},['contrasena'])}).responses['200']=msg('Escanee el QR y confirme un TOTP.');
op('get','/acceso/segundo-factor/qr','Obtener QR SVG para configurar TOTP',{access:'personal',result:obj({svg:str()})});
op('post','/acceso/segundo-factor/confirmar','Confirmar TOTP y obtener códigos de recuperación',{access:'personal',request:obj({codigo:str({pattern:'^\\d{6}$'})},['codigo']),result:obj({codigos_recuperacion:arr(str())})});
op('post','/acceso/segundo-factor/verificar','Verificar el segundo factor en una nueva sesión',{access:'personal',request:{...obj({codigo:str({pattern:'^\\d{6}$'}),codigo_recuperacion:str({maxLength:100})}),anyOf:[{required:['codigo']},{required:['codigo_recuperacion']}]}}).responses['200']=msg('Segundo factor verificado.');

op('get','/servicios','Consultar catálogo público',{access:'publico',result:arr(ref('Servicio'))});
const imagenServicio=op('get','/servicios/{id}/imagen','Consultar imagen del servicio',{access:'publico',session:false});
imagenServicio.responses['200']={description:'Imagen del servicio.',content:Object.fromEntries(['image/jpeg','image/png','image/webp'].map(type=>[type,{schema:str({format:'binary'})}]))};
op('get','/disponibilidad','Consultar estilistas e intervalos libres',{access:'publico',result:arr(ref('Disponibilidad')),query:[['fecha',day],['ids_servicios[]',ids],['id_empleado',integer()]]});

op('post','/citas','Crear una cita confirmada (soporta múltiples especialistas)',{access:'Cliente, Administración con MFA o Recepción.',session:false,request:ref('Reserva'),result:ref('Cita'),code:201});
op('get','/citas','Consultar agenda según rol y registro',{access:'compartido',session:false,list:true,result:ref('Cita'),query:citasQuery});
op('get','/citas/{id}','Consultar cita, servicios, descuentos y cobros',{access:'compartido',session:false,result:ref('Cita')});
op('get','/citas/{id}/disponibilidad','Consultar disponibilidad de reprogramación',{access:'Cliente propietario; Administración con MFA o Recepción.',session:false,result:arr(ref('Disponibilidad')),query:[['fecha',day],['id_empleado',integer()],['id_servicio_cita',integer()]]});
op('post','/citas/{id}/reprogramar','Reprogramar cita confirmada',{access:'Cliente propietario; Administración con MFA o Recepción.',session:false,request:ref('Reprogramacion'),result:ref('Cita')});
op('post','/citas/{id}/cancelar','Cancelar cita confirmada',{access:'compartido',session:false,request:obj({motivo:text}),result:ref('Cita')});
op('post','/citas/{id}/completar','Registrar resultado de todos los servicios y completar cita',{access:'Administración con MFA, Recepción o Estilista asignada; sesión web.',request:obj({servicios:arr(ref('ResultadoServicio')),nota:ref('Nota')},['servicios']),result:ref('Cita')});
op('post','/citas/{id}/marcar-inasistencia','Marcar inasistencia desde la hora de inicio',{access:'Administración con MFA, Recepción o Estilista asignada; sesión web.',result:ref('Cita')});
op('patch','/citas/{id}/servicios/{idServicioCita}','Actualizar estado individual de un servicio de la cita',{access:'Administración con MFA, Recepción o Estilista asignada; sesión web.',request:obj({estado_servicio:str({enum:['en_proceso','realizado','cancelado','no_realizado']}),motivo_estado_servicio:nullable(text)},['estado_servicio']),result:ref('Cita')});
op('put','/citas/{id}/servicios/{idServicioCita}/descuento-manual','Aplicar o actualizar descuento manual antes del cobro',{access:'recepcion',request:ref('DescuentoManualSolicitud'),result:ref('Cita')});
op('delete','/citas/{id}/servicios/{idServicioCita}/descuento-manual','Eliminar descuento manual antes del cobro',{access:'recepcion',result:ref('Cita')});

op('get','/mi-perfil','Consultar identidad y contacto autenticados',{access:'Cuenta activa autenticada.',session:false,result:ref('Perfil')});
op('patch','/mi-perfil','Actualizar contacto y preferencias',{access:'cliente',session:false,request:ref('Contacto'),result:ref('Perfil')});
op('get','/mi-perfil/historial','Consultar servicios realizados propios',{access:'cliente',session:false,result:ref('HistorialServicio'),list:true});
op('get','/mi-perfil/pagos','Consultar pagos registrados propios',{access:'cliente',session:false,result:ref('Cobro'),list:true});
op('get','/mi-perfil/fidelizacion','Consultar puntos, nivel, descuento y movimientos',{access:'cliente',session:false,result:ref('Fidelizacion')});

op('get','/empleados','Listar personal y configuración',{result:ref('Empleado'),list:true});
op('post','/empleados','Crear cuenta y perfil del personal',{request:ref('EmpleadoSolicitud'),result:ref('Empleado'),code:201});
op('get','/empleados/{id}','Consultar perfil e insumos asignados',{result:ref('Empleado')});
op('patch','/empleados/{id}','Actualizar perfil del empleado',{request:ref('EmpleadoActualizacion'),result:ref('Empleado')});
op('patch','/empleados/{id}/cuenta','Cambiar correo, rol o acceso',{request:ref('CuentaActualizacion'),result:ref('Perfil')});
op('put','/empleados/{id}/cuenta/contrasena','Establecer nueva contraseña inicial',{request:obj({contrasena:password},['contrasena']),code:204});
op('put','/empleados/{id}/jornada','Reemplazar jornada semanal',{request:obj({jornada:arr(ref('Tramo'))},['jornada']),result:arr(ref('Jornada'))});
op('put','/empleados/{id}/servicios','Reemplazar servicios habilitados',{request:obj({ids_servicios:{...ids,minItems:0,maxItems:100}},['ids_servicios']),result:arr(ref('Servicio'))});
op('get','/empleados/{id}/atenciones','Consultar atenciones completadas de una estilista',{list:true,result:ref('Cita'),query:dates});

op('get','/clientes','Listar fichas de clientes',{access:'recepcion',list:true,result:ref('Cliente'),query:[['buscar',str({maxLength:100})],['activo',bool]]});
op('post','/clientes','Crear ficha sin cuenta',{access:'recepcion',request:ref('ClienteSolicitud'),result:ref('Cliente'),code:201});
op('get','/clientes/{id}','Consultar ficha',{access:'recepcion',result:ref('Cliente')});
op('patch','/clientes/{id}','Actualizar ficha de cliente',{access:'recepcion',request:partial(schemas.ClienteSolicitud),result:ref('Cliente')});
op('get','/clientes/{id}/historial','Consultar servicios realizados de una ficha',{access:'recepcion',list:true,result:ref('HistorialServicio')});
op('get','/clientes/{id}/fidelizacion','Consultar puntos y nivel de un cliente',{access:'recepcion',result:ref('Fidelizacion')});

op('get','/administracion/servicios','Listar catálogo interno',{list:true,result:ref('Servicio')});
op('post','/administracion/servicios','Crear servicio',{request:ref('ServicioSolicitud'),result:ref('Servicio'),code:201});
op('patch','/administracion/servicios/{id}','Actualizar servicio',{request:partial(schemas.ServicioSolicitud),result:ref('Servicio')});
for(const [method,pathUrl] of [['post','/administracion/servicios'],['patch','/administracion/servicios/{id}']]) {
 const schema=structuredClone(method==='patch'?partial(schemas.ServicioSolicitud):schemas.ServicioSolicitud);
 schema.properties.imagen=str({format:'binary',description:'JPEG, PNG o WebP; máximo 2048 KiB.'});
 for(const field of ['activo','visible_al_publico','eliminar_imagen']) schema.properties[field]=str({enum:['0','1']});
 if(method==='patch') schema.properties._method=str({const:'PATCH'});
 paths[pathUrl][method].requestBody.content['multipart/form-data']={schema};
}
op('get','/configuracion/estudio','Consultar configuración general del estudio',{result:ref('ConfiguracionEstudio')});
op('put','/configuracion/estudio','Actualizar configuración general del estudio',{request:partial(schemas.ConfiguracionEstudio),result:ref('ConfiguracionEstudio')});
op('get','/configuracion/horario-estudio','Consultar horario semanal',{result:arr(ref('Horario'))});
op('put','/configuracion/horario-estudio','Reemplazar horario semanal',{request:obj({horario:arr(ref('Tramo'))},['horario']),result:arr(ref('Horario'))});
op('get','/configuracion/notificaciones','Consultar canal y anticipación de avisos',{result:ref('Notificaciones')});
op('put','/configuracion/notificaciones','Configurar avisos por correo',{request:ref('Notificaciones'),result:ref('Notificaciones')});
op('get','/administracion/resumen','Consultar operación, ingresos y alertas',{result:ref('Resumen'),query:dates});

op('get','/caja/metodos-pago','Listar métodos de pago activos',{access:'recepcion',result:arr(ref('MetodoPago'))});
const cajaQuery=[...dates,['id_cliente',integer()],['id_cita',integer()],['id_metodo_pago',integer()],['estado',str({enum:['registrado','anulado']})]];
op('get','/caja/cobros','Listar cobros registrados y anulados',{access:'recepcion',list:true,result:ref('Cobro'),query:cajaQuery,extra:{metodos_pago:arr(ref('MetodoPago'))}});
op('post','/caja/cobros','Registrar cobro total o parcial y otorgar puntos',{access:'recepcion',request:ref('CobroSolicitud'),result:ref('Cobro'),code:201,extra:{puntos_otorgados:{type:'integer'}}});
op('post','/caja/cobros/{id}/anular','Anular un cobro registrado y revertir puntos',{access:'recepcion',request:obj({motivo:text},['motivo']),result:ref('Cobro')});
op('get','/caja/ingresos','Consultar ingresos por fecha y método',{result:ref('Ingresos'),query:cajaQuery});

op('get','/inventario/insumos','Listar insumos y categorías',{list:true,result:ref('Insumo'),query:[['requiere_reposicion',bool]],extra:{categorias:arr(ref('CategoriaInventario'))}});
op('post','/inventario/insumos','Crear insumo con stock cero',{request:ref('InsumoSolicitud'),result:ref('Insumo'),code:201});
op('get','/inventario/insumos/{id}','Consultar insumo',{result:ref('Insumo')});
op('patch','/inventario/insumos/{id}','Actualizar datos y mínimo del insumo',{request:partial(schemas.InsumoSolicitud),result:ref('Insumo')});
op('get','/inventario/movimientos','Listar trazabilidad de stock',{list:true,result:ref('Movimiento'),query:[['id_insumo',integer()],['tipo_movimiento',str({enum:['entrada','salida','devolucion','ajuste']})]]});
op('post','/inventario/movimientos','Registrar entrada, salida, devolución o ajuste',{request:ref('MovimientoSolicitud'),result:ref('Movimiento'),code:201});
op('get','/inventario/asignaciones','Listar entregas por estilista y estado',{access:'Administración con MFA o Estilista, mediante sesión web.',list:true,result:ref('Asignacion'),query:[['id_empleado',integer()],['estado',str({enum:['en_uso','agotado']})]]});
op('post','/inventario/asignaciones','Entregar insumo y descontar stock atómicamente',{request:ref('AsignacionSolicitud'),result:ref('Asignacion'),code:201});
op('post','/inventario/asignaciones/{id}/agotar','Marcar entrega como agotada',{access:'Administración con MFA o Estilista titular; sesión web.',result:ref('Asignacion')});

op('get','/fidelizacion/niveles','Consultar niveles de fidelización y tasa de puntos',{result:arr(ref('Nivel')),extra:{monto_por_punto:money}});
op('put','/fidelizacion/niveles','Configurar niveles Glow, Golden y VIP',{request:obj({monto_por_punto:amount,niveles:{type:'array',items:ref('NivelSolicitud'),minItems:3,maxItems:3}},['niveles']),result:arr(ref('Nivel')),extra:{monto_por_punto:money}});
op('get','/fidelizacion/motivos-descuento','Listar motivos de descuento manual',{access:'recepcion',result:arr(ref('MotivoDescuento')),query:[['solo_activos',bool]]});
op('post','/fidelizacion/motivos-descuento','Crear motivo de descuento manual',{request:ref('MotivoDescuentoSolicitud'),result:ref('MotivoDescuento'),code:201});
op('patch','/fidelizacion/motivos-descuento/{id}','Actualizar motivo de descuento manual',{request:partial(schemas.MotivoDescuentoSolicitud),result:ref('MotivoDescuento')});
op('get','/fidelizacion/puntos','Consultar historial de movimientos de puntos',{list:true,result:ref('MovimientoPuntos'),query:[['id_cliente',integer()],['tipo',str({enum:['ganado','reversion','ajuste']})]]});
op('post','/fidelizacion/puntos/ajustes','Registrar ajuste manual de puntos',{request:ref('AjustePuntosSolicitud'),result:ref('MovimientoPuntos'),code:201});

const document={openapi:'3.1.0',info:{title:'MiliBrows Studio API',version:'2.0.0',description:'Monolito modular Laravel 13 y MySQL/InnoDB para portal, panel y app móvil. Esquema vigente (multi-estilista, pagos parciales, fidelización por puntos y descuentos, inventario por unidades enteras).'},servers:[{url:'/api/v1'}],paths,components:{securitySchemes:{sesionWeb:{type:'apiKey',in:'cookie',name:'laravel-session'},tokenMovil:{type:'http',scheme:'bearer'}}},schemas};
function yaml(value,indent=0){
 const pad=' '.repeat(indent);
 if(Array.isArray(value))return value.length?value.map(v=>typeof v==='object'&&v!==null?pad+'-\n'+yaml(v,indent+2):pad+'- '+JSON.stringify(v)).join('\n'):pad+'[]';
 return Object.entries(value).map(([k,v])=>{const key=/^[A-Za-z_][A-Za-z0-9_]*$/.test(k)?k:JSON.stringify(k);const nested=typeof v==='object'&&v!==null;const empty=nested&&Object.keys(v).length===0;return pad+key+':'+(nested&&!empty?'\n'+yaml(v,indent+2):' '+(empty?(Array.isArray(v)?'[]':'{}'):JSON.stringify(v)));}).join('\n');
}
const contenido = '# Generado con node scripts/generar-openapi.cjs\n'+yaml(document)+'\n';
fs.writeFileSync('docs/openapi.yaml', contenido);
const sharedPath = path.resolve('..', 'shared', 'api', 'openapi.yaml');
if (fs.existsSync(path.dirname(sharedPath))) {
 fs.writeFileSync(sharedPath, contenido);
}
console.log(`${Object.keys(paths).length} rutas, ${Object.values(paths).reduce((n,p)=>n+Object.keys(p).length,0)} operaciones documentadas.`);
