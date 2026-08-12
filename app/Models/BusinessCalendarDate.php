<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessCalendarDate extends Model
{
    use HasPublicId;

    protected $fillable = ['organization_id', 'business_calendar_id', 'calendar_date', 'day_type', 'name_en', 'name_my'];

    protected function casts(): array
    {
        return ['calendar_date' => 'date'];
    }

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(BusinessCalendar::class, 'business_calendar_id');
    }
}
