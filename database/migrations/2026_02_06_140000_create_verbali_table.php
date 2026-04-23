<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verbali', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 30); // consiglio_direttivo | assemblea
            $table->date('data');
            $table->string('titolo', 255);
            $table->text('contenuto')->nullable();
            $table->unsignedTinyInteger('numero')->nullable();
            $table->timestamps();
        });

        Schema::table('verbali', function (Blueprint $table) {
            $table->index(['tipo', 'data']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verbali');
    }
};
