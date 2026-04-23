<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rendiconto_voci', function (Blueprint $table) {
            $table->string('slug', 31)->nullable()->unique()->after('ordine');
        });

        \DB::table('rendiconto_voci')->where('codice_voce', 'A.1')->update(['slug' => 'quota']);
        \DB::table('rendiconto_voci')->where('codice_voce', 'A.2')->update(['slug' => 'donazione']);
        \DB::table('rendiconto_voci')->where('codice_voce', 'E.1')->update(['slug' => 'rimborsi']);
    }

    public function down(): void
    {
        Schema::table('rendiconto_voci', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
