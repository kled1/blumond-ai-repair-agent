<?php

namespace App\Providers;

use App\Agent\Llm\AnthropicLlmClient;
use App\Agent\Llm\FakeLlmClient;
use App\Agent\Llm\LlmClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Pick the LLM implementation from config. Tests bind their own FakeLlmClient.
        $this->app->bind(LlmClient::class, function () {
            if (config('agent.driver') === 'fake') {
                return new FakeLlmClient;
            }

            $cfg = config('agent.anthropic');

            return new AnthropicLlmClient($cfg['api_key'], $cfg['model'], $cfg['effort'], $cfg['max_tokens']);
        });
    }

    public function boot(): void
    {
        //
    }
}
