<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['member_id']);
            $table->dropColumn(['member_id', 'name', 'file_path', 'category', 'uploaded_at']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->string('titolo', 255)->nullable()->after('id');
            $table->date('data')->nullable()->after('titolo');
            $table->longText('contenuto')->nullable()->after('data');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['titolo', 'data', 'contenuto']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('member_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->string('name')->after('member_id');
            $table->string('file_path', 512)->after('name');
            $table->string('category', 50)->nullable()->after('file_path');
            $table->timestamp('uploaded_at')->useCurrent()->after('category');
        });
    }
};
