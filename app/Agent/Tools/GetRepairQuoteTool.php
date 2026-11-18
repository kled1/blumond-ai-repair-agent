<?php

namespace App\Agent\Tools;

use App\Agent\AgentContext;
use App\Agent\Rules\Catalog;
use App\Agent\Rules\RepairPricing;

/**
 * Price + turnaround + technician queue from the price list (deterministic).
 * The quote is stored in the context so the OutputGuard can check the final price.
 */
class GetRepairQuoteTool extends Tool
{
    public function __construct(private readonly RepairPricing $pricing) {}

    public function name(): string
    {
        return 'get_repair_quote';
    }

    protected function description(): string
    {
        return 'Get the price, turnaround time and technician queue for a repair from the Blumond price list. '
            .'You MUST call this before telling the customer any price or creating a repair order. Never invent prices.';
    }

    protected function properties(): array
    {
        return [
            'device_category' => ['type' => 'string', 'enum' => Catalog::DEVICE_CATEGORIES],
            'device_brand' => ['type' => ['string', 'null'], 'description' => 'e.g. Apple, Samsung, Lenovo; null if unknown'],
            'device_model' => ['type' => ['string', 'null'], 'description' => 'e.g. iPhone 13, Galaxy S21; null if unknown'],
            'repair_type' => ['type' => 'string', 'enum' => Catalog::REPAIR_TYPES],
        ];
    }

    protected function rules(): array
    {
        return [
            'device_category' => ['required', 'in:'.implode(',', Catalog::DEVICE_CATEGORIES)],
            'device_brand' => ['nullable', 'string', 'max:50'],
            'device_model' => ['nullable', 'string', 'max:80'],
            'repair_type' => ['required', 'in:'.implode(',', Catalog::REPAIR_TYPES)],
        ];
    }

    protected function handle(array $input, AgentContext $ctx): array
    {
        $quote = $this->pricing->quote($input['device_category'], $input['device_brand'] ?? null, $input['device_model'] ?? null, $input['repair_type']);

        $ctx->quotes[AgentContext::quoteKey($input['device_category'], $input['repair_type'])] = $quote;
        $ctx->rememberAmount($quote['price']);
        $ctx->rememberAmount($quote['diagnostic_fee']);

        return $quote;
    }
}
