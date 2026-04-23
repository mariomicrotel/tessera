<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corregge la chiave primaria della tabella settings per supportare il multi-tenant.
 *
 * Problema: la PK era solo su `key` (globale), quindi tenant diversi non possono
 * avere la stessa chiave (es. 'quota_annuale'). In multi-tenant ogni setting è
 * identificato univocamente da (key, tenant_id).
 *
 * Soluzione: ricrea la tabella con `id` autoincrement come PK e unique su (key, tenant_id).
 * La ricreazione è necessaria per compatibilità SQLite (non supporta DROP PRIMARY KEY
 * né ADD COLUMN ... PRIMARY KEY su tabelle esistenti).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Salva i dati esistenti
        $righeEsistenti = DB::table('settings')->get();

        // Crea la nuova struttura con nome temporaneo
        Schema::create('settings_new', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->text('value')->nullable();
            $table->string('tenant_id', 36)->nullable();
            $table->unique(['key', 'tenant_id']);
        });

        // Migra i dati esistenti (senza tenant_id: erano globali)
        foreach ($righeEsistenti as $riga) {
            DB::table('settings_new')->insert([
                'key'       => $riga->key,
                'value'     => $riga->value ?? null,
                'tenant_id' => null,
            ]);
        }

        // Sostituisce la vecchia tabella
        Schema::drop('settings');
        Schema::rename('settings_new', 'settings');
    }

    public function down(): void
    {
        $righeEsistenti = DB::table('settings')->get();

        Schema::create('settings_old', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
        });

        // Ripristina solo i record senza tenant_id (quelli globali originali)
        foreach ($righeEsistenti as $riga) {
            if ($riga->tenant_id === null) {
                DB::table('settings_old')->insertOrIgnore([
                    'key'   => $riga->key,
                    'value' => $riga->value ?? null,
                ]);
            }
        }

        Schema::drop('settings');
        Schema::rename('settings_old', 'settings');
    }
};
