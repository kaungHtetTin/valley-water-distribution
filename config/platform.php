<?php

return [
    'business_timezone' => env('BUSINESS_TIMEZONE', 'Asia/Yangon'),
    'currency' => env('BUSINESS_CURRENCY', 'MMK'),
    'locales' => ['en', 'my-MM'],
    'default_locale' => env('APP_LOCALE', 'en'),
    'development_organization_code' => env('DEVELOPMENT_ORGANIZATION_CODE', 'VALLEY'),

    'applications' => [
        'office' => '/office',
        'sales' => '/sales',
        'driver' => '/driver',
        'client' => '/client',
    ],

    'features' => [
        'authentication' => env('FEATURE_AUTHENTICATION', false),
        'master_data' => env('FEATURE_MASTER_DATA', false),
        'customer_sales' => env('FEATURE_CUSTOMER_SALES', false),
        'ordering' => env('FEATURE_ORDERING', false),
        'warehouse' => env('FEATURE_WAREHOUSE', false),
        'finance' => env('FEATURE_FINANCE', false),
        'payroll' => env('FEATURE_PAYROLL', false),
        'delivery' => env('FEATURE_DELIVERY', false),
        'gps_tracking' => env('FEATURE_GPS_TRACKING', false),
        'reports' => env('FEATURE_REPORTS', false),
        'executive_dashboard' => env('FEATURE_EXECUTIVE_DASHBOARD', false),
    ],
];
