<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anagrafica soci, volontari e collaboratori. user_id opzionale per area self-service.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('member_type_id')->constrained()->cascadeOnDelete();
            $table->string('nome');
            $table->string('cognome');
            $table->string('email')->nullable();
            $table->string('codice_fiscale', 16)->nullable()->index();
            $table->date('data_iscrizione')->nullable();
            $table->string('stato', 20)->default('attivo'); // attivo, sospeso, cessato
            $table->string('indirizzo')->nullable();
            $table->string('telefono')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
