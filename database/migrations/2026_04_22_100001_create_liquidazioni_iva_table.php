<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Liquidazione IVA periodica (mensile o trimestrale).
 *
 * Quando una liquidazione passa da "bozza" a "definitiva", le fatture
 * con data_registrazione nel periodo vengono agganciate via liquidazione_iva_id
 * (vedi migration successive) e diventano read-only.
 *
 * Creata PRIMA delle fatture per poter referenziare la FK su fatture_passive/attive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liquidazioni_iva', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->smallInteger('anno');
            $table->tinyInteger('periodo'); // 1-12 se mensile, 1-4 se trimestrale
            $table->string('tipo_periodo', 15); // mensile | trimestrale

            $table->date('data_inizio');
            $table->date('data_fine');

            $table->decimal('iva_debito', 12, 2)->default(0);
            $table->decimal('iva_credito', 12, 2)->default(0);
            $table->decimal('credito_periodo_precedente', 12, 2)->default(0);
            $table->decimal('saldo_periodo', 12, 2)->default(0);
            $table->decimal('saldo_finale', 12, 2)->default(0);

            $table->decimal('acconto_versato', 12, 2)->nullable();
            $table->decimal('interessi_trimestrali', 12, 2)->nullable();

            // bozza | definitiva | versata
            $table->string('status', 15)->default('bozza');

            $table->date('data_chiusura')->nullable();
            $table->date('data_versamento')->nullable();
            $table->string('numero_f24', 50)->nullable();

            $table->text('note')->nullable();
            $table->timestamps();

            // Una sola liquidazione per periodo
            $table->unique(['tenant_id', 'anno', 'periodo', 'tipo_periodo'], 'liq_iva_tenant_periodo_unique');
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liquidazioni_iva');
    }
};
