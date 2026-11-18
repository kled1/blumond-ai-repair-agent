<?php

namespace App\Agent;

use App\Models\Ticket;

/**
 * State of ONE agent run. Tools read from it (who is the customer?)
 * and write facts into it (which price did the price list give? is it under warranty?).
 * The OutputGuard uses these facts instead of trusting the LLM.
 */
class AgentContext
{
    /** @var array<string, array<string, mixed>> quotes keyed by "category|repair_type" */
    public array $quotes = [];

    /** @var array<string, array{covered: bool, reason: string}> warranty checks keyed by job number */
    public array $warranty = [];

    /** @var array<int, float> every money amount a tool returned; the reply may only mention these */
    public array $knownAmounts = [];

    /** @var array<int, string> */
    public array $flags = [];

    public function __construct(
        public readonly Ticket $ticket,
        public readonly Tracer $tracer,
    ) {}

    public function customerPhone(): string
    {
        return $this->ticket->customer_phone;
    }

    public function rememberAmount(?float $amount): void
    {
        if ($amount !== null) {
            $this->knownAmounts[] = round($amount, 2);
        }
    }

    public static function quoteKey(string $category, string $repairType): string
    {
        return "{$category}|{$repairType}";
    }
}
