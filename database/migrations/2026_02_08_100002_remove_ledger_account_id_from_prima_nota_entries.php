<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prima_nota_entries', function (Blueprint $table) {
            $table->dropForeign(['ledger_account_id']);
            $table->dropIndex(['date', 'ledger_account_id']);
            $table->dropColumn('ledger_account_id');
            $table->index('rendiconto_code');
        });
    }

    public function down(): void
    {
        Schema::table('prima_nota_entries', function (Blueprint $table) {
            $table->dropIndex(['rendiconto_code']);
            $table->foreignId('ledger_account_id')->nullable()->after('id')->constrained('ledger_accounts')->cascadeOnDelete();
            $table->index(['date', 'ledger_account_id']);
        });
    }
};
