<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $annoDefault = (int) date('Y');

        Schema::table('verbali', function (Blueprint $table) use ($annoDefault) {
            $table->unsignedSmallInteger('anno')->default($annoDefault)->after('data');
        });

        foreach (DB::table('verbali')->get(['id', 'data']) as $row) {
            $anno = $row->data ? (int) date('Y', strtotime($row->data)) : $annoDefault;
            DB::table('verbali')->where('id', $row->id)->update(['anno' => $anno]);
        }

        Schema::table('verbali', function (Blueprint $table) {
            $table->unique(['tipo', 'anno', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::table('verbali', function (Blueprint $table) {
            $table->dropUnique(['tipo', 'anno', 'numero']);
            $table->dropColumn('anno');
        });
    }
};
