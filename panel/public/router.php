<?php
// Router entry point - handles all requests
require_once __DIR__ . '/../app/helpers.php';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = rtrim($path, '/') ?: '/';
$method = $_SERVER['REQUEST_METHOD'];

// Serve static files directly through built-in server (except dynamic routes)
$dynamic_paths = ['/theme.css', '/service-worker.js', '/manifest.webmanifest', '/install.sh', '/install-daemon.sh'];
if (!in_array($path, $dynamic_paths) && preg_match('#\.(css|js|png|jpg|jpeg|svg|ico|webp|woff2?)$#i', $path)) {
    return false;
}

// Route map
$routes = [
    'GET /'                        => ['App\\Controllers\\Home', 'index'],
    'GET /login'                   => ['App\\Controllers\\Auth', 'showLogin'],
    'POST /login'                  => ['App\\Controllers\\Auth', 'login'],
    'GET /register'                => ['App\\Controllers\\Auth', 'showRegister'],
    'POST /register'               => ['App\\Controllers\\Auth', 'register'],
    'POST /logout'                 => ['App\\Controllers\\Auth', 'logout'],

    'GET /dashboard'               => ['App\\Controllers\\Dashboard', 'index'],
    'GET /servers'                 => ['App\\Controllers\\Servers', 'index'],
    'GET /servers/new'             => ['App\\Controllers\\Servers', 'create'],
    'POST /servers'                => ['App\\Controllers\\Servers', 'store'],
    'POST /servers/action'         => ['App\\Controllers\\Servers', 'action'],
    'POST /servers/delete'         => ['App\\Controllers\\Servers', 'delete'],
    'GET /json/servers'             => ['App\\Controllers\\Servers', 'apiList'],
    'GET /json/servers/logs'        => ['App\\Controllers\\Servers', 'apiLogs'],
    'POST /json/servers/console'    => ['App\\Controllers\\Servers', 'apiConsoleCmd'],

    'GET /nodes'                   => ['App\\Controllers\\Nodes', 'index'],
    'POST /nodes'                  => ['App\\Controllers\\Nodes', 'store'],
    'POST /nodes/delete'           => ['App\\Controllers\\Nodes', 'delete'],

    'GET /users'                   => ['App\\Controllers\\Users', 'index'],
    'POST /users'                  => ['App\\Controllers\\Users', 'store'],
    'POST /users/delete'           => ['App\\Controllers\\Users', 'delete'],

    'GET /theme'                   => ['App\\Controllers\\Theme', 'index'],
    'POST /theme'                  => ['App\\Controllers\\Theme', 'save'],
    'GET /theme.css'               => ['App\\Controllers\\Theme', 'css'],

    'GET /discord'                 => ['App\\Controllers\\Discord', 'index'],
    'POST /discord'                => ['App\\Controllers\\Discord', 'save'],
    'POST /discord/test'           => ['App\\Controllers\\Discord', 'test'],

    'GET /activity'                => ['App\\Controllers\\Activity', 'index'],
    'GET /install'                 => ['App\\Controllers\\Install', 'index'],
    'GET /install.sh'              => ['App\\Controllers\\Install', 'panelScript'],
    'GET /install-daemon.sh'       => ['App\\Controllers\\Install', 'daemonScript'],
    'GET /manifest.webmanifest'    => ['App\\Controllers\\Pwa', 'manifest'],
    'GET /service-worker.js'       => ['App\\Controllers\\Pwa', 'serviceWorker'],
];

// Server detail routes (dynamic ID)
if (preg_match('#^/servers/(\d+)$#', $path, $m) && $method === 'GET') {
    require_once __DIR__ . '/../app/Controllers/Servers.php';
    (new App\Controllers\Servers())->show((int)$m[1]);
    return true;
}

$key = "$method $path";
if (isset($routes[$key])) {
    [$class, $action] = $routes[$key];
    $file = __DIR__ . '/../app/Controllers/' . basename(str_replace('App\\Controllers\\', '', $class)) . '.php';
    require_once $file;
    $controller = new $class();
    $controller->$action();
    return true;
}

http_response_code(404);
require __DIR__ . '/../app/helpers.php';
view('errors/404');
