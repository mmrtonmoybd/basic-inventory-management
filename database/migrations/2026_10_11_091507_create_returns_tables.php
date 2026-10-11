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
        Schema::create('customer_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_no', 32)->nullable()->unique();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->date('return_date');
            $table->string('customer_name')->nullable();
            $table->string('reference', 100)->nullable();
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 24)->default('pending_inspection')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('return_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('good_quantity')->default(0);
            $table->unsignedInteger('damaged_quantity')->default(0);
            $table->string('reason')->nullable();
            $table->string('note')->nullable();

            $table->unique(['customer_return_id', 'product_variant_id']);
        });

        Schema::create('return_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_movement_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('return_inspection_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_inspection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('return_line_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('good_quantity')->default(0);
            $table->unsignedInteger('damaged_quantity')->default(0);
            $table->string('damage_reason')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('return_inspection_lines');
        Schema::dropIfExists('return_inspections');
        Schema::dropIfExists('return_lines');
        Schema::dropIfExists('customer_returns');
    }
};
