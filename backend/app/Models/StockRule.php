<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockRule extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['recipient_ids' => 'array', 'active' => 'boolean', 'version' => 'integer', 'threshold' => 'integer'];
    }
}
