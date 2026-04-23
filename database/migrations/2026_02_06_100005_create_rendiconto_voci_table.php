<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rendiconto_voci', function (Blueprint $table) {
            $table->id();
            $table->string('sezione', 5);
            $table->string('codice_voce', 20);
            $table->string('descrizione');
            $table->string('tipo', 20); // entrata | uscita
            $table->unsignedSmallInteger('ordine')->default(0);
            $table->timestamps();
        });

        Schema::table('rendiconto_voci', function (Blueprint $table) {
            $table->unique(['sezione', 'codice_voce']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rendiconto_voci');
    }
};
