<?php
// ============================================================
// services/Notificacion/ProveedorNotificacionesInterface.php
// ============================================================
// Módulo: "Sistema de gestión de citas online" (Turnera)
//
// POR QUÉ EXISTE ESTA INTERFAZ
// ---------------------------
// La turnera tiene que "enviar notificaciones" (por WhatsApp o correo) para
// reducir el ausentismo, pero el proyecto todavía no define con QUIÉN se
// envía. Cambiar de mail() a PHPMailer, o de ahí a la API de WhatsApp,
// obligaría a reescribir el servicio de notificaciones entero.
//
// Con esta interfaz, el servicio de negocio no sabe ni le importa por dónde
// sale el mensaje. Solo dice "enviá esto". Quien decide el canal es una
// implementación concreta, y cambiarla es cambiar UNA línea en el bootstrap.
//
// Esto es el mismo criterio que ya usa el proyecto con los repositorios:
// contratos (interfaces) en la frontera, implementación concreta adentro.
//
// IMPLEMENTACIONES DISPONIBLES
// ----------------------------
//   ProveedorNotificacionesStub  → development (solo deja registro, no envía)
//   ProveedorNotificacionesMail  → producción con SMTP configurado
//                              (ver más abajo las instrucciones de conexión)
interface ProveedorNotificacionesInterface
{
    /**
     * Envía un mensaje de recordatorio de turno.
     *
     * @param array $datos [
     *     'destino'      => string,  email o teléfono al que enviar
     *     'canal'        => string,  'email' | 'whatsapp' | 'sistema'
     *     'asunto'       => string,  título del mensaje
     *     'cuerpo'       => string,  cuerpo del mensaje ya redactado
     *     'datos_extra'  => array,   datos crudos (cita, paciente, links) por si
     *                                el proveedor necesita armar su propio formato
     * ]
     * @return array ['ok' => bool, 'detalle' => string]
     *         'ok' indica si el envío quedó confirmado por el proveedor.
     *         'detalle' describe el resultado o el error, y se persiste en la
     *         tabla notificaciones para poder diagnosticar sin adivinar.
     */
    // En una interface el método se declara SIN cuerpo: es un contrato que
    // describe qué debe existir, no cómo se hace. Quien pone el cuerpo es
    // la implementación concreta (en este caso, el Stub).
    public function enviar(array $datos): array;
}
