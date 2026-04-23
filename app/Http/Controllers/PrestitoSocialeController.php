<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\PrestitoSocialeLibretto;
use App\Models\PrestitoSocialeMovimento;
use App\Services\PrestitoSocialeService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Gestione del Prestito Sociale per cooperative.
 *
 * Tutte le route sono protette da middleware 'cooperative'.
 */
class PrestitoSocialeController extends Controller
{
    public function __construct(
        private readonly PrestitoSocialeService $service,
    ) {}

    // ── Elenco libretti ───────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $this->authorize('viewAny', Member::class);

        $anno = (int) ($request->get('anno', now()->year));

        // Libretti con relazioni e ricerca
        $query = PrestitoSocialeLibretto::with('member')
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $q = $request->search;
            $query->whereHas('member', fn ($mq) => $mq->where(function ($sub) use ($q) {
                $sub->where('cognome', 'like', "%{$q}%")
                    ->orWhere('nome', 'like', "%{$q}%")
                    ->orWhere('ragione_sociale', 'like', "%{$q}%");
            }));
        }

        $libretti = $query->paginate(50)->withQueryString();

        // ── KPI ──
        $totaleDepositi = (float) PrestitoSocialeMovimento::where('tipo', PrestitoSocialeMovimento::TIPO_DEPOSITO)
            ->sum('importo');

        $interessiAnno = (float) PrestitoSocialeMovimento::where('tipo', PrestitoSocialeMovimento::TIPO_INTERESSI)
            ->where('anno_competenza', $anno)
            ->sum('importo');

        $ritenuteAnno = (float) PrestitoSocialeMovimento::where('tipo', PrestitoSocialeMovimento::TIPO_RITENUTA_FISCALE)
            ->where('anno_competenza', $anno)
            ->sum('importo');

        $numLibrettiAttivi = (int) PrestitoSocialeLibretto::attivi()->count();

        $totSaldoAttivo = (float) PrestitoSocialeLibretto::attivi()->sum('saldo_attuale');

        return Inertia::render('PrestitoSociale/Index', [
            'libretti'           => $libretti,
            'kpi'                => [
                'num_libretti_attivi' => $numLibrettiAttivi,
                'totale_depositi'     => $totaleDepositi,
                'tot_saldo_attivo'    => $totSaldoAttivo,
                'interessi_anno'      => $interessiAnno,
                'ritenute_anno'       => $ritenuteAnno,
                'anno'               => $anno,
            ],
            'filters'            => $request->only('status', 'search', 'anno'),
        ]);
    }

    // ── Form apertura libretto ────────────────────────────────────────────────

    public function create()
    {
        $this->authorize('create', Member::class);

        // Soci attivi che NON hanno già un libretto aperto
        $membersConLibretto = PrestitoSocialeLibretto::attivi()->pluck('member_id')->toArray();

        $members = Member::nonCessati()
            ->whereNotNull('data_iscrizione')
            ->whereNotIn('id', $membersConLibretto)
            ->orderBy('cognome')
            ->orderBy('nome')
            ->get(['id', 'nome', 'cognome', 'ragione_sociale', 'tipo_persona', 'numero_tessera']);

        return Inertia::render('PrestitoSociale/Create', [
            'members'               => $members,
            'tasso_default'         => 0.02, // 2% come default ragionevole
        ]);
    }

    // ── Apri libretto ─────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $this->authorize('create', Member::class);

        $validated = $request->validate([
            'member_id'    => 'required|exists:members,id',
            'tasso_annuo'  => 'required|numeric|min:0|max:0.20',
            'data'         => 'required|date',
        ]);

        $member = Member::findOrFail($validated['member_id']);

        $libretto = $this->service->apriLibretto(
            member:    $member,
            tassoAnnuo: (float) $validated['tasso_annuo'],
            data:      Carbon::parse($validated['data']),
        );

        return redirect()
            ->route('prestito-sociale.show', $libretto)
            ->with('flash', ['type' => 'success', 'message' => "Libretto {$libretto->numero_libretto} aperto per {$member->nomeCompleto()}."]);
    }

    // ── Dettaglio libretto ────────────────────────────────────────────────────

    public function show(PrestitoSocialeLibretto $libretto)
    {
        $this->authorize('view', Member::class);

        $libretto->load('member');

        $movimenti = PrestitoSocialeMovimento::where('libretto_id', $libretto->id)
            ->orderBy('data_valuta', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(30);

        // Riepilogo anno corrente
        $annoCorrente = now()->year;
        $interessiAnno = (float) PrestitoSocialeMovimento::where('libretto_id', $libretto->id)
            ->where('tipo', PrestitoSocialeMovimento::TIPO_INTERESSI)
            ->where('anno_competenza', $annoCorrente)
            ->sum('importo');
        $ritenuteAnno = (float) PrestitoSocialeMovimento::where('libretto_id', $libretto->id)
            ->where('tipo', PrestitoSocialeMovimento::TIPO_RITENUTA_FISCALE)
            ->where('anno_competenza', $annoCorrente)
            ->sum('importo');

        return Inertia::render('PrestitoSociale/Show', [
            'libretto'  => $libretto,
            'movimenti' => $movimenti,
            'riepilogo_anno' => [
                'anno'          => $annoCorrente,
                'interessi'     => $interessiAnno,
                'ritenute'      => $ritenuteAnno,
                'interessi_netti' => round($interessiAnno - $ritenuteAnno, 2),
            ],
        ]);
    }

    // ── Deposita ──────────────────────────────────────────────────────────────

    public function deposita(Request $request, PrestitoSocialeLibretto $libretto)
    {
        $this->authorize('update', Member::class);

        $validated = $request->validate([
            'importo'  => 'required|numeric|min:0.01',
            'data'     => 'required|date',
            'desc'     => 'nullable|string|max:255',
        ]);

        $movimento = $this->service->deposita(
            lib:     $libretto,
            importo: (float) $validated['importo'],
            data:    Carbon::parse($validated['data']),
            desc:    $validated['desc'] ?? '',
        );

        return redirect()
            ->route('prestito-sociale.show', $libretto)
            ->with('flash', ['type' => 'success', 'message' => "Deposito di €".number_format((float)$validated['importo'], 2, ',', '.')." registrato."]);
    }

    // ── Preleva ───────────────────────────────────────────────────────────────

    public function preleva(Request $request, PrestitoSocialeLibretto $libretto)
    {
        $this->authorize('update', Member::class);

        $validated = $request->validate([
            'importo'   => 'required|numeric|min:0.01',
            'data'      => 'required|date',
            'desc'      => 'nullable|string|max:255',
            'prenotato' => 'boolean',
        ]);

        $this->service->preleva(
            lib:        $libretto,
            importo:    (float) $validated['importo'],
            data:       Carbon::parse($validated['data']),
            desc:       $validated['desc'] ?? '',
            prenotato:  (bool) ($validated['prenotato'] ?? false),
        );

        return redirect()
            ->route('prestito-sociale.show', $libretto)
            ->with('flash', ['type' => 'success', 'message' => "Prelievo di €".number_format((float)$validated['importo'], 2, ',', '.')." registrato."]);
    }

    // ── Calcola interessi mese ────────────────────────────────────────────────

    public function calcolaInteressi(Request $request)
    {
        $this->authorize('update', Member::class);

        $validated = $request->validate([
            'anno' => 'required|integer|min:2000|max:2100',
            'mese' => 'required|integer|min:1|max:12',
        ]);

        $risultati = $this->service->calcolaInteressi(
            anno: (int) $validated['anno'],
            mese: (int) $validated['mese'],
        );

        $totInteressi = $risultati->sum('interessi_lordi');
        $totRitenute  = $risultati->sum('ritenuta');
        $nLibretti    = $risultati->count();

        $mese = str_pad((string) $validated['mese'], 2, '0', STR_PAD_LEFT);
        $msg  = $nLibretti > 0
            ? "Interessi {$mese}/{$validated['anno']} calcolati su {$nLibretti} librett" . ($nLibretti === 1 ? 'o' : 'i') . ". Lordi: €".number_format($totInteressi, 2, ',', '.').", Ritenute: €".number_format($totRitenute, 2, ',', '.')."."
            : "Nessun libretto da elaborare per {$mese}/{$validated['anno']} (già calcolati o nessun libretto attivo).";

        return redirect()
            ->route('prestito-sociale.index')
            ->with('flash', ['type' => 'success', 'message' => $msg]);
    }

    // ── Export estratto conto CSV ─────────────────────────────────────────────

    public function exportEstrattoConto(Request $request, PrestitoSocialeLibretto $libretto)
    {
        $this->authorize('view', Member::class);

        $dal = $request->filled('dal') ? Carbon::parse($request->dal) : Carbon::now()->startOfYear();
        $al  = $request->filled('al')  ? Carbon::parse($request->al)  : Carbon::now();

        $estratto  = $this->service->getEstrattoConto($libretto, $dal, $al);
        $nomeSocio = $libretto->member?->nomeCompleto() ?? "socio-{$libretto->member_id}";
        $filename  = 'estratto-'.Str::slug($libretto->numero_libretto).'-'.Str::slug($nomeSocio).'-'.$dal->format('Ymd').'-'.$al->format('Ymd').'.csv';

        $tipoLabels = [
            'deposito'          => 'Deposito',
            'prelievo'          => 'Prelievo',
            'interessi'         => 'Interessi',
            'ritenuta_fiscale'  => 'Ritenuta fiscale',
            'rettifica'         => 'Rettifica',
        ];

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($estratto, $tipoLabels) {
            $f = fopen('php://output', 'w');
            fputs($f, "\xEF\xBB\xBF"); // BOM UTF-8

            // Intestazione libretto
            fputcsv($f, ['Libretto:', $estratto['libretto']->numero_libretto], ';');
            fputcsv($f, ['Socio:', $estratto['libretto']->member?->nomeCompleto() ?? '—'], ';');
            fputcsv($f, ['Tasso annuo:', number_format((float)$estratto['libretto']->tasso_interesse_annuo * 100, 2, ',', '').'%'], ';');
            fputcsv($f, ['Periodo dal:', $estratto['dal']->format('d/m/Y'), 'al:', $estratto['al']->format('d/m/Y')], ';');
            fputcsv($f, ['Saldo iniziale:', number_format($estratto['saldo_iniziale'], 2, ',', '')], ';');
            fputcsv($f, [], ';');

            // Intestazione movimenti
            fputcsv($f, ['Data valuta', 'Tipo', 'Segno', 'Importo €', 'Importo netto €', 'Saldo dopo €', 'Descrizione'], ';');

            foreach ($estratto['movimenti'] as $mov) {
                fputcsv($f, [
                    $mov->data_valuta?->format('d/m/Y'),
                    $tipoLabels[$mov->tipo] ?? $mov->tipo,
                    $mov->segno === 'avere' ? 'Entrata' : 'Uscita',
                    number_format((float)$mov->importo, 2, ',', ''),
                    $mov->importo_netto !== null ? number_format((float)$mov->importo_netto, 2, ',', '') : '',
                    number_format((float)$mov->saldo_dopo, 2, ',', ''),
                    $mov->descrizione ?? '',
                ], ';');
            }

            fputcsv($f, [], ';');
            fputcsv($f, ['Saldo finale:', number_format($estratto['saldo_finale'], 2, ',', '')], ';');
            fputcsv($f, ['Tot. depositi:', number_format($estratto['totale_depositi'], 2, ',', '')], ';');
            fputcsv($f, ['Tot. prelievi:', number_format($estratto['totale_prelievi'], 2, ',', '')], ';');
            fputcsv($f, ['Tot. interessi lordi:', number_format($estratto['totale_interessi_lordi'], 2, ',', '')], ';');
            fputcsv($f, ['Tot. ritenute:', number_format($estratto['totale_ritenute'], 2, ',', '')], ';');

            fclose($f);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ── Chiudi libretto ───────────────────────────────────────────────────────

    public function chiudi(Request $request, PrestitoSocialeLibretto $libretto)
    {
        $this->authorize('update', Member::class);

        $validated = $request->validate([
            'data' => 'required|date',
        ]);

        $this->service->chiudiLibretto($libretto, Carbon::parse($validated['data']));

        $nome = $libretto->member?->nomeCompleto() ?? "libretto #{$libretto->id}";
        return redirect()
            ->route('prestito-sociale.index')
            ->with('flash', ['type' => 'info', 'message' => "Libretto {$libretto->numero_libretto} chiuso ({$nome})."]);
    }
}
