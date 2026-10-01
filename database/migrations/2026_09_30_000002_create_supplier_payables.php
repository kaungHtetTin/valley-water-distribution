<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('stock_document_code')->nullable()->unique();
            $table->string('invoice_no')->nullable();
            $table->date('invoice_date')->index();
            $table->date('due_date')->nullable()->index();
            $table->unsignedInteger('payment_terms_days')->default(30);
            $table->decimal('total', 14, 2);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->string('status')->default('unpaid')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['supplier_id', 'status', 'due_date']);
        });

        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_invoice_id')->constrained()->restrictOnDelete();
            $table->date('payment_date')->index();
            $table->decimal('amount', 14, 2);
            $table->string('payment_method');
            $table->string('reference_no')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');
        Schema::dropIfExists('supplier_invoices');
    }
};
