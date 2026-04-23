<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fatture attive (di vendita).
 *
 * Supporta numerazione progressiva annuale con sezionali (es. "1", "1/2026",
 * o sezionale separato per note di credito). Il sezionale di default è
 * configurabile via settings (iva_numerazione_sezionale_default).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fatture_attive', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            // FK cliente opzionale: la tabella clienti verrà creata nella fase C1.
            $table->unsignedBigInteger('cliente_id')->nullable();

            $table->string('sezionale', 20)->default('');
            $table->smallInteger('anno');
            $table->unsignedInteger('progressivo'); // numero progressivo nell'anno + sezionale
            $table->string('numero_fattura', 50);   // stringa completa visualizzata (es. "2026/15")

            $table->date('data_fattura');
            $table->date('data_scadenza')->nullable();

            $table->decimal('imponibile_totale', 12, 2)->default(0);
            $table->decimal('iva_totale', 12, 2)->default(0);
            $table->decimal('totale_documento', 12, 2)->default(0);

            // immediata | differita | split_payment
            $table->string('esigibilita', 20)->default('immediata');

            // TD01, TD04 (nota credito), TD24 (differita), ecc.
            $table->string('tipo_documento', 10)->default('TD01');

            // bozza | emessa | inviata_sdi | accettata | scartata | annullata
            $table->string('stato', 20)->default('bozza');

            // da_incassare | incassata | parzialmente_incassata
            $table->string('stato_pagamento', 25)->default('da_incassare');

            $table->string('xml_sdi_path', 255)->nullable();
            $table->string('sdi_identificativo', 50)->nullable(); // id SdI ricevuto dopo invio

            $table->text('note')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Numerazione univoca per anno + sezionale
            $table->unique(
                ['tenant_id', 'anno', 'sezionale', 'progressivo'],
                'fatt_att_numerazione_unique'
            );
            $table->index(['tenant_id', 'data_fattura'], 'fatt_att_tenant_data_idx');
            $table->index(['tenant_id', 'stato'], 'fatt_att_tenant_stato_idx');
            $table->index(['tenant_id', 'stato_pagamento'], 'fatt_att_tenant_statoPag_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fatture_attive');
    }
};
