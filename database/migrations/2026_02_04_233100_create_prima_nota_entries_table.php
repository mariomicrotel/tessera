<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prima nota: movimenti con conto, importo (signed), collegamento opzionale a pagamento/rimborso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prima_nota_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ledger_account_id')->constrained()->cascadeOnDelete();
            $table->string('entryable_type')->nullable();
            $table->unsignedBigInteger('entryable_id')->nullable();
            $table->date('date');
            $table->decimal('amount', 12, 2); // positivo = entrata, negativo = uscita
            $table->string('description')->nullable();
            $table->string('gestione', 20)->nullable();
            $table->boolean('competenza_cassa')->default(true);
            $table->timestamps();
            $table->index(['date', 'ledger_account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prima_nota_entries');
    }
};
