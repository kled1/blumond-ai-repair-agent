<?php

namespace App\Agent\Llm;

use Anthropic\Client;
use Anthropic\Core\Exceptions\AnthropicException;

/**
 * Real implementation using the official Anthropic PHP SDK.
 *
 * - The SDK already retries 429 / 5xx / connection errors (2 retries with backoff).
 * - Server-side fallbacks: if the model refuses for a safety reason, the API can
 *   automatically retry on a fallback model ("fallbacks: default").
 * - The system prompt is cached (cacheControl) because it is the same for every ticket.
 */
class AnthropicLlmClient implements LlmClient
{
    private Client $client;

    private bool $hasApiKey;

    public function __construct(
        ?string $apiKey,
        private readonly string $model,
        private readonly string $effort,
        private readonly int $maxTokens,
    ) {
        $this->hasApiKey = (string) $apiKey !== '';
        $this->client = new Client(apiKey: $apiKey);
    }

    public function send(string $system, array $messages, array $tools): LlmResponse
    {
        if (! $this->hasApiKey) {
            throw new LlmException('ANTHROPIC_API_KEY is not set in .env');
        }

        try {
            $message = $this->client->beta->messages->create(
                model: $this->model,
                maxTokens: $this->maxTokens,
                system: [['type' => 'text', 'text' => $system, 'cacheControl' => ['type' => 'ephemeral']]],
                messages: $messages,
                tools: $tools,
                outputConfig: ['effort' => $this->effort],
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
            );
        } catch (AnthropicException $e) {
            throw new LlmException('LLM request failed: '.$e->getMessage(), previous: $e);
        }

        $blocks = [];
        foreach ($message->content as $block) {
            $blocks[] = match ($block->type) {
                'text' => ['type' => 'text', 'text' => $block->text],
                'tool_use' => ['type' => 'tool_use', 'id' => $block->id, 'name' => $block->name, 'input' => $block->input],
                // Thinking blocks must be sent back unchanged in the next request.
                'thinking' => ['type' => 'thinking', 'thinking' => $block->thinking, 'signature' => $block->signature],
                default => null,
            };
        }

        return new LlmResponse(
            stopReason: (string) $message->stopReason,
            blocks: array_values(array_filter($blocks)),
            usage: [
                'input_tokens' => $message->usage->inputTokens,
                'output_tokens' => $message->usage->outputTokens,
            ],
        );
    }
}
