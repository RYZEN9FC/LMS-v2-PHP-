<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_ingredients', function (Blueprint $table) {
            $table->dropColumn('low_stock_base');
        });
    }

    public function down(): void
    {
        Schema::table('food_ingredients', function (Blueprint $table) {
            $table->decimal('low_stock_base', 14, 3)->nullable()->after('purchase_to_base');
        });
    }
};
