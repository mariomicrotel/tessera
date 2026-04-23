<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ente_id')->constrained('enti')->cascadeOnDelete();
            $table->string('nome');
            $table->unsignedSmallInteger('durata_mesi')->nullable();
            $table->boolean('richiedi_elezioni_fine_mandato')->default(false);
            $table->date('mandato_da')->nullable();
            $table->date('mandato_a')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organi');
    }
};
