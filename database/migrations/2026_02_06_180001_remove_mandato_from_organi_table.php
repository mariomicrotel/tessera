<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organi', function (Blueprint $table) {
            $table->dropColumn(['mandato_da', 'mandato_a']);
        });
    }

    public function down(): void
    {
        Schema::table('organi', function (Blueprint $table) {
            $table->date('mandato_da')->nullable();
            $table->date('mandato_a')->nullable();
        });
    }
};
