<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->unsignedBigInteger('numero_tessera')->nullable()->unique()->after('id');
        });

        \DB::table('members')->orderBy('id')->get()->each(function ($member, $index) {
            \DB::table('members')->where('id', $member->id)->update(['numero_tessera' => $member->id]);
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropUnique(['numero_tessera']);
            $table->dropColumn('numero_tessera');
        });
    }
};
