<?php

namespace App\Agent\Llm;

/**
 * Scripted LLM used in tests: returns pre-defined responses in order
 * and records every request so tests can assert on what was sent.
 */
class FakeLlmClient implements LlmClient
{
    /** @var array<int, LlmResponse|\Throwable> */
    private array $queue;

    /** @var array<int, array{system: string, messages: array, tools: array}> */
    public array $requests = [];

    /** @param  array<int, LlmResponse|\Throwable>  $responses */
    public function __construct(array $responses = [])
    {
        $this->queue = $responses;
    }

    public function send(string $system, array $messages, array $tools): LlmResponse
    {
        $this->requests[] = compact('system', 'messages', 'tools');

        $next = array_shift($this->queue) ?? throw new LlmException('FakeLlmClient: no more scripted responses');

        if ($next instanceof \Throwable) {
            throw $next;
        }

        return $next;
    }

    /** Helper: a response where the model calls one tool. */
    public static function toolCall(string $name, array $input, ?string $id = null): LlmResponse
    {
        return new LlmResponse('tool_use', [
            ['type' => 'tool_use', 'id' => $id ?? 'toolu_'.uniqid(), 'name' => $name, 'input' => $input],
        ]);
    }

    /** Helper: a plain text answer (no tool call). */
    public static function text(string $text): LlmResponse
    {
        return new LlmResponse('end_turn', [['type' => 'text', 'text' => $text]]);
    }
}
