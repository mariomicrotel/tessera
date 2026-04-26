<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compensi a terzi con ritenuta d'acconto.
 *
 * Copre:
 *  - Collaborazioni occasionali (art. 2222 c.c.) — ritenuta 20%, codice 1040
 *  - Prestazioni professionali (partita IVA) — ritenuta 20%, codice 1040
 *  - Provvigioni agenti/mediatori — ritenuta 23% su 50% imponibile, codici 1038/1044
 *
 * La Certificazione Unica (CU) viene generata aggregando per percipiente/anno.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compensi_terzi', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();

            // Percipiente: socio/membro o soggetto esterno
            $table->unsignedBigInteger('member_id')->nullable();   // FK members (opzionale)
            $table->string('nome_percipiente', 150);               // nome visualizzato
            $table->string('codice_fiscale', 16);                  // CF obbligatorio per CU
            $table->string('partita_iva', 11)->nullable();
            $table->string('indirizzo', 255)->nullable();

            // Classificazione
            // occasionale | professionale | provvigioni
            $table->string('tipo_rapporto', 30)->default('occasionale');
            // A = lavoro autonomo occasionale/professionale
            // Q = provvigioni agente monomandatario
            // R = provvigioni agente plurimandatario
            // V = provvigioni procacciatori d'affari
            $table->string('codice_causale', 5)->default('A');

            // Importi
            $table->smallInteger('anno_competenza');
            $table->date('data_pagamento');
            $table->string('causale_prestazione', 500);           // descrizione prestazione

            $table->decimal('compenso_lordo', 12, 2);             // importo totale pattuito
            $table->decimal('base_imponibile_ritenuta', 12, 2);   // base su cui si calcola (= lordo per occasionali)
            $table->decimal('aliquota_ritenuta', 5, 2)->default(20.00); // % normalmente 20%
            $table->decimal('ritenuta', 12, 2);                   // = base × aliquota / 100
            $table->decimal('compenso_netto', 12, 2);             // = lordo - ritenuta

            // Contributo INPS Gestione Separata (solo per collaborazioni occasionali > 5000€/anno)
            $table->decimal('contributo_inps_beneficiario', 12, 2)->default(0);
            $table->decimal('contributo_inps_committente', 12, 2)->default(0);

            // Rimborsi spese (non soggetti a ritenuta)
            $table->decimal('rimborsi_spese', 12, 2)->default(0);

            // Stato versamento ritenuta
            // da_versare | versata
            $table->string('stato_ritenuta', 20)->default('da_versare');

            // FK al versamento F24 che ha liquidato questa ritenuta
            $table->unsignedBigInteger('versamento_ritenuta_id')->nullable();

            // Contabilità
            $table->unsignedBigInteger('conto_costo_id')->nullable();     // conto costo prestazione
            $table->unsignedBigInteger('conto_ritenute_id')->nullable();  // conto ritenute da versare
            $table->unsignedBigInteger('movimento_id')->nullable();       // movimento contabile generato

            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'anno_competenza'], 'comp_terzi_tenant_anno_idx');
            $table->index(['tenant_id', 'codice_fiscale'], 'comp_terzi_tenant_cf_idx');
            $table->index(['tenant_id', 'stato_ritenuta'], 'comp_terzi_tenant_stato_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compensi_terzi');
    }
};
