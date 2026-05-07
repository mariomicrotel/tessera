<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tessere', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->string('numero')->comment('Es: 2026-001');
            $table->unsignedSmallInteger('anno');
            $table->date('data_emissione')->nullable();
            $table->date('data_scadenza')->nullable();
            $table->enum('stato', ['bozza', 'emessa', 'scaduta', 'revocata'])->default('bozza');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'numero']);
            $table->index(['tenant_id', 'anno']);
            $table->index(['tenant_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tessere');
    }
};
