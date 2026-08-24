<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierLedgerEntry extends Model
{
    protected $guarded = [];

    protected $casts = ['entry_date' => 'date', 'debit' => 'decimal:2', 'credit' => 'decimal:2'];
}
