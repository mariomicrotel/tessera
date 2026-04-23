<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Categorie fiscali per i cespiti (Registro Cespiti italiano).
 *
 * Ogni categoria contiene:
 * - Coefficiente ministeriale (DM 31/12/1988)
 * - Percentuale di deducibilità fiscale (es. auto = 20%, art. 164 TUIR)
 * - Conti contabili di default (bene, fondo ammortamento, costo ammortamento)
 *
 * Le categorie `di_sistema = true` sono pre-caricate e non eliminabili.
 * I tenant possono creare categorie personalizzate (tenant_id non null).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_categories', function (Blueprint $table) {
            $table->id();

            // Nullable: null = categoria di sistema condivisa tra tutti i tenant
            $table->uuid('tenant_id')->nullable()->index();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            // Identificativo univoco per categoria (es. "ATTREZZ", "AUTO", "SW")
            $table->string('codice', 20);
            $table->string('descrizione');

            // Coefficiente di ammortamento ministeriale (DM 31/12/1988), es. 15.00 = 15%
            $table->decimal('coefficiente_ministeriale', 5, 2)->default(0);

            // Deducibilità fiscale predefinita: 100% standard, 20% autovetture art.164 TUIR,
            // 80% auto agenti, 40% uso promiscuo
            $table->decimal('percentuale_deducibilita_default', 5, 2)->default(100);

            // Flag: il primo anno si applica il 50% del coefficiente (prassi fiscale italiana)
            $table->boolean('primo_anno_ridotto_default')->default(true);

            // Conti contabili di default (FK nullable: popolati dal seeder dopo il piano conti)
            $table->unsignedBigInteger('conto_bene_default_id')->nullable();
            $table->unsignedBigInteger('conto_fondo_default_id')->nullable();
            $table->unsignedBigInteger('conto_ammortamento_default_id')->nullable();

            $table->boolean('di_sistema')->default(false);
            $table->boolean('attivo')->default(true);
            $table->text('note')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Codice univoco per tenant (null = sistema)
            $table->unique(['tenant_id', 'codice'], 'asset_cat_tenant_codice_unique');

            // FK conti (non usa constrained() per gestire tenant diversi)
            $table->foreign('conto_bene_default_id')
                ->references('id')->on('conti_contabili')->nullOnDelete();
            $table->foreign('conto_fondo_default_id')
                ->references('id')->on('conti_contabili')->nullOnDelete();
            $table->foreign('conto_ammortamento_default_id')
                ->references('id')->on('conti_contabili')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_categories');
    }
};
