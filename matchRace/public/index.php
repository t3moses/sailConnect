<?php

declare(strict_types=1);

// Let the PHP built-in server serve static files (css, js, assets) directly.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (PHP_SAPI === 'cli-server' && $path !== '/' && is_file(__DIR__ . $path)) {
    return false;
}

require __DIR__ . '/../vendor/autoload.php';

(new MatchRace\App(dirname(__DIR__)))->handle($_SERVER['REQUEST_METHOD'], $path);
