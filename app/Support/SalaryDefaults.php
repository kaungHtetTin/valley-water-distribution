<?php

namespace App\Support;

class SalaryDefaults
{
    public const AMOUNTS = [
        'office' => 450000,
        'sales' => 380000,
        'sales_supervisor' => 450000,
        'driver' => 360000,
        'warehouse' => 330000,
        'other' => 300000,
    ];

    public static function forEmployeeType(string $type, array $defaults): float
    {
        return (float) ($defaults[$type] ?? $defaults['other'] ?? self::AMOUNTS['other']);
    }
}
