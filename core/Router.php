<?php
// Enrutador HTTP de la copia independiente del backend.
// ============================================================
// core/Router.php - Enrutador de URLs
// ============================================================
// Router = Enrutador. Se encarga de:
// 1. Registrar rutas (asociar una URL con una función controladora)
// 2. Hacer "match" entre la URL solicitada y las rutas registradas
// 3. Ejecutar el controlador correspondiente
// Soporta: GET, POST, PUT, PATCH, DELETE y parámetros dinámicos {id}
// ============================================================

class Router
{
    // Arreglo donde se guardan todas las rutas registradas
    // Cada ruta tiene: method, path, handler, middlewares
    private array $routes = [];

    // Arreglo de middlewares globales (interceptores que se ejecutan antes de las rutas)
    private array $middlewares = [];

    // Función que se ejecuta cuando ninguna ruta coincide
    private mixed $notFoundHandler = null;

    // Ruta base del proyecto (ej: /Organizacion_Modulos)
    // Se usa para recortar el prefijo de las URLs
    private string $basePath = '';

    /**
     * Constructor: recibe la ruta base del proyecto
     * @param string $basePath Ej: "/Organizacion_Modulos"
     */
    public function __construct(string $basePath = '')
    {
        // rtrim() saca la barra del final si existe
        // "/Organizacion_Modulos/" -> "/Organizacion_Modulos"
        $this->basePath = rtrim($basePath, '/');
    }

    // ============================================================
    // Métodos para registrar rutas según el verbo HTTP
    // Cada uno llama a addRoute() con el método correspondiente
    // ============================================================

    /**
     * Registra una ruta para peticiones GET (obtener datos)
     * GET = cuando el usuario pide ver una página o recurso
     */
    public function get(string $path, $handler, array $middlewares = []): void
    {
        $this->addRoute('GET', $path, $handler, $middlewares);
    }

    /**
     * Registra una ruta para peticiones POST (crear datos)
     * POST = cuando el usuario envía un formulario para crear algo
     */
    public function post(string $path, $handler, array $middlewares = []): void
    {
        $this->addRoute('POST', $path, $handler, $middlewares);
    }

    /**
     * Registra una ruta para peticiones PUT (actualizar datos completos)
     * PUT = cuando se reemplaza un recurso entero
     */
    public function put(string $path, $handler, array $middlewares = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middlewares);
    }

    /**
     * Registra una ruta para peticiones PATCH (actualizar parcialmente)
     * PATCH = cuando se modifica solo una parte de un recurso
     * Ej: cambiar solo el estado "activo" de un médico
     */
    public function patch(string $path, $handler, array $middlewares = []): void
    {
        $this->addRoute('PATCH', $path, $handler, $middlewares);
    }

    /**
     * Registra una ruta para peticiones DELETE (borrar datos)
     */
    public function delete(string $path, $handler, array $middlewares = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middlewares);
    }

    /**
     * Agrega un middleware global (se ejecuta en todas las rutas)
     * Middleware = capa intermedia, como un control de seguridad
     */
    public function middleware(string $middleware): void
    {
        // Agrega el middleware al arreglo de middlewares globales
        $this->middlewares[] = $middleware;
    }

    /**
     * Define qué hacer cuando ninguna ruta coincide (error 404)
     * @param callable|string $handler Función o archivo a ejecutar
     */
    public function notFound($handler): void
    {
        $this->notFoundHandler = $handler;
    }

    /**
     * Registra una ruta en el arreglo interno
     * @param string $method Método HTTP (GET, POST, etc.)
     * @param string $path URL relativa (ej: /medicos, /api/medicos/{id})
     * @param callable|string $handler Función o controlador@método
     * @param array $middlewares Middlewares específicos de esta ruta
     */
    private function addRoute(string $method, string $path, $handler, array $middlewares): void
    {
        // strtoupper() convierte a mayúsculas por si vino en minúsculas
        $this->routes[] = [
            // 'method': verbo HTTP normalizado a mayúsculas (GET, POST, etc.)
            'method' => strtoupper($method),
            // 'path': URL relativa registrada (ej: /medicos/{id})
            'path' => $path,
            // 'handler': función o "Controlador@método" que maneja la ruta
            'handler' => $handler,
            // 'middlewares': interceptores propios de esta ruta
            'middlewares' => $middlewares
        ];
    }

    /**
     * Despacha (ejecuta) la ruta correspondiente a la URL recibida
     * dispatch = despachar, enviar, ejecutar
     * 
     * @param string $method Método HTTP (GET, POST, etc.)
     * @param string $uri URL completa (ej: /Organizacion_Modulos/medicos)
     */
    public function dispatch(string $method, string $uri): void
    {
        // parse_url() analiza la URL y devuelve solo el path (sin query string)
        // Ej: "/medicos?id=1" -> "/medicos"
        // PHP_URL_PATH: extrae solo la parte de la ruta
        $uri = parse_url($uri, PHP_URL_PATH);

        // Si hay basePath y la URL empieza con él, lo recorta
        // str_starts_with() = verifica si un string empieza con otro
        // Ej: basePath=/Organizacion_Modulos, uri=/Organizacion_Modulos/medicos
        // -> queda /medicos
        if ($this->basePath !== '' && str_starts_with($uri, $this->basePath)) {
            $uri = substr($uri, strlen($this->basePath));
        }

        // Si la URL termina en /index.php, lo saca
        // Ej: /Organizacion_Modulos/index.php -> /
        $uri = preg_replace('#/index\.php$#', '', $uri);

        // rtrim() saca la barra del final
        // Si queda vacío, usa "/" (la raíz)
        $uri = rtrim($uri, '/') ?: '/';

        // Recorre todas las rutas registradas buscando una que coincida
        foreach ($this->routes as $route) {
            // Si el método HTTP no coincide, salta a la siguiente ruta
            if ($route['method'] !== strtoupper($method)) {
                continue;
            }

            // Intenta hacer "match" entre la ruta registrada y la URL solicitada
            // matchRoute() devuelve los parámetros dinámicos (si los hay)
            // Ej: ruta /api/medicos/{id}, URL /api/medicos/5 -> params = [5]
            $params = $this->matchRoute($route['path'], $uri);

            // Si el match fue exitoso ($params no es null)
            if ($params !== null) {
                // Ejecuta los middlewares específicos de esta ruta
                foreach ($route['middlewares'] as $middleware) {
                    if (is_callable($middleware)) {
                        // Si es una función, la ejecuta
                        // Si devuelve false, detiene la ejecución (acceso denegado)
                        if (!$middleware()) {
                            return;
                        }
                    // Si es el nombre de una clase con método handle(), la instancia y ejecuta
                    } elseif (class_exists($middleware) && method_exists($middleware, 'handle')) {
                        (new $middleware())->handle();
                    }
                }

                // ============================================================
                // Ejecuta el manejador (handler) de la ruta
                // El handler puede ser:
                // 1. Una función anónima (closure): function() { ... }
                // 2. Un string "Controlador@metodo": "MedicoController@index"
                // 3. Un string con la ruta de un archivo: "vista.php"
                // ============================================================

                // 1. Si es una función, la ejecuta pasando los parámetros
                if (is_callable($route['handler'])) {
                    // call_user_func_array() llama a la función con los parámetros
                    call_user_func_array($route['handler'], $params);

                // 2. Si es string "Controlador@metodo"
                } elseif (is_string($route['handler']) && strpos($route['handler'], '@') !== false) {
                    // explode() divide el string por el @
                    // Ej: "MedicoController@index" -> ["MedicoController", "index"]
                    [$controller, $method] = explode('@', $route['handler']);

                    // Verifica que la clase y el método existan
                    if (class_exists($controller) && method_exists($controller, $method)) {
                        // Crea una instancia del controlador y llama al método
                        // ...$params: "spread operator", pasa el array como argumentos separados
                        (new $controller())->$method(...$params);
                    } else {
                        // El controlador o método no existe: responde con error 500 de servidor
                        http_response_code(500);
                        // Muestra un mensaje legible para el desarrollador
                        echo "Error: Controlador o método no encontrado.";
                    }

                // 3. Si es string (ruta de archivo), lo incluye
                } elseif (is_string($route['handler'])) {
                    require_once $route['handler'];
                }

                // Una vez encontrada la ruta, sale del método (return)
                return;
            }
        }

        // Si ninguna ruta coincidió, ejecuta el manejador de 404 (not found)
        if ($this->notFoundHandler !== null) {
            // Si el manejador es una función, se la invoca directamente
            if (is_callable($this->notFoundHandler)) {
                call_user_func($this->notFoundHandler);
            // Si el manejador es un string, se trata como ruta de archivo a incluir
            } elseif (is_string($this->notFoundHandler)) {
                require_once $this->notFoundHandler;
            }
        } else {
            // Si no hay manejador 404 definido, muestra uno por defecto
            // Envía el código HTTP 404 (recurso no encontrado)
            http_response_code(404);
            // Imprime un mensaje simple de página no encontrada
            echo "404 - Página no encontrada";
        }
    }

    /**
     * Compara una ruta registrada con la URL solicitada
     * Soporta parámetros dinámicos {id}, {slug}, etc.
     * 
     * @param string $routePath Ruta registrada (ej: /api/medicos/{id})
     * @param string $requestUri URL solicitada (ej: /api/medicos/5)
     * @return array|null Los valores de los parámetros, o null si no coincide
     * 
     * Ejemplo: ruta="/api/medicos/{id}", uri="/api/medicos/5"
     *          -> explode() divide ambas por /
     *          -> parte por parte compara, {id} acepta cualquier valor
     *          -> devuelve ["5"]
     */
    private function matchRoute(string $routePath, string $requestUri): ?array
    {
        // Divide la ruta registrada en partes separadas por /
        // Ej: "/api/medicos/{id}" -> ["api", "medicos", "{id}"]
        $routeParts = explode('/', trim($routePath, '/'));

        // Divide la URL solicitada en partes
        // Ej: "/api/medicos/5" -> ["api", "medicos", "5"]
        $uriParts = explode('/', trim($requestUri, '/'));

        // Si tienen distinta cantidad de partes, no coinciden
        if (count($routeParts) !== count($uriParts)) {
            return null;
        }

        // Arreglo para guardar los valores de los parámetros dinámicos
        $params = [];

        // Compara cada parte de la ruta con la URL
        foreach ($routeParts as $index => $part) {
            // str_starts_with($part, '{') y str_ends_with($part, '}')
            // = la parte es un parámetro dinámico como {id} o {nombre}
            if (str_starts_with($part, '{') && str_ends_with($part, '}')) {
                // substr() extrae el nombre del parámetro (sin las llaves)
                // Ej: "{id}" -> "id"
                $paramName = substr($part, 1, -1);
                // Guarda el valor de la URL en la misma posición
                $params[] = $uriParts[$index];
            } elseif ($part !== $uriParts[$index]) {
                // Si la parte no coincide y no es dinámica, no hay match
                return null;
            }
        }

        // Si todas las partes coincidieron, devuelve los parámetros
        return $params;
    }
}
