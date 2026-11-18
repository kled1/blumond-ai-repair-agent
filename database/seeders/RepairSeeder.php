<?php

namespace Database\Seeders;

use App\Models\Repair;
use Illuminate\Database\Seeder;

/**
 * Dummy repair jobs (fake customers). Dates are relative to "now",
 * so the 90-day warranty rule always behaves the same way.
 */
class RepairSeeder extends Seeder
{
    public function run(): void
    {
        $repairs = [
            // Battery replaced, picked up 40 days ago -> still under warranty.
            ['REP-1001', '+355690000001', 'Anna Smith', 'phone', 'Apple', 'iPhone 12', 'battery', 'picked_up', 69, null, now()->subDays(40)],
            // Waiting for a part -> "where is my phone?" case.
            ['REP-1002', '+355690000002', 'Ben Jones', 'phone', 'Samsung', 'Galaxy S21', 'screen', 'waiting_parts', 149, now()->addDays(2), null],
            // Picked up 120 days ago -> warranty expired. Also used as "another customer's" job in the injection test.
            ['REP-1003', '+355690000003', 'Carla Diaz', 'laptop', 'Dell', 'XPS 13', 'keyboard', 'picked_up', 79, null, now()->subDays(120)],
            // Ready for pickup.
            ['REP-1004', '+355690000001', 'Anna Smith', 'tablet', 'Apple', 'iPad 9', 'screen', 'ready', 99, now(), null],
        ];

        foreach ($repairs as [$job, $phone, $name, $category, $brand, $model, $type, $status, $price, $ready, $pickedUp]) {
            Repair::updateOrCreate(['job_number' => $job], [
                'customer_phone' => $phone,
                'customer_name' => $name,
                'device_category' => $category,
                'device_brand' => $brand,
                'device_model' => $model,
                'repair_type' => $type,
                'status' => $status,
                'price' => $price,
                'estimated_ready_at' => $ready,
                'picked_up_at' => $pickedUp,
            ]);
        }
    }
}
