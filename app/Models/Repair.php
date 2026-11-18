<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Repair extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'estimated_ready_at' => 'date',
            'picked_up_at' => 'datetime',
        ];
    }
}
