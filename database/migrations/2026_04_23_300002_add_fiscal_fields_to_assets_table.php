<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estende la tabella assets con i campi necessari per il Registro Cespiti italiano.
 *
 * La tabella assets esistente è minimale (name, code, purchase_date, value, notes).
 * Questa migration aggiunge:
 * - Metadati fiscali (categoria, aliquota, metodo, deducibilità)
 * - Riferimenti a fornitore e fattura di acquisto
 * - Conti contabili (override rispetto ai default di categoria)
 * - Gestione stato e dismissione
 * - Soft delete
 *
 * NOTA: il campo `value` originale rimane per compatibilità;
 * il campo `costo_storico` diventa il riferimento fiscale ufficiale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {

            // — Categoria fiscale —
            $table->unsignedBigInteger('asset_category_id')
                ->nullable()
                ->after('property_id');
            $table->foreign('asset_category_id')
                ->references('id')->on('asset_categories')->nullOnDelete();

            // — Fornitore e fattura acquisto —
            $table->unsignedBigInteger('supplier_id')
                ->nullable()
                ->after('asset_category_id');
            $table->foreign('supplier_id')
                ->references('id')->on('suppliers')->nullOnDelete();

            $table->unsignedBigInteger('fattura_passiva_id')
                ->nullable()
                ->after('supplier_id');
            $table->foreign('fattura_passiva_id')
                ->references('id')->on('fatture_passive')->nullOnDelete();

            // — Dati fiscali —
            $table->string('matricola', 100)->nullable()->after('code');

            // Costo storico fiscale (può differire da `value` se incluse spese accessorie)
            $table->decimal('costo_storico', 12, 2)->nullable()->after('value');

            // Data da cui parte il calcolo dell'ammortamento (solitamente = purchase_date)
            $table->date('data_inizio_ammortamento')->nullable()->after('purchase_date');

            // Aliquota custom: se valorizzata, sovrascrive il coefficiente della categoria
            $table->decimal('aliquota_custom', 5, 2)->nullable()->after('costo_storico');

            // Metodo di ammortamento
            $table->string('metodo_ammortamento', 20)
                ->default('ordinario')
                ->after('aliquota_custom');
            // Valori: ordinario | ridotto | accelerato | anticipato

            // Il primo anno si applica il 50% (normativa italiana DM 31/12/1988)
            $table->boolean('primo_anno_ridotto')->default(true)->after('metodo_ammortamento');

            // Deducibilità fiscale (override rispetto al default di categoria)
            $table->decimal('percentuale_deducibilita', 5, 2)
                ->default(100)
                ->after('primo_anno_ridotto');

            // — Stato cespite —
            $table->string('stato', 20)->default('in_uso')->after('percentuale_deducibilita');
            // Valori: in_uso | dismesso | venduto

            // — Conti contabili (override rispetto ai default di categoria) —
            $table->unsignedBigInteger('conto_bene_id')->nullable()->after('stato');
            $table->foreign('conto_bene_id')
                ->references('id')->on('conti_contabili')->nullOnDelete();

            $table->unsignedBigInteger('conto_fondo_id')->nullable()->after('conto_bene_id');
            $table->foreign('conto_fondo_id')
                ->references('id')->on('conti_contabili')->nullOnDelete();

            // — Note fiscali aggiuntive —
            $table->text('note_fiscali')->nullable()->after('notes');

            // — Soft delete —
            $table->softDeletes()->after('updated_at');

            // Index per query frequenti
            $table->index(['tenant_id', 'stato'], 'assets_tenant_stato_idx');
            $table->index(['tenant_id', 'asset_category_id'], 'assets_tenant_cat_idx');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropIndex('assets_tenant_stato_idx');
            $table->dropIndex('assets_tenant_cat_idx');
            $table->dropForeign(['conto_fondo_id']);
            $table->dropForeign(['conto_bene_id']);
            $table->dropForeign(['fattura_passiva_id']);
            $table->dropForeign(['supplier_id']);
            $table->dropForeign(['asset_category_id']);

            $table->dropColumn([
                'asset_category_id',
                'supplier_id',
                'fattura_passiva_id',
                'matricola',
                'costo_storico',
                'data_inizio_ammortamento',
                'aliquota_custom',
                'metodo_ammortamento',
                'primo_anno_ridotto',
                'percentuale_deducibilita',
                'stato',
                'conto_bene_id',
                'conto_fondo_id',
                'note_fiscali',
            ]);
        });
    }
};
