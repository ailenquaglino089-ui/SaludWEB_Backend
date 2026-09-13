<?php
// Punto de entrada de la API del backend separado.
// Punto de entrada principal del proyecto (Front Controller / Controlador Frontal)
// Todas las peticiones HTTP llegan aquí gracias al .htaccess

// Incluye el archivo de rutas, que a su vez carga bootstrap.php con todas las dependencias
require_once __DIR__ . '/routes.php';

// Despacha la ruta actual: lee el método HTTP (GET, POST, etc.) y la URL solicitada
// $router es una instancia de la clase Router creada en bootstrap.php
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
