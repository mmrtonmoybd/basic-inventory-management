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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('variant_attributes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->boolean('is_color')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('variant_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_attribute_id')->constrained()->restrictOnDelete();
            $table->string('value');
            $table->string('code', 32)->nullable();
            $table->string('swatch', 16)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['variant_attribute_id', 'value']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code', 64)->unique();
            $table->string('unit', 16)->default('pcs');
            $table->text('description')->nullable();
            $table->string('status', 16)->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('name');
        });

        Schema::create('product_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_attribute_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('position')->default(0);

            $table->unique(['product_id', 'variant_attribute_id']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('sku', 64)->unique();
            $table->string('barcode', 64)->nullable()->unique();
            $table->string('combination_key', 191);
            $table->string('name');
            $table->unsignedInteger('low_stock_threshold')->default(0);
            $table->string('status', 16)->default('active');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'combination_key']);
            $table->index(['product_id', 'status']);
        });

        Schema::create('product_variant_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained(indexName: 'pvav_variant_fk')->cascadeOnDelete();
            $table->foreignId('variant_attribute_id')->constrained(indexName: 'pvav_attribute_fk')->restrictOnDelete();
            $table->foreignId('variant_attribute_value_id')->constrained(indexName: 'pvav_value_fk')->restrictOnDelete();

            $table->unique(['product_variant_id', 'variant_attribute_id'], 'pvav_variant_attribute_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variant_attribute_values');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_attributes');
        Schema::dropIfExists('products');
        Schema::dropIfExists('variant_attribute_values');
        Schema::dropIfExists('variant_attributes');
        Schema::dropIfExists('categories');
    }
};
