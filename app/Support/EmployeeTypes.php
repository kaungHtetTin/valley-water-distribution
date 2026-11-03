<?php

namespace App\Support;

class EmployeeTypes
{
    public const LABELS = [
        'sale_helper' => 'Sale Helper',
        'sales' => 'Sale Representative',
        'warehouse' => 'Storekeeper',
        'accountant' => 'Accountant',
        'manager' => 'Manager',
        'sales_supervisor' => 'Sale Manager',
        'hr' => 'HR',
        'driver' => 'Driver',
        'other' => 'Other',
    ];

    public const LOGIN_TYPES = ['sales', 'sales_supervisor', 'driver'];

    public const KPI_TEMPLATES = [
        'sale_helper' => 'SALE-HELPER-V1',
        'sales' => 'SALES-REP-V1',
        'warehouse' => 'STOREKEEPER-V1',
        'accountant' => 'ACCOUNTANT-V1',
        'manager' => 'MANAGER-V1',
        'sales_supervisor' => 'SALES-SUPERVISOR-V1',
        'hr' => 'HR-V1',
        'driver' => 'DRIVER-V1',
        'other' => 'OFFICE-STAFF-V1',
    ];
}
