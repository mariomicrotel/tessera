<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aggiunge supporto per Note di Credito (TD04) al modulo Fatture Attive.
 *
 * Nuovi campi:
 * - fattura_collegata_id: FK auto-referenziale per collegare una NC alla fattura originale
 * - motivo_nota_credito: causale dello storno (es. "Reso", "Sconto accordato", "Errore fatturazione")
 *
 * Nota: il campo tipo_documento è già presente nel table originale con enum TD01|TD04|...
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fatture_attive', function (Blueprint $table) {
            // FK auto-referenziale per collegamento NC → fattura originale
            // nullable: una fattura regolare (TD01) non ha collegamento
            $table->unsignedBigInteger('fattura_collegata_id')->nullable()
                ->after('tipo_documento');

            // Causale dello storno: "Reso", "Sconto accordato", "Errore fatturazione", etc.
            // nullable: una fattura regolare non ha causale
            $table->text('motivo_nota_credito')->nullable()
                ->after('fattura_collegata_id');

            // FK vincolo verso tabella stessa
            $table->foreign('fattura_collegata_id')
                ->references('id')
                ->on('fatture_attive')
                ->nullableOnDelete();

            // Indice per query efficienti:
            // - trovare tutte le NC di una fattura originale
            // - trovare tutte le NC per tenant e tipo documento
            $table->index(
                ['tenant_id', 'tipo_documento', 'fattura_collegata_id'],
                'fatt_att_nota_credito_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('fatture_attive', function (Blueprint $table) {
            $table->dropForeign(['fattura_collegata_id']);
            $table->dropIndex('fatt_att_nota_credito_idx');
            $table->dropColumn(['fattura_collegata_id', 'motivo_nota_credito']);
        });
    }
};
