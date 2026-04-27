<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modelli_f24', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();

            // Periodo di riferimento
            $table->unsignedSmallInteger('anno');
            $table->unsignedTinyInteger('mese')->nullable(); // null = annuale (acconto/saldo)

            // Date
            $table->date('data_compilazione');
            $table->date('data_versamento')->nullable();

            // Stato
            $table->string('stato', 20)->default('bozza'); // bozza | compilato | versato

            // Totali calcolati (denormalizzati per velocità)
            $table->decimal('totale_debiti', 12, 2)->default(0);
            $table->decimal('totale_crediti', 12, 2)->default(0);
            $table->decimal('saldo', 12, 2)->default(0); // debiti - crediti (>0 da versare)

            // Collegamento sorgenti automatiche (nullable)
            $table->foreignId('liquidazione_iva_id')->nullable()->constrained('liquidazioni_iva')->nullOnDelete();
            $table->foreignId('versamento_ritenuta_id')->nullable()->constrained('versamenti_ritenute')->nullOnDelete();

            $table->text('note')->nullable();
            $table->timestamps();

            // Indici
            $table->index(['tenant_id', 'anno', 'stato'], 'f24_tenant_anno_stato_idx');
        });

        Schema::create('righe_f24', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modello_f24_id')->constrained('modelli_f24')->cascadeOnDelete();

            // Sezione F24
            $table->string('sezione', 20); // erario | inps | regioni | altri_enti | accise

            $table->string('codice_tributo', 10);
            $table->string('descrizione', 200)->nullable();

            // Campi standard F24
            $table->string('rateazione', 6)->nullable();    // es. "0126" (rata 01/26 = gen 2026)
            $table->unsignedSmallInteger('anno_riferimento')->nullable();
            $table->string('regione_codice', 3)->nullable();   // per sezione regioni
            $table->string('ente_codice', 4)->nullable();      // per altri enti

            $table->decimal('importo_debito', 12, 2)->default(0);
            $table->decimal('importo_credito', 12, 2)->default(0);

            $table->unsignedSmallInteger('ordinamento')->default(0);
            $table->timestamps();

            $table->index(['modello_f24_id', 'sezione']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('righe_f24');
        Schema::dropIfExists('modelli_f24');
    }
};
