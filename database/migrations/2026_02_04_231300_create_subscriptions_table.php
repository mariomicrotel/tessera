<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Iscrizioni periodiche: socio + tipo quota, date inizio/fine, stato.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_type_id')->constrained()->cascadeOnDelete();
            $table->date('started_at');
            $table->date('ends_at');
            $table->string('status', 20)->default('attiva'); // attiva, scaduta, sospesa
            $table->timestamps();
            $table->index(['member_id', 'membership_type_id', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
