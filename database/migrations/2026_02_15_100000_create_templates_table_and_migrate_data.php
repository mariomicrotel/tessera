<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('categoria', 20); // documento | verbale
            $table->string('tipo_verbale', 30)->nullable(); // consiglio_direttivo | assemblea | null
            $table->longText('contenuto')->nullable();
            $table->timestamps();
        });

        if (Schema::hasTable('document_templates')) {
            $rows = DB::table('document_templates')->get();
            foreach ($rows as $row) {
                DB::table('templates')->insert([
                    'nome' => $row->nome,
                    'categoria' => 'documento',
                    'tipo_verbale' => null,
                    'contenuto' => $row->contenuto,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }
            Schema::drop('document_templates');
        }

        if (Schema::hasTable('verbale_templates')) {
            $rows = DB::table('verbale_templates')->get();
            foreach ($rows as $row) {
                DB::table('templates')->insert([
                    'nome' => $row->nome,
                    'categoria' => 'verbale',
                    'tipo_verbale' => $row->tipo,
                    'contenuto' => $row->contenuto,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }
            Schema::drop('verbale_templates');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
