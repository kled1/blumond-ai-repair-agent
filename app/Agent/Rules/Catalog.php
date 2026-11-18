<?php

namespace App\Agent\Rules;

/**
 * The fixed vocabulary shared by the tools, the JSON schemas and the rules.
 * The LLM must pick from these lists (enums), so the code can make decisions on them.
 */
final class Catalog
{
    /** "other" = a device Blumond does not repair (fridge, car, ...). */
    public const DEVICE_CATEGORIES = ['phone', 'tablet', 'laptop', 'desktop', 'console', 'smartwatch', 'other'];

    public const REPAIR_TYPES = [
        'screen', 'battery', 'port', 'camera', 'audio', 'keyboard',
        'software', 'water_damage', 'no_power', 'data_recovery', 'other',
    ];

    /** Problems where nobody can name a price before a technician opens the device. */
    public const DIAGNOSIS_FIRST = ['water_damage', 'no_power', 'data_recovery', 'other'];

    public const DAMAGE_CAUSES = ['normal_use', 'accidental_damage', 'liquid', 'unknown'];
}
