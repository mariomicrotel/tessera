<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabella ratei_risconti
 *
 * Gestisce le scritture di rettifica infrannuali per competenza economica:
 *  - Rateo attivo:     ricavo maturato nell'esercizio corrente, non ancora incassato
 *  - Rateo passivo:    costo maturato nell'esercizio corrente, non ancora pagato
 *  - Risconto attivo:  quota di costo già pagato ma di competenza futura
 *  - Risconto passivo: quota di ricavo già incassato ma di competenza futura
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ratei_risconti')) {
            return;
        }

        Schema::create('ratei_risconti', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36)->index();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->unsignedSmallInteger('anno_esercizio');

            $table->enum('tipo', [
                'rateo_attivo',
                'rateo_passivo',
                'risconto_attivo',
                'risconto_passivo',
            ]);

            $table->string('descrizione', 250);

            // Importo totale del contratto/documento sottostante (a scopo documentale)
            $table->decimal('importo_totale', 15, 2)->default(0);

            // Quota di competenza dell'esercizio da registrare
            $table->decimal('quota_esercizio', 15, 2);

            $table->date('data_inizio');   // inizio periodo di competenza
            $table->date('data_fine');     // fine periodo di competenza

            // Conto CE (costo o ricavo) coinvolto nella rettifica
            $table->foreignId('conto_economico_id')
                ->constrained('conti_contabili')
                ->restrictOnDelete();

            // Conto SP di rettifica (rateo/risconto attivo o passivo)
            $table->foreignId('conto_rettifica_id')
                ->constrained('conti_contabili')
                ->restrictOnDelete();

            $table->enum('stato', ['da_registrare', 'registrato', 'stornato'])
                ->default('da_registrare');

            // Movimento contabile generato dalla registrazione
            $table->foreignId('movimento_id')
                ->nullable()
                ->constrained('movimenti_contabili')
                ->nullOnDelete();

            // Movimento contabile di storno (inizio anno successivo)
            $table->foreignId('storno_id')
                ->nullable()
                ->constrained('movimenti_contabili')
                ->nullOnDelete();

            $table->text('note')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'anno_esercizio', 'stato']);
            $table->index(['tenant_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ratei_risconti');
    }
};
