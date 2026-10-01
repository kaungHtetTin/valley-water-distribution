<?php

return [
    'initial_admin' => [
        'name' => env('VALLEY_ADMIN_NAME', 'Administrator'),
        'email' => env('VALLEY_ADMIN_EMAIL'),
        'password' => env('VALLEY_ADMIN_PASSWORD'),
    ],
    'slow_query_ms' => (int) env('VALLEY_SLOW_QUERY_MS', 250),
    'backup_retention_days' => (int) env('VALLEY_BACKUP_RETENTION_DAYS', 14),
    'backup_directory' => env('VALLEY_BACKUP_DIRECTORY', storage_path('app/backups')),
    'mysqldump_path' => env('MYSQLDUMP_PATH'),
    'mysql_path' => env('MYSQL_PATH'),
    'demo_endpoints' => (bool) env('VALLEY_DEMO_ENDPOINTS', in_array(env('APP_ENV', 'production'), ['local', 'testing'], true)),
];
