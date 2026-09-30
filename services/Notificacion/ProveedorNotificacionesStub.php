<?php
// ============================================================
// services/Notificacion/ProveedorNotificacionesStub.php
// ============================================================
// Módulo: "Sistema de gestión de citas online" (Turnera)
//
// PROVEEDOR DE DESARROLLO: NO ENVÍA NADA.
//
// Qué hace y por qué existe
// ------------------------
// En desarrollo (XAMPP, sin SMTP configurado, sin cuenta de WhatsApp
// Business) no hay a dónde enviar un recordatorio. Este proveedor simula
// el envío y devuelve éxito, de modo que todo el circuito de la turnera
// se puede probar de punta a punta: se genera el recordatorio, se "envía",
// queda registrado como enviado y el paciente puede confirmar o cancelar
// con el token.
//
// Eso SÍ es funcionalidad real: el token, los endpoints de confirmar y
// cancelar, la persistencia y las estadísticas de ausentismo funcionan
// completos. Lo único que falta es que el mensaje salga del servidor.
//
// Qué NO hace
// -----------
// No envía email, no llama a WhatsApp y no deja el mensaje en ningún lado.
// Por eso en los listados se ven las notificaciones como "enviadas" sin
// que nadie las haya recibido realmente. En un entorno de prueba eso es
// INRESPONSABLE a propósito: no se quiere que un compañero reciba mensajes
// reales por un turno ficticio.
//
// CÓMO PASAR A PRODUCCIÓN
// -----------------------
//  1. Escribir la clase real que implemente ProveedorNotificacionesInterface
//     (por ejemplo ProveedorNotificacionesMail usando mail(), o
//     ProveedorNotificacionesSms con la API de WhatsApp).
//  2. Enrastrarla en core/bootstrap.php, en la línea donde hoy se crea el stub.
//
// Hasta entonces, NO se activa ningún canal de salida: el sistema funciona
// completo sin enviar nada.
class ProveedorNotificacionesStub implements ProveedorNotificacionesInterface
{
    /**
     * Simula el envío de un mensaje.
     *
     * @param array $datos Ver ProveedorNotificacionesInterface::enviar()
     * @return array ['ok' => true, 'detalle' => ...]
     */
    public function enviar(array $datos): array
    {
        // No se envía nada de verdad: se declara éxito para que el circuito
        // de la turnera se pueda probar completo en desarrollo.
        return [
            'ok' => true,
            // El detalle se persiste en la tabla notificaciones. Delata que
            // el envío fue simulado, para que nadie confunda un recordatorio
            // de prueba con uno real.
            'detalle' => 'Envío SIMULADO (proveedor de desarrollo). No se envió ningún mensaje real.',
        ];
    }
}
