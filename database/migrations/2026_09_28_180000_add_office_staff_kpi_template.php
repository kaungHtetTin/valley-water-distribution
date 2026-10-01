<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('kpi_templates')->updateOrInsert(
            ['code' => 'OFFICE-STAFF-V1'],
            [
                'name' => 'Office Staff',
                'employee_type' => 'office',
                'target_bonus' => 40000,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
        $templateId = (int) DB::table('kpi_templates')->where('code', 'OFFICE-STAFF-V1')->value('id');

        $metrics = [
            ['OFF-ATTENDANCE', 'Attendance and punctuality', 'higher', 'days', 20, 26],
            ['OFF-TASK', 'Task completion', 'manual', 'score', 30, 100],
            ['OFF-ACCURACY', 'Work accuracy and quality', 'manual', 'score', 25, 100],
            ['OFF-SERVICE', 'Internal and customer service', 'manual', 'score', 15, 100],
            ['OFF-DISCIPLINE', 'Teamwork and discipline', 'manual', 'score', 10, 100],
        ];
        foreach ($metrics as $index => $metric) {
            DB::table('kpi_template_metrics')->updateOrInsert(
                ['code' => $metric[0]],
                [
                    'kpi_template_id' => $templateId,
                    'name' => $metric[1],
                    'calculation_type' => $metric[2],
                    'unit' => $metric[3],
                    'weight' => $metric[4],
                    'default_target' => $metric[5],
                    'sort_order' => $index + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        foreach ([[0, 75, 0], [75, 80, 50], [80, 90, 80], [90, 100, 100], [100, null, 120]] as $index => $band) {
            DB::table('kpi_bonus_rules')->updateOrInsert(
                ['kpi_template_id' => $templateId, 'sort_order' => $index + 1],
                [
                    'minimum_score' => $band[0],
                    'maximum_score' => $band[1],
                    'payout_percent' => $band[2],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $attendanceMetricId = (int) DB::table('kpi_template_metrics')->where('code', 'OFF-ATTENDANCE')->value('id');
        $officeEmployees = DB::table('employees')
            ->where('employee_type', 'office')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->get(['id']);
        foreach ($officeEmployees as $employee) {
            DB::table('kpi_staff_profiles')->insertOrIgnore([
                'employee_id' => $employee->id,
                'kpi_template_id' => $templateId,
                'target_bonus' => 40000,
                'created_by' => null,
                'updated_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $profileId = DB::table('kpi_staff_profiles')->where('employee_id', $employee->id)->value('id');
            if ($profileId && $attendanceMetricId) {
                DB::table('kpi_staff_target_items')->insertOrIgnore([
                    'kpi_staff_profile_id' => $profileId,
                    'kpi_template_metric_id' => $attendanceMetricId,
                    'target_value' => 26,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        $templateId = DB::table('kpi_templates')->where('code', 'OFFICE-STAFF-V1')->value('id');
        if (! $templateId) {
            return;
        }

        $resultIds = DB::table('kpi_results')->where('kpi_template_id', $templateId)->pluck('id');
        DB::table('kpi_result_items')->whereIn('kpi_result_id', $resultIds)->delete();
        DB::table('kpi_results')->whereIn('id', $resultIds)->delete();
        $profileIds = DB::table('kpi_staff_profiles')->where('kpi_template_id', $templateId)->pluck('id');
        DB::table('kpi_staff_target_items')->whereIn('kpi_staff_profile_id', $profileIds)->delete();
        DB::table('kpi_staff_profiles')->whereIn('id', $profileIds)->delete();
        DB::table('kpi_bonus_rules')->where('kpi_template_id', $templateId)->delete();
        DB::table('kpi_template_metrics')->where('kpi_template_id', $templateId)->delete();
        DB::table('kpi_templates')->where('id', $templateId)->delete();
    }
};
