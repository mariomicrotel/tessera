<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organi', function (Blueprint $table) {
            $table->dropForeign(['ente_id']);
            $table->dropColumn('ente_id');
        });
    }

    public function down(): void
    {
        Schema::table('organi', function (Blueprint $table) {
            $table->foreignId('ente_id')->nullable()->after('id');
            $table->foreign('ente_id')->references('id')->on('enti')->cascadeOnDelete();
        });
    }
};
