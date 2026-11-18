<?php

namespace App\Agent;

use App\Agent\Guardrails\InputGuard;
use App\Agent\Guardrails\OutputGuard;
use App\Agent\Llm\LlmClient;
use App\Agent\Llm\LlmException;
use App\Agent\Rules\RepairRouter;
use App\Agent\Tools\CheckWarrantyTool;
use App\Agent\Tools\GetRepairQuoteTool;
use App\Agent\Tools\LookupRepairsTool;
use App\Agent\Tools\SearchServiceInfoTool;
use App\Agent\Tools\SubmitResolutionTool;
use App\Agent\Tools\Tool;
use App\Agent\Tools\ToolInputException;
use App\Models\Ticket;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * The orchestrator (custom agent loop).
 *
 *   1. Input guardrails (deterministic)
 *   2. Loop: ask Claude -> run the tools it asked for -> send results back
 *   3. Claude calls submit_resolution -> validate (retry if invalid)
 *   4. Output guardrails (deterministic business rules)
 *   5. Routing to a technician queue (deterministic, RepairRouter)
 *   6. Save structured JSON + human summary on the ticket
 *
 * Every step is traced (Tracer). Any unexpected failure ends in a safe
 * "escalate_to_human" result – the agent never crashes the request.
 */
class SupportAgent
{
    /** @var array<string, Tool> */
    private array $tools = [];

    public function __construct(
        private readonly LlmClient $llm,
        private readonly InputGuard $inputGuard,
        private readonly OutputGuard $outputGuard,
        private readonly RepairRouter $router,
        LookupRepairsTool $lookupRepairs,
        CheckWarrantyTool $checkWarranty,
        GetRepairQuoteTool $getQuote,
        SearchServiceInfoTool $searchInfo,
        private readonly SubmitResolutionTool $submit,
    ) {
        foreach ([$lookupRepairs, $checkWarranty, $getQuote, $searchInfo, $submit] as $tool) {
            $this->tools[$tool->name()] = $tool;
        }
    }

    public function handle(Ticket $ticket): Ticket
    {
        $ctx = new AgentContext($ticket, new Tracer($ticket));

        try {
            $resolution = $this->runLoop($ctx);
        } catch (Throwable $e) {
            $ctx->tracer->record('error', 'unexpected_exception', output: ['message' => $e->getMessage()], isError: true);
            $resolution = $this->fallbackResolution('Agent failed: '.$e->getMessage());
        }

        return $this->finish($ctx, $resolution);
    }

    /** @return array<string, mixed> the (validated) resolution from submit_resolution */
    private function runLoop(AgentContext $ctx): array
    {
        $ticket = $ctx->ticket;

        // ---- 1. Input guardrails -------------------------------------------------
        $ctx->flags = $this->inputGuard->inspect($ticket->message);
        $ctx->tracer->record('guardrail', 'input_guard', output: ['flags' => $ctx->flags, 'has_photo' => $ticket->photo_path !== null]);

        $messages = [['role' => 'user', 'content' => $this->buildUserMessage($ctx)]];
        $toolDefinitions = array_values(array_map(fn (Tool $t) => $t->definition(), $this->tools));
        $corrections = 0;

        // ---- 2. Agent loop ---------------------------------------------------------
        for ($step = 1; $step <= config('agent.max_steps'); $step++) {
            $start = microtime(true);
            try {
                $response = $this->llm->send(Prompts::system(), $messages, $toolDefinitions);
            } catch (LlmException $e) {
                $ctx->tracer->record('error', 'llm_call', output: ['message' => $e->getMessage()], isError: true);

                return $this->fallbackResolution('LLM unavailable: '.$e->getMessage());
            }

            $ctx->tracer->record('llm_call', "step_{$step}", output: [
                'stop_reason' => $response->stopReason,
                'text' => $response->text(),
                'tool_calls' => array_map(fn ($c) => $c['name'], $response->toolCalls()),
                'usage' => $response->usage,
            ], durationMs: $this->ms($start));

            // The model (safety classifier) refused, or ran out of tokens -> safe exit.
            if (in_array($response->stopReason, ['refusal', 'max_tokens'], true)) {
                return $this->fallbackResolution("Model stopped with reason: {$response->stopReason}");
            }

            $messages[] = ['role' => 'assistant', 'content' => $response->blocks];

            // Model answered with plain text instead of using the final tool -> nudge it.
            if ($response->toolCalls() === []) {
                if (++$corrections > config('agent.max_corrections')) {
                    return $this->fallbackResolution('Model did not submit a resolution.');
                }
                $messages[] = ['role' => 'user', 'content' => 'Please finish by calling the submit_resolution tool.'];

                continue;
            }

            // ---- Run every tool the model asked for, collect ALL results in one message.
            $results = [];
            foreach ($response->toolCalls() as $call) {
                [$output, $isError] = $this->runTool($call, $ctx);

                // ---- 3. Final answer -------------------------------------------------
                if ($call['name'] === $this->submit->name() && ! $isError) {
                    return $output;
                }

                if ($call['name'] === $this->submit->name() && ++$corrections > config('agent.max_corrections')) {
                    return $this->fallbackResolution('Model kept submitting an invalid resolution.');
                }

                $results[] = [
                    'type' => 'tool_result',
                    'toolUseID' => $call['id'],
                    'content' => json_encode($output),
                    'isError' => $isError,
                ];
            }

            $messages[] = ['role' => 'user', 'content' => $results];
        }

        return $this->fallbackResolution('Reached the maximum number of steps.');
    }

    /** @return array{0: array<string, mixed>, 1: bool} [output, isError] */
    private function runTool(array $call, AgentContext $ctx): array
    {
        $start = microtime(true);
        $tool = $this->tools[$call['name']] ?? null;

        try {
            if ($tool === null) {
                throw new ToolInputException("Unknown tool: {$call['name']}");
            }
            $output = $tool->execute($call['input'], $ctx);
            $isError = false;
        } catch (ToolInputException $e) {
            // Sent back to the model with is_error=true so it can correct itself.
            $output = ['error' => $e->getMessage()];
            $isError = true;
        }

        $ctx->tracer->record('tool_call', $call['name'], $call['input'], $output, $isError, $this->ms($start));

        return [$output, $isError];
    }

    /** Applies output guardrails, routes the ticket and stores the final result. */
    private function finish(AgentContext $ctx, array $resolution): Ticket
    {
        ['resolution' => $resolution, 'overrides' => $overrides] = $this->outputGuard->enforce($resolution, $ctx);

        if ($overrides !== []) {
            $ctx->tracer->record('guardrail', 'output_guard', output: ['overrides' => $overrides]);
        }

        $status = match ($resolution['decision']) {
            'create_repair_order', 'warranty_repair' => 'routed',
            'request_more_info' => 'waiting_customer',
            'escalate_to_human' => 'needs_human',
            'reject_out_of_scope' => 'rejected',
            default => 'resolved',
        };

        // Routing is decided by code from the (validated) device + problem, never by the LLM.
        $queue = match ($status) {
            'routed' => $this->router->queue($resolution['device_category'], $resolution['repair_type']),
            'needs_human' => 'front_desk',
            default => null,
        };

        $result = $resolution + ['assigned_queue' => $queue, 'guardrail_overrides' => $overrides, 'flags' => $ctx->flags];
        $summary = $this->summarize($ctx->ticket, $result);

        $ctx->tracer->record('decision', $resolution['decision'], output: ['status' => $status, 'queue' => $queue, 'summary' => $summary]);

        $ctx->ticket->update([
            'status' => $status,
            'assigned_queue' => $queue,
            'result' => $result,
            'summary' => $summary,
            'guardrail_flags' => $ctx->flags,
        ]);

        return $ctx->ticket->fresh('steps');
    }

    /** Human-readable summary built from the structured result (deterministic). */
    private function summarize(Ticket $ticket, array $r): string
    {
        $device = trim(implode(' ', array_filter([$r['device_brand'] ?? null, $r['device_model'] ?? null]))) ?: 'unknown model';
        $price = $r['quoted_price'] !== null ? '€'.number_format((float) $r['quoted_price'], 2) : 'n/a';

        $lines = [
            "Ticket #{$ticket->id} via {$ticket->channel} from {$ticket->customer_phone}".($ticket->customer_name ? " ({$ticket->customer_name})" : ''),
            'Category: '.$r['category'].' | Decision: '.$r['decision'].' | Confidence: '.$r['confidence'],
            'Device: '.($r['device_category'] ?? 'n/a')." / {$device} | Problem: ".($r['repair_type'] ?? 'n/a').' | Price: '.$price,
            'Queue: '.($r['assigned_queue'] ?? 'none').' | Job: '.($r['job_number'] ?? 'new'),
            'Summary: '.$r['internal_summary'],
        ];
        if ($r['missing_info'] !== []) {
            $lines[] = 'Waiting for: '.implode('; ', $r['missing_info']);
        }
        if ($r['guardrail_overrides'] !== []) {
            $lines[] = 'Guardrail overrides: '.implode('; ', $r['guardrail_overrides']);
        }

        return implode("\n", $lines);
    }

    /**
     * First user message: verified metadata outside the tags, customer text inside the tags.
     * If there is a photo, it is sent as an image block before the text.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildUserMessage(AgentContext $ctx): array
    {
        $t = $ctx->ticket;
        $message = $this->inputGuard->sanitize($t->message);
        $flags = $ctx->flags === [] ? 'none' : implode(', ', $ctx->flags);
        $channel = $t->channel === 'whatsapp'
            ? 'WhatsApp (phone number verified by WhatsApp)'
            : 'Walk-in at the shop (typed by Blumond staff)';
        $name = $t->customer_name ?: 'unknown';
        $photo = $t->photo_path ? 'yes, attached above' : 'no';

        $text = <<<MSG
        New customer message.
        Channel: {$channel}
        Customer phone (verified): {$t->customer_phone}
        Customer name: {$name}
        Photo: {$photo}
        Automatic flags: {$flags}

        <customer_message>
        {$message}
        </customer_message>
        MSG;

        $content = [];
        if ($t->photo_path && Storage::disk('local')->exists($t->photo_path)) {
            $content[] = [
                'type' => 'image',
                'source' => [
                    'type' => 'base64',
                    'mediaType' => Storage::disk('local')->mimeType($t->photo_path),
                    'data' => base64_encode(Storage::disk('local')->get($t->photo_path)),
                ],
            ];
        }
        $content[] = ['type' => 'text', 'text' => $text];

        return $content;
    }

    private function fallbackResolution(string $reason): array
    {
        return [
            'category' => 'other',
            'decision' => 'escalate_to_human',
            'device_category' => null,
            'device_brand' => null,
            'device_model' => null,
            'repair_type' => null,
            'job_number' => null,
            'quoted_price' => null,
            'customer_reply' => OutputGuard::SAFE_REPLY,
            'internal_summary' => "Automatic escalation. {$reason}",
            'missing_info' => [],
            'policy_references' => [],
            'confidence' => 'low',
        ];
    }

    private function ms(float $start): int
    {
        return (int) round((microtime(true) - $start) * 1000);
    }
}
