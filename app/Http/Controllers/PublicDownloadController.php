<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Services\RendicontoCassaService;
use App\Support\PdfLetterheadData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Download pubblici con URL firmati (signed): logo, statuto e rendiconto PDF.
 * Nessuna autenticazione: l'accesso è consentito solo con URL firmato valido.
 */
class PublicDownloadController extends Controller
{
    /**
     * Logo associazione (solo attachment con tag 'logo' per setting). Inline per visualizzazione.
     */
    public function logo(Request $request, int $attachment): Response
    {
        $att = Attachment::forSetting('logo')->where('id', $attachment)->first();
        if (! $att || ! $att->file_path || ! Storage::disk($att->disk)->exists($att->file_path)) {
            abort(404, 'File non trovato.');
        }

        $path = Storage::disk($att->disk)->path($att->file_path);
        $mime = $att->mime_type ?: 'application/octet-stream';
        $filename = $att->original_name;
        $disposition = str_starts_with($mime, 'image/') ? 'inline' : 'attachment';

        return response()->file($path, [
            'Content-Type' => $mime,
            'Content-Disposition' => $disposition . '; filename="' . addslashes($filename) . '"',
        ]);
    }

    /**
     * Download statuto (solo attachment con tag 'statuto' per setting).
     */
    public function statuto(Request $request, int $attachment): Response
    {
        $att = Attachment::forSetting('statuto')->where('id', $attachment)->first();
        if (! $att || ! $att->file_path || ! Storage::disk($att->disk)->exists($att->file_path)) {
            abort(404, 'File non trovato.');
        }

        $path = Storage::disk($att->disk)->path($att->file_path);
        $mime = $att->mime_type ?: 'application/octet-stream';
        $filename = $att->original_name;

        return response()->file($path, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="' . addslashes($filename) . '"',
        ]);
    }

    /**
     * Immagine di sfondo sezione sito pubblico (solo attachment con tag section_bg_{sectionId}). Inline per visualizzazione.
     */
    public function sectionBackground(Request $request, string $sectionId, int $attachment): Response
    {
        $allowedIds = config('site_sections.allowed_ids', []);
        if (! in_array($sectionId, $allowedIds, true)) {
            abort(404, 'Sezione non valida.');
        }
        $att = Attachment::forSetting('section_bg_' . $sectionId)->where('id', $attachment)->first();
        if (! $att || ! $att->file_path || ! Storage::disk($att->disk)->exists($att->file_path)) {
            abort(404, 'File non trovato.');
        }

        $path = Storage::disk($att->disk)->path($att->file_path);
        $mime = $att->mime_type ?: 'application/octet-stream';
        $filename = $att->original_name;
        $disposition = str_starts_with($mime, 'image/') ? 'inline' : 'attachment';

        return response()->file($path, [
            'Content-Type' => $mime,
            'Content-Disposition' => $disposition . '; filename="' . addslashes($filename) . '"',
        ]);
    }

    /**
     * Download PDF rendiconto per cassa per l'anno indicato.
     */
    public function rendiconto(Request $request, int $anno, RendicontoCassaService $service): Response
    {
        $anno = $anno >= 2000 && $anno <= 2100 ? $anno : (int) now()->year;

        $data = $service->buildRendiconto($anno);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('rendiconto_cassa.template', [
            'rendiconto' => $data,
            'letterhead' => PdfLetterheadData::data(),
        ]);

        $filename = 'rendiconto_cassa_' . $anno . '.pdf';

        return $pdf->download($filename);
    }
}
