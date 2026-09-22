<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category', 80)->nullable();
            $table->string('base_unit', 16);
            $table->string('purchase_unit', 32);
            $table->decimal('purchase_to_base', 14, 4)->default(1);
            $table->decimal('current_cost_per_base', 14, 6)->default(0);
            $table->decimal('low_stock_base', 14, 3)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['outlet_id', 'name']);
            $table->index(['outlet_id', 'category', 'is_active']);
        });

        Schema::create('food_recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['outlet_id', 'name']);
        });

        Schema::create('food_recipe_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('food_recipe_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->decimal('yield_quantity', 10, 3)->default(1);
            $table->date('effective_from');
            $table->timestamps();
            $table->unique(['food_recipe_id', 'version']);
        });

        Schema::create('food_recipe_ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('food_recipe_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('food_ingredient_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_base', 14, 3);
            $table->timestamps();
            $table->unique(['food_recipe_version_id', 'food_ingredient_id'], 'food_recipe_ingredient_unique');
        });

        Schema::create('food_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('food_recipe_version_id')->nullable()->constrained()->nullOnDelete();
            $table->date('effective_date');
            $table->string('source_name');
            $table->string('channel', 24)->default('sold');
            $table->decimal('quantity', 14, 3);
            $table->decimal('gross_sales', 14, 2)->default(0);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('net_sales', 14, 2)->default(0);
            $table->decimal('recipe_cost', 14, 2)->default(0);
            $table->timestamps();
            $table->index(['outlet_id', 'effective_date']);
        });

        Schema::create('food_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('food_ingredient_id')->constrained()->restrictOnDelete();
            $table->foreignId('food_sale_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('effective_date');
            $table->string('movement_type', 32);
            $table->decimal('quantity_base', 14, 3);
            $table->decimal('unit_cost_per_base', 14, 6)->default(0);
            $table->decimal('value_change', 14, 2)->default(0);
            $table->string('reference')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['outlet_id', 'food_ingredient_id', 'effective_date'], 'food_movement_lookup');
            $table->index(['outlet_id', 'movement_type', 'effective_date'], 'food_movement_type_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_stock_movements');
        Schema::dropIfExists('food_sales');
        Schema::dropIfExists('food_recipe_ingredients');
        Schema::dropIfExists('food_recipe_versions');
        Schema::dropIfExists('food_recipes');
        Schema::dropIfExists('food_ingredients');
    }
};
