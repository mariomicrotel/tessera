<?php

namespace App\Http\Controllers;

use App\Models\CooperativeShare;
use App\Models\Member;
use App\Models\Settings;
use App\Services\CapitaleSocialeService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Gestione quote di capitale sociale per cooperative.
 *
 * Tutte le route di questo controller sono protette dal middleware 'cooperative',
 * quindi non è necessario verificare is_cooperativa nei singoli metodi.
 */
class CooperativeShareController extends Controller
{
    public function __construct(
        private readonly CapitaleSocialeService $service,
    ) {}

    // ── Elenco quote ─────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $this->authorize('viewAny', CooperativeShare::class);

        $query = CooperativeShare::with('member.memberType')
            ->orderBy('created_at', 'desc');

        // Filtro per status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Ricerca socio (nome, cognome, ragione_sociale)
        if ($request->filled('search')) {
            $q = $request->search;
            $query->whereHas('member', function ($mq) use ($q) {
                $mq->where(function ($sub) use ($q) {
                    $sub->where('cognome', 'like', "%{$q}%")
                        ->orWhere('nome', 'like', "%{$q}%")
                        ->orWhere('ragione_sociale', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                });
            });
        }

        $shares = $query->paginate(50)->withQueryString();

        return Inertia::render('CooperativeShares/Index', [
            'shares'             => $shares,
            'situazione_capitale' => $this->service->getSituazioneCapitale(),
            'filters'            => $request->only('status', 'search'),
        ]);
    }

    // ── Form sottoscrizione ───────────────────────────────────────────────────

    public function create()
    {
        $this->authorize('create', CooperativeShare::class);

        $members = Member::nonCessati()
            ->whereNotNull('data_iscrizione')
            ->orderBy('cognome')
            ->orderBy('nome')
            ->get(['id', 'nome', 'cognome', 'ragione_sociale', 'tipo_persona', 'numero_tessera']);

        return Inertia::render('CooperativeShares/Create', [
            'members'               => $members,
            'valore_unitario_default' => (float) Settings::get('quota_valore_unitario_coop', 50),
        ]);
    }

    // ── Salva sottoscrizione ──────────────────────────────────────────────────

    public function store(Request $request)
    {
        $this->authorize('create', CooperativeShare::class);

        $validated = $request->validate([
            'member_id'       => 'required|exists:members,id',
            'numero_quote'    => 'required|integer|min:1',
            'valore_unitario' => 'required|numeric|min:0.01',
            'data'            => 'required|date',
        ]);

        $member = Member::findOrFail($validated['member_id']);

        $this->service->sottoscriviQuote(
            member:         $member,
            numeroQuote:    $validated['numero_quote'],
            valoreUnitario: (float) $validated['valore_unitario'],
            data:           Carbon::parse($validated['data']),
        );

        return redirect()
            ->route('capitale-sociale.index')
            ->with('flash', ['type' => 'success', 'message' => "Quote sottoscritte per {$member->nomeCompleto()}."]);
    }

    // ── Dettaglio quota ───────────────────────────────────────────────────────

    public function show(CooperativeShare $share)
    {
        $this->authorize('view', $share);

        $share->load('member.memberType');

        return Inertia::render('CooperativeShares/Show', [
            'share' => $share,
        ]);
    }

    // ── Versa importo ─────────────────────────────────────────────────────────

    public function versa(Request $request, CooperativeShare $share)
    {
        $this->authorize('versa', $share);

        $validated = $request->validate([
            'importo_versato' => 'required|numeric|min:0.01',
            'data'            => 'required|date',
        ]);

        $this->service->versaQuote(
            share:          $share,
            importoVersato: (float) $validated['importo_versato'],
            data:           Carbon::parse($validated['data']),
        );

        $nome = $share->member?->nomeCompleto() ?? "quota #{$share->id}";

        return redirect()
            ->route('capitale-sociale.show', $share)
            ->with('flash', ['type' => 'success', 'message' => "Versamento registrato per {$nome}."]);
    }

    // ── Riscatta quote ────────────────────────────────────────────────────────

    public function riscatta(Request $request, CooperativeShare $share)
    {
        $this->authorize('riscatta', $share);

        $validated = $request->validate([
            'motivo_riscatto' => 'required|string|max:500',
            'data'            => 'required|date',
        ]);

        $this->service->riscattaQuote(
            share:  $share,
            motivo: $validated['motivo_riscatto'],
            data:   Carbon::parse($validated['data']),
        );

        $nome = $share->member?->nomeCompleto() ?? "quota #{$share->id}";

        return redirect()
            ->route('capitale-sociale.index')
            ->with('flash', ['type' => 'success', 'message' => "Quote riscattate per {$nome}."]);
    }

    // ── Export CSV ────────────────────────────────────────────────────────────

    public function export(Request $request)
    {
        $this->authorize('viewAny', CooperativeShare::class);

        $tenant   = app()->bound('current_tenant') ? app('current_tenant') : null;
        $filename = 'capitale-sociale-' . Str::slug($tenant?->name ?? 'cooperativa') . '-' . now()->format('Ymd') . '.csv';

        $query = CooperativeShare::with('member')
            ->orderBy('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $shares = $query->get();

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $statusLabels = [
            'sottoscritta'          => 'Sottoscritta',
            'parzialmente_versata'  => 'Parz. versata',
            'versata'               => 'Versata',
            'riscattata'            => 'Riscattata',
            'annullata'             => 'Annullata',
        ];

        $callback = function () use ($shares, $statusLabels) {
            $file = fopen('php://output', 'w');
            fputs($file, "\xEF\xBB\xBF"); // BOM UTF-8

            fputcsv($file, [
                'Socio',
                'Tipo persona',
                'Data sottoscrizione',
                'N. quote',
                'Valore unitario €',
                'Totale sottoscritto €',
                'Totale versato €',
                'Da versare €',
                'Status',
                'Data riscatto',
            ], ';');

            foreach ($shares as $share) {
                $m = $share->member;
                fputcsv($file, [
                    $m?->nomeCompleto() ?? "ID {$share->member_id}",
                    $m?->tipo_persona === 'giuridica' ? 'Giuridica' : 'Fisica',
                    $share->data_sottoscrizione?->format('d/m/Y') ?? '',
                    $share->numero_quote,
                    number_format((float) $share->valore_unitario, 2, ',', ''),
                    number_format((float) $share->totale_sottoscritto, 2, ',', ''),
                    number_format((float) $share->totale_versato, 2, ',', ''),
                    number_format($share->ancora_da_versare, 2, ',', ''),
                    $statusLabels[$share->status] ?? $share->status,
                    $share->data_riscatto?->format('d/m/Y') ?? '',
                ], ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
