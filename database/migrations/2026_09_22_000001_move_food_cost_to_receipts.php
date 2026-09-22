<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_ingredients', function (Blueprint $table) {
            $table->dropColumn('current_cost_per_base');
        });
    }

    public function down(): void
    {
        Schema::table('food_ingredients', function (Blueprint $table) {
            $table->decimal('current_cost_per_base', 14, 6)->default(0)->after('purchase_to_base');
        });
    }
};
