<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class KpiDefaultTargetsSeeder extends Seeder
{
    /**
     * Defaults translated from docs/KPI.xlsx into the units used by the app.
     *
     * The workbook's Sales Target is 4,000 product units. The application
     * records net sales in MMK, so the initial value uses the workbook's
     * 4,000 units at a conservative 1,000 MMK reference value per unit.
     */
    private const OPERATIONAL_TARGETS = [
        'SAL-NET-SALES' => 4_000_000,
        'SAL-NEW-CUSTOMER' => 20,
        'SAL-VISIT' => 150,
        'SAL-COLLECTION' => 12_500_000,
        'SAL-ATTENDANCE' => 26,
        'SUP-TEAM-SALES' => 100,
        'SUP-NEW-CUSTOMER' => 95,
        'SUP-CUSTOMER-VISIT' => 95,
        'SUP-COLLECTION' => 100,
        'SUP-ATTENDANCE' => 98,
        'DRV-ATTENDANCE' => 26,
        // Delivery refresh replaces this fallback with the month's assigned stops.
        'DRV-COMPLETION' => 1,
        'OFF-ATTENDANCE' => 26,
    ];

    private const DEFAULT_TEMPLATE_BY_EMPLOYEE_TYPE = [
        'sales' => 'SALES-REP-V1',
        'sales_supervisor' => 'SALES-SUPERVISOR-V1',
        'driver' => 'DRIVER-V1',
        'warehouse' => 'STOREKEEPER-V1',
        'office' => 'OFFICE-STAFF-V1',
    ];

    public function run(): void
    {
        if (! Schema::hasTable('kpi_templates')
            || ! Schema::hasTable('kpi_staff_profiles')
            || ! Schema::hasTable('kpi_staff_target_items')) {
            return;
        }

        $summary = DB::transaction(function (): array {
            $now = now();
            $defaultCount = 0;

            foreach (self::OPERATIONAL_TARGETS as $metricCode => $targetValue) {
                $defaultCount += DB::table('kpi_template_metrics')
                    ->where('code', $metricCode)
                    ->whereNull('default_target')
                    ->update([
                        'default_target' => $targetValue,
                        'updated_at' => $now,
                    ]);
            }

            $templateIds = DB::table('kpi_templates')
                ->whereIn('code', array_values(self::DEFAULT_TEMPLATE_BY_EMPLOYEE_TYPE))
                ->pluck('id', 'code');

            $createdProfiles = 0;
            $createdTargets = 0;
            $updatedDraftTargets = 0;

            $employees = DB::table('employees')
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->whereIn('employee_type', array_keys(self::DEFAULT_TEMPLATE_BY_EMPLOYEE_TYPE))
                ->get(['id', 'employee_type']);

            foreach ($employees as $employee) {
                $profile = DB::table('kpi_staff_profiles')
                    ->where('employee_id', $employee->id)
                    ->first();

                if (! $profile) {
                    $templateCode = self::DEFAULT_TEMPLATE_BY_EMPLOYEE_TYPE[$employee->employee_type];
                    $templateId = $templateIds->get($templateCode);
                    if (! $templateId) {
                        continue;
                    }

                    $targetBonus = DB::table('kpi_templates')
                        ->where('id', $templateId)
                        ->value('target_bonus') ?? 40_000;

                    $profileId = DB::table('kpi_staff_profiles')->insertGetId([
                        'employee_id' => $employee->id,
                        'kpi_template_id' => $templateId,
                        'target_bonus' => $targetBonus,
                        'created_by' => null,
                        'updated_by' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $profile = DB::table('kpi_staff_profiles')->where('id', $profileId)->first();
                    $createdProfiles++;
                }

                $metrics = DB::table('kpi_template_metrics')
                    ->where('kpi_template_id', $profile->kpi_template_id)
                    ->where('calculation_type', '!=', 'manual')
                    ->whereNotNull('default_target')
                    ->get(['id', 'default_target']);

                foreach ($metrics as $metric) {
                    $createdTargets += DB::table('kpi_staff_target_items')->insertOrIgnore([
                        'kpi_staff_profile_id' => $profile->id,
                        'kpi_template_metric_id' => $metric->id,
                        'target_value' => $metric->default_target,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $updatedDraftTargets += DB::table('kpi_result_items')
                        ->join('kpi_results', 'kpi_result_items.kpi_result_id', '=', 'kpi_results.id')
                        ->where('kpi_results.employee_id', $employee->id)
                        ->where('kpi_results.status', 'draft')
                        ->where('kpi_result_items.kpi_template_metric_id', $metric->id)
                        ->whereNull('kpi_result_items.target_value')
                        ->update([
                            'kpi_result_items.target_value' => $metric->default_target,
                            'kpi_result_items.updated_at' => $now,
                        ]);
                }
            }

            return compact('defaultCount', 'createdProfiles', 'createdTargets', 'updatedDraftTargets');
        });

        $this->command?->info(sprintf(
            'KPI defaults seeded: %d role defaults, %d staff profiles, %d staff targets, %d draft targets.',
            $summary['defaultCount'],
            $summary['createdProfiles'],
            $summary['createdTargets'],
            $summary['updatedDraftTargets'],
        ));
    }
}
