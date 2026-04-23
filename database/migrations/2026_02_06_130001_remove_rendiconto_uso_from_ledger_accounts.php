<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ledger_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('ledger_accounts', 'rendiconto_uso')) {
                $table->dropColumn('rendiconto_uso');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ledger_accounts', function (Blueprint $table) {
            $table->string('rendiconto_uso', 31)->nullable()->after('competenza_cassa');
        });
    }
};
