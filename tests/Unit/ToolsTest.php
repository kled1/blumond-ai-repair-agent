<?php

namespace Tests\Unit;

use App\Agent\AgentContext;
use App\Agent\Tools\CheckWarrantyTool;
use App\Agent\Tools\GetRepairQuoteTool;
use App\Agent\Tools\LookupRepairsTool;
use App\Agent\Tools\SearchServiceInfoTool;
use App\Agent\Tools\SubmitResolutionTool;
use App\Agent\Tools\ToolInputException;
use App\Agent\Tracer;
use App\Models\Ticket;
use Database\Seeders\RepairSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToolsTest extends TestCase
{
    use RefreshDatabase;

    private function ctxFor(string $phone): AgentContext
    {
        $ticket = Ticket::create(['channel' => 'whatsapp', 'customer_phone' => $phone, 'message' => 'Hi']);

        return new AgentContext($ticket, new Tracer($ticket));
    }

    public function test_lookup_repairs_returns_own_jobs(): void
    {
        $this->seed(RepairSeeder::class);

        $out = app(LookupRepairsTool::class)->execute(['job_number' => null], $this->ctxFor('+355690000001'));

        $this->assertTrue($out['found']);
        $this->assertEqualsCanonicalizing(['REP-1001', 'REP-1004'], array_column($out['jobs'], 'job_number'));
    }

    public function test_lookup_repairs_hides_other_customers_jobs(): void
    {
        $this->seed(RepairSeeder::class);

        $out = app(LookupRepairsTool::class)->execute(['job_number' => 'REP-1003'], $this->ctxFor('+355690000001'));

        $this->assertFalse($out['found']);
        $this->assertArrayNotHasKey('jobs', $out);
    }

    public function test_warranty_check_on_another_customers_job_finds_nothing(): void
    {
        $this->seed(RepairSeeder::class);
        $ctx = $this->ctxFor('+355690000002');

        $out = app(CheckWarrantyTool::class)->execute(['job_number' => 'REP-1001', 'repair_type' => 'battery', 'damage_cause' => 'normal_use'], $ctx);

        $this->assertFalse($out['covered']);
        $this->assertSame([], $ctx->warranty);
    }

    public function test_tools_reject_invalid_input(): void
    {
        $this->expectException(ToolInputException::class);

        app(LookupRepairsTool::class)->execute(['job_number' => "1'; DROP TABLE repairs"], $this->ctxFor('+355690000001'));
    }

    /**
     * Regression: the real API (strict mode) rejects an enum combined with type ['string', 'null'].
     * Found when running against the real Claude API; nullable enums must use anyOf.
     */
    public function test_tool_schemas_never_combine_enum_with_a_type_list(): void
    {
        $agentTools = [LookupRepairsTool::class, CheckWarrantyTool::class, GetRepairQuoteTool::class, SearchServiceInfoTool::class, SubmitResolutionTool::class];

        foreach ($agentTools as $class) {
            foreach (app($class)->definition()['inputSchema']['properties'] as $name => $schema) {
                $this->assertFalse(isset($schema['enum']) && is_array($schema['type'] ?? null), "{$class}::{$name}");
            }
        }
    }

    public function test_search_service_info_finds_warranty_terms(): void
    {
        $results = (new SearchServiceInfoTool)->search('warranty days pickup');

        $this->assertNotEmpty($results);
        $this->assertSame('warranty.md', $results[0]['source']);
    }
}
