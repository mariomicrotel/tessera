<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropForeign(['member_id']);
        });
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE event_registrations MODIFY member_id BIGINT UNSIGNED NULL');
        } else {
            Schema::table('event_registrations', function (Blueprint $table) {
                $table->unsignedBigInteger('member_id')->nullable()->change();
            });
        }
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->string('guest_name', 255)->nullable()->after('member_id');
        });
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->foreign('member_id')->references('id')->on('members')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropForeign(['member_id']);
        });
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropColumn('guest_name');
        });
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE event_registrations MODIFY member_id BIGINT UNSIGNED NOT NULL');
        } else {
            Schema::table('event_registrations', function (Blueprint $table) {
                $table->unsignedBigInteger('member_id')->nullable(false)->change();
            });
        }
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->foreign('member_id')->references('id')->on('members')->cascadeOnDelete();
        });
    }
};
