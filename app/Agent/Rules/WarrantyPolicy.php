<?php

namespace App\Agent\Rules;

use App\Models\Repair;

/**
 * Deterministic warranty rules: the repaired part is covered for 90 days after pickup,
 * for the same problem, unless the new damage was caused by a drop or liquid.
 */
class WarrantyPolicy
{
    /** @return array{covered: bool, reason: string} */
    public function evaluate(Repair $repair, string $repairType, string $damageCause): array
    {
        if ($repair->status !== 'picked_up' || $repair->picked_up_at === null) {
            return $this->result(false, "Job {$repair->job_number} is still in the shop (status: {$repair->status}); warranty starts at pickup.");
        }

        $days = (int) $repair->picked_up_at->diffInDays(now());
        $limit = (int) config('agent.warranty_days');

        if ($days > $limit) {
            return $this->result(false, "Picked up {$days} days ago; the warranty lasts {$limit} days.");
        }

        if ($repairType !== $repair->repair_type) {
            return $this->result(false, "The warranty covers only the repaired part ({$repair->repair_type}), not '{$repairType}'.");
        }

        if (in_array($damageCause, ['accidental_damage', 'liquid'], true)) {
            return $this->result(false, 'The warranty does not cover new accidental or liquid damage.');
        }

        return $this->result(true, "Covered: same repair ({$repairType}), picked up {$days} days ago (limit {$limit}). Free of charge; the technician confirms there is no physical or liquid damage at inspection.");
    }

    private function result(bool $covered, string $reason): array
    {
        return ['covered' => $covered, 'reason' => $reason];
    }
}
