<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class FiscalPeriod extends Model
{
    use HasPublicId;

    protected $fillable = ['organization_id', 'code', 'name', 'starts_on', 'ends_on', 'lock_version', 'status'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }
}
