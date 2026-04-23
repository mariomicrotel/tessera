<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
        });

        \DB::table('settings')->insert([
            'key' => 'quota_annuale',
            'value' => '0',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
