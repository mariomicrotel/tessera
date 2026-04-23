<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Collega le fatture (attive e passive) alla liquidazione IVA di competenza.
 *
 * Viene valorizzato quando la liquidazione passa da "bozza" a "definitiva":
 * tutte le fatture con data_registrazione nel periodo vengono agganciate
 * e diventano read-only (enforcement lato application layer).
 *
 * onDelete('set null'): se la liquidazione viene eliminata (solo in bozza),
 * le fatture tornano "libere" per essere riassegnate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fatture_passive', function (Blueprint $table) {
            $table->foreignId('liquidazione_iva_id')
                ->nullable()
                ->after('stato_pagamento')
                ->constrained('liquidazioni_iva')
                ->nullOnDelete();

            $table->index(['tenant_id', 'liquidazione_iva_id'], 'fatt_pass_tenant_liq_idx');
        });

        Schema::table('fatture_attive', function (Blueprint $table) {
            $table->foreignId('liquidazione_iva_id')
                ->nullable()
                ->after('stato_pagamento')
                ->constrained('liquidazioni_iva')
                ->nullOnDelete();

            $table->index(['tenant_id', 'liquidazione_iva_id'], 'fatt_att_tenant_liq_idx');
        });
    }

    public function down(): void
    {
        Schema::table('fatture_passive', function (Blueprint $table) {
            $table->dropIndex('fatt_pass_tenant_liq_idx');
            $table->dropForeign(['liquidazione_iva_id']);
            $table->dropColumn('liquidazione_iva_id');
        });

        Schema::table('fatture_attive', function (Blueprint $table) {
            $table->dropIndex('fatt_att_tenant_liq_idx');
            $table->dropForeign(['liquidazione_iva_id']);
            $table->dropColumn('liquidazione_iva_id');
        });
    }
};
