<?php

namespace Database\Seeders;

use App\Models\RepairPrice;
use Illuminate\Database\Seeder;

/**
 * Dummy price list (not Blumond's real prices).
 * Exact model rows give a fixed price; brand/model NULL rows are "from" prices for the category.
 */
class RepairPriceSeeder extends Seeder
{
    public function run(): void
    {
        $prices = [
            // [category, brand, model, repair_type, price, turnaround_days]
            ['phone', 'Apple', 'iPhone 13', 'screen', 129, 1],
            ['phone', 'Apple', 'iPhone 13', 'battery', 79, 0],
            ['phone', 'Apple', 'iPhone 12', 'screen', 119, 1],
            ['phone', 'Apple', 'iPhone 12', 'battery', 69, 0],
            ['phone', 'Samsung', 'Galaxy S21', 'screen', 149, 2],
            ['phone', null, null, 'screen', 89, 2],
            ['phone', null, null, 'battery', 49, 1],
            ['phone', null, null, 'port', 39, 1],
            ['phone', null, null, 'camera', 59, 2],
            ['phone', null, null, 'audio', 39, 1],
            ['tablet', 'Apple', 'iPad 9', 'screen', 99, 2],
            ['tablet', null, null, 'screen', 79, 3],
            ['tablet', null, null, 'battery', 59, 2],
            ['tablet', null, null, 'port', 45, 2],
            ['laptop', 'Apple', 'MacBook Pro 14', 'screen', 389, 5],
            ['laptop', null, null, 'screen', 149, 4],
            ['laptop', null, null, 'battery', 89, 2],
            ['laptop', null, null, 'keyboard', 79, 3],
            ['laptop', null, null, 'port', 59, 3],
            ['desktop', null, null, 'port', 49, 2],
            ['console', null, null, 'port', 69, 3],
            ['smartwatch', null, null, 'screen', 79, 3],
            ['smartwatch', null, null, 'battery', 49, 2],
            [null, null, null, 'software', 35, 0],
        ];

        foreach ($prices as [$category, $brand, $model, $type, $price, $days]) {
            RepairPrice::updateOrCreate(
                ['device_category' => $category, 'device_brand' => $brand, 'device_model' => $model, 'repair_type' => $type],
                ['price' => $price, 'turnaround_days' => $days],
            );
        }
    }
}
