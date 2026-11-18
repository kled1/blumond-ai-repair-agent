<?php

namespace Tests\Unit;

use App\Agent\AgentContext;
use App\Agent\Guardrails\OutputGuard;
use App\Agent\Tracer;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutputGuardTest extends TestCase
{
    use RefreshDatabase;

    private function ctx(): AgentContext
    {
        $ticket = Ticket::create(['channel' => 'whatsapp', 'customer_phone' => '+355690000005', 'message' => 'Cracked screen']);

        return new AgentContext($ticket, new Tracer($ticket));
    }

    /** A context where get_repair_quote returned €129 for a phone screen. */
    private function quotedCtx(array $quote = []): AgentContext
    {
        $ctx = $this->ctx();
        $ctx->quotes['phone|screen'] = array_merge([
            'supported' => true, 'price_type' => 'fixed', 'price' => 129.0, 'diagnostic_fee' => null, 'requires_human_confirmation' => false,
        ], $quote);
        $ctx->knownAmounts = [129.0];

        return $ctx;
    }

    private function order(array $overrides = []): array
    {
        return array_merge([
            'category' => 'new_repair', 'decision' => 'create_repair_order',
            'device_category' => 'phone', 'device_brand' => 'Apple', 'device_model' => 'iPhone 13', 'repair_type' => 'screen',
            'job_number' => null, 'quoted_price' => 129, 'customer_reply' => 'Hi! The screen repair costs €129 and takes 1 day.',
            'internal_summary' => 'Cracked iPhone 13 screen.', 'missing_info' => [], 'policy_references' => [], 'confidence' => 'high',
        ], $overrides);
    }

    private function decision(array $resolution, AgentContext $ctx): string
    {
        return (new OutputGuard)->enforce($resolution, $ctx)['resolution']['decision'];
    }

    public function test_valid_repair_order_passes(): void
    {
        $out = (new OutputGuard)->enforce($this->order(), $this->quotedCtx());

        $this->assertSame('create_repair_order', $out['resolution']['decision']);
        $this->assertSame([], $out['overrides']);
    }

    public function test_repair_order_without_quote_is_overridden(): void
    {
        $this->assertSame('escalate_to_human', $this->decision($this->order(), $this->ctx()));
    }

    public function test_price_that_differs_from_price_list_is_overridden(): void
    {
        $this->assertSame('escalate_to_human', $this->decision($this->order(['quoted_price' => 99]), $this->quotedCtx()));
    }

    public function test_invented_amount_in_reply_is_overridden(): void
    {
        $out = (new OutputGuard)->enforce($this->order(['customer_reply' => 'Hi! It costs €129, or €99 if you pay cash.']), $this->quotedCtx());

        $this->assertSame('escalate_to_human', $out['resolution']['decision']);
        $this->assertSame(OutputGuard::SAFE_REPLY, $out['resolution']['customer_reply']);
        $this->assertStringContainsString('99', $out['overrides'][0]);
    }

    public function test_override_resets_confidence_and_questions_from_the_replaced_decision(): void
    {
        $ctx = $this->ctx();
        $ctx->flags = ['possible_prompt_injection'];
        $askInfo = $this->order(['decision' => 'request_more_info', 'quoted_price' => null, 'missing_info' => ['phone model'], 'confidence' => 'high']);

        $out = (new OutputGuard)->enforce($askInfo, $ctx)['resolution'];

        $this->assertSame('escalate_to_human', $out['decision']);
        $this->assertSame('low', $out['confidence']);
        $this->assertSame([], $out['missing_info']);
    }

    public function test_quote_above_limit_needs_a_human(): void
    {
        $this->assertSame('escalate_to_human', $this->decision($this->order(), $this->quotedCtx(['requires_human_confirmation' => true])));
    }

    public function test_diagnosis_order_must_not_have_a_price(): void
    {
        $ctx = $this->quotedCtx(['price_type' => 'diagnosis_required', 'price' => null]);

        $this->assertSame('escalate_to_human', $this->decision($this->order(), $ctx));
        $this->assertSame('create_repair_order', $this->decision($this->order(['quoted_price' => null, 'customer_reply' => 'Hi! We need to diagnose it first.']), $ctx));
    }

    public function test_warranty_repair_needs_a_successful_check(): void
    {
        $warranty = $this->order(['decision' => 'warranty_repair', 'job_number' => 'REP-1001', 'quoted_price' => 0, 'customer_reply' => 'Hi! This is covered by our warranty, free of charge.']);

        $this->assertSame('escalate_to_human', $this->decision($warranty, $this->ctx()));

        $ctx = $this->ctx();
        $ctx->warranty['REP-1001'] = ['covered' => true, 'reason' => 'ok'];
        $this->assertSame('warranty_repair', $this->decision($warranty, $ctx));
    }

    public function test_red_flags_always_go_to_a_human(): void
    {
        foreach (['possible_prompt_injection', 'legal_or_chargeback', 'account_lock_or_imei'] as $flag) {
            $ctx = $this->quotedCtx();
            $ctx->flags = [$flag];

            $this->assertSame('escalate_to_human', $this->decision($this->order(), $ctx), $flag);
        }
    }
}
