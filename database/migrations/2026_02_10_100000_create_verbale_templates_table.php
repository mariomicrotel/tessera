<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verbale_templates', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('tipo', 30)->nullable(); // consiglio_direttivo | assemblea | null = per tutti
            $table->longText('contenuto')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verbale_templates');
    }
};
