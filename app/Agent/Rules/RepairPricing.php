<?php

namespace App\Agent\Rules;

use App\Models\RepairPrice;

/**
 * Deterministic price quotes from the price list. The LLM never invents a price:
 * it must call get_repair_quote, which runs this code, and the OutputGuard checks
 * that the final answer uses exactly this price.
 */
class RepairPricing
{
    public function __construct(private readonly RepairRouter $router) {}

    /**
     * @return array{supported: bool, price_type: string|null, price: float|null, currency: string, diagnostic_fee: float|null,
     *               turnaround_days: int|null, requires_human_confirmation: bool, queue: string|null, note: string}
     */
    public function quote(string $category, ?string $brand, ?string $model, string $repairType): array
    {
        if ($category === 'other') {
            return $this->result(false, null, null, null, false, null, 'Blumond does not repair this type of device.');
        }

        $queue = $this->router->queue($category, $repairType);

        if (in_array($repairType, Catalog::DIAGNOSIS_FIRST, true)) {
            return $this->result(true, 'diagnosis_required', null, 2, false, $queue,
                'A technician must diagnose the device before giving a price. The diagnostic fee is waived if the customer goes ahead with the repair.');
        }

        $row = $this->findPrice($category, $brand, $model, $repairType);

        if ($row === null) {
            return $this->result(true, 'diagnosis_required', null, 2, false, $queue,
                'No list price for this repair. A technician must inspect the device first.');
        }

        // Exact model match = fixed price. Category-level row = "from" price (final price depends on the model).
        $priceType = $row->device_model !== null ? 'fixed' : 'from';
        $price = (float) $row->price;
        $needsHuman = $price > config('agent.human_confirmation_above');

        $note = $priceType === 'fixed'
            ? 'Fixed price for this model (parts and labour).'
            : 'Starting price for this kind of device; the final price depends on the exact model.';
        if ($needsHuman) {
            $note .= ' Above '.config('agent.human_confirmation_above').' a technician must confirm the quote before it is sent.';
        }

        return $this->result(true, $priceType, $price, $row->turnaround_days, $needsHuman, $queue, $note);
    }

    /** Most specific row wins: model -> brand -> category -> any device. */
    private function findPrice(string $category, ?string $brand, ?string $model, string $repairType): ?RepairPrice
    {
        $normalize = fn (?string $v) => $v === null ? null : str_replace(' ', '', strtolower($v));
        $brand = $normalize($brand);
        $model = $normalize($model);

        $rows = RepairPrice::where('repair_type', $repairType)
            ->where(fn ($q) => $q->where('device_category', $category)->orWhereNull('device_category'))
            ->get();

        $candidates = [
            fn ($r) => $r->device_category === $category && $normalize($r->device_brand) === $brand && $normalize($r->device_model) === $model && $model !== null,
            fn ($r) => $r->device_category === $category && $normalize($r->device_brand) === $brand && $r->device_model === null && $brand !== null,
            fn ($r) => $r->device_category === $category && $r->device_brand === null,
            fn ($r) => $r->device_category === null,
        ];

        foreach ($candidates as $matches) {
            if ($found = $rows->first($matches)) {
                return $found;
            }
        }

        return null;
    }

    private function result(bool $supported, ?string $priceType, ?float $price, ?int $days, bool $needsHuman, ?string $queue, string $note): array
    {
        return [
            'supported' => $supported,
            'price_type' => $priceType,
            'price' => $price,
            'currency' => config('agent.currency'),
            'diagnostic_fee' => $priceType === 'diagnosis_required' ? (float) config('agent.diagnostic_fee') : null,
            'turnaround_days' => $days,
            'requires_human_confirmation' => $needsHuman,
            'queue' => $queue,
            'note' => $note,
        ];
    }
}
