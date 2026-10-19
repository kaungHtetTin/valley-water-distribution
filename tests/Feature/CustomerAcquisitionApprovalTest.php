<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CustomerAcquisitionApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function register(User $sales): int
    {
        return $this->actingAs($sales)->postJson('/api/mobile/master/customers', [
            'shop_name' => 'New acquisition shop', 'contact_name' => 'New contact', 'phone' => '09123456789',
            'acquisition_approved_by' => $sales->id, 'acquisition_approved_at' => now()->toDateTimeString(),
        ])->assertCreated()->assertJsonPath('data.customer.acquisition_approved_at', null)->json('data.customer.id');
    }

    private function generate(User $owner, User $sales, string $month): object
    {
        $this->actingAs($owner)->postJson('/api/kpi-reviews/generate', ['month' => $month, 'employee_type' => 'sales'])->assertOk();
        return DB::table('kpi_result_items')
            ->join('kpi_results', 'kpi_result_items.kpi_result_id', '=', 'kpi_results.id')
            ->join('kpi_periods', 'kpi_results.kpi_period_id', '=', 'kpi_periods.id')
            ->join('kpi_template_metrics', 'kpi_result_items.kpi_template_metric_id', '=', 'kpi_template_metrics.id')
            ->where('kpi_results.employee_id', $sales->employee_id)->where('kpi_periods.month', $month)
            ->where('kpi_template_metrics.code', 'SAL-NEW-CUSTOMER')
            ->first(['kpi_result_items.id', 'kpi_result_items.actual_value', 'kpi_results.id as result_id']);
    }

    public function test_common_page_lists_team_registrations_and_enforces_team_scope(): void
    {
        $this->seed();
        $sales = User::where('email', 'sales@valley.test')->firstOrFail();
        $supervisor = User::where('email', 'sales.supervisor@valley.test')->firstOrFail();
        $secondId = DB::table('employees')->where('code', 'SAL-002')->value('id');
        $second = User::factory()->create(['role' => 'Sales Representative', 'employee_id' => $secondId]);
        $firstCustomer = $this->register($sales);
        $secondCustomer = $this->register($second);
        $url = '/api/mobile/supervisor/new-customers';
        $this->actingAs($supervisor)->getJson($url)->assertOk()
            ->assertJsonFragment(['id' => $firstCustomer])->assertJsonFragment(['id' => $secondCustomer])
            ->assertJsonFragment(['employee_id' => $sales->employee_id]);
        $filtered = $this->getJson($url.'?employee_id='.$secondId)->assertOk();
        $this->assertContains($secondCustomer, collect($filtered->json('data.items'))->pluck('id')->all());
        $this->assertNotContains($firstCustomer, collect($filtered->json('data.items'))->pluck('id')->all());
        $this->postJson("{$url}/{$firstCustomer}/approve")->assertOk()
            ->assertJsonPath('data.customer.acquisition_approved_by', $supervisor->id);
        $this->getJson($url.'?status=approved')->assertOk()->assertJsonFragment(['id' => $firstCustomer]);
        DB::table('employees')->where('id', $secondId)->update(['supervisor_id' => null]);
        $remaining = $this->getJson($url.'?status=all')->assertOk();
        $this->assertNotContains($secondCustomer, collect($remaining->json('data.items'))->pluck('id')->all());
        $this->postJson("{$url}/{$secondCustomer}/approve")->assertNotFound();
        $this->getJson($url.'?employee_id='.$secondId)->assertNotFound();
        $this->actingAs($sales)->getJson($url)->assertForbidden();
        $this->postJson("{$url}/{$secondCustomer}/approve")->assertForbidden();
    }

    public function test_only_supervisor_approved_registrations_earn_acquisition_points(): void
    {
        $this->seed();
        $sales = User::where('email', 'sales@valley.test')->firstOrFail();
        $owner = User::where('email', 'owner@valley.test')->firstOrFail();
        $supervisor = User::where('email', 'sales.supervisor@valley.test')->firstOrFail();
        $customer = $this->register($sales);
        $item = $this->generate($owner, $sales, now()->format('Y-m'));
        $this->assertEquals(0, $item->actual_value);
        $url = "/api/mobile/supervisor/team/{$sales->employee_id}/new-customers";
        $this->actingAs($sales)->postJson("{$url}/{$customer}/approve")->assertForbidden();
        $this->actingAs($supervisor)->getJson($url)->assertOk()->assertJsonFragment(['id' => $customer]);
        $this->postJson("{$url}/{$customer}/approve")->assertOk()
            ->assertJsonPath('data.customer.acquisition_approved_by', $supervisor->id);
        $score = DB::table('kpi_result_items')->find($item->id);
        $this->assertEquals(1, $score->actual_value);
        $this->assertEquals(0.75, $score->weighted_score);
        $this->getJson($url.'?status=approved')->assertOk()->assertJsonFragment(['id' => $customer]);
        $approvedAt = DB::table('customers')->where('id', $customer)->value('acquisition_approved_at');
        $this->postJson("{$url}/{$customer}/approve")->assertOk();
        $this->assertSame($approvedAt, DB::table('customers')->where('id', $customer)->value('acquisition_approved_at'));
        $this->assertEquals(1, DB::table('kpi_result_items')->where('id', $item->id)->value('actual_value'));
        DB::table('customers')->where('id', $customer)->update(['is_active' => false]);
        $this->actingAs($owner)->postJson("/api/kpi-reviews/{$item->result_id}/refresh")->assertOk();
        $this->assertEquals(0, DB::table('kpi_result_items')->where('id', $item->id)->value('actual_value'));
    }

    public function test_approval_is_limited_to_own_team_and_registration_creator(): void
    {
        $this->seed();
        $sales = User::where('email', 'sales@valley.test')->firstOrFail();
        $supervisor = User::where('email', 'sales.supervisor@valley.test')->firstOrFail();
        $customer = $this->register($sales);
        $otherId = DB::table('employees')->where('code', 'SAL-002')->value('id');
        $this->actingAs($supervisor)->postJson("/api/mobile/supervisor/team/{$otherId}/new-customers/{$customer}/approve")->assertNotFound();
        DB::table('employees')->where('id', $sales->employee_id)->update(['supervisor_id' => null]);
        $url = "/api/mobile/supervisor/team/{$sales->employee_id}/new-customers";
        $this->getJson($url)->assertNotFound();
        $this->postJson("{$url}/{$customer}/approve")->assertNotFound();
        $this->assertNull(DB::table('customers')->where('id', $customer)->value('acquisition_approved_at'));
    }

    public function test_final_kpi_approval_cannot_use_stale_unapproved_acquisition_points(): void
    {
        $this->seed();
        $sales = User::where('email', 'sales@valley.test')->firstOrFail();
        $owner = User::where('email', 'owner@valley.test')->firstOrFail();
        $this->register($sales);
        $item = $this->generate($owner, $sales, now()->format('Y-m'));
        DB::table('kpi_result_items')->where('id', $item->id)->update(['actual_value' => 20, 'weighted_score' => 15]);
        DB::table('kpi_results')->where('id', $item->result_id)->update(['status' => 'submitted']);
        $this->postJson("/api/kpi-reviews/{$item->result_id}/approve")->assertOk();
        $score = DB::table('kpi_result_items')->find($item->id);
        $this->assertEquals(0, $score->actual_value);
        $this->assertEquals(0, $score->weighted_score);
    }

    public function test_late_approval_credits_join_month_and_preserves_finalized_reviews(): void
    {
        $this->seed();
        $sales = User::where('email', 'sales@valley.test')->firstOrFail();
        $owner = User::where('email', 'owner@valley.test')->firstOrFail();
        $supervisor = User::where('email', 'sales.supervisor@valley.test')->firstOrFail();
        $customer = $this->register($sales);
        $joinedAt = Carbon::now()->subMonthsNoOverflow(1)->startOfMonth()->addDays(5);
        DB::table('customers')->where('id', $customer)->update(['created_at' => $joinedAt]);
        $previous = $this->generate($owner, $sales, $joinedAt->format('Y-m'));
        $current = $this->generate($owner, $sales, now()->format('Y-m'));
        $url = "/api/mobile/supervisor/team/{$sales->employee_id}/new-customers/{$customer}/approve";
        $this->actingAs($supervisor)->postJson($url)->assertOk();
        $this->assertEquals(1, DB::table('kpi_result_items')->where('id', $previous->id)->value('actual_value'));
        $this->assertEquals(0, DB::table('kpi_result_items')->where('id', $current->id)->value('actual_value'));
        DB::table('kpi_results')->where('id', $previous->result_id)->update(['status' => 'approved']);
        DB::table('kpi_result_items')->where('id', $previous->id)->update(['actual_value' => 12]);
        $this->postJson($url)->assertOk();
        $this->assertEquals(12, DB::table('kpi_result_items')->where('id', $previous->id)->value('actual_value'));
    }
}
