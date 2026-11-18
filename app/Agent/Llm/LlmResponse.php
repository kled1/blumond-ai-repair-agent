<?php

namespace App\Agent\Llm;

/**
 * Provider-independent response.
 *
 * $blocks are plain arrays in the same shape we send back to the API:
 *   ['type' => 'text', 'text' => '...']
 *   ['type' => 'tool_use', 'id' => '...', 'name' => '...', 'input' => [...]]
 *   ['type' => 'thinking', 'thinking' => '...', 'signature' => '...']
 */
final class LlmResponse
{
    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @param  array{input_tokens?: int, output_tokens?: int}  $usage
     */
    public function __construct(
        public readonly string $stopReason,
        public readonly array $blocks,
        public readonly array $usage = [],
    ) {}

    /** @return array<int, array<string, mixed>> */
    public function toolCalls(): array
    {
        return array_values(array_filter($this->blocks, fn ($b) => $b['type'] === 'tool_use'));
    }

    public function text(): string
    {
        return implode("\n", array_map(
            fn ($b) => $b['text'],
            array_filter($this->blocks, fn ($b) => $b['type'] === 'text'),
        ));
    }
}
