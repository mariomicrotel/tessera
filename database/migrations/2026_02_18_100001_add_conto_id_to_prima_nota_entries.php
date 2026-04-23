<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prima_nota_entries', function (Blueprint $table) {
            $table->foreignId('conto_id')->after('id')->constrained('conti')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('prima_nota_entries', function (Blueprint $table) {
            $table->dropForeign(['conto_id']);
            $table->dropColumn('conto_id');
        });
    }
};
