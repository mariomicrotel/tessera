<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Aggiunge il ruolo "consultant" alla tabella roles se non esiste già.
 * Idempotente: non fallisce se il ruolo è già presente.
 */
return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('roles')->where('name', 'consultant')->exists();

        if (! $exists) {
            DB::table('roles')->insert([
                'name'         => 'consultant',
                'display_name' => 'Consulente',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('roles')->where('name', 'consultant')->delete();
    }
};
