<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verbali', function (Blueprint $table) {
            $table->string('stato', 20)->default('bozza')->after('numero');
        });

        // Verbali esistenti: considerarli confermati (non modificabili)
        \DB::table('verbali')->whereRaw('1=1')->update(['stato' => 'confermato']);
    }

    public function down(): void
    {
        Schema::table('verbali', function (Blueprint $table) {
            $table->dropColumn('stato');
        });
    }
};
