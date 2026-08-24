<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $guarded = [];

    protected $casts = ['expense_date' => 'date', 'amount' => 'decimal:2', 'reviewed_at' => 'datetime'];
}
