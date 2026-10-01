<?php
// ============================================================
// controllers/RealtimeController.php - Capa HTTP del canal en vivo
// ============================================================
// Módulo: "Primera funcionalidad en tiempo real" (SSE)
//
// Este controlador es el ÚNICO punto del proyecto que NO responde con un
// JSON y termina. Mantiene la conexión abierta y escribe eventos a medida que
// aparecen. Esa diferencia cambia por completo cómo se programan las dos
// capas, y el resto del archivo está comentado por eso.
//
// CÓMO SE ESCRIBE UN EVENTO (Server-Sent Events)
// ----------------------------------------------
// El protocolo es texto plano con una gramática muy corta, y son cuatro
// líneas por evento:
//
//     id: 42                 → el número de secuencia; el navegador lo
//                              recuerda y lo reenvía en Last-Event-ID al
//                              reconectar, así que nunca se pierde un evento
//     event: cita_creada     → el nombre del evento; es lo que permite tener
//                              varios tipos en el mismo canal en vez de un
//                              único "mensaje" genérico
//     data: {...}            → el contenido (una sola línea de texto)
//                              <línea en blanco> → cierra el evento
//
// Las líneas que empiezan con ':' son COMENTARIOS del protocolo: el
// navegador las ignora por completo. Acá se usan para el latido (heartbeat).
//
// POR QUÉ HACE FALTA EL LATIDO
// ----------------------------
// Un stream sin tráfico parece caído. Los proxies, los puertos de las redes
// de empresas y algunos navegadores cierran una conexión que lleva demasiado
// tiempo sin enviar nada, y el usuario solo ve que "la pantalla se dejó de
// actualizar". Mandando un comentario cada 15 segundos se demuestra que la
// conexión está viva y se mantiene abierta. No lleva datos: es solo una
// señal de vida.
//
// POR QUÉ EL BUCLE CORRE DENTRO DE UNA PETICIÓN HTTP
// -----------------------------------------------
// Esta es la diferencia central con un WebSocket, y conviene entenderla antes
// de tocar nada del resto del proyecto:
//
//   • WebSocket abre un canal en ambos sentidos y mantiene UN proceso del
//     servidor por cliente durante horas.
//   • SSE abre UN canal de ida (el servidor escribe, el navegador escucha) y
//     lo hace con una petición HTTP normal que no termina nunca.
//
// En la práctica SSE necesita un proceso de PHP por cada pestaña conectada,
// exactamente igual que WebSocket. La diferencia es que no hay protocolo
// propio ni handshake: es HTTP puro, que es justo lo que hace que funcione
// detrás de Apache sin configurar nada, y que no haya que instalar un servidor
// de WebSockets aparte.
//
// POR ESO HAY UN LÍMITE DE DURACIÓN
// ---------------------------------
// Un proceso de PHP sostenido durante horas es un recurso que Apache no
// regala. Por eso la conexión se cierra sola después de SEUNDOS_MAXIMOS
// segundos. El corte NO se ve en pantalla: el navegador de EventSource
// reconecta solo, y como manda el último id recibido, sigue exactamente
// donde estaba. Además, cerrando y reabriendo, la conexión rota se
// repara sola sin necesidad de que el usuario recargue la página.
//
// OJO, es un detalle que cambia todo lo demás: este endpoint NO puede usar
// Response::ok() ni Response::error(), porque ambos terminan el script con
// exit. Si se usara Response en el medio del bucle, el stream cortaría en la
// primera respuesta. Por eso el módulo repite la idea de las dos formas que
// tiene de responder: JSON para el diagnóstico, texto plano para el stream.

class RealtimeController
{
    // Servicio de tiempo real (reglas de canal y publicación), inyectado
    private RealtimeService $service;

    // Verificación del token tomada del query string, porque el cliente SSE
    // no puede mandar headers. Se inyecta el JwtService directamente para no
    // depender del middleware (que solo sabe leer el header Authorization).
    private JwtService $jwt;

    // Servicio de autenticación, para resolver el contexto del usuario
    // (rol + vínculos con paciente y médico) igual que el resto de módulos
    private AuthService $authService;

    // Segundos que se mantiene abierta una conexión antes de cerrarla para
    // que el navegador la reabra sola. Ver la explicación de arriba.
    private const SEGUNDOS_MAXIMOS = 300;

    // Cada cuántos segundos se manda un comentario de latido
    private const LATIDO_SEGUNDOS = 15;

    // Cada cuántos segundos se consulta la tabla buscando eventos nuevos.
    //
    // Este NO es polling de datos hacia el cliente: acá nadie pregunta "dame
    // los turnos", solo "hay algo nuevo desde el último id". El peso por
    // vuelta es una consulta indexada que devuelve cero filas, y el cliente
    // sigue sin hacer ninguna petición hasta que hay un cambio real. Es la
    // diferencia entre consultar y entregar: el cliente dejó de preguntar.
    private const CONSULTA_SEGUNDOS = 1;

    /**
     * @param RealtimeService $service     Servicio de tiempo real
     * @param JwtService      $jwt         Verificador de tokens
     * @param AuthService     $authService Contexto del usuario
     */
    public function __construct(RealtimeService $service, JwtService $jwt, AuthService $authService)
    {
        $this->service = $service;
        $this->jwt = $jwt;
        $this->authService = $authService;
    }

    // ============================================================
    // GET /api/eventos - Canal en vivo (Server-Sent Events)
    // ============================================================
    /**
     * Abre el canal y mantiene la conexión abierta escribiendo eventos.
     *
     * Query:
     *   token  → JWT obligatorio. Va en la URL porque EventSource no puede
     *            mandar el encabezado Authorization (ver RealtimeController
     *            y AuthMiddleware::verificarTokenDeQuery()).
     *   canal  → canal declarativo: tablero | mis-turnos | mi-agenda |
     *            agenda:<id_medico>
     *   ultimo → id desde el que se empieza a leer. Opcional: si no viene,
     *            se usa el encabezado Last-Event-ID que envía el navegador
     *            al reconectar, y si tampoco existe, se arranca desde el
     *            último evento del canal (interesa el futuro, no el pasado).
     */
    public function canal(): void
    {
        // --------------------------------------------------
        // PASO 1: Autenticación, ANTES de enviar ninguna cabecera de streaming
        // --------------------------------------------------
        // Si el token no fuera válido hay que responder 401 en JSON, no abrir
        // un stream: un canal sin autenticar que se abre y se cierra solo es
        // mucho más difícil de entender que un 401 que dice qué pasó.
        $payload = $this->verificarTokenDeQuery();

        // Se arma el contexto del usuario (rol, id_paciente, id_medico) con
        // el payload que se acaba de verificar. Se usa contextoDesdePayload() y
        // NO contextoDePeticion() a propósito: el segundo lee un contexto que
        // deja AuthMiddleware::verificarToken(), y esta ruta no puede pasar por
        // ahí porque el token viene en el query y no en el header. Si se usara
        // el método equivocado, el canal rechazaría con "Sesión no verificada"
        // un token totalmente válido.
        try {
            $contexto = $this->authService->contextoDesdePayload($payload);
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 401);
            return;
        }

        // --------------------------------------------------
        // PASO 2: Autorización del canal
        // --------------------------------------------------
        // Se traduce el canal declarativo al canal real (que lleva los ids
        // del token). Si el rol no puede escucharlo, se responde 403 y se
        // termina, sin abrir el stream.
        try {
            $canal = $this->service->resolverCanal(
                trim((string)($_GET['canal'] ?? 'tablero')),
                $contexto
            );
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 422);
            return;
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 403);
            return;
        }

        // --------------------------------------------------
        // PASO 3: Preparar la respuesta para streaming
        // --------------------------------------------------
        // El token viaja en la URL, así que la respuesta no puede quedar en
        // ninguna caché del medio. 'no-store' es lo que evita que un proxy
        // le devuelva a otro usuario un stream que ya estaba abierto.
        header('Cache-Control: no-cache, no-store, must-revalidate');
        // 'no-cache' evita que se use una copia guardada, y 'no-store' impide
        // además que se guarde. Los dos hacen falta: con solo uno, hay
        // combinaciones de navegador y proxy que sí guardan la respuesta.

        header('X-Accel-Buffering: no');
        // Cabecera para proxies inversos (nginx y similares): les dice que no
        // acumulen la respuesta en un búfer para entregarla de golpe. Sin
        // esto, la conexión abre pero los eventos llegan todos juntos al
        // final, que es el síntoma clásico de "SSE anda raro".

        // Se quita el Content-Type que puso routes.php (JSON) y se reemplaza
        // por el del stream. header() solo no alcanza en algunos servidores
        // donde la cabecera ya fue enviada.
        header_remove('Content-Type');
        header('Content-Type: text/event-stream; charset=utf-8');
        // text/event-stream es el MIME que le dice al navegador que esto NO es
        // un documento para mostrar sino un flujo de eventos (por eso nunca
        // se ve HTML ni JSON en la pestaña).

        header('Connection: keep-alive');
        // La conexión se mantiene viva: es una respuesta larga por definición.

        // --------------------------------------------------
        // PASO 4: Sacar el búfer de salida de en medio
        // --------------------------------------------------
        // Este paso es el que separa un SSE que funciona de uno que no
        // funciona, y la causa siempre es la misma: PHP (o Apache) retiene la
        // salida en un búfer y no la manda hasta que el script termina. Como
        // este script no termina nunca, no se ve NADA. Con el búfer
        // desactivado, cada echo sale por la conexión en el momento.
        @ini_set('output_buffering', '0');
        // 'output_buffering' en 0 desactiva el búfer de PHP. La @ silencia
        // el aviso que aparece cuando la directiva ya fue fijada en el
        // php.ini y no se puede cambiar en tiempo de ejecución.

        @ini_set('zlib.output_compression', '0');
        // La compresión de salida agruparía los eventos para "optimizar"
        // bytes, que es justo lo contrario de lo que se quiere: sin salida
        // hasta juntar varios KB, que es lo que produce el retardo visible.

        while (ob_get_level() > 0) {
            // Cierra cualquier búfer que ya estuviera abierto. El while es
            // porque puede haber más de uno (el propio de PHP y alguno que
            // abra un módulo o el bootstrap).
            ob_end_flush();
        }

        ob_implicit_flush(true);
        // Cada echo que se haga a partir de ahora se manda inmediatamente,
        // sin esperar a que se llene el búfer.

        // --------------------------------------------------
        // PASO 5: Soltar el candado de sesión
        // --------------------------------------------------
        // Este detalle parece menor y es el que más cuesta encontrar si falta.
        // El arranque del backend abre sesión (session_start), y PHP mantiene
        // un archivo de sesión bloqueado mientras dura el script. Como este
        // script dura minutos, TODAS las demás peticiones del MISMO usuario
        // se quedarían esperando ese candado: la web se vería congelada al
        // reservar un turno mientras tiene el canal abierto.
        //
        // session_write_close() escribe los datos de la sesión y libera el
        // archivo. A partir de ese momento las demás peticiones del mismo
        // usuario funcionan con normalidad.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        // --------------------------------------------------
        // PASO 6: Configurar el proceso para una respuesta larga
        // --------------------------------------------------
        // El límite de ejecución por defecto de PHP (30 s) cortaría el stream
        // a mitad de camino. set_time_limit(0) lo desactiva para este script.
        @set_time_limit(0);

        // ignore_user_abort(false) es el valor por defecto y se deja
        // explícito porque es una decisión: si el navegador cierra la
        // pestaña, el script debe TERMINAR. Dejarlo en true dejaría procesos
        // de PHP girando hasta el timeout por cada pestaña cerrada, que en una
        // máquina de desarrollo termina con el equipo sin recursos.
        ignore_user_abort(false);

        // --------------------------------------------------
        // PASO 7: Mensaje de apertura
        // --------------------------------------------------
        // 'retry:' le dice al navegador cuánto esperar si se cae la conexión.
        // Sin esta línea el valor por defecto del navegador puede ser de
        // varios segundos, y además puede ser DIFERENTE en cada navegador,
        // lo que hace la reconexión impredecible.
        $this->escribirCrudo("retry: 3000\n\n");

        $inicio = time();
        // Momento en que se abrió esta conexión. Se usa para no superarse la
        // duración máxima y para mostrarlo en el evento de apertura.

$ultimoId = $this->ultimoIdInicial($canal);
        // Cursor de lectura: desde qué evento se empieza a entregar. Sale de
        // resolverCanal() ya hecho, así que en este punto el canal está
        // autorizado y no hay que volver a validarlo en cada vuelta.

        $this->escribirEvento('conectado', [
            'canal' => $canal,          // el canal real (puede diferir del pedido)
            'ultimo_id' => $ultimoId,   // desde dónde va a leer
            'servidor' => date('c'),    // fecha ISO del servidor, para diagnosticar
        ], null);
        // Evento de apertura. No lleva 'id' a propósito: es un mensaje de
        // estado de la conexión, no un hecho del consultorio. Si llevara id,
        // el cursor avanzaría por un evento que no existe en la tabla.

        $ultimoLatido = time();   // se cuenta el latido desde ahora

        // ================================================================
        // PASO 8: El bucle de escucha
        // ================================================================
        // Cada vuelta consulta los eventos nuevos del canal y los escribe.
        // El bucle termina por tres motivos, todos deliberados:
        //   a) el cliente se desconectó (detección por conexión abortada),
        //   b) se alcanzó SEGUNDOS_MAXIMOS y se cede el proceso,
        //   c) el script llega al final (por ejemplo, si el servidor corta).
        while (true) {   // bucle infinito: termina solo por las tres condiciones de arriba
            // Se leen los eventos posteriores al cursor, hasta un máximo por
            // vuelta para no saturar si el canal acumuló muchos.
            $eventos = $this->service->leerEventos($canal, $ultimoId, 50);   // tope: 50

            foreach ($eventos as $evento) {
                // Se avanza el cursor ANTES de escribir. Si el script se
                // cortara entre leer y escribir, es preferible perder este
                // evento en esta vuelta y no repetirlo en la siguiente: un
                // evento duplicado se ve como un fallo visible, mientras que
                // uno perdido en un corte se recupera con el reintento de la
                // acción que lo produjo.
                $ultimoId = (int)$evento['id'];

                // Se escribe el evento con su id, su tipo y su contenido.
                $this->escribirEvento($evento['tipo'], $this->datosDe($evento), (int)$evento['id']);
            }

            // Si hubo eventos, no hace falta mandar latido: ya se mandó
            // tráfico por la conexión y eso la mantiene viva igual.
            if (!empty($eventos)) {
                $ultimoLatido = time();
            } elseif ((time() - $ultimoLatido) >= self::LATIDO_SEGUNDOS) {
                // LÍNEA DE LATIDO
                // --------------
                // Es un comentario que empieza con ':' según el protocolo SSE:
                // el navegador no lo pinta, no lo entrega como evento y no cambia
                // el último id. Su único objetivo es mantener la conexión viva
                // cuando no hay eventos. Sin esto, algunos proxies HTTP (Apache
                // detrás de un balanceador, o ciertas configuraciones de nginx)
                // cortan la conexión por inactividad, aunque en XAMPP suele
                // aguantar más tiempo.
                $this->escribirCrudo(": ping " . time() . "\n\n");
                $ultimoLatido = time();   // reinicia el cronómetro de inactividad
            }

            // --------------------------------------------------
            // Detección de desconexión
            // --------------------------------------------------
            // connection_aborted() es la función que hace útil todo lo demás.
            // PHP no se entera solo de que el cliente se fue: sigue
            // ejecutando el script y mandando bytes a una conexión que ya no
            // existe. Como en este módulo se escribe en cada vuelta, esa
            // escritura es la que falla, y ahí connection_aborted() pasa a
            // valer true. Por eso el latido no es solo buena práctica: es la
            // forma de que la desconexión se detecte en segundos y no en el
            // timeout.
            if (connection_aborted()) {
                // El cliente cerró: se corta el bucle y el proceso se libera.
                break;
            }

            // --------------------------------------------------
            // Cierre por duración
            // --------------------------------------------------
            if ((time() - $inicio) >= self::SEGUNDOS_MAXIMOS) {
                // Se avisa antes de cerrar, para que el navegador pueda
                // reconectar de inmediato en lugar de esperar su propio
                // temporizador de reintento.
                $this->escribirEvento('reconectar', [
                    'motivo' => 'La conexión se renueva para liberar el proceso del servidor',
                ], null);
                break;
            }

            // Espera entre vueltas. Es el descanso del proceso: sin esto el
            // bucle consumiría CPU preguntándole a la base si hay novedades.
            sleep(self::CONSULTA_SEGUNDOS);
            // Un segundo es el punto justo para tiempo real: el retraso total
            // percibido es el que se le suma a la publicación del evento, y
            // queda muy por debajo de los 500 ms que propone la guía.
        }

        // Se cierra explícitamente. Con ignore_user_abort(false), si el
        // cliente se fue, PHP ya terminó solo; si salió por tiempo, cerrar
        // la sesión y terminar es lo que libera el proceso.
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }
    }

    /**
     * Desempaqueta la columna 'datos' de un evento.
     *
     * En la base el payload está guardado como texto JSON. Si por algún
     * motivo viniera mal formado (una escritura manual, un cambio de formato),
     * no se corta la conexión: se devuelve un objeto mínimo con el tipo y se
     * sigue. Perder el contenido de UN evento es aceptable; que el stream
     * entero muera por eso no lo es.
     *
     * @param array $evento Fila de la tabla eventos_realtime
     * @return array Contenido del evento
     */
    private function datosDe(array $evento): array
    {
        $crudo = $evento['datos'] ?? null;

        if (is_string($crudo) && trim($crudo) !== '') {
            $decodificado = json_decode($crudo, true);

            // El segundo parámetro true hace que json_decode devuelva un
            // arreglo en lugar de un objeto stdClass, que es la forma que usa
            // el resto del proyecto y la que espera json_encode después.
            if (is_array($decodificado)) {
                return $decodificado;
            }
        }

        // Contenido mínimo de reemplazo: el frontend puede mostrar el tipo
        // del evento aunque no sepa de qué turno se trata.
        return ['tipo' => $evento['tipo'] ?? 'desconocido'];
    }

    // ============================================================
    // GET /api/eventos/estado - Diagnóstico del módulo (JSON)
    // ============================================================
    /**
     * Devuelve el estado del módulo en JSON normal.
     *
     * Sirve para tres cosas concretas, todas de la guía:
     *   1. Comprobar que el canal recibe eventos (es la forma de separar
     *      "no llegó nada porque no pasó nada" de "no llegó nada porque el
     *      canal está roto").
     *   2. Verificar que un evento se publicó con el id esperado, para medir
     *      la latencia real de punta a punta.
     *   3. Demostrar que la tabla se purga, en lugar de crecer para siempre.
     *
     * Este endpoint SÍ responde JSON normal con Response::ok() y termina:
     * no tiene nada que ver con el stream. Por eso conviven los dos estilos
     * de respuesta en la misma clase.
     */
    public function estado(): void
    {
        try {
            Response::ok($this->service->diagnostico());
        } catch (\Exception $e) {
            Response::error('Error interno del servidor', 500);
        }
    }

    // ============================================================
    // HELPERS PRIVADOS
    // ============================================================

    /**
     * Verifica el JWT que viene en el query string.
     *
     * Por qué el token viaja en la URL y no en un encabezado: el objeto
     * EventSource del navegador es la API estándar para SSE y NO admite
     * encabezados personalizados. No hay forma de mandarle el
     * 'Authorization: Bearer ...' que usa el resto de la API.
     *
     * Es una limitación real del protocolo, no una decisión de diseño, y por
     * eso el resto de endpoints siguen usando el encabezado. Las dos cosas
     * que hacen que sea aceptable acá:
     *   - es el mismo token que ya está en el localStorage del navegador,
     *     con la misma vigencia corta;
     *   - la respuesta va con 'no-store', así que el token no queda en la
     *     caché de ningún intermediario.
     *
     * El riesgo real (el token puede quedar registrado en el log de accesos
     * de Apache) está anotado en README, junto con la alternativa
     * profesional: una cookie de sesión HttpOnly, que es lo que se hace en
     * un despliegue real. Para un proyecto de cátedra alcanza con esto.
     *
     * @return array Payload del token verificado
     */
    private function verificarTokenDeQuery(): array
    {
        // Se lee el token del query. No se usa $_REQUEST a propósito: ese
        // arreglo mezcla GET, POST y COOKIE, y una cookie con el mismo nombre
        // Ganaría la precedencia. Solo se lee lo que viene en la URL.
        $token = isset($_GET['token']) ? trim((string)$_GET['token']) : '';

        if ($token === '') {
            Response::error('Token no proporcionado', 401);
            // Response::error() hace exit, así que la ejecución no sigue.
        }

        try {
            // Se verifica firma y expiración con el MISMO servicio que el
            // resto de la API: mismo secreto, mismo algoritmo, mismo reloj.
            return $this->jwt->verificar($token);
        } catch (\Exception $e) {
            Response::error('Token inválido o expirado', 401);
            return [];
            // Código inalcanzable: por construcción de la función, o se
            // devuelve el payload o se terminó el script. Está porque un
            // return sin valor en una función que promete array es una
            // inconsistencia que un analizador estático marcaría.
        }
    }

    /**
     * Decide desde qué evento arranca la lectura de esta conexión.
     *
     * Tres casos, en este orden de prioridad:
     *   1. Viene ?ultimo=N  → el cliente pide explícitamente desde N.
     *   2. Viene el encabezado Last-Event-ID → es el navegador reconectando
     *      después de un corte: tiene que seguir donde se quedó.
     *   3. Conexión nueva → se arranca desde el último id existente, porque a
     *      un suscriptor nuevo le interesa lo que pase de aquí en adelante y
     *      no el historial.
     *
     * @param string $canal Canal ya autorizado
     * @return int Id desde el que leer
     */
    private function ultimoIdInicial(string $canal): int
    {
        // Caso 1: parámetro explícito (lo usa el script de pruebas).
        if (isset($_GET['ultimo']) && $_GET['ultimo'] !== '') {
            return max(0, (int)$_GET['ultimo']);
        }

        // Caso 2: reconexión del navegador.
        // Last-Event-ID es un encabezado HTTP estándar que el navegador
        // reenvía solo, sin que el código del frontend haga nada. Por eso el
        // frontend no tiene que guardar el cursor: eso es justamente una de
        // las ventajas de SSE frente a tener que hacerlo a mano con WebSocket.
        if (isset($_SERVER['HTTP_LAST_EVENT_ID']) && $_SERVER['HTTP_LAST_EVENT_ID'] !== '') {
            return max(0, (int)$_SERVER['HTTP_LAST_EVENT_ID']);
        }

        // Caso 3: conexión nueva, se empieza desde el final del canal.
        return $this->service->ultimoIdDelCanal($canal);
    }

    /**
     * Escribe un evento completo con el protocolo de SSE.
     *
     * @param string      $tipo  Nombre del evento
     * @param array       $datos Contenido
     * @param int|null    $id    Id de secuencia, o null si no aplica
     */
    private function escribirEvento(string $tipo, array $datos, ?int $id): void
    {
        $lineas = '';

        if ($id !== null) {
            // El id SOLO se escribe si el evento existe en la tabla. Por eso
            // los eventos de control ('conectado', 'reconectar') van sin id.
            $lineas .= 'id: ' . $id . "\n";
        }

        $lineas .= 'event: ' . $tipo . "\n";

        // El contenido se serializa en UNA sola línea. Si el JSON tuviera
        // saltos de línea reales, el protocolo los interpretaría como el
        // fin del evento y el resto se descartaría. JSON_UNESCAPED_UNICODE
        // deja las tildes legibles en lugar de escribirlas como \u00e1.
        $lineas .= 'data: ' . json_encode($datos, JSON_UNESCAPED_UNICODE) . "\n\n";

        $this->escribirCrudo($lineas);
    }

    /**
     * Escribe texto crudo y fuerza el envío.
     *
     * @param string $texto Contenido a escribir
     */
    private function escribirCrudo(string $texto): void
    {
        echo $texto;

        // flush() empuja lo escrito hacia la conexión. Sin esto, PHP puede
        // seguir acumulando en un búfer interno aunque output_buffering esté
        // en 0, y el evento se vería con retraso.
        flush();
        // flush() en PHP también limpia el búfer de salida del usuario.

        // Si hay un búfer de nivel superior (por ejemplo, el de Apache con
        // mod_deflate), se envía también. Es la última barrera contra el
        // síntoma de "los eventos llegan todos juntos".
        if (ob_get_level() > 0) {
            @ob_flush();
        }

        // IMPORTANTE: acá NO se llama a fastcgi_finish_request(), aunque la
        // función pueda existir. Esa función le dice al servidor "terminá de
        // mandar la respuesta AHORA", y en un stream eso significa cerrar la
        // conexión: los eventos siguientes se escribirían sobre una respuesta
        // ya cerrada y se perderían. Es la trampa clásica al copiar código de
        // SSE que estaba pensado para un request que termina.
        //
        // En este proyecto (XAMPP con mod_php) la función directamente no
        // existe, así que el problema no se veía. Con PHP-FPM el canal
        // emitía el primer evento y se cortaba, y el síntoma era desconcertante:
        // "funciona una vez y después nunca más".
        //
        // Para terminar la respuesta al final del bucle alcanza con dejar que
        // el script acabe: PHP cierra la conexión solo.
    }
}