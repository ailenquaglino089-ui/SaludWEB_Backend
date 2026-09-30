<?php
// ============================================================
// persistence/ContactoRepository.php - Datos de contacto para avisos
// ============================================================
// Repositorio CHICO y sin interfaz, por decisión de diseño.
//
// ¿Por qué sin interfaz, si los otros repositorios grandes sí la tienen?
//   Este repositorio no es parte de ningún agregado de negocio: es una
//   consulta de lectura que solo usa NotificacionService para saber a
//   dónde enviar un aviso. La interfaz (contrato) aporta valor cuando
//   hay varios repositorios intercambiables o cuando se quiere testear la
//   capa de negocio con dobles. Aquí una sola consulta directa es más
//   simple y hace lo mismo. Es el mismo criterio que ya se aplicó en
//   ObraSocialRepository, que tampoco define interfaz.
//
// IMPORTANTE: este archivo NO guarda ni decide el estado de nada.
// Solo resuelve direcciones de contacto. La fuente de verdad de las citas
// sigue siendo la tabla citas.
class ContactoRepository
{
    // Conexión PDO guardada para ejecutar las consultas de contacto
    private $pdo;

    /**
     * Constructor con inyección de dependencias
     * @param PDO $pdo
     */
    public function __construct(PDO $pdo)
    {
        // Recibe la conexión "desde afuera" (inyección de dependencias) y la guarda en el atributo
        $this->pdo = $pdo;
    }

    /**
     * Obtiene el email del usuario asociado a un paciente.
     *
     * Un paciente puede tener o no una cuenta de usuario en el sistema. Si
     * no la tiene, devuelve null y quien llama decide qué hacer (por ejemplo,
     * marcar la notificación como 'fallido' con un motivo claro en vez de
     * inventar una dirección).
     *
     * @param int $idPaciente ID del paciente
     * @return string|null Email del usuario o null si no tiene cuenta
     */
    public function obtenerEmailDePaciente(int $idPaciente): ?string
    {
        // JOIN entre usuarios y pacientes por la FK usuarios.id_paciente
        $stmt = $this->pdo->prepare(
            "SELECT u.email, u.nombre, u.activo
             FROM usuarios u
             WHERE u.id_paciente = ? AND u.activo = 1
             LIMIT 1"
        );
        $stmt->execute([$idPaciente]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        // isset() devuelve false si la clave no existe o si su valor es null,
        // que es exactamente lo que se quiere detectar acá
        return $fila && isset($fila['email']) ? $fila['email'] : null;
    }

    /**
     * Obtiene el email del profesional de un médico
     * @param int $idMedico ID del médico
     * @return string|null Email del usuario o null si no tiene cuenta
     */
    public function obtenerEmailDeMedico(int $idMedico): ?string
    {
        $stmt = $this->pdo->prepare(
            "SELECT u.email, u.nombre, u.activo
             FROM usuarios u
             WHERE u.id_medico = ? AND u.activo = 1
             LIMIT 1"
        );
        $stmt->execute([$idMedico]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila && isset($fila['email']) ? $fila['email'] : null;
    }

    /**
     * Devuelve los datos de contacto de un paciente de una sola vez
     *
     * Incluye el id del usuario, no solo el email. El id hace falta porque
     * la tabla notificaciones guarda a quién se le avisa (id_usuario): sin
     * él, el aviso se guardaría con id_usuario = NULL y el paciente no
     * vería sus propios avisos en el listado al entrar a la app.
     *
     * @param int $idPaciente ID del paciente
     * @return array ['email' => ?string, 'nombre' => string, 'tiene_cuenta' => bool, 'id_usuario' => ?int]
     */
    public function obtenerContactoDePaciente(int $idPaciente): array
    {
        // Se selecciona también u.id para poder asociar el aviso a la cuenta
        $stmt = $this->pdo->prepare(
            "SELECT u.id AS id_usuario, u.email, u.nombre, p.nombre AS nombre_paciente
             FROM pacientes p
             LEFT JOIN usuarios u ON u.id_paciente = p.id AND u.activo = 1
             WHERE p.id = ?
             LIMIT 1"
        );
        $stmt->execute([$idPaciente]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        // Si el paciente no existe, se devuelve una estructura vacía coherente
        if (!$fila) {
            return ['email' => null, 'nombre' => '', 'tiene_cuenta' => false, 'id_usuario' => null];
        }

        return [
            'email' => $fila['email'] ?? null,
            'nombre' => $fila['nombre_paciente'] ?? '',
            // Tiene cuenta si tiene email asociado
            'tiene_cuenta' => !empty($fila['email']),
            // El id se devuelve como entero o null, nunca como string vacío
            'id_usuario' => !empty($fila['id_usuario']) ? (int)$fila['id_usuario'] : null,
        ];
    }
}
