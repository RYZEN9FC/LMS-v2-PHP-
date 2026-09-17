<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('timezone')->default('Asia/Kolkata');
            $table->timestamps();
        });

        Schema::create('outlets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 40)->nullable();
            $table->timestamps();
            $table->unique(['organisation_id', 'name']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('excise_code', 60)->nullable();
            $table->decimal('bottle_size_ml', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['outlet_id', 'name']);
            $table->index(['outlet_id', 'excise_code']);
        });

        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['outlet_id', 'name']);
        });

        Schema::create('recipe_ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('volume_ml', 10, 2);
            $table->timestamps();
            $table->unique(['recipe_id', 'product_id']);
        });

        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('source_type', ['opening', 'excise', 'pos']);
            $table->string('file_name')->nullable();
            $table->string('fingerprint', 64)->nullable();
            $table->date('effective_from');
            $table->date('effective_to');
            $table->enum('status', ['processing', 'applied', 'replaced', 'failed'])->default('processing');
            $table->unsignedInteger('row_count')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['outlet_id', 'source_type', 'effective_from']);
            $table->unique(['outlet_id', 'fingerprint']);
        });

        Schema::create('import_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recipe_id')->nullable()->constrained()->nullOnDelete();
            $table->date('effective_date');
            $table->string('source_item_name');
            $table->enum('sale_type', ['opening', 'indent', 'peg_30', 'peg_60', 'full_bottle', 'cocktail'])->nullable();
            $table->decimal('quantity', 12, 3)->default(0);
            $table->decimal('volume_ml', 12, 2)->default(0);
            $table->decimal('line_value', 14, 2)->nullable();
            $table->decimal('unit_price', 14, 2)->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();
            $table->index(['import_id', 'effective_date']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('import_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('import_line_id')->nullable()->constrained()->nullOnDelete();
            $table->date('effective_date');
            $table->enum('movement_type', ['opening', 'indent', 'sale']);
            $table->enum('sale_type', ['peg_30', 'peg_60', 'full_bottle', 'cocktail'])->nullable();
            $table->decimal('volume_ml', 12, 2); // receipt positive, sale negative
            $table->decimal('value_change', 14, 2)->default(0); // moving-average value change
            $table->decimal('receipt_price_per_ml', 14, 6)->nullable();
            $table->string('reference')->nullable();
            $table->timestamps();
            $table->index(['outlet_id', 'product_id', 'effective_date']);
            $table->index(['import_id', 'effective_date']);
        });

        Schema::create('product_daily_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->date('balance_date');
            $table->decimal('expected_ml', 12, 2);
            $table->decimal('stock_value', 14, 2)->default(0);
            $table->decimal('average_cost_per_ml', 14, 6)->default(0);
            $table->timestamps();
            $table->unique(['outlet_id', 'product_id', 'balance_date']);
            $table->index(['outlet_id', 'balance_date']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('organisation_id')->references('id')->on('organisations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['organisation_id']);
        });

        Schema::dropIfExists('product_daily_balances');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('import_lines');
        Schema::dropIfExists('imports');
        Schema::dropIfExists('recipe_ingredients');
        Schema::dropIfExists('recipes');
        Schema::dropIfExists('products');
        Schema::dropIfExists('outlets');
        Schema::dropIfExists('organisations');
    }
};
