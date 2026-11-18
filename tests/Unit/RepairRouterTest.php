<?php

namespace Tests\Unit;

use App\Agent\Rules\RepairRouter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RepairRouterTest extends TestCase
{
    public static function cases(): array
    {
        return [
            'phone screen' => ['phone', 'screen', 'mobile_bench'],
            'tablet battery' => ['tablet', 'battery', 'mobile_bench'],
            'laptop keyboard' => ['laptop', 'keyboard', 'computer_bench'],
            'console port' => ['console', 'port', 'console_bench'],
            'water damage on any device' => ['laptop', 'water_damage', 'board_level'],
            'phone not turning on' => ['phone', 'no_power', 'board_level'],
            'data recovery' => ['phone', 'data_recovery', 'data_recovery'],
            'software' => ['desktop', 'software', 'software_desk'],
            'unknown device' => ['other', 'other', 'front_desk'],
        ];
    }

    #[DataProvider('cases')]
    public function test_routes_device_and_problem_to_the_right_bench(string $category, string $type, string $queue): void
    {
        $this->assertSame($queue, (new RepairRouter)->queue($category, $type));
    }
}
