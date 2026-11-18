<?php

namespace App\Agent\Guardrails;

/**
 * Cheap deterministic checks on the customer text BEFORE the LLM sees it.
 * It does not block the ticket; it adds flags. A flagged ticket always
 * goes to a human (see OutputGuard), whatever the LLM decides.
 */
class InputGuard
{
    private const INJECTION_PATTERNS = [
        '/ignore (all |any )?(previous|prior|above|your) (instructions|rules)/i',
        '/disregard (the |all |your )?(system|previous) (prompt|instructions)/i',
        '/you are now/i',
        '/system prompt/i',
        '/act as (an? )?(admin|administrator|developer|manager)/i',
        '/<\/?(system|instructions|customer_message)>/i',
        '/(repair|fix) (it |my \w+ )?for free/i',
        '/\b100\s?% (discount|off)\b/i',
    ];

    /** Requests Blumond must never do without proof of ownership (possible stolen device). */
    private const ACCOUNT_LOCK_PATTERNS = [
        '/\b(icloud|apple id|google account|frp)\b.{0,40}\b(bypass|unlock|remove|removal)\b/i',
        '/\b(bypass|unlock|remove)\b.{0,40}\b(icloud|apple id|google account|frp|activation lock)\b/i',
        '/\b(change|repair|new)\b.{0,10}\bimei\b/i',
    ];

    /** @return array<int, string> */
    public function inspect(string $text): array
    {
        $flags = [];

        if ($this->matchesAny(self::INJECTION_PATTERNS, $text)) {
            $flags[] = 'possible_prompt_injection';
        }

        if (preg_match('/\b(lawyer|attorney|sue|lawsuit|court|chargeback|police)\b/i', $text)) {
            $flags[] = 'legal_or_chargeback';
        }

        if ($this->matchesAny(self::ACCOUNT_LOCK_PATTERNS, $text)) {
            $flags[] = 'account_lock_or_imei';
        }

        return $flags;
    }

    /** Removes our own delimiter tags so the customer cannot "close" the data block. */
    public function sanitize(string $text): string
    {
        return preg_replace('/<\/?\s*customer_message\s*>/i', '', $text);
    }

    private function matchesAny(array $patterns, string $text): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }
}
