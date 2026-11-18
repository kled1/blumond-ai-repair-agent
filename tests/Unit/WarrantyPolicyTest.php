<?php

namespace Tests\Unit;

use App\Agent\Rules\WarrantyPolicy;
use App\Models\Repair;
use Tests\TestCase;

class WarrantyPolicyTest extends TestCase
{
    /** Not saved to the database: the policy only reads attributes. */
    private function repair(array $attrs = []): Repair
    {
        return new Repair(array_merge([
            'job_number' => 'REP-9000', 'repair_type' => 'battery', 'status' => 'picked_up', 'picked_up_at' => now()->subDays(40),
        ], $attrs));
    }

    public function test_same_problem_within_90_days_is_covered(): void
    {
        $this->assertTrue((new WarrantyPolicy)->evaluate($this->repair(), 'battery', 'normal_use')['covered']);
    }

    public function test_warranty_expires_after_90_days(): void
    {
        $this->assertFalse((new WarrantyPolicy)->evaluate($this->repair(['picked_up_at' => now()->subDays(91)]), 'battery', 'normal_use')['covered']);
    }

    public function test_only_the_repaired_part_is_covered(): void
    {
        $this->assertFalse((new WarrantyPolicy)->evaluate($this->repair(), 'screen', 'normal_use')['covered']);
    }

    public function test_drops_and_liquid_are_not_covered(): void
    {
        $policy = new WarrantyPolicy;

        $this->assertFalse($policy->evaluate($this->repair(), 'battery', 'accidental_damage')['covered']);
        $this->assertFalse($policy->evaluate($this->repair(), 'battery', 'liquid')['covered']);
    }

    public function test_device_still_in_the_shop_has_no_warranty_yet(): void
    {
        $this->assertFalse((new WarrantyPolicy)->evaluate($this->repair(['status' => 'in_repair', 'picked_up_at' => null]), 'battery', 'normal_use')['covered']);
    }
}
