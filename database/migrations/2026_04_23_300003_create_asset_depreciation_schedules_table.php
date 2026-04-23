<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Piano di ammortamento annuale per ogni cespite (Registro Cespiti).
 *
 * Una riga per ogni coppia (asset, esercizio).
 * Il flusso è: bozza → definitivo (dopo registrazione in prima nota).
 *
 * I campi `_calcolata` e `_registrata` separano la quota teorica
 * (calcolata automaticamente) dalla quota effettivamente registrata
 * (che l'admin può modificare prima della conferma).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_depreciation_schedules', function (Blueprint $table) {
            $table->id();

            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignId('asset_id')
                ->constrained('assets')
                ->cascadeOnDelete();

            // Esercizio fiscale (anno solare, es. 2025)
            $table->smallInteger('esercizio')->unsigned();

            // Quota teorica calcolata automaticamente dal coefficiente
            $table->decimal('quota_calcolata', 12, 2)->default(0);

            // Quota effettivamente registrata (può essere modificata prima della conferma)
            $table->decimal('quota_registrata', 12, 2)->default(0);

            // Snapshot dell'aliquota applicata quest'anno (incluso 50% primo anno)
            $table->decimal('aliquota_applicata', 5, 2)->default(0);

            // Percentuale di deducibilità applicata (per report fiscale)
            $table->decimal('deducibilita_applicata', 5, 2)->default(100);

            // Snapshot fondo ammortamento a inizio e fine anno
            $table->decimal('fondo_inizio_anno', 12, 2)->default(0);
            $table->decimal('fondo_fine_anno', 12, 2)->default(0);

            // Valore netto contabile a fine anno = costo_storico - fondo_fine_anno
            $table->decimal('valore_residuo_fine_anno', 12, 2)->default(0);

            // FK al movimento contabile generato (null finché non confermato)
            $table->foreignId('movimento_contabile_id')
                ->nullable()
                ->constrained('movimenti_contabili')
                ->nullOnDelete();

            // bozza: calcolata ma non ancora registrata in prima nota
            // definitivo: scrittura contabile emessa e confermata
            // stornato: movimento annullato
            $table->string('stato', 15)->default('bozza');

            $table->date('data_registrazione')->nullable();
            $table->text('note')->nullable();

            $table->timestamps();

            // Un solo record per cespite per esercizio
            $table->unique(['tenant_id', 'asset_id', 'esercizio'], 'dep_sched_unique');

            $table->index(['tenant_id', 'esercizio', 'stato'], 'dep_sched_tenant_anno_stato_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_depreciation_schedules');
    }
};
