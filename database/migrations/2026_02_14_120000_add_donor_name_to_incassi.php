<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incassi', function (Blueprint $table) {
            $table->string('donor_name', 255)->nullable()->after('member_id');
        });
    }

    public function down(): void
    {
        Schema::table('incassi', function (Blueprint $table) {
            $table->dropColumn('donor_name');
        });
    }
};
