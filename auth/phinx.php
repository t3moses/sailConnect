<?php

require_once __DIR__ . '/vendor/autoload.php';

if (file_exists(__DIR__ . '/.env')) {
    Dotenv\Dotenv::createImmutable(__DIR__)->safeLoad();
}

$dbPath = $_ENV['DB_PATH'] ?? './database/auth.db';
$name = preg_replace('/\.db$/', '', $dbPath);

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/database/migrations',
        'seeds' => '%%PHINX_CONFIG_DIR%%/database/seeds',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'main',
        'main' => [
            'adapter' => 'sqlite',
            'name' => $name,
            'suffix' => '.db',
        ],
    ],
    'version_order' => 'creation',
];
