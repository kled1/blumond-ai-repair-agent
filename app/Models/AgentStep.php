<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentStep extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'output' => 'array',
            'is_error' => 'boolean',
        ];
    }
}
