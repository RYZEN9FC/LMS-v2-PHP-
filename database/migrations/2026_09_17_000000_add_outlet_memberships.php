<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('password');
        });

        Schema::create('outlet_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 30)->default('viewer');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['outlet_id', 'user_id']);
            $table->index(['user_id', 'is_active']);
        });

        $now = now();
        foreach (DB::table('users')->whereNotNull('organisation_id')->get(['id', 'organisation_id']) as $user) {
            foreach (DB::table('outlets')->where('organisation_id', $user->organisation_id)->pluck('id') as $outletId) {
                DB::table('outlet_user')->insertOrIgnore([
                    'outlet_id' => $outletId,
                    'user_id' => $user->id,
                    'role' => 'owner',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('outlet_user');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
