<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('elezioni', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organo_id')->nullable()->constrained('organi')->nullOnDelete();
            $table->string('titolo');
            $table->date('data_elezione');
            $table->string('stato', 20)->default('bozza'); // bozza, aperta, chiusa
            $table->boolean('permetti_astenuti')->default(false);
            $table->timestamp('invalidata_at')->nullable();
            $table->text('motivazione_invalidazione')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('elezioni');
    }
};
