<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientContact extends Model
{
    use HasPublicId;

    protected $fillable = ['organization_id', 'client_account_id', 'client_outlet_id', 'name', 'phone', 'phone_normalized', 'email', 'is_primary_ordering', 'status'];

    protected function casts(): array
    {
        return ['is_primary_ordering' => 'boolean'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ClientAccount::class, 'client_account_id');
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(ClientOutlet::class, 'client_outlet_id');
    }
}
