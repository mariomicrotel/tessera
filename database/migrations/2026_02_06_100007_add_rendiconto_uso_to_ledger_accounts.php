<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ledger_accounts', function (Blueprint $table) {
            $table->string('rendiconto_uso', 31)->nullable()->after('competenza_cassa');
        });

        \DB::table('ledger_accounts')->where('code', '2.1')->update(['rendiconto_uso' => 'quota']);
        \DB::table('ledger_accounts')->where('code', '2.2')->update(['rendiconto_uso' => 'donazione']);
        \DB::table('ledger_accounts')->where('code', '3.1')->update(['rendiconto_uso' => 'rimborsi']);
    }

    public function down(): void
    {
        Schema::table('ledger_accounts', function (Blueprint $table) {
            $table->dropColumn('rendiconto_uso');
        });
    }
};
