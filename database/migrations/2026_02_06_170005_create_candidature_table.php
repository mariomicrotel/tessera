<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidature', function (Blueprint $table) {
            $table->id();
            $table->foreignId('elezione_id')->constrained('elezioni')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->timestamps();
        });
        Schema::table('candidature', function (Blueprint $table) {
            $table->unique(['elezione_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidature');
    }
};
