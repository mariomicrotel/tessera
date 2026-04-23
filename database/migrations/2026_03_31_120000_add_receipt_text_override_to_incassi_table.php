<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incassi', function (Blueprint $table) {
            $table->longText('receipt_text_override')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('incassi', function (Blueprint $table) {
            $table->dropColumn('receipt_text_override');
        });
    }
};
