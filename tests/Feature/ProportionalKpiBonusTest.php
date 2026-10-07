<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\KpiBonus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProportionalKpiBonusTest extends TestCase
{
    use RefreshDatabase;

    public function test_bonus_matches_the_displayed_score_without_a_minimum_payout_threshold(): void
    {
        $this->assertEquals(24308, KpiBonus::amount(40000, 60.77));
        $this->assertEquals(24308, KpiBonus::amount(40000, 60.76923));
        $this->assertEquals(30000, KpiBonus::amount(40000, 75));
        $this->assertEquals(40000, KpiBonus::amount(40000, 100));
        $this->assertEquals(40000, KpiBonus::amount(40000, 120));
        $this->assertEquals(0, KpiBonus::amount(40000, 0));
    }

    public function test_reloading_kpi_updates_the_proportional_bonus_and_posting_feeds_payroll(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@valley.test')->firstOrFail();
        $driver = User::where('email', 'driver@valley.test')->firstOrFail();
        $this->actingAs($owner)->postJson('/api/kpi-reviews/generate', ['month' => '2026-10', 'employee_type' => 'driver'])->assertOk();
        $resultId = DB::table('kpi_results')->where('employee_id', $driver->employee_id)->value('id');
        DB::table('attendance_records')->insert(['employee_id' => $driver->employee_id, 'attendance_at' => '2026-10-07 08:00:00', 'status' => 'accepted']);
        DB::table('kpi_result_items')->where('kpi_result_id', $resultId)->update(['manual_score' => 100]);
        $response = $this->getJson('/api/kpi-reviews/'.$resultId)->assertOk();
        $before = $response->json('data.result');
        $this->assertGreaterThan(0, $before['bonus_amount']);
        $this->assertEquals(KpiBonus::amount($before['target_bonus'], $before['overall_score']), $before['bonus_amount']);
        DB::table('attendance_records')->insert(['employee_id' => $driver->employee_id, 'attendance_at' => '2026-10-08 08:00:00', 'status' => 'accepted']);
        $after = $this->actingAs($driver)->getJson('/api/mobile/kpi?month=2026-10')->assertOk()->json('data.result');
        $this->assertGreaterThan($before['bonus_amount'], $after['bonus_amount']);
        $this->assertSame('proportional', $after['bonus_calculation']);
        $this->assertEquals(KpiBonus::amount($after['target_bonus'], $after['overall_score']), $after['bonus_amount']);

        $this->actingAs($owner);
        DB::table('payroll_adjustments')->insert([
            'employee_id' => $driver->employee_id, 'adjustment_type' => 'incentive', 'title' => 'Extra incentive',
            'amount' => 5000, 'effective_date' => '2026-10-07', 'status' => 'active',
        ]);
        $payroll = $this->postJson('/api/payrolls/generate', ['month' => '2026-10', 'employee_type' => 'driver'])->assertCreated()->json('data.payroll');
        $expectedIncentive = $after['bonus_amount'] + 5000;
        $this->assertDatabaseHas('payroll_items', ['payroll_id' => $payroll['id'], 'incentive_amount' => $expectedIncentive, 'net_pay' => 360000 + $expectedIncentive]);

        // The approval workflow uses the same stored live bonus when it is posted.
        DB::table('kpi_results')->where('id', $resultId)->update(['status' => 'submitted']);
        $this->actingAs($owner)->postJson('/api/kpi-reviews/'.$resultId.'/approve')->assertOk();
        $posted = $this->postJson('/api/kpi-reviews/'.$resultId.'/post-bonus')->assertOk()->json('data.result');
        $this->assertDatabaseHas('payroll_adjustments', ['id' => $posted['payroll_adjustment_id'], 'amount' => $after['bonus_amount']]);
        $this->postJson('/api/payrolls/generate', ['month' => '2026-10', 'employee_type' => 'driver'])->assertCreated();
        $this->assertDatabaseHas('payroll_items', ['employee_id' => $driver->employee_id, 'incentive_amount' => $expectedIncentive]);
        $this->postJson('/api/payrolls/'.$payroll['id'].'/approve')->assertOk();
        DB::table('payroll_adjustments')->where('id', $posted['payroll_adjustment_id'])->update(['amount' => 999999]);
        $this->getJson('/api/payrolls/'.$payroll['id'])->assertOk()->assertJsonPath('data.items.0.incentive_amount', $expectedIncentive);
    }

    public function test_loading_an_existing_payroll_draft_refreshes_the_current_kpi_bonus(): void
    {
        $this->seed();
        $driver = User::where('email', 'driver@valley.test')->firstOrFail();
        $this->actingAs(User::where('email', 'owner@valley.test')->firstOrFail());
        $payroll = $this->postJson('/api/payrolls/generate', ['month' => '2026-10', 'employee_type' => 'driver'])->assertCreated()->json('data.payroll');
        $this->postJson('/api/kpi-reviews/generate', ['month' => '2026-10', 'employee_type' => 'driver'])->assertOk();
        DB::table('attendance_records')->insert(['employee_id' => $driver->employee_id, 'attendance_at' => '2026-10-07 08:00:00', 'status' => 'accepted']);
        $response = $this->getJson('/api/payrolls/'.$payroll['id'])->assertOk();
        $bonus = $response->json('data.items.0.incentive_amount');
        $this->assertGreaterThan(0, $bonus);
        $this->assertEquals(360000 + $bonus, $response->json('data.payroll.total_net'));
        $itemId = $response->json('data.items.0.id');
        DB::table('attendance_records')->insert(['employee_id' => $driver->employee_id, 'attendance_at' => '2026-10-08 08:00:00', 'status' => 'accepted']);
        $updated = $this->getJson('/api/payrolls/'.$payroll['id'])->assertOk();
        $this->assertGreaterThan($bonus, $updated->json('data.items.0.incentive_amount'));
        $this->assertSame($itemId, $updated->json('data.items.0.id'));
    }
}
