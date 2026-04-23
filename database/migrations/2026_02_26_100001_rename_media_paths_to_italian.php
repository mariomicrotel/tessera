<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Aggiorna i path su disco da inglese a italiano:
 * - receipts.file_path: media/receipts/ → media/ricevute/
 * - attachments.file_path: media/attachments/ → media/allegati/
 * - attachments.file_path: media/allegati/settings/ → media/allegati/impostazioni/
 * - attachments.file_path: media/allegati/documents/ → media/allegati/documenti/
 *
 * Eseguire questa migration DOPO il deploy e DOPO aver rinominato le cartelle
 * sul filesystem.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('receipts')
            ->whereNotNull('file_path')
            ->where('file_path', 'like', 'media/receipts/%')
            ->update([
                'file_path' => DB::raw("REPLACE(file_path, 'media/receipts/', 'media/ricevute/')"),
            ]);

        DB::table('attachments')
            ->whereNotNull('file_path')
            ->where('file_path', 'like', 'media/attachments/%')
            ->update([
                'file_path' => DB::raw("REPLACE(file_path, 'media/attachments/', 'media/allegati/')"),
            ]);

        DB::table('attachments')
            ->whereNotNull('file_path')
            ->where('file_path', 'like', 'media/allegati/settings/%')
            ->update([
                'file_path' => DB::raw("REPLACE(file_path, 'media/allegati/settings/', 'media/allegati/impostazioni/')"),
            ]);

        DB::table('attachments')
            ->whereNotNull('file_path')
            ->where('file_path', 'like', 'media/allegati/documents/%')
            ->update([
                'file_path' => DB::raw("REPLACE(file_path, 'media/allegati/documents/', 'media/allegati/documenti/')"),
            ]);
    }

    public function down(): void
    {
        DB::table('attachments')
            ->whereNotNull('file_path')
            ->where('file_path', 'like', 'media/allegati/documenti/%')
            ->update([
                'file_path' => DB::raw("REPLACE(file_path, 'media/allegati/documenti/', 'media/allegati/documents/')"),
            ]);

        DB::table('attachments')
            ->whereNotNull('file_path')
            ->where('file_path', 'like', 'media/allegati/impostazioni/%')
            ->update([
                'file_path' => DB::raw("REPLACE(file_path, 'media/allegati/impostazioni/', 'media/allegati/settings/')"),
            ]);

        DB::table('receipts')
            ->whereNotNull('file_path')
            ->where('file_path', 'like', 'media/ricevute/%')
            ->update([
                'file_path' => DB::raw("REPLACE(file_path, 'media/ricevute/', 'media/receipts/')"),
            ]);

        DB::table('attachments')
            ->whereNotNull('file_path')
            ->where('file_path', 'like', 'media/allegati/%')
            ->update([
                'file_path' => DB::raw("REPLACE(file_path, 'media/allegati/', 'media/attachments/')"),
            ]);
    }
};
