<?php

namespace Tests\Unit;

use App\Agent\Rules\RepairPricing;
use Database\Seeders\RepairPriceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepairPricingTest extends TestCase
{
    use RefreshDatabase;

    private RepairPricing $pricing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RepairPriceSeeder::class);
        $this->pricing = app(RepairPricing::class);
    }

    public function test_exact_model_gets_fixed_price_and_is_matched_case_insensitively(): void
    {
        $q = $this->pricing->quote('phone', 'apple', 'iphone13', 'screen');

        $this->assertSame('fixed', $q['price_type']);
        $this->assertSame(129.0, $q['price']);
        $this->assertSame('mobile_bench', $q['queue']);
        $this->assertFalse($q['requires_human_confirmation']);
    }

    public function test_unknown_model_falls_back_to_category_from_price(): void
    {
        $q = $this->pricing->quote('laptop', 'Lenovo', 'IdeaPad 5', 'keyboard');

        $this->assertSame('from', $q['price_type']);
        $this->assertSame(79.0, $q['price']);
        $this->assertSame('computer_bench', $q['queue']);
    }

    public function test_software_price_applies_to_any_device(): void
    {
        $this->assertSame(35.0, $this->pricing->quote('console', null, null, 'software')['price']);
    }

    public function test_water_damage_needs_diagnosis_and_has_no_price(): void
    {
        $q = $this->pricing->quote('phone', 'Samsung', 'Galaxy A52', 'water_damage');

        $this->assertSame('diagnosis_required', $q['price_type']);
        $this->assertNull($q['price']);
        $this->assertSame(15.0, $q['diagnostic_fee']);
        $this->assertSame('board_level', $q['queue']);
    }

    public function test_expensive_quote_requires_human_confirmation(): void
    {
        $q = $this->pricing->quote('laptop', 'Apple', 'MacBook Pro 14', 'screen');

        $this->assertSame(389.0, $q['price']);
        $this->assertTrue($q['requires_human_confirmation']);
    }

    public function test_unsupported_device_is_rejected(): void
    {
        $this->assertFalse($this->pricing->quote('other', 'Bosch', 'Washing machine', 'other')['supported']);
    }
}
