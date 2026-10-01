<?php

namespace App\Models;

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
    ];

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? asset('storage/'.$this->logo_path) : null;
    }
}
