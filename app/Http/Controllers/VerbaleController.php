<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Verbale;
use App\Models\Template;
use App\Services\AttachmentService;
use App\Services\PlaceholderResolver;
use App\Support\PdfLetterheadData;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class VerbaleController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,segreteria');
    }

    public function index(Request $request)
    {
        $query = Verbale::query();
        if ($request->filled('tipo') && in_array($request->tipo, [Verbale::TIPO_CONSIGLIO, Verbale::TIPO_ASSEMBLEA], true)) {
            $query->where('tipo', $request->tipo);
        }
        $verbali = $query->orderByDesc('data')->paginate(15)->withQueryString();

        return Inertia::render('Verbali/Index', [
            'verbali' => $verbali,
            'filters' => ['tipo' => $request->input('tipo')],
        ]);
    }

    public function create(Request $request)
    {
        $anno = $request->filled('anno') && is_numeric($request->anno) ? (int) $request->anno : (int) date('Y');

        return Inertia::render('Verbali/Create', [
            'tipoOptions' => [
                ['value' => Verbale::TIPO_CONSIGLIO, 'label' => 'Consiglio direttivo'],
                ['value' => Verbale::TIPO_ASSEMBLEA, 'label' => 'Assemblea'],
            ],
            'prossimoNumeroConsiglio' => Verbale::prossimoNumeroPer(Verbale::TIPO_CONSIGLIO, $anno),
            'prossimoNumeroAssemblea' => Verbale::prossimoNumeroPer(Verbale::TIPO_ASSEMBLEA, $anno),
            'annoSuggerito' => $anno,
            'templates' => Template::where('categoria', 'verbale')->orderBy('nome')->get(['id', 'nome', 'tipo_verbale', 'contenuto']),
        ]);
    }

    /** Restituisce il prossimo numero progressivo per tipo e anno (numerazione separata CD / Assemblea). */
    public function prossimoNumero(Request $request)
    {
        $request->validate([
            'tipo' => 'required|in:consiglio_direttivo,assemblea',
            'anno' => 'required|integer|min:2000|max:2100',
        ]);

        return response()->json([
            'prossimo_numero' => Verbale::prossimoNumeroPer($request->input('tipo'), (int) $request->input('anno')),
        ]);
    }

    public function store(Request $request)
    {
        $anno = \Carbon\Carbon::parse($request->input('data'))->year;
        $request->merge([
            'anno' => $anno,
            'numero' => $request->filled('numero') && $request->input('numero') !== '' ? (int) $request->input('numero') : null,
        ]);

        $validated = $request->validate([
            'tipo' => 'required|in:consiglio_direttivo,assemblea',
            'data' => [
                'required',
                'date',
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    $exists = Verbale::where('tipo', $request->input('tipo'))
                        ->where('data', '>', $value)
                        ->exists();
                    if ($exists) {
                        $fail('Esiste già un verbale di questo tipo con data successiva. Non è possibile creare un verbale con data precedente.');
                    }
                },
            ],
            'anno' => 'required|integer|min:2000|max:2100',
            'titolo' => 'required|string|max:255',
            'contenuto' => 'nullable|string',
            'numero' => [
                'nullable',
                'integer',
                'min:1',
                Rule::unique('verbali')->where('tipo', $request->input('tipo'))->where('anno', $anno),
            ],
        ]);

        $context = ['data' => \Carbon\Carbon::parse($validated['data'])];
        $validated['titolo'] = PlaceholderResolver::resolve($validated['titolo'] ?? '', $context);
        $validated['contenuto'] = PlaceholderResolver::resolve(
            $validated['contenuto'] ?? '',
            $context
        );
        $verbale = Verbale::create(array_merge($validated, ['stato' => Verbale::STATO_BOZZA]));

        return redirect()->route('verbali.show', $verbale)
            ->with('flash', ['type' => 'success', 'message' => 'Verbale creato.']);
    }

    public function show(Verbale $verbale)
    {
        $verbale->load('attachments');

        return Inertia::render('Verbali/Show', [
            'verbale' => $verbale,
            'canEdit' => $verbale->isBozza(),
            'canEditAttachments' => $verbale->isBozza(),
            'uploadMaxFileSizeHuman' => self::uploadMaxFileSizeHuman(),
        ]);
    }

    /**
     * Scarica il verbale in bozza come PDF (per firma e ricarico come allegato).
     */
    public function downloadPdf(Verbale $verbale)
    {
        if (! $verbale->isBozza()) {
            return redirect()->route('verbali.show', $verbale)
                ->with('flash', ['type' => 'error', 'message' => 'Solo i verbali in bozza possono essere scaricati in PDF.']);
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('verbali.pdf', [
            'verbale' => $verbale,
            'letterhead' => PdfLetterheadData::data(),
        ])->setOption('enable_php', true);

        $safeTitle = preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $verbale->titolo) ?: 'verbale';
        $slug = Str::slug(mb_substr($safeTitle, 0, 50));
        $filename = 'verbale-bozza-' . $slug . '-' . $verbale->id . '.pdf';

        return $pdf->download($filename);
    }

    public function edit(Verbale $verbale)
    {
        if (! $verbale->isBozza()) {
            return redirect()->route('verbali.show', $verbale)
                ->with('flash', ['type' => 'error', 'message' => 'Il verbale è confermato e non è modificabile.']);
        }

        return Inertia::render('Verbali/Edit', [
            'verbale' => $verbale,
            'templates' => Template::where('categoria', 'verbale')->orderBy('nome')->get(['id', 'nome', 'tipo_verbale', 'contenuto']),
        ]);
    }

    public function update(Request $request, Verbale $verbale)
    {
        if (! $verbale->isBozza()) {
            return redirect()->route('verbali.show', $verbale)
                ->with('flash', ['type' => 'error', 'message' => 'Il verbale è confermato e non è modificabile.']);
        }

        if (! $request->boolean('force_overwrite')) {
            $submittedAt = $request->input('_updated_at');
            if ($submittedAt !== null && $submittedAt !== '') {
                $verbale->refresh();
                if (\Carbon\Carbon::parse($verbale->updated_at)->gt(\Carbon\Carbon::parse($submittedAt))) {
                    return redirect()->back()
                        ->withErrors(['stale' => 'Questo verbale è stato modificato da un altro utente. Vuoi sovrascrivere comunque?'])
                        ->withInput();
                }
            }
        }

        $anno = \Carbon\Carbon::parse($request->input('data'))->year;
        $request->merge([
            'anno' => $anno,
            'numero' => $request->filled('numero') && $request->input('numero') !== '' ? (int) $request->input('numero') : null,
        ]);

        $validated = $request->validate([
            'tipo' => 'required|in:consiglio_direttivo,assemblea',
            'data' => 'required|date',
            'anno' => 'required|integer|min:2000|max:2100',
            'titolo' => 'required|string|max:255',
            'contenuto' => 'nullable|string',
            'numero' => [
                'nullable',
                'integer',
                'min:1',
                Rule::unique('verbali')->where('tipo', $request->input('tipo'))->where('anno', $anno)->ignore($verbale->id),
            ],
        ]);

        $context = ['data' => \Carbon\Carbon::parse($validated['data'])];
        $validated['titolo'] = PlaceholderResolver::resolve($validated['titolo'] ?? '', $context);
        $validated['contenuto'] = PlaceholderResolver::resolve(
            $validated['contenuto'] ?? '',
            $context
        );
        $verbale->update($validated);

        return redirect()->route('verbali.show', $verbale)
            ->with('flash', ['type' => 'success', 'message' => 'Verbale aggiornato.']);
    }

    public function destroy(Verbale $verbale)
    {
        if (! $verbale->isBozza()) {
            return redirect()->route('verbali.show', $verbale)
                ->with('flash', ['type' => 'error', 'message' => 'Il verbale è confermato e non può essere eliminato.']);
        }

        $verbale->delete();

        return redirect()->route('verbali.index')
            ->with('flash', ['type' => 'success', 'message' => 'Verbale eliminato.']);
    }

    public function conferma(Verbale $verbale)
    {
        if (! $verbale->isBozza()) {
            return redirect()->route('verbali.show', $verbale)
                ->with('flash', ['type' => 'error', 'message' => 'Il verbale è già confermato.']);
        }

        $verbale->stato = Verbale::STATO_CONFERMATO;
        if ($verbale->numero === null) {
            $verbale->numero = Verbale::prossimoNumeroPer($verbale->tipo, (int) $verbale->anno);
        }
        $verbale->save();

        return redirect()->route('verbali.show', $verbale)
            ->with('flash', ['type' => 'success', 'message' => 'Verbale confermato.']);
    }

    public function storeAttachment(Request $request, Verbale $verbale, AttachmentService $attachmentService)
    {
        if (! $verbale->isBozza()) {
            return redirect()->back()->with('flash', ['type' => 'error', 'message' => 'Non è possibile aggiungere allegati a un verbale confermato.']);
        }

        $maxKb = (int) floor(UploadedFile::getMaxFilesize() / 1024);
        $limitKb = $maxKb > 0 ? $maxKb : 51200; // solo limite PHP; fallback 50 MB se getMaxFilesize() restituisce 0

        $request->validate([
            'file' => 'required|file|max:'.$limitKb.'|mimes:pdf,jpg,jpeg,png,gif,doc,docx,xls,xlsx',
        ], [
            'file.required' => 'Seleziona un file da caricare.',
            'file.max' => 'Il file non deve superare '.self::uploadMaxFileSizeHuman().' (limite del server).',
            'file.mimes' => 'Formato non consentito. Usa PDF, immagini, Word o Excel.',
        ]);

        $file = $request->file('file');
        if ($file->getError() !== \UPLOAD_ERR_OK) {
            $message = match ($file->getError()) {
                \UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE => 'Il file è troppo grande per le impostazioni del server.',
                \UPLOAD_ERR_PARTIAL => 'Il file è stato caricato solo in parte. Riprova.',
                default => 'Errore durante l\'upload del file. Riprova.',
            };

            return redirect()->back()->with('flash', ['type' => 'error', 'message' => $message]);
        }

        try {
            $attachmentService->store($file, $verbale);
        } catch (\Throwable $e) {
            report($e);
            Log::error('Upload allegato verbale fallito', [
                'verbale_id' => $verbale->id,
                'exception' => $e->getMessage(),
            ]);

            return redirect()->back()->with('flash', ['type' => 'error', 'message' => 'Caricamento non riuscito. Riprova o contatta l\'assistenza.']);
        }

        return redirect()->back()->with('flash', ['type' => 'success', 'message' => 'Allegato caricato.']);
    }

    public function destroyAttachment(Verbale $verbale, Attachment $attachment)
    {
        if (! $verbale->isBozza()) {
            return redirect()->back()->with('flash', ['type' => 'error', 'message' => 'Non è possibile rimuovere allegati da un verbale confermato.']);
        }

        if ($attachment->attachable_type !== Verbale::class || (int) $attachment->attachable_id !== (int) $verbale->id) {
            abort(404, 'Allegato non trovato su questo verbale.');
        }

        $attachment->delete();

        return redirect()->back()->with('flash', ['type' => 'success', 'message' => 'Allegato rimosso.']);
    }

    private static function uploadMaxFileSizeHuman(): string
    {
        $bytes = (int) UploadedFile::getMaxFilesize();
        if ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024), 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return $bytes . ' B';
    }
}
