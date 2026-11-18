<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Blumond's repair jobs (current and past). Read by the lookup_repairs and check_warranty tools.
        Schema::create('repairs', function (Blueprint $table) {
            $table->id();
            $table->string('job_number')->unique();        // e.g. REP-1001 (printed on the customer's receipt)
            $table->string('customer_phone')->index();     // used for the ownership check
            $table->string('customer_name');
            $table->string('device_category');             // phone | tablet | laptop | ...
            $table->string('device_brand');
            $table->string('device_model');
            $table->string('repair_type');                 // screen | battery | ...
            $table->string('status');                      // received | diagnosing | waiting_parts | in_repair | ready | picked_up
            $table->decimal('price', 10, 2);
            $table->date('estimated_ready_at')->nullable();
            $table->timestamp('picked_up_at')->nullable(); // warranty starts here
            $table->timestamps();
        });

        // The price list. brand/model NULL = generic price for the whole category ("from" price).
        // device_category NULL = same price for every device (e.g. software).
        Schema::create('repair_prices', function (Blueprint $table) {
            $table->id();
            $table->string('device_category')->nullable();
            $table->string('device_brand')->nullable();
            $table->string('device_model')->nullable();
            $table->string('repair_type');
            $table->decimal('price', 10, 2);
            $table->unsignedSmallInteger('turnaround_days'); // 0 = same day
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_prices');
        Schema::dropIfExists('repairs');
    }
};
