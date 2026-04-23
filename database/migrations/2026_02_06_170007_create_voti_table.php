<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voti', function (Blueprint $table) {
            $table->id();
            $table->foreignId('elezione_id')->constrained('elezioni')->cascadeOnDelete();
            $table->foreignId('candidatura_id')->constrained('candidature')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voti');
    }
};
