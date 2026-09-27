<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('kpi_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('employee_type')->index();
            $table->decimal('target_bonus', 14, 2)->default(40000);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('kpi_template_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_template_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('calculation_type')->default('higher');
            $table->string('unit')->default('percent');
            $table->decimal('weight', 5, 2);
            $table->decimal('default_target', 14, 2)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('kpi_bonus_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_template_id')->constrained()->cascadeOnDelete();
            $table->decimal('minimum_score', 6, 2);
            $table->decimal('maximum_score', 6, 2)->nullable();
            $table->decimal('payout_percent', 6, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('kpi_periods', function (Blueprint $table) {
            $table->id();
            $table->string('month', 7)->unique();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status')->default('open')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('kpi_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kpi_template_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('draft')->index();
            $table->decimal('overall_score', 6, 2)->default(0);
            $table->decimal('target_bonus', 14, 2)->default(0);
            $table->decimal('bonus_amount', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['kpi_period_id', 'employee_id']);
        });

        Schema::create('kpi_result_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_result_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kpi_template_metric_id')->constrained()->restrictOnDelete();
            $table->decimal('target_value', 14, 2)->nullable();
            $table->decimal('actual_value', 14, 2)->nullable();
            $table->decimal('manual_score', 6, 2)->nullable();
            $table->decimal('achievement_percent', 6, 2)->nullable();
            $table->decimal('weighted_score', 6, 2)->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->unique(['kpi_result_id', 'kpi_template_metric_id']);
        });

        $now = now();
        $salesTemplateId = DB::table('kpi_templates')->insertGetId([
            'code' => 'SALES-REP-V1',
            'name' => 'Sales Representative',
            'employee_type' => 'sales',
            'target_bonus' => 40000,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $driverTemplateId = DB::table('kpi_templates')->insertGetId([
            'code' => 'DRIVER-V1',
            'name' => 'Driver',
            'employee_type' => 'driver',
            'target_bonus' => 40000,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $metrics = [
            [$salesTemplateId, 'SAL-NET-SALES', 'Net sales achievement', 'higher', 'MMK', 35, null, 1],
            [$salesTemplateId, 'SAL-NEW-CUSTOMER', 'New customer acquisition', 'higher', 'customers', 15, null, 2],
            [$salesTemplateId, 'SAL-VISIT', 'Customer visit completion', 'higher', 'visits', 15, null, 3],
            [$salesTemplateId, 'SAL-COLLECTION', 'Collection achievement', 'higher', 'MMK', 20, null, 4],
            [$salesTemplateId, 'SAL-ATTENDANCE', 'Attendance and punctuality', 'higher', 'percent', 5, 100, 5],
            [$salesTemplateId, 'SAL-DISCIPLINE', 'Teamwork and discipline', 'manual', 'score', 5, 100, 6],
            [$salesTemplateId, 'SAL-TASK', 'Task and report completion', 'manual', 'score', 5, 100, 7],
            [$driverTemplateId, 'DRV-ATTENDANCE', 'Attendance and punctuality', 'higher', 'percent', 20, 100, 1],
            [$driverTemplateId, 'DRV-LEAVE', 'Leave compliance', 'manual', 'score', 5, 100, 2],
            [$driverTemplateId, 'DRV-COMPLETION', 'Delivery task completion', 'higher', 'deliveries', 20, null, 3],
            [$driverTemplateId, 'DRV-COMPLAINT', 'Complaint-free delivery', 'manual', 'score', 10, 100, 4],
            [$driverTemplateId, 'DRV-DISCIPLINE', 'Teamwork and responsibility', 'manual', 'score', 20, 100, 5],
            [$driverTemplateId, 'DRV-DAMAGE', 'Damage-free delivery', 'manual', 'score', 15, 100, 6],
            [$driverTemplateId, 'DRV-ONTIME-SAFETY', 'On-time and safe delivery', 'manual', 'score', 5, 100, 7],
            [$driverTemplateId, 'DRV-VEHICLE-COST', 'Vehicle and fuel cost control', 'manual', 'score', 5, 100, 8],
        ];

        DB::table('kpi_template_metrics')->insert(array_map(fn ($metric) => [
            'kpi_template_id' => $metric[0],
            'code' => $metric[1],
            'name' => $metric[2],
            'calculation_type' => $metric[3],
            'unit' => $metric[4],
            'weight' => $metric[5],
            'default_target' => $metric[6],
            'sort_order' => $metric[7],
            'created_at' => $now,
            'updated_at' => $now,
        ], $metrics));

        $bands = [
            [0, 75, 0],
            [75, 80, 50],
            [80, 90, 80],
            [90, 100, 100],
            [100, null, 120],
        ];
        $bonusRows = [];
        foreach ([$salesTemplateId, $driverTemplateId] as $templateId) {
            foreach ($bands as $index => $band) {
                $bonusRows[] = [
                    'kpi_template_id' => $templateId,
                    'minimum_score' => $band[0],
                    'maximum_score' => $band[1],
                    'payout_percent' => $band[2],
                    'sort_order' => $index + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        DB::table('kpi_bonus_rules')->insert($bonusRows);
    }

    public function down()
    {
        Schema::dropIfExists('kpi_result_items');
        Schema::dropIfExists('kpi_results');
        Schema::dropIfExists('kpi_periods');
        Schema::dropIfExists('kpi_bonus_rules');
        Schema::dropIfExists('kpi_template_metrics');
        Schema::dropIfExists('kpi_templates');
    }
};
