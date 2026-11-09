<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockAlert extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['open' => 'boolean', 'resolved_at' => 'immutable_datetime'];
    }
}
