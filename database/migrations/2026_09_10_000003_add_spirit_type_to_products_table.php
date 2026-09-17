<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('spirit_type', 40)->default('Other')->after('name');
            $table->index(['outlet_id', 'spirit_type']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['outlet_id', 'spirit_type']);
            $table->dropColumn('spirit_type');
        });
    }
};
