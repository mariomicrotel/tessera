<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cariche_sociali', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organo_id')->constrained('organi')->cascadeOnDelete();
            $table->string('nome');
            $table->unsignedSmallInteger('ordine')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cariche_sociali');
    }
};
