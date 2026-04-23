<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Document;
use App\Models\ExpenseRefund;
use App\Models\Spesa;
use App\Models\Verbale;
use Illuminate\Support\Facades\Storage;

/**
 * Restituisce il file di un allegato (es. logo, allegati rimborsi).
 */
class AttachmentController extends Controller
{
    /**
     * Mostra/scarica il file. Per immagini usa inline (visualizzazione), altrimenti attachment (download).
     * Per allegati di ExpenseRefund: autorizzazione come show rimborso (socio solo proprio, staff tutti).
     */
    public function show(Attachment $attachment)
    {
        if ($attachment->attachable_type === ExpenseRefund::class) {
            $refund = ExpenseRefund::find($attachment->attachable_id);
            if (! $refund) {
                abort(404, 'Rimborso non trovato.');
            }
            $user = auth()->user();
            if ($user->member && ! $user->hasRole('admin') && ! $user->hasRole('segreteria') && ! $user->hasRole('contabile')) {
                if ((int) $refund->member_id !== (int) $user->member->id) {
                    abort(403, 'Non autorizzato a scaricare questo allegato.');
                }
            }
        }

        if ($attachment->attachable_type === Verbale::class) {
            $verbale = Verbale::find($attachment->attachable_id);
            if (! $verbale) {
                abort(404, 'Verbale non trovato.');
            }
            $user = auth()->user();
            if (! $user->hasRole('admin') && ! $user->hasRole('segreteria')) {
                abort(403, 'Non autorizzato a scaricare questo allegato.');
            }
        }

        if ($attachment->attachable_type === Document::class) {
            $document = Document::find($attachment->attachable_id);
            if (! $document) {
                abort(404, 'Documento non trovato.');
            }
            $user = auth()->user();
            if (! $user->hasRole('admin') && ! $user->hasRole('segreteria')) {
                abort(403, 'Non autorizzato a scaricare questo allegato.');
            }
        }

        if ($attachment->attachable_type === Spesa::class) {
            $spesa = Spesa::find($attachment->attachable_id);
            if (! $spesa) {
                abort(404, 'Spesa non trovata.');
            }
            $user = auth()->user();
            if (! $user->hasRole('admin') && ! $user->hasRole('segreteria') && ! $user->hasRole('contabile')) {
                abort(403, 'Non autorizzato a scaricare questo allegato.');
            }
        }

        if (! $attachment->file_path || ! Storage::disk($attachment->disk)->exists($attachment->file_path)) {
            abort(404, 'File non trovato.');
        }

        $path = Storage::disk($attachment->disk)->path($attachment->file_path);
        $mime = $attachment->mime_type ?: 'application/octet-stream';
        $disposition = (str_starts_with($mime, 'image/') || $mime === 'application/pdf') ? 'inline' : 'attachment';
        $filename = $attachment->original_name;

        return response()->file($path, [
            'Content-Type' => $mime,
            'Content-Disposition' => $disposition . '; filename="' . addslashes($filename) . '"',
        ]);
    }
}
