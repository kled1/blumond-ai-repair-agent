<?php

namespace App\Agent\Tools;

use App\Agent\AgentContext;
use App\Models\Repair;

/**
 * Lists the customer's repair jobs (current and past).
 * Security: only jobs that belong to the phone number of the ticket are returned,
 * even if the message (or a prompt injection) asks for another job number.
 */
class LookupRepairsTool extends Tool
{
    public function name(): string
    {
        return 'lookup_repairs';
    }

    protected function description(): string
    {
        return 'Look up the repair jobs of the customer who sent this message (status, device, price, ready date, pickup date). '
            .'Pass a job number (format REP-1234) to get one job, or null to list all of this customer\'s jobs. '
            .'Only jobs that belong to this customer are returned.';
    }

    protected function properties(): array
    {
        return [
            'job_number' => ['type' => ['string', 'null'], 'description' => 'Job number from the receipt, e.g. REP-1001, or null for all jobs'],
        ];
    }

    protected function rules(): array
    {
        return ['job_number' => ['nullable', 'string', 'regex:/^REP-\d{3,10}$/i']];
    }

    protected function handle(array $input, AgentContext $ctx): array
    {
        $query = Repair::where('customer_phone', $ctx->customerPhone())->latest('id');

        if ($input['job_number'] ?? null) {
            $query->where('job_number', strtoupper($input['job_number']));
        }

        $jobs = $query->limit(5)->get();

        // Same answer for "does not exist" and "belongs to someone else" -> no data leak.
        if ($jobs->isEmpty()) {
            return ['found' => false, 'message' => 'No repair jobs found for this customer'.(($input['job_number'] ?? null) ? ' with this job number.' : '.')];
        }

        return [
            'found' => true,
            'jobs' => $jobs->map(function (Repair $r) use ($ctx) {
                $ctx->rememberAmount((float) $r->price);

                return [
                    'job_number' => $r->job_number,
                    'customer_name' => $r->customer_name,
                    'device' => "{$r->device_brand} {$r->device_model} ({$r->device_category})",
                    'repair_type' => $r->repair_type,
                    'status' => $r->status,
                    'price' => (float) $r->price,
                    'estimated_ready_at' => $r->estimated_ready_at?->toDateString(),
                    'picked_up_at' => $r->picked_up_at?->toDateString(),
                ];
            })->all(),
        ];
    }
}
