<?php

declare(strict_types=1);

use SailConnect\Auth\{Config, Controller, Database, Mailer, SessionService, TokenService, Turnstile, UserRepository, View};

require __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
$config = Config::load($root);
date_default_timezone_set($config->get('APP_TIMEZONE', 'UTC'));
$debug = $config->bool('APP_DEBUG');
ini_set('display_errors', $debug ? '1' : '0');
error_reporting($debug ? E_ALL : E_ALL & ~E_DEPRECATED);

$path = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';

// Static assets (used by the PHP built-in server; the web server serves them directly in production).
$staticTypes = ['css' => 'text/css', 'png' => 'image/png', 'svg' => 'image/svg+xml'];
$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
if (
    (str_starts_with($path, '/css/') || str_starts_with($path, '/assets/'))
    && isset($staticTypes[$extension]) && is_file(__DIR__ . $path) && !str_contains($path, '..')
) {
    header('Content-Type: ' . $staticTypes[$extension]);
    readfile(__DIR__ . $path);
    return;
}

try {
    $db = Database::connect($config->dbPath());
    $controller = new Controller(
        $config,
        $db,
        new UserRepository($db),
        new SessionService($db, $config->inactivitySeconds(), $config->sessionMaxSeconds()),
        new TokenService($db),
        new Turnstile($config->get('TURNSTILE_SECRET_KEY')),
        new Mailer($config),
        new View($root . '/views'),
    );
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $controller->dispatch($method === 'HEAD' ? 'GET' : $method, $path);
} catch (Throwable $e) {
    error_log('auth.error ' . $e);
    http_response_code(500);
    echo $debug ? '<pre>' . htmlspecialchars((string) $e) . '</pre>' : 'Something went wrong. Please try again later.';
}
