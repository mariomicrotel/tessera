<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fatture passive (di acquisto).
 *
 * Riceve le fatture dai fornitori (incluse quelle importate da XML SDI).
 * Il file XML originale viene conservato in storage/app/tenants/{id}/sdi/{anno}/{mese}/.
 *
 * La FK verso liquidazioni_iva viene aggiunta in una migration successiva
 * (per evitare dipendenze circolari con il ciclo di sviluppo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fatture_passive', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            // FK fornitore opzionale: la tabella suppliers verrà creata nella fase C1.
            // Per ora teniamo solo l'id nullable senza constraint, lo aggiungeremo dopo.
            $table->unsignedBigInteger('supplier_id')->nullable();

            $table->string('numero_fattura', 50);
            $table->date('data_fattura');
            $table->date('data_ricezione')->nullable();
            $table->date('data_registrazione');
            $table->date('data_scadenza')->nullable();

            $table->decimal('imponibile_totale', 12, 2)->default(0);
            $table->decimal('iva_totale', 12, 2)->default(0);
            $table->decimal('totale_documento', 12, 2)->default(0);

            // immediata | differita | split_payment
            $table->string('esigibilita', 20)->default('immediata');

            // TD01, TD02, TD04 (nota credito), TD16 (reverse charge interno), TD17/18/19 (estero), ecc.
            $table->string('tipo_documento', 10)->default('TD01');

            // da_pagare | pagata | parzialmente_pagata | annullata
            $table->string('stato_pagamento', 25)->default('da_pagare');

            // Percorso relativo al file XML SDI (se importata da SDI)
            $table->string('xml_sdi_path', 255)->nullable();

            $table->text('note')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Una fattura dello stesso fornitore con stesso numero non può essere duplicata
            $table->unique(
                ['tenant_id', 'supplier_id', 'numero_fattura', 'data_fattura'],
                'fatt_pass_tenant_supp_num_unique'
            );
            $table->index(['tenant_id', 'data_registrazione'], 'fatt_pass_tenant_dataReg_idx');
            $table->index(['tenant_id', 'stato_pagamento'], 'fatt_pass_tenant_stato_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fatture_passive');
    }
};
