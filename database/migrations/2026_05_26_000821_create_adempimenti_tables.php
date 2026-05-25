<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabelle per gli adempimenti fiscali / amministrativi periodici di ente.
 *
 * Architettura:
 *  - `adempimenti_templates`: catalogo statico (seedato) degli adempimenti
 *    italiani per ETS e cooperative. Una riga = una "tipologia" di adempimento
 *    (es. "LIPE", "CU consegna lavoratori"). Definisce periodicità e regola
 *    di calcolo della scadenza.
 *  - `adempimenti_items`: istanza concreta per (tenant, anno, periodo). Una
 *    riga = "scadenza che il commercialista deve curare per quell'ente in
 *    quel periodo". Generata dal AdempimentoGeneratorService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adempimenti_templates', function (Blueprint $table) {
            $table->id();

            // Identità
            $table->string('codice', 60)->unique();   // 'lipe', 'cu_consegna', 'mod_eas', ecc.
            $table->string('nome', 200);
            $table->text('descrizione')->nullable();
            $table->string('riferimento_normativo', 200)->nullable();

            // Applicabilità
            $table->enum('applicabile_a', ['ets', 'cooperativa', 'entrambi'])->default('entrambi');
            $table->enum('categoria', [
                'iva',                  // LIPE, dichiarazione IVA, versamenti IVA, acconto
                'ritenute',             // ritenute lavoro autonomo, CU, 770
                'dichiarativi',         // Modelli Redditi, IRAP
                'bilancio',             // bilancio ETS, RUNTS deposito
                'ets',                  // 5x1000, RUNTS variazioni, modello EAS
                'cooperative',          // revisione, ristorni, libro soci
                'altro',
            ])->default('altro');

            // Periodicità: definisce quante istanze generare per anno
            $table->enum('periodicita', [
                'mensile',
                'trimestrale',
                'semestrale',
                'annuale',
                'biennale',
                'una_tantum',          // evento (es. cambio statuto)
            ]);

            // Regola di calcolo della data di scadenza
            $table->enum('scadenza_kind', [
                'fixed_yearly',                  // data fissa nell'anno (es. 31 ottobre)
                'fixed_monthly',                 // giorno fisso del mese successivo (es. 16)
                'relative_to_period_end',        // X mesi dopo la fine del periodo (es. LIPE = +2 mesi)
            ]);

            $table->unsignedTinyInteger('scadenza_giorno')->nullable();   // 1-31
            $table->unsignedTinyInteger('scadenza_mese')->nullable();     // 1-12 (solo fixed_yearly)
            $table->unsignedTinyInteger('scadenza_mesi_dopo_periodo')->nullable(); // solo relative_to_period_end
            $table->tinyInteger('scadenza_anno_offset')->default(0);      // 0 = stesso anno; 1 = anno successivo

            // Documenti hints (lista di etichette per la UI)
            $table->json('documenti_richiesti')->nullable();

            // Severità / criticità per UI
            $table->enum('priorita', ['bassa', 'normale', 'alta', 'critica'])->default('normale');

            // Soft disable
            $table->boolean('attivo')->default(true);

            $table->timestamps();

            $table->index(['applicabile_a', 'attivo'], 'at_appl_attivo_idx');
            $table->index('categoria', 'at_categoria_idx');
        });

        Schema::create('adempimenti_items', function (Blueprint $table) {
            $table->id();

            $table->uuid('tenant_id');
            $table->foreignId('template_id')->constrained('adempimenti_templates')->cascadeOnDelete();

            // Periodo di riferimento
            $table->unsignedSmallInteger('anno');                    // 2026
            $table->string('periodo', 10)->nullable();               // 'T1', '01'..'12', 'S1', null per annuali
            $table->date('data_scadenza');

            // Stato workflow
            $table->enum('stato', [
                'da_fare',              // non ancora avviato
                'in_lavorazione',       // consulente o ente sta lavorando
                'consegnato',           // documenti raccolti, pronto per invio
                'completato',           // inviato/depositato, definitivo
                'non_applicabile',      // questo ente non ha questo obbligo (es. forfettario)
            ])->default('da_fare');

            // Assegnazione
            $table->foreignId('assegnato_a_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Timing
            $table->timestamp('data_completamento')->nullable();
            $table->date('data_invio_telematico')->nullable();
            $table->string('protocollo_invio', 100)->nullable();    // protocollo Entratel/PEC

            $table->text('note')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            // Un solo item per (tenant, template, anno, periodo)
            $table->unique(
                ['tenant_id', 'template_id', 'anno', 'periodo'],
                'ai_tenant_tpl_periodo_unique'
            );

            $table->index(['tenant_id', 'anno', 'stato'], 'ai_tenant_anno_stato_idx');
            $table->index(['data_scadenza', 'stato'], 'ai_scadenza_stato_idx');
            $table->index('assegnato_a_user_id', 'ai_assegnato_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adempimenti_items');
        Schema::dropIfExists('adempimenti_templates');
    }
};
