<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->string('drink_type', 20)->default('classic')->after('name');
            $table->index(['outlet_id', 'drink_type']);
        });

        DB::table('recipes')->whereIn('name', [
            'Dill With It!', 'Kiraak Kaapi', 'Lite Lo', 'Meloni', 'Miyaa Martini',
            'Moonfire', 'Sip in Peace', 'The OG', 'Wild Tea',
        ])->update(['drink_type' => 'signature']);
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropIndex(['outlet_id', 'drink_type']);
            $table->dropColumn('drink_type');
        });
    }
};
