<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('damage_cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_no', 32)->nullable()->unique();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('customer_return_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('stock_movement_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('source', 24)->default('warehouse');
            $table->date('damage_date');
            $table->string('reason');
            $table->text('internal_note')->nullable();
            $table->text('supplier_message')->nullable();
            $table->string('status', 24)->default('open')->index();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('damage_case_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('damage_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('claimed_quantity')->default(0);

            $table->unique(['damage_case_id', 'product_variant_id']);
        });

        Schema::create('damage_case_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('damage_case_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->unsignedInteger('size')->default(0);
            $table->timestamps();
        });

        Schema::create('supplier_exchange_claims', function (Blueprint $table) {
            $table->id();
            $table->string('claim_no', 32)->nullable()->unique();
            $table->foreignId('damage_case_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('status', 24)->default('pending_review')->index();
            $table->text('supplier_message')->nullable();
            $table->string('supplier_reference', 100)->nullable();
            $table->text('supplier_agreement_note')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('dispatch_movement_id')->nullable()->constrained('stock_movements')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('supplier_agreed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('supplier_agreed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('dispatched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dispatched_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'status']);
        });

        Schema::create('supplier_exchange_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_exchange_claim_id')->constrained(indexName: 'sel_claim_fk')->cascadeOnDelete();
            $table->foreignId('damage_case_line_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->foreignId('replacement_variant_id')->nullable()->constrained('product_variants')->restrictOnDelete();
            $table->unsignedInteger('claimed_quantity');
            $table->unsignedInteger('approved_quantity')->default(0);
            $table->unsignedInteger('dispatched_quantity')->default(0);
            $table->unsignedInteger('received_quantity')->default(0);
            $table->unsignedInteger('written_off_quantity')->default(0);
        });

        Schema::create('supplier_exchange_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_no', 32)->nullable()->unique();
            $table->foreignId('supplier_exchange_claim_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_movement_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('received_date');
            $table->text('notes')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('supplier_exchange_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_exchange_receipt_id')->constrained(indexName: 'serl_receipt_fk')->cascadeOnDelete();
            $table->foreignId('supplier_exchange_line_id')->constrained(indexName: 'serl_line_fk')->restrictOnDelete();
            $table->unsignedInteger('quantity');
        });

        Schema::create('approval_records', function (Blueprint $table) {
            $table->id();
            $table->morphs('approvable');
            $table->string('action', 32);
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24)->nullable();
            $table->text('comment')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_records');
        Schema::dropIfExists('supplier_exchange_receipt_lines');
        Schema::dropIfExists('supplier_exchange_receipts');
        Schema::dropIfExists('supplier_exchange_lines');
        Schema::dropIfExists('supplier_exchange_claims');
        Schema::dropIfExists('damage_case_images');
        Schema::dropIfExists('damage_case_lines');
        Schema::dropIfExists('damage_cases');
    }
};
