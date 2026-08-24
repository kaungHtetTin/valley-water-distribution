<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Collection extends Model
{
    protected $guarded = [];

    protected $casts = ['collection_date' => 'date', 'amount' => 'decimal:2', 'reviewed_at' => 'datetime'];
}
