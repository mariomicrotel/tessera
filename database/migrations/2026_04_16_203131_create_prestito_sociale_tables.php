<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ── Tabella 1: Libretti di conto per prestito sociale ─────────────────────────
        Schema::create('prestito_sociale_libretti', function (Blueprint $table) {
            $table->id();
            $table->char('tenant_id', 36);
            $table->unsignedBigInteger('member_id');
            $table->string('numero_libretto', 20);
            $table->decimal('saldo_attuale', 14, 2)->default(0);
            $table->decimal('tasso_interesse_annuo', 5, 4)->default(0); // es. 0.0200 = 2%
            $table->date('data_apertura');
            $table->date('data_chiusura')->nullable();
            $table->enum('status', ['attivo', 'sospeso', 'chiuso'])->default('attivo');
            $table->text('note')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');
            $table->foreign('member_id')
                ->references('id')
                ->on('members')
                ->onDelete('cascade');

            // Unique and indices
            $table->unique(['tenant_id', 'numero_libretto']);
            $table->index(['tenant_id', 'member_id']);
            $table->index(['tenant_id', 'status']);
        });

        // ── Tabella 2: Movimenti su libretti ────────────────────────────────────────────
        Schema::create('prestito_sociale_movimenti', function (Blueprint $table) {
            $table->id();
            $table->char('tenant_id', 36);
            $table->unsignedBigInteger('libretto_id');
            $table->enum('tipo', ['deposito', 'prelievo', 'interessi', 'ritenuta_fiscale', 'rettifica']);
            $table->decimal('importo', 14, 2); // sempre positivo
            $table->enum('segno', ['dare', 'avere']); // dare = prelievo/uscita, avere = deposito/entrata
            $table->decimal('saldo_dopo', 14, 2); // saldo dopo questo movimento
            $table->date('data_valuta');
            $table->date('data_registrazione');
            $table->smallInteger('anno_competenza')->nullable(); // per calcolo interessi
            $table->tinyInteger('mese_competenza')->nullable();
            $table->decimal('aliquota_ritenuta', 5, 4)->nullable(); // es. 0.2600 = 26%
            $table->decimal('importo_ritenuta', 14, 2)->nullable();
            $table->decimal('importo_netto', 14, 2)->nullable(); // importo - ritenuta
            $table->string('descrizione', 255)->nullable();
            $table->unsignedBigInteger('incasso_id')->nullable(); // per depositi
            $table->unsignedBigInteger('spesa_id')->nullable(); // per prelievi
            $table->timestamps();

            // Foreign keys
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');
            $table->foreign('libretto_id')
                ->references('id')
                ->on('prestito_sociale_libretti')
                ->onDelete('cascade');
            $table->foreign('incasso_id')
                ->references('id')
                ->on('incassi')
                ->onDelete('set null');
            $table->foreign('spesa_id')
                ->references('id')
                ->on('spese')
                ->onDelete('set null');

            // Indices
            $table->index(['tenant_id', 'libretto_id', 'data_valuta'], 'psoc_mov_tenant_libr_data');
            $table->index(['tenant_id', 'tipo'], 'psoc_mov_tenant_tipo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prestito_sociale_movimenti');
        Schema::dropIfExists('prestito_sociale_libretti');
    }
};
