<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allarga codice_fiscale per supportare numeri di identificazione fiscale di altri Paesi.
     */
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('codice_fiscale', 64)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('codice_fiscale', 16)->nullable()->change();
        });
    }
};
