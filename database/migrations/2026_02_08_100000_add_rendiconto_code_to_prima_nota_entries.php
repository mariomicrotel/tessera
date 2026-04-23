<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prima_nota_entries', function (Blueprint $table) {
            $table->string('rendiconto_code', 50)->nullable()->after('ledger_account_id');
        });
    }

    public function down(): void
    {
        Schema::table('prima_nota_entries', function (Blueprint $table) {
            $table->dropColumn('rendiconto_code');
        });
    }
};
