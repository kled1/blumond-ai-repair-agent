<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per incoming customer message + the agent's final result.
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('channel');                    // whatsapp | walk_in
            $table->string('customer_phone');
            $table->string('customer_name')->nullable();
            $table->text('message');
            $table->string('photo_path')->nullable();     // optional photo of the device (storage/app/private)
            $table->string('status')->default('pending'); // pending | routed | resolved | waiting_customer | needs_human | rejected
            $table->string('assigned_queue')->nullable(); // technician bench chosen by RepairRouter (code, not LLM)
            $table->json('result')->nullable();           // structured JSON result of the agent
            $table->text('summary')->nullable();          // human-readable summary
            $table->json('guardrail_flags')->nullable();  // e.g. ["possible_prompt_injection"]
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
