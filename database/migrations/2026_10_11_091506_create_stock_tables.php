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
        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('available')->default(0);
            $table->unsignedInteger('inspection')->default(0);
            $table->unsignedInteger('damaged')->default(0);
            $table->unsignedInteger('with_supplier')->default(0);
            $table->timestamps();

            $table->unique(['product_variant_id', 'warehouse_id']);
            $table->index(['warehouse_id', 'available']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 32)->nullable()->unique();
            $table->string('type', 32);
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->restrictOnDelete();
            $table->nullableMorphs('source');
            $table->date('business_date');
            $table->string('external_reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->unsignedInteger('total_quantity')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['type', 'business_date']);
            $table->index('business_date');
        });

        Schema::create('stock_movement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_movement_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->string('from_condition', 16)->nullable();
            $table->string('to_condition', 16)->nullable();
            $table->unsignedInteger('quantity');
            $table->string('note')->nullable();

            $table->index('product_variant_id');
        });

        Schema::create('stock_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_movement_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_movement_line_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('movement_type', 32);
            $table->string('condition', 16);
            $table->integer('quantity_change');
            $table->unsignedInteger('balance_after');
            $table->date('business_date');
            $table->timestamp('created_at')->nullable();

            $table->index(['product_variant_id', 'warehouse_id', 'condition', 'business_date'], 'ledger_variant_lookup');
            $table->index(['business_date', 'movement_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_ledger_entries');
        Schema::dropIfExists('stock_movement_lines');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_balances');
    }
};
