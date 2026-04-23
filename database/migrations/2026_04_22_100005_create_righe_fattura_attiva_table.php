<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Righe di dettaglio delle fatture attive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('righe_fattura_attiva', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignId('fattura_attiva_id')
                ->constrained('fatture_attive')
                ->cascadeOnDelete();

            $table->foreignId('codice_iva_id')
                ->constrained('codici_iva');

            // Conto di ricavo (opzionale per ora).
            $table->unsignedBigInteger('conto_id')->nullable();

            $table->string('descrizione', 255);
            $table->decimal('quantita', 12, 4)->default(1);
            $table->decimal('prezzo_unitario', 12, 4)->default(0);
            $table->decimal('sconto_percentuale', 5, 2)->default(0);

            $table->decimal('imponibile', 12, 2)->default(0);
            $table->decimal('iva', 12, 2)->default(0);
            $table->decimal('totale', 12, 2)->default(0);

            $table->timestamps();

            $table->index(['tenant_id', 'fattura_attiva_id'], 'righe_fa_tenant_fatt_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('righe_fattura_attiva');
    }
};
