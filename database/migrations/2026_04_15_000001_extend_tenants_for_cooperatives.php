<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->enum('organization_type', ['ets', 'cooperative'])->default('ets')->after('slug');
            $table->enum('cooperative_type', [
                'lavoro',
                'sociale_a',
                'sociale_b',
                'agricola',
                'comunita',
                'consumo',
                'abitazione',
                'consortile',
            ])->nullable()->after('organization_type');
            $table->string('codice_fiscale', 16)->nullable()->unique()->after('domain');
            $table->string('partita_iva', 11)->nullable()->unique()->after('codice_fiscale');
            $table->string('numero_iscrizione_albo_coop', 50)->nullable()->unique()->after('partita_iva');
            $table->decimal('capitale_sottoscritto', 14, 2)->nullable()->default(null);
            $table->decimal('capitale_versato', 14, 2)->nullable()->default(null);

            $table->index('organization_type');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['organization_type']);
            $table->dropUnique(['codice_fiscale']);
            $table->dropUnique(['partita_iva']);
            $table->dropUnique(['numero_iscrizione_albo_coop']);
            $table->dropColumn([
                'organization_type',
                'cooperative_type',
                'codice_fiscale',
                'partita_iva',
                'numero_iscrizione_albo_coop',
                'capitale_sottoscritto',
                'capitale_versato',
            ]);
        });
    }
};
