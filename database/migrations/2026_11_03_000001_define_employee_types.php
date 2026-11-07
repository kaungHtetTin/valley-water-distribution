<?php

use App\Support\EmployeeTypes;
use App\Support\SalaryDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $now = now();
            // Job titles do not change login roles or their permissions.
            foreach (DB::table('employees')->where('employee_type', 'office')->get() as $employee) {
                $roles = DB::table('users')->where('employee_id', $employee->id)->pluck('role')->unique();
                $type = $roles->count() === 1 ? match ($roles->first()) {
                    'Accountant' => 'accountant',
                    'HR' => 'hr',
                    default => 'other',
                } : 'other';
                // Preserve the effective salary even when the new category has a different default.
                $company = DB::table('companies')->orderBy('id')->value('default_base_salaries');
                $defaults = array_replace(SalaryDefaults::AMOUNTS, $company ? json_decode($company, true) : []);
                DB::table('employees')->where('id', $employee->id)->update([
                    'employee_type' => $type,
                    'base_salary' => $employee->base_salary ?? $defaults['office'],
                    'updated_at' => $now,
                ]);
            }

            foreach (DB::table('companies')->get(['id', 'default_base_salaries']) as $company) {
                $defaults = $company->default_base_salaries ? json_decode($company->default_base_salaries, true) : [];
                foreach (['accountant', 'manager', 'hr'] as $type) {
                    $defaults[$type] ??= $defaults['office'] ?? SalaryDefaults::AMOUNTS['office'];
                }
                $defaults['sale_helper'] ??= $defaults['other'] ?? SalaryDefaults::AMOUNTS['other'];
                DB::table('companies')->where('id', $company->id)->update(['default_base_salaries' => json_encode($defaults)]);
            }

            // Reuse each office template for Other and copy it for the newly separated categories.
            foreach (DB::table('kpi_templates')->where('employee_type', 'office')->get() as $source) {
                DB::table('kpi_templates')->where('id', $source->id)->update([
                    'employee_type' => 'other',
                    'name' => $source->code === 'OFFICE-STAFF-V1' ? 'Other' : $source->name,
                ]);
                foreach (['accountant', 'manager', 'hr'] as $type) {
                    [$templateId, $metricIds] = $this->copyTemplate($source, $type,
                        $source->code === 'OFFICE-STAFF-V1' ? EmployeeTypes::KPI_TEMPLATES[$type] : strtoupper($type).'-'.$source->code);
                    $employeeIds = DB::table('employees')->where('employee_type', $type)->pluck('id');
                    $profileIds = DB::table('kpi_staff_profiles')->whereIn('employee_id', $employeeIds)
                        ->where('kpi_template_id', $source->id)->pluck('id');
                    DB::table('kpi_staff_profiles')->whereIn('id', $profileIds)->update(['kpi_template_id' => $templateId]);
                    foreach ($metricIds as $oldId => $newId) {
                        DB::table('kpi_staff_target_items')->whereIn('kpi_staff_profile_id', $profileIds)
                            ->where('kpi_template_metric_id', $oldId)->update(['kpi_template_metric_id' => $newId]);
                    }
                    // Submitted and approved results keep their original templates and snapshots.
                    $draftIds = DB::table('kpi_results')->whereIn('employee_id', $employeeIds)
                        ->where('kpi_template_id', $source->id)->where('status', 'draft')->pluck('id');
                    DB::table('kpi_results')->whereIn('id', $draftIds)->update(['kpi_template_id' => $templateId]);
                    foreach ($metricIds as $oldId => $newId) {
                        DB::table('kpi_result_items')->whereIn('kpi_result_id', $draftIds)
                            ->where('kpi_template_metric_id', $oldId)->update(['kpi_template_metric_id' => $newId]);
                    }
                }
            }
            $helper = DB::table('kpi_templates')->where('code', 'HELPER-V1')->first();
            if ($helper) {
                // Existing driver assignments to the old helper template remain intact.
                $this->copyTemplate($helper, 'sale_helper', 'SALE-HELPER-V1');
            }
            foreach (['SALES-REP-V1' => 'Sale Representative', 'SALES-SUPERVISOR-V1' => 'Sale Manager', 'STOREKEEPER-V1' => 'Storekeeper'] as $code => $name) {
                DB::table('kpi_templates')->where('code', $code)->update(['name' => $name]);
            }
        });
    }

    private function copyTemplate(object $source, string $type, string $code): array
    {
        $values = (array) $source;
        unset($values['id']);
        // An imported database may already contain the new templates. Keep their
        // IDs and configured values so profiles and historical results stay valid.
        $templateId = DB::table('kpi_templates')->where('code', $code)->value('id');
        $templateId ??= DB::table('kpi_templates')->insertGetId(array_replace($values, [
            'code' => $code, 'name' => EmployeeTypes::LABELS[$type], 'employee_type' => $type,
        ]));
        $metricIds = [];
        foreach (DB::table('kpi_template_metrics')->where('kpi_template_id', $source->id)->get() as $metric) {
            $values = (array) $metric;
            unset($values['id']);
            $metricCode = strtoupper($type).'-'.$metric->code;
            $metricIds[$metric->id] = DB::table('kpi_template_metrics')
                ->where('kpi_template_id', $templateId)->where('code', $metricCode)->value('id')
                ?? DB::table('kpi_template_metrics')->insertGetId(array_replace($values, [
                    'kpi_template_id' => $templateId, 'code' => $metricCode,
                ]));
        }
        // Existing payout bands may be customized; only seed an empty ruleset.
        if (! DB::table('kpi_bonus_rules')->where('kpi_template_id', $templateId)->exists()) {
            foreach (DB::table('kpi_bonus_rules')->where('kpi_template_id', $source->id)->get() as $rule) {
                $values = (array) $rule;
                unset($values['id']);
                DB::table('kpi_bonus_rules')->insert(array_replace($values, ['kpi_template_id' => $templateId]));
            }
        }

        return [$templateId, $metricIds];
    }

    public function down(): void
    {
        // Employee classifications and KPI history are business data.
    }
};
