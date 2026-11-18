<?php

namespace App\Agent\Rules;

/**
 * Routing: which technician bench gets the job. Pure code, not the LLM.
 * The LLM only classifies the device and the problem; this table decides where it goes.
 */
class RepairRouter
{
    public const QUEUES = ['mobile_bench', 'computer_bench', 'console_bench', 'board_level', 'data_recovery', 'software_desk', 'front_desk'];

    public function queue(string $category, ?string $repairType): string
    {
        return match (true) {
            $repairType === 'data_recovery' => 'data_recovery',
            in_array($repairType, ['water_damage', 'no_power'], true) => 'board_level',   // micro-soldering, diagnostics
            $repairType === 'software' => 'software_desk',
            in_array($category, ['laptop', 'desktop'], true) => 'computer_bench',
            $category === 'console' => 'console_bench',
            in_array($category, ['phone', 'tablet', 'smartwatch'], true) => 'mobile_bench',
            default => 'front_desk',                                                       // a person decides
        };
    }
}
