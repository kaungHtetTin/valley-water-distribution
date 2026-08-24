<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialTransaction extends Model
{
    protected $guarded = [];

    protected $casts = ['transaction_date' => 'date', 'amount' => 'decimal:2'];
}
