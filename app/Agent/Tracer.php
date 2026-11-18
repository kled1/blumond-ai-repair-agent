<?php

namespace App\Agent;

use App\Models\Ticket;
use Illuminate\Support\Facades\Log;

/**
 * Observability: writes every step to the agent_steps table (shown in the UI)
 * and to the "agent" log channel (storage/logs/agent.log).
 */
class Tracer
{
    private int $sequence = 0;

    public function __construct(private readonly Ticket $ticket) {}

    public function record(string $type, string $name, mixed $input = null, mixed $output = null, bool $isError = false, ?int $durationMs = null): void
    {
        $this->sequence++;

        $this->ticket->steps()->create([
            'sequence' => $this->sequence,
            'type' => $type,
            'name' => $name,
            'input' => $input,
            'output' => $output,
            'is_error' => $isError,
            'duration_ms' => $durationMs,
        ]);

        Log::channel('agent')->info("[ticket {$this->ticket->id}] #{$this->sequence} {$type}:{$name}", [
            'input' => $input,
            'output' => $output,
            'is_error' => $isError,
            'duration_ms' => $durationMs,
        ]);
    }
}
