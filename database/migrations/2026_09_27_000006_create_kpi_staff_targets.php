<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('kpi_staff_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('kpi_template_id')->constrained()->restrictOnDelete();
            $table->decimal('target_bonus', 14, 2)->default(40000);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('kpi_staff_target_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_staff_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kpi_template_metric_id')->constrained()->cascadeOnDelete();
            $table->decimal('target_value', 14, 2)->nullable();
            $table->timestamps();
            $table->unique(['kpi_staff_profile_id', 'kpi_template_metric_id'], 'kpi_staff_target_metric_unique');
        });

        $now = now();
        $templates = [
            [
                'code' => 'SALES-SUPERVISOR-V1',
                'name' => 'Sales Supervisor',
                'employee_type' => 'sales',
                'metrics' => [
                    ['SUP-TEAM-SALES', 'Team sales achievement', 'higher', 'percent', 30, 100],
                    ['SUP-NEW-CUSTOMER', 'New customer growth', 'higher', 'percent', 20, 95],
                    ['SUP-CUSTOMER-VISIT', 'Team customer visit', 'higher', 'percent', 15, 95],
                    ['SUP-COLLECTION', 'Team collection achievement', 'higher', 'percent', 10, 100],
                    ['SUP-ATTENDANCE', 'Team attendance and discipline', 'higher', 'percent', 10, 98],
                    ['SUP-COACHING', 'Team management and coaching', 'manual', 'score', 10, 100],
                    ['SUP-REPORT', 'Daily and weekly report accuracy', 'manual', 'score', 5, 100],
                ],
            ],
            [
                'code' => 'HELPER-V1',
                'name' => 'Helper',
                'employee_type' => 'driver',
                'metrics' => [
                    ['HLP-ATTENDANCE', 'Attendance and punctuality', 'higher', 'percent', 20, 98],
                    ['HLP-LEAVE', 'Leave compliance', 'manual', 'score', 5, 100],
                    ['HLP-TASK', 'Task completion', 'manual', 'score', 20, 100],
                    ['HLP-COMPLAINT', 'Complaint-free service', 'manual', 'score', 15, 100],
                    ['HLP-DISCIPLINE', 'Teamwork and responsibility', 'manual', 'score', 20, 100],
                    ['HLP-DAMAGE', 'Damage-free handling', 'manual', 'score', 15, 100],
                    ['HLP-CLEANLINESS', 'Office and workplace cleanliness', 'manual', 'score', 5, 100],
                ],
            ],
            [
                'code' => 'STOREKEEPER-V1',
                'name' => 'Storekeeper',
                'employee_type' => 'warehouse',
                'metrics' => [
                    ['STK-BALANCE', 'Stock record and physical balance accuracy', 'higher', 'percent', 30, 98],
                    ['STK-LOSS', 'Stock loss and damage control', 'higher', 'percent', 20, 95],
                    ['STK-MOVEMENT', 'Stock receiving and issue accuracy', 'higher', 'percent', 15, 100],
                    ['STK-PREPARE', 'On-time order and delivery preparation', 'higher', 'percent', 10, 95],
                    ['STK-CLEANLINESS', 'Warehouse cleanliness and organization', 'manual', 'score', 10, 100],
                    ['STK-TASK', 'Task completion', 'manual', 'score', 10, 100],
                    ['STK-ATTENDANCE', 'Attendance, lateness and leave', 'manual', 'score', 5, 100],
                ],
            ],
        ];

        $bands = [[0, 75, 0], [75, 80, 50], [80, 90, 80], [90, 100, 100], [100, null, 120]];
        foreach ($templates as $template) {
            $templateId = DB::table('kpi_templates')->insertGetId([
                'code' => $template['code'],
                'name' => $template['name'],
                'employee_type' => $template['employee_type'],
                'target_bonus' => 40000,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($template['metrics'] as $index => $metric) {
                DB::table('kpi_template_metrics')->insert([
                    'kpi_template_id' => $templateId,
                    'code' => $metric[0],
                    'name' => $metric[1],
                    'calculation_type' => $metric[2],
                    'unit' => $metric[3],
                    'weight' => $metric[4],
                    'default_target' => $metric[5],
                    'sort_order' => $index + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach ($bands as $index => $band) {
                DB::table('kpi_bonus_rules')->insert([
                    'kpi_template_id' => $templateId,
                    'minimum_score' => $band[0],
                    'maximum_score' => $band[1],
                    'payout_percent' => $band[2],
                    'sort_order' => $index + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $defaultTemplates = [
            'sales' => 'SALES-REP-V1',
            'driver' => 'DRIVER-V1',
            'warehouse' => 'STOREKEEPER-V1',
        ];
        foreach ($defaultTemplates as $employeeType => $templateCode) {
            $templateId = DB::table('kpi_templates')->where('code', $templateCode)->value('id');
            if (! $templateId) {
                continue;
            }
            $metrics = DB::table('kpi_template_metrics')->where('kpi_template_id', $templateId)->get();
            $employees = DB::table('employees')->where('employee_type', $employeeType)->where('is_active', true)->get(['id']);
            foreach ($employees as $employee) {
                $profileId = DB::table('kpi_staff_profiles')->insertGetId([
                    'employee_id' => $employee->id,
                    'kpi_template_id' => $templateId,
                    'target_bonus' => 40000,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                foreach ($metrics as $metric) {
                    if ($metric->default_target === null) {
                        continue;
                    }
                    DB::table('kpi_staff_target_items')->insert([
                        'kpi_staff_profile_id' => $profileId,
                        'kpi_template_metric_id' => $metric->id,
                        'target_value' => $metric->default_target,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down()
    {
        Schema::dropIfExists('kpi_staff_target_items');
        Schema::dropIfExists('kpi_staff_profiles');

        $templateIds = DB::table('kpi_templates')
            ->whereIn('code', ['SALES-SUPERVISOR-V1', 'HELPER-V1', 'STOREKEEPER-V1'])
            ->pluck('id');
        DB::table('kpi_templates')->whereIn('id', $templateIds)->delete();
    }
};
