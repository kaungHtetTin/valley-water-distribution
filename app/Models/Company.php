<?php

namespace App\Models;

use App\Support\SalaryDefaults;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $appends = ['logo_url'];

    protected $casts = [
        'is_active' => 'boolean',
        'default_customer_credit_limit' => 'decimal:2',
        'delivery_credit_due_days' => 'integer',
        'print_settings' => 'array',
        'contact_channels' => 'array',
        'default_base_salaries' => 'array',
    ];

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? asset('storage/'.$this->logo_path) : null;
    }

    public function getDefaultBaseSalariesAttribute($value): array
    {
        return array_replace(SalaryDefaults::AMOUNTS, $value ? json_decode($value, true) : []);
    }
}
