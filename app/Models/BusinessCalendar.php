<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessCalendar extends Model
{
    use HasPublicId;

    protected $fillable = ['organization_id', 'branch_id', 'code', 'name_en', 'name_my', 'weekend_days', 'effective_from', 'effective_to', 'lock_version', 'status'];

    protected function casts(): array
    {
        return ['weekend_days' => 'array', 'effective_from' => 'date', 'effective_to' => 'date'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function dates(): HasMany
    {
        return $this->hasMany(BusinessCalendarDate::class)->orderBy('calendar_date');
    }
}
