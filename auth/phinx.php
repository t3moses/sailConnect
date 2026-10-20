<?php

require_once __DIR__ . '/vendor/autoload.php';

// Same source as the web app: auth/.env only. Seeds read SEED_ADMIN_* from $_ENV.
$env = SailConnect\Auth\Config::load(__DIR__);
foreach (['SEED_ADMIN_EMAIL', 'SEED_ADMIN_FIRST_NAME', 'SEED_ADMIN_LAST_NAME', 'SEED_ADMIN_PASSWORD'] as $k) {
    $_ENV[$k] = $env->get($k);
}

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
            'name' => preg_replace('/\.db$/', '', $env->dbPath()),
            'suffix' => '.db',
        ],
    ],
    'version_order' => 'creation',
];
