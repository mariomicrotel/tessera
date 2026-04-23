<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dismissioni e vendite di cespiti.
 *
 * Registra l'uscita definitiva di un cespite dal patrimonio aziendale,
 * che sia per vendita, rottamazione, donazione o furto.
 *
 * La scrittura contabile generata storna il fondo ammortamento cumulato
 * e chiude il conto del cespite, rilevando eventuale plus o minusvalenza.
 *
 *   DARE:  Fondo ammortamento (storno)
 *   DARE:  Crediti v/clienti o Cassa (realizzo, se vendita)
 *   DARE:  Minusvalenza (se realizzo < VNC)
 *   AVERE: Cespite (costo storico)
 *   AVERE: Plusvalenza (se realizzo > VNC)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_disposals', function (Blueprint $table) {
            $table->id();

            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignId('asset_id')
                ->constrained('assets')
                ->cascadeOnDelete();

            // vendita | rottamazione | donazione | furto
            $table->string('tipo', 20);

            $table->date('data_dismissione');

            // Corrispettivo di vendita (0 per rottamazione/furto/donazione)
            $table->decimal('valore_realizzo', 12, 2)->default(0);

            // Valore netto contabile al momento della dismissione
            // = costo_storico - fondo_ammortamento_cumulato
            $table->decimal('valore_netto_contabile', 12, 2)->default(0);

            // Positivo = plusvalenza, negativo = minusvalenza
            $table->decimal('plusvalenza_minusvalenza', 12, 2)->default(0);

            // Fattura attiva emessa per la vendita (se applicabile)
            $table->unsignedBigInteger('fattura_attiva_id')->nullable();

            // Movimento contabile generato dalla dismissione
            $table->foreignId('movimento_contabile_id')
                ->nullable()
                ->constrained('movimenti_contabili')
                ->nullOnDelete();

            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'data_dismissione'], 'disposals_tenant_data_idx');
            $table->index(['tenant_id', 'tipo'], 'disposals_tenant_tipo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_disposals');
    }
};
