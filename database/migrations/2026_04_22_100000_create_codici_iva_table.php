<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Codici IVA configurabili per tenant.
 *
 * Precaricati via seeder (9 codici di sistema) al momento della creazione
 * di un tenant di tipo cooperativa, personalizzabili dall'admin.
 * I codici "di sistema" non sono eliminabili da UI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('codici_iva', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->string('codice', 10);
            $table->string('descrizione', 100);
            $table->decimal('percentuale', 5, 2);

            // normale | esente | fuori_campo | non_imponibile | reverse_charge | split_payment
            $table->string('tipo', 20)->default('normale');

            // Codice Natura FatturaPA (N1, N2.1, N3.5, N4, N6.1, ecc.) per fatturazione elettronica
            $table->string('natura_sdi', 5)->nullable();

            // Quota non detraibile (0=tutto detraibile, 100=indetraibile, es. auto aziendali 60)
            $table->decimal('indetraibile_percentuale', 5, 2)->default(0);

            $table->boolean('attivo')->default(true);
            $table->boolean('di_sistema')->default(false);
            $table->timestamps();

            // Un tenant non può avere due codici uguali
            $table->unique(['tenant_id', 'codice']);
            // Filtro per select attivi
            $table->index(['tenant_id', 'attivo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('codici_iva');
    }
};
