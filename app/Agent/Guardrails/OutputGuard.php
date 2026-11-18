<?php

namespace App\Agent\Guardrails;

use App\Agent\AgentContext;

/**
 * Deterministic checks AFTER the LLM decided. The LLM proposes, the code decides:
 * if the decision breaks a business rule, it is overridden to "escalate_to_human"
 * and the reply is replaced by a safe generic message.
 */
class OutputGuard
{
    public const SAFE_REPLY = 'Thank you for contacting Blumond! A member of our team will check your request and reply to you shortly.';

    /**
     * @param  array<string, mixed>  $resolution
     * @return array{resolution: array<string, mixed>, overrides: array<int, string>}
     */
    public function enforce(array $resolution, AgentContext $ctx): array
    {
        $overrides = [
            ...$this->checkFlags($resolution, $ctx),
            ...$this->checkRepairOrder($resolution, $ctx),
            ...$this->checkWarranty($resolution, $ctx),
            ...$this->checkAmountsInReply($resolution, $ctx),
        ];

        if ($overrides !== []) {
            $resolution['decision'] = 'escalate_to_human';
            $resolution['quoted_price'] = null;
            $resolution['customer_reply'] = self::SAFE_REPLY;
            // The AI's confidence and questions belonged to the decision we just replaced.
            $resolution['confidence'] = 'low';
            $resolution['missing_info'] = [];
        }

        return ['resolution' => $resolution, 'overrides' => $overrides];
    }

    /** Rule 1: red-flag tickets (injection, legal threat, account lock / IMEI) always go to a human. */
    private function checkFlags(array $r, AgentContext $ctx): array
    {
        if ($ctx->flags === [] || $r['decision'] === 'escalate_to_human') {
            return [];
        }

        return ['ticket has red flags ('.implode(', ', $ctx->flags).') and must be handled by a human'];
    }

    /** Rule 2: a repair order needs a quote from the price list, and must use exactly that price. */
    private function checkRepairOrder(array $r, AgentContext $ctx): array
    {
        if ($r['decision'] !== 'create_repair_order') {
            return [];
        }

        $quote = $ctx->quotes[AgentContext::quoteKey($r['device_category'], $r['repair_type'])] ?? null;

        if ($quote === null) {
            return ['repair order without a price list quote for this device and repair'];
        }
        if (! $quote['supported']) {
            return ['repair order for a device Blumond does not repair'];
        }
        if ($quote['requires_human_confirmation']) {
            return ["quote above the limit ({$quote['price']}) must be confirmed by a technician"];
        }

        $expected = $quote['price_type'] === 'diagnosis_required' ? null : $quote['price'];
        $given = $r['quoted_price'] === null ? null : (float) $r['quoted_price'];

        if ($given !== $expected) {
            return ['quoted price ('.json_encode($given).') does not match the price list ('.json_encode($expected).')'];
        }

        return [];
    }

    /** Rule 3: a free warranty repair needs a successful warranty check for that job. */
    private function checkWarranty(array $r, AgentContext $ctx): array
    {
        if ($r['decision'] !== 'warranty_repair') {
            return [];
        }

        $check = $ctx->warranty[strtoupper((string) $r['job_number'])] ?? null;

        if ($check === null) {
            return ['warranty_repair without a warranty check'];
        }
        if (! $check['covered']) {
            return ['warranty_repair but the warranty check failed: '.$check['reason']];
        }
        if (! in_array($r['quoted_price'], [null, 0, 0.0], true)) {
            return ['warranty repair must be free'];
        }

        return [];
    }

    /** Rule 4: every money amount written in the reply must come from a tool result (no invented prices). */
    private function checkAmountsInReply(array $r, AgentContext $ctx): array
    {
        preg_match_all('/€\s?(\d+(?:[.,]\d{1,2})?)|(\d+(?:[.,]\d{1,2})?)\s?(?:€|eur\b|euro)/iu', $r['customer_reply'], $m);

        $unknown = [];
        foreach (array_merge($m[1], $m[2]) as $amount) {
            if ($amount === '') {
                continue;
            }
            $value = round((float) str_replace(',', '.', $amount), 2);
            if ($value != 0 && ! in_array($value, $ctx->knownAmounts)) {
                $unknown[] = $amount;
            }
        }

        return $unknown === [] ? [] : ['reply mentions amounts that no tool returned: '.implode(', ', $unknown)];
    }
}
