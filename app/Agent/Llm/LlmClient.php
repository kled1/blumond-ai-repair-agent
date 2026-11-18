<?php

namespace App\Agent\Llm;

/**
 * Small abstraction over the LLM provider.
 * The agent only talks to this interface, so tests can swap in FakeLlmClient
 * and run the whole workflow without network or API key.
 */
interface LlmClient
{
    /**
     * @param  string  $system  System prompt
     * @param  array<int, array{role: string, content: mixed}>  $messages  Conversation so far
     * @param  array<int, array<string, mixed>>  $tools  Tool definitions
     *
     * @throws LlmException when the provider fails after its own retries
     */
    public function send(string $system, array $messages, array $tools): LlmResponse;
}
