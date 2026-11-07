<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NativeDevice extends Model
{
    protected $guarded = [];

    protected $hidden = ['fcm_token', 'tracking_token_hash', 'session_hash'];

    protected $casts = [
        'fcm_token' => 'encrypted',
        'expires_at' => 'datetime',
        'tracking_expires_at' => 'datetime',
        'alert_state' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
