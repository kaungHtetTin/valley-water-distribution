<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class MasterImportBatch extends Model
{
    use HasPublicId;

    protected $fillable = ['organization_id', 'actor_user_id', 'master_type', 'source_name', 'status', 'total_rows', 'valid_rows', 'invalid_rows', 'rows', 'errors', 'committed_at'];

    protected function casts(): array
    {
        return ['rows' => 'array', 'errors' => 'array', 'committed_at' => 'datetime'];
    }
}
