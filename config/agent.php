<?php

return [
    // Which LLM driver to use: "anthropic" (real Claude API) or "fake" (scripted, used by tests).
    'driver' => env('AGENT_LLM_DRIVER', 'anthropic'),

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-5-5'),
        'effort' => env('ANTHROPIC_EFFORT', 'medium'),
        'max_tokens' => 16000,
    ],

    // Guardrails for the agent loop.
    'max_steps' => 8,           // max LLM round-trips per ticket
    'max_corrections' => 2,     // how many times we let the model fix an invalid final answer
    'max_input_chars' => 5000,  // reject very long messages
    'max_photo_kb' => 5120,     // max photo size (5 MB)

    // Business rules (deterministic, NOT decided by the LLM).
    'currency' => 'EUR',
    'warranty_days' => 90,               // repaired part is covered for 90 days after pickup
    'diagnostic_fee' => 15.00,           // charged when the problem must be diagnosed first
    'human_confirmation_above' => 250.00, // quotes above this need a technician to confirm
];
