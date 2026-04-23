<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ricevute (fiscali o liberali) collegate a pagamenti, donazioni o rimborsi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('receivable_type'); // Payment, Donation, ExpenseRefund
            $table->unsignedBigInteger('receivable_id');
            $table->string('number')->unique();
            $table->date('issued_at');
            $table->string('file_path', 512)->nullable();
            $table->string('type', 20)->default('liberale'); // fiscale, liberale
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
