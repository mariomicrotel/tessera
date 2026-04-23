<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Piano dei conti (entrata, uscita, patrimoniale). Gestione istituzionale/commerciale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('ledger_accounts')->nullOnDelete();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('type', 20)->default('patrimoniale'); // entrata, uscita, patrimoniale
            $table->string('gestione', 20)->nullable(); // istituzionale, commerciale
            $table->boolean('competenza_cassa')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_accounts');
    }
};
