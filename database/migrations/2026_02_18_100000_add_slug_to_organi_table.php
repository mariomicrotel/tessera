<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organi', function (Blueprint $table) {
            $table->string('slug', 100)->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('organi', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
