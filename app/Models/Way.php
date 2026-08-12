<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Way extends Model
{
    use HasPublicId;

    protected $fillable = ['organization_id', 'code', 'name_en', 'name_my', 'description', 'lock_version', 'status'];

    public function versions(): HasMany
    {
        return $this->hasMany(WayVersion::class);
    }

    public function currentVersion(): HasOne
    {
        return $this->hasOne(WayVersion::class)->latestOfMany('version');
    }

    public function routeTemplates(): BelongsToMany
    {
        return $this->belongsToMany(RouteTemplate::class, 'route_template_ways')->withPivot('sequence');
    }
}
