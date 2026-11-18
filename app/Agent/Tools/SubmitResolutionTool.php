<?php

namespace App\Agent\Tools;

use App\Agent\AgentContext;
use App\Agent\Rules\Catalog;

/**
 * The "final answer" tool. Claude calls it once at the end with the
 * structured result. Its schema IS the JSON output contract of the agent.
 * It is not executed like the others – the agent validates it and finishes.
 */
class SubmitResolutionTool extends Tool
{
    public const CATEGORIES = ['new_repair', 'repair_status', 'warranty_claim', 'question', 'complaint', 'other'];

    public const DECISIONS = ['create_repair_order', 'warranty_repair', 'answer_question', 'request_more_info', 'escalate_to_human', 'reject_out_of_scope'];

    public function name(): string
    {
        return 'submit_resolution';
    }

    protected function description(): string
    {
        return 'Submit the final resolution for the message. Call this exactly once, as your last action.';
    }

    protected function properties(): array
    {
        return [
            'category' => ['type' => 'string', 'enum' => self::CATEGORIES],
            'decision' => ['type' => 'string', 'enum' => self::DECISIONS],
            'device_category' => self::nullableEnum(Catalog::DEVICE_CATEGORIES),
            'device_brand' => ['type' => ['string', 'null']],
            'device_model' => ['type' => ['string', 'null']],
            'repair_type' => self::nullableEnum(Catalog::REPAIR_TYPES),
            'job_number' => ['type' => ['string', 'null'], 'description' => 'Related existing job number (REP-1234) or null'],
            'quoted_price' => ['type' => ['number', 'null'], 'description' => 'Price from get_repair_quote (0 for warranty repairs); null if a diagnosis is needed or no price applies'],
            'customer_reply' => ['type' => 'string', 'description' => 'Short, friendly message to send to the customer (WhatsApp style), in the customer\'s language'],
            'internal_summary' => ['type' => 'string', 'description' => '1-3 sentences for the Blumond staff: what was asked, what you checked, why this decision'],
            'missing_info' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'What we still need from the customer (for request_more_info), otherwise []'],
            'policy_references' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Service documents used, e.g. ["warranty.md"]'],
            'confidence' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
        ];
    }

    /** "One of these values, or null". The API's strict mode needs anyOf for this (enum + ['string','null'] is rejected). */
    private static function nullableEnum(array $values): array
    {
        return ['anyOf' => [['type' => 'string', 'enum' => $values], ['type' => 'null']]];
    }

    protected function rules(): array
    {
        $needsDevice = 'required_if:decision,create_repair_order,warranty_repair';

        return [
            'category' => ['required', 'in:'.implode(',', self::CATEGORIES)],
            'decision' => ['required', 'in:'.implode(',', self::DECISIONS)],
            'device_category' => [$needsDevice, 'nullable', 'in:'.implode(',', Catalog::DEVICE_CATEGORIES)],
            'device_brand' => ['nullable', 'string', 'max:50'],
            'device_model' => ['nullable', 'string', 'max:80'],
            'repair_type' => [$needsDevice, 'nullable', 'in:'.implode(',', Catalog::REPAIR_TYPES)],
            'job_number' => ['required_if:decision,warranty_repair', 'nullable', 'string', 'regex:/^REP-\d{3,10}$/i'],
            'quoted_price' => ['nullable', 'numeric', 'min:0'],
            'customer_reply' => ['required', 'string', 'min:20', 'max:2000'],
            'internal_summary' => ['required', 'string', 'min:10', 'max:1000'],
            'missing_info' => ['present', 'array', 'required_if:decision,request_more_info'],
            'missing_info.*' => ['string'],
            'policy_references' => ['present', 'array'],
            'policy_references.*' => ['string'],
            'confidence' => ['required', 'in:low,medium,high'],
        ];
    }

    protected function handle(array $input, AgentContext $ctx): array
    {
        return $input;
    }
}
