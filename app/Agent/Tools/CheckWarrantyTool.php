<?php

namespace App\Agent\Tools;

use App\Agent\AgentContext;
use App\Agent\Rules\Catalog;
use App\Agent\Rules\WarrantyPolicy;
use App\Models\Repair;

/**
 * Runs the deterministic warranty rules and stores the result in the context,
 * so the OutputGuard can verify any "warranty_repair" (free repair) decision.
 */
class CheckWarrantyTool extends Tool
{
    public function __construct(private readonly WarrantyPolicy $policy) {}

    public function name(): string
    {
        return 'check_warranty';
    }

    protected function description(): string
    {
        return 'Check if a problem is covered by the warranty of a previous Blumond repair. You MUST call this before deciding warranty_repair.';
    }

    protected function properties(): array
    {
        return [
            'job_number' => ['type' => 'string', 'description' => 'Job number of the previous repair, e.g. REP-1001'],
            'repair_type' => ['type' => 'string', 'enum' => Catalog::REPAIR_TYPES, 'description' => 'The problem the customer has now'],
            'damage_cause' => ['type' => 'string', 'enum' => Catalog::DAMAGE_CAUSES, 'description' => 'What caused the new problem, as described by the customer'],
        ];
    }

    protected function rules(): array
    {
        return [
            'job_number' => ['required', 'string', 'regex:/^REP-\d{3,10}$/i'],
            'repair_type' => ['required', 'in:'.implode(',', Catalog::REPAIR_TYPES)],
            'damage_cause' => ['required', 'in:'.implode(',', Catalog::DAMAGE_CAUSES)],
        ];
    }

    protected function handle(array $input, AgentContext $ctx): array
    {
        $number = strtoupper($input['job_number']);
        $repair = Repair::where('job_number', $number)->where('customer_phone', $ctx->customerPhone())->first();

        if (! $repair) {
            return ['covered' => false, 'reason' => 'No repair job with this number was found for this customer.'];
        }

        $result = $this->policy->evaluate($repair, $input['repair_type'], $input['damage_cause']);
        $ctx->warranty[$number] = $result;

        return $result;
    }
}
