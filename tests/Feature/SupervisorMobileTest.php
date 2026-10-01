<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SupervisorMobileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-31 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_sales_supervisor_can_sign_in_and_view_only_the_assigned_team(): void
    {
        $this->seed();

        $this->postJson('/api/auth/login', [
            'email' => 'sales.supervisor@valley.test',
            'password' => 'password',
            'app' => 'supervisor',
        ])->assertOk()
            ->assertJsonPath('data.user.role', 'Sales Supervisor')
            ->assertJsonPath('data.user.default_app', 'supervisor')
            ->assertJsonFragment(['supervisor.team.view']);

        $this->getJson('/api/mobile/supervisor')
            ->assertOk()
            ->assertJsonPath('data.supervisor.code', 'SUP-001')
            ->assertJsonPath('data.summary.team_members', 2)
            ->assertJsonCount(2, 'data.team')
            ->assertJsonFragment(['code' => 'SAL-001'])
            ->assertJsonFragment(['code' => 'SAL-002']);
    }

    public function test_non_supervisor_and_unlinked_supervisor_profiles_are_rejected(): void
    {
        $this->seed();

        $sales = User::where('email', 'sales@valley.test')->firstOrFail();
        $this->actingAs($sales)->getJson('/api/mobile/supervisor')->assertForbidden();

        $sales->update(['role' => 'Sales Supervisor']);
        $this->actingAs($sales->fresh())->getJson('/api/mobile/supervisor/team')->assertNotFound();
    }

    public function test_supervisor_cannot_view_a_representative_outside_their_team(): void
    {
        $this->seed();
        $supervisor = User::where('email', 'sales.supervisor@valley.test')->firstOrFail();
        $warehouseEmployeeId = DB::table('employees')->where('code', 'WH-001')->value('id');

        $this->actingAs($supervisor)
            ->getJson("/api/mobile/supervisor/team/{$warehouseEmployeeId}")
            ->assertNotFound();
    }

    public function test_supervisor_can_view_monthly_and_yearly_kpi_for_a_team_member(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@valley.test')->firstOrFail();
        $templateId = DB::table('kpi_templates')->where('code', 'SALES-REP-V1')->value('id');
        $salesEmployeeId = DB::table('employees')->where('code', 'SAL-001')->value('id');

        $this->actingAs($owner)->postJson('/api/kpi-reviews/generate', [
            'month' => '2026-08',
            'template_id' => $templateId,
        ])->assertOk();

        $supervisor = User::where('email', 'sales.supervisor@valley.test')->firstOrFail();
        $this->actingAs($supervisor)
            ->getJson("/api/mobile/supervisor/team/{$salesEmployeeId}?period=month&month=2026-08")
            ->assertOk()
            ->assertJsonPath('data.representative.code', 'SAL-001')
            ->assertJsonPath('data.filters.period', 'month')
            ->assertJsonPath('data.summary.reviews', 1)
            ->assertJsonCount(7, 'data.metrics');

        $this->getJson("/api/mobile/supervisor/team/{$salesEmployeeId}?period=year&year=2026")
            ->assertOk()
            ->assertJsonPath('data.filters.period', 'year')
            ->assertJsonPath('data.summary.reviews', 1);
    }

    public function test_a_sales_representative_can_belong_to_only_one_supervisor(): void
    {
        $this->seed();
        $this->actingAs(User::where('email', 'owner@valley.test')->firstOrFail());
        $existingSupervisorId = DB::table('employees')->where('code', 'SUP-001')->value('id');
        $salesOneId = DB::table('employees')->where('code', 'SAL-001')->value('id');
        $salesTwoId = DB::table('employees')->where('code', 'SAL-002')->value('id');
        $secondSupervisorId = DB::table('employees')->insertGetId([
            'code' => 'SUP-002',
            'name' => 'Second Supervisor',
            'employee_type' => 'sales_supervisor',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->putJson("/api/master-data/employees/{$secondSupervisorId}/sales-team", [
            'sales_representative_ids' => [$salesOneId],
        ])->assertUnprocessable()->assertJsonValidationErrors('sales_representative_ids');

        $this->putJson("/api/master-data/employees/{$existingSupervisorId}/sales-team", [
            'sales_representative_ids' => [$salesOneId],
        ])->assertOk()->assertJsonCount(1, 'data.team_members');

        $this->assertDatabaseHas('employees', ['id' => $salesOneId, 'supervisor_id' => $existingSupervisorId]);
        $this->assertDatabaseHas('employees', ['id' => $salesTwoId, 'supervisor_id' => null]);
    }
}
