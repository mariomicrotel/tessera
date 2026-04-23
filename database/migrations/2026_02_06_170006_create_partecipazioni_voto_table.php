<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partecipazioni_voto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('elezione_id')->constrained('elezioni')->cascadeOnDelete();
            $table->timestamps();
        });
        Schema::table('partecipazioni_voto', function (Blueprint $table) {
            $table->unique(['member_id', 'elezione_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partecipazioni_voto');
    }
};
