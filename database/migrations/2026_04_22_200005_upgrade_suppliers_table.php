<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estende la tabella `suppliers` dallo stub originale (name, email, phone)
 * al modulo completo per il ciclo passivo fornitori.
 *
 * Aggiunge:
 *  - Multi-tenancy (tenant_id)
 *  - Dati anagrafici italiani (P.IVA, CF, SDI, PEC)
 *  - Indirizzo completo
 *  - Condizioni di pagamento e categoria merceologica
 *  - Soft delete
 *  - FK da fatture_passive.supplier_id (predisposta in fase A4)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            // ── Multi-tenancy ─────────────────────────────────────────────
            $table->foreignUuid('tenant_id')
                ->after('id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            // ── Dati anagrafici ───────────────────────────────────────────
            // name già presente → sarà il nome commerciale / nome breve
            $table->string('ragione_sociale', 200)->after('name')->nullable();
            // P.IVA italiana: 11 cifre; può essere estera (formato diverso)
            $table->string('partita_iva', 20)->nullable()->after('ragione_sociale');
            $table->string('codice_fiscale', 16)->nullable()->after('partita_iva');
            // Codice Destinatario SDI (7 caratteri alfanumerici) o XXXXXXX per PEC
            $table->string('codice_sdi', 7)->nullable()->after('codice_fiscale');
            // PEC già presente come email? No, manteniamo entrambi
            $table->string('pec', 150)->nullable()->after('codice_sdi');

            // ── Indirizzo ─────────────────────────────────────────────────
            $table->string('indirizzo', 200)->nullable()->after('pec');
            $table->string('cap', 5)->nullable()->after('indirizzo');
            $table->string('citta', 100)->nullable()->after('cap');
            $table->string('provincia', 2)->nullable()->after('citta');     // sigla IT
            $table->string('nazione', 2)->default('IT')->after('provincia');// ISO 3166-1 alpha-2

            // ── Dati bancari ──────────────────────────────────────────────
            $table->string('iban', 34)->nullable()->after('nazione');

            // ── Condizioni commerciali ────────────────────────────────────
            // valori: immediato, 30gg, 60gg, 90gg
            $table->string('condizioni_pagamento', 20)->default('30gg')->after('iban');

            // Categoria merceologica principale
            // valori: beni, servizi, professionista, altro
            $table->string('categoria', 20)->default('servizi')->after('condizioni_pagamento');

            // ── Metadati ──────────────────────────────────────────────────
            $table->text('note')->nullable()->after('categoria');
            $table->boolean('attivo')->default(true)->after('note');

            // ── Soft delete ───────────────────────────────────────────────
            $table->softDeletes();

            // ── Indici ────────────────────────────────────────────────────
            $table->index(['tenant_id', 'attivo']);
            $table->index(['tenant_id', 'categoria']);
            $table->unique(['tenant_id', 'partita_iva'], 'suppliers_tenant_piva_unique');
        });

        // Aggiunge la FK predisposta in fatture_passive (fase A4)
        Schema::table('fatture_passive', function (Blueprint $table) {
            $table->foreign('supplier_id')
                ->references('id')
                ->on('suppliers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fatture_passive', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropIndex(['tenant_id', 'attivo']);
            $table->dropIndex(['tenant_id', 'categoria']);
            $table->dropUnique('suppliers_tenant_piva_unique');
            $table->dropForeign(['tenant_id']);
            $table->dropColumn([
                'tenant_id', 'ragione_sociale', 'partita_iva', 'codice_fiscale',
                'codice_sdi', 'pec', 'indirizzo', 'cap', 'citta', 'provincia',
                'nazione', 'iban', 'condizioni_pagamento', 'categoria', 'note', 'attivo',
            ]);
        });
    }
};
