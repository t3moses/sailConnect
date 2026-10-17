<?php
/**
 * Phinx Configuration File
 *
 * This file configures Phinx for database migrations in the Sail Friday project.
 *
 * Documentation: https://book.cakephp.org/phinx/0/en/configuration.html
 */

require_once __DIR__ . '/vendor/autoload.php';

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/database/migrations',
        'seeds' => '%%PHINX_CONFIG_DIR%%/database/seeds'
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'development',
        'development' => [
            'adapter' => 'sqlite',
            'name' => './database/sailFriday',
            'suffix' => '.db'
        ],
        'testing' => [
            'adapter' => 'sqlite',
            'name' => ':memory:'
        ],
        'production' => [
            'adapter' => 'sqlite',
            'name' => getenv('DB_PATH') ?: './database/sailFriday',
            'suffix' => '.db'
        ]
    ],
    'version_order' => 'creation'
];
