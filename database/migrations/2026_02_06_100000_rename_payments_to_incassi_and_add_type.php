<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('payments', 'incassi');

        Schema::table('incassi', function (Blueprint $table) {
            $table->string('type', 20)->default('quota')->after('genera_prima_nota');
        });
    }

    public function down(): void
    {
        Schema::table('incassi', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::rename('incassi', 'payments');
    }
};
