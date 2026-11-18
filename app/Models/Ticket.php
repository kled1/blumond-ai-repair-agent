<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'result' => 'array',
            'guardrail_flags' => 'array',
        ];
    }

    public function steps(): HasMany
    {
        return $this->hasMany(AgentStep::class)->orderBy('sequence');
    }
}
