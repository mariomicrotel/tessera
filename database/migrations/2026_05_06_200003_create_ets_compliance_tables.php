<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motore di compliance parametrico: regole in DB (non hardcoded), esecuzione
 * on-demand, storico dei check per tenant.
 */
return new class extends Migration {
    public function up(): void
    {
        // Catalogo regole (globale, non per tenant)
        Schema::create('ets_compliance_rules', function (Blueprint $table) {
            $table->id();
            $table->string('codice', 20)->unique();
            $table->string('titolo');
            $table->text('descrizione');
            $table->string('categoria', 30)->comment('statuto/atto/runts/governance/contabilita/volontari/trasparenza');
            $table->json('forme_applicabili')->nullable()->comment('null = tutte le forme ETS');
            $table->enum('severita', ['info', 'avviso', 'errore'])->default('avviso');
            $table->string('check_type', 30)->comment('presenza/scadenza/documento/manuale');
            $table->json('check_config')->nullable();
            $table->boolean('attivo')->default(true);
            $table->unsignedSmallInteger('ordine')->default(0);
            $table->timestamps();

            $table->index('categoria');
            $table->index('attivo');
        });

        // Sessione di check (una per esecuzione)
        Schema::create('ets_compliance_checks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('run_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('run_at')->useCurrent();
            $table->enum('stato', ['completato', 'con_errori'])->default('completato');
            $table->unsignedSmallInteger('totale')->default(0);
            $table->unsignedSmallInteger('n_ok')->default(0);
            $table->unsignedSmallInteger('n_avviso')->default(0);
            $table->unsignedSmallInteger('n_errore')->default(0);
            $table->unsignedSmallInteger('n_na')->default(0);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'run_at']);
        });

        // Risultati per singola regola
        Schema::create('ets_compliance_check_results', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('compliance_check_id')
                ->constrained('ets_compliance_checks')
                ->cascadeOnDelete();
            $table->foreignId('rule_id')
                ->constrained('ets_compliance_rules')
                ->cascadeOnDelete();
            $table->enum('esito', ['ok', 'avviso', 'errore', 'non_applicabile'])->default('non_applicabile');
            $table->text('valore_rilevato')->nullable();
            $table->text('messaggio')->nullable();
            $table->text('suggerimento')->nullable();
            $table->timestamps();

            $table->index('compliance_check_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ets_compliance_check_results');
        Schema::dropIfExists('ets_compliance_checks');
        Schema::dropIfExists('ets_compliance_rules');
    }
};
