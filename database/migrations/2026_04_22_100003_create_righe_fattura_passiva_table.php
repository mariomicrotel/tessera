<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Righe di dettaglio delle fatture passive.
 *
 * tenant_id è denormalizzato qui per performance (query dirette su riga
 * senza dover joinare fatture_passive) e per coerenza con il global scope
 * del trait BelongsToTenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('righe_fattura_passiva', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignId('fattura_passiva_id')
                ->constrained('fatture_passive')
                ->cascadeOnDelete();

            $table->foreignId('codice_iva_id')
                ->constrained('codici_iva');

            // Conto di costo (plan of accounts): opzionale per ora, diventerà FK
            // quando il piano dei conti sarà collegato alle fatture.
            $table->unsignedBigInteger('conto_id')->nullable();

            $table->string('descrizione', 255);
            $table->decimal('quantita', 12, 4)->default(1);
            $table->decimal('prezzo_unitario', 12, 4)->default(0);

            $table->decimal('imponibile', 12, 2)->default(0);
            $table->decimal('iva', 12, 2)->default(0);
            $table->decimal('totale', 12, 2)->default(0);

            // Percentuale indetraibile applicata (ereditata da codice_iva o override)
            $table->decimal('indetraibile_percentuale', 5, 2)->default(0);
            $table->decimal('iva_indetraibile', 12, 2)->default(0);

            $table->timestamps();

            $table->index(['tenant_id', 'fattura_passiva_id'], 'righe_fp_tenant_fatt_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('righe_fattura_passiva');
    }
};
