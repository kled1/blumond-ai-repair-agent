<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Observability: every LLM call, tool call, guardrail hit and error is one row (the "trace").
        Schema::create('agent_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('type');              // llm_call | tool_call | guardrail | error | decision
            $table->string('name');              // tool name, guardrail name, ...
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->boolean('is_error')->default(false);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_steps');
    }
};
