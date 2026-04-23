<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_account_rendiconto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ledger_account_id')->constrained('ledger_accounts')->cascadeOnDelete();
            $table->foreignId('rendiconto_voce_id')->constrained('rendiconto_voci')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::table('ledger_account_rendiconto', function (Blueprint $table) {
            $table->unique('ledger_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_account_rendiconto');
    }
};
