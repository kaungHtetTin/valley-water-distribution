<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $now = now();
            $permissions = [
                'office.dashboard.view',
                'office.customers.view',
                'office.orders.view',
            ];

            $roleId = DB::table('roles')->where('name', 'Sales Supervisor')->value('id');
            if (! $roleId) {
                $roleId = DB::table('roles')->insertGetId([
                    'name' => 'Sales Supervisor',
                    'description' => 'Office access to sales dashboards, orders, and customers, without selling permissions.',
                    'guard_name' => 'web',
                    'allowed_apps' => json_encode(['office']),
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            DB::table('roles')->where('id', $roleId)->update([
                'description' => 'Office access to sales dashboards, orders, and customers, without selling permissions.',
                'allowed_apps' => json_encode(['office']),
                'updated_at' => $now,
            ]);
            DB::table('permission_role')->where('role_id', $roleId)->delete();
            foreach (DB::table('permissions')->whereIn('name', $permissions)->pluck('id') as $permissionId) {
                DB::table('permission_role')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
            }

            $templateId = DB::table('kpi_templates')->where('code', 'SALES-SUPERVISOR-V1')->value('id');
            if (! $templateId) {
                return;
            }

            DB::table('kpi_templates')->where('id', $templateId)->update([
                'employee_type' => 'sales_supervisor',
                'updated_at' => $now,
            ]);

            $employeeIds = DB::table('kpi_staff_profiles')
                ->join('employees', 'kpi_staff_profiles.employee_id', '=', 'employees.id')
                ->where('kpi_staff_profiles.kpi_template_id', $templateId)
                ->where('employees.employee_type', 'sales')
                ->pluck('employees.id');
            foreach ($employeeIds as $employeeId) {
                DB::table('employees')->where('id', $employeeId)->update([
                    'employee_type' => 'sales_supervisor',
                    'updated_at' => $now,
                ]);
                DB::table('users')->where('employee_id', $employeeId)->where('role', 'Sales Representative')->update([
                    'role' => 'Sales Supervisor',
                    'updated_at' => $now,
                ]);
            }

            $userEmployeeIds = DB::table('users')
                ->join('employees', 'users.employee_id', '=', 'employees.id')
                ->where('users.role', 'Sales Supervisor')
                ->where('employees.employee_type', 'sales')
                ->pluck('employees.id');
            foreach ($userEmployeeIds as $employeeId) {
                DB::table('employees')->where('id', $employeeId)->update([
                    'employee_type' => 'sales_supervisor',
                    'updated_at' => $now,
                ]);
                $profile = DB::table('kpi_staff_profiles')->where('employee_id', $employeeId)->first();
                if ($profile && (int) $profile->kpi_template_id !== (int) $templateId) {
                    DB::table('kpi_staff_profiles')->where('id', $profile->id)->update([
                        'kpi_template_id' => $templateId,
                        'updated_at' => $now,
                    ]);
                    DB::table('kpi_staff_target_items')->where('kpi_staff_profile_id', $profile->id)->delete();
                }
            }

            $supervisorIds = DB::table('employees')->where('employee_type', 'sales_supervisor')->pluck('id');
            $targetBonus = DB::table('kpi_templates')->where('id', $templateId)->value('target_bonus');
            foreach ($supervisorIds as $employeeId) {
                $profile = DB::table('kpi_staff_profiles')->where('employee_id', $employeeId)->first();
                if (! $profile) {
                    DB::table('kpi_staff_profiles')->insert([
                        'employee_id' => $employeeId,
                        'kpi_template_id' => $templateId,
                        'target_bonus' => $targetBonus,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                } elseif ((int) $profile->kpi_template_id !== (int) $templateId) {
                    DB::table('kpi_staff_profiles')->where('id', $profile->id)->update([
                        'kpi_template_id' => $templateId,
                        'updated_at' => $now,
                    ]);
                    DB::table('kpi_staff_target_items')->where('kpi_staff_profile_id', $profile->id)->delete();
                }
            }

            foreach (DB::table('kpi_staff_profiles')
                ->join('employees', 'kpi_staff_profiles.employee_id', '=', 'employees.id')
                ->where('employees.employee_type', 'sales_supervisor')
                ->where('kpi_staff_profiles.kpi_template_id', $templateId)
                ->get(['kpi_staff_profiles.id as profile_id']) as $profile) {
                foreach (DB::table('kpi_template_metrics')->where('kpi_template_id', $templateId)->where('calculation_type', '!=', 'manual')->whereNotNull('default_target')->get(['id', 'default_target']) as $metric) {
                    DB::table('kpi_staff_target_items')->insertOrIgnore([
                        'kpi_staff_profile_id' => $profile->profile_id,
                        'kpi_template_metric_id' => $metric->id,
                        'target_value' => $metric->default_target,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            // Existing submitted and approved reviews remain historical records.
            foreach (DB::table('kpi_results')->whereIn('employee_id', $supervisorIds)->where('status', 'draft')->get(['id']) as $draft) {
                DB::table('kpi_results')->where('id', $draft->id)->update([
                    'kpi_template_id' => $templateId,
                    'overall_score' => 0,
                    'bonus_amount' => 0,
                    'updated_at' => $now,
                ]);
                DB::table('kpi_result_items')->where('kpi_result_id', $draft->id)->delete();
                foreach (DB::table('kpi_template_metrics')->where('kpi_template_id', $templateId)->get() as $metric) {
                    DB::table('kpi_result_items')->insert([
                        'kpi_result_id' => $draft->id,
                        'kpi_template_metric_id' => $metric->id,
                        'target_value' => $metric->default_target,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        // Employee role changes and historical KPI records are business data.
    }
};
