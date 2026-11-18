<?php

namespace App\Console\Commands;

use App\Agent\SupportAgent;
use App\Models\Ticket;
use Illuminate\Console\Command;

/**
 * Run the agent from the terminal on a sample file:
 *   php artisan agent:run samples/01-new-repair-fixed-price.json
 */
class RunAgent extends Command
{
    protected $signature = 'agent:run {file : Path to a JSON file with channel, customer_phone, customer_name, message}';

    protected $description = 'Run the repair intake agent on a sample message and print the trace + result';

    public function handle(SupportAgent $agent): int
    {
        $data = json_decode((string) @file_get_contents($this->argument('file')), true);

        if (! is_array($data) || ! isset($data['channel'], $data['customer_phone'], $data['message'])) {
            $this->error('File must be JSON with channel, customer_phone and message.');

            return self::FAILURE;
        }

        $ticket = $agent->handle(Ticket::create([
            'channel' => $data['channel'],
            'customer_phone' => $data['customer_phone'],
            'customer_name' => $data['customer_name'] ?? null,
            'message' => $data['message'],
        ]));

        $this->info('--- Trace ---');
        foreach ($ticket->steps as $step) {
            $this->line(sprintf('#%d %-10s %-22s %s', $step->sequence, $step->type, $step->name, $step->is_error ? '(error)' : ''));
        }

        $this->info("\n--- Summary ---");
        $this->line($ticket->summary);

        $this->info("\n--- JSON result ---");
        $this->line(json_encode($ticket->result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        if (isset($data['expected'])) {
            $ok = $ticket->result['decision'] === $data['expected']['decision'] && $ticket->status === $data['expected']['status'];
            $this->newLine();
            $ok ? $this->info("MATCHES expected: {$data['expected']['decision']} / {$data['expected']['status']}")
                : $this->warn("DIFFERS from expected: {$data['expected']['decision']} / {$data['expected']['status']}");
        }

        return self::SUCCESS;
    }
}
