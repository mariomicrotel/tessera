<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Movimenti amministrativi semplificati.
 *
 * Tabella parallela a prima_nota_entries: stessa logica di cassa/banca
 * ma senza vincoli sul piano dei conti o sul rendiconto MOD/D.
 * Destinata a enti che usano l'amministrazione semplificata (flag
 * TESSERA_MODULE_ADMINISTRATIVE_MOVEMENTS).
 *
 * Tipi:
 *   - entrata  : importo positivo sul conto
 *   - uscita   : importo negativo sul conto
 *   - giroconto: trasferimento tra due conti dello stesso tenant
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('administrative_movements', function (Blueprint $table) {
            $table->id();
            $table->char('tenant_id', 36)->index();
            $table->date('data')->index();
            $table->enum('tipo', ['entrata', 'uscita', 'giroconto'])->index();
            $table->decimal('importo', 12, 2);                     // sempre positivo
            $table->string('descrizione', 255);
            $table->unsignedBigInteger('category_id')->nullable();  // → administrative_movement_categories
            $table->unsignedBigInteger('conto_id')->nullable();     // → conti
            $table->unsignedBigInteger('conto_destinazione_id')->nullable(); // → conti (solo giroconto)
            $table->string('riferimento', 100)->nullable();         // num. fattura, ricevuta, ecc.
            $table->text('note')->nullable();
            // Collegamento opzionale a un record correlato (es. Incasso, ExpenseRefund)
            $table->string('entryable_type', 150)->nullable();
            $table->unsignedBigInteger('entryable_id')->nullable();
            $table->json('tags')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'data'], 'am_tenant_data_idx');
            $table->index(['tenant_id', 'tipo'], 'am_tenant_tipo_idx');
            $table->index(['tenant_id', 'category_id'], 'am_tenant_cat_idx');
            $table->index(['entryable_type', 'entryable_id'], 'am_entryable_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('administrative_movements');
    }
};
