<?php

namespace App\Http\Controllers;

use App\Models\EstrattoContoModel;
use App\Models\FatturaAttiva;
use App\Models\FatturaPassiva;
use App\Models\MovimentoBancario;
use App\Models\PrimaNotaEntry;
use App\Models\RiconciliazioneVoce;
use App\Services\EstrattoCsv940ImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RiconciliazioneController extends Controller
{
    public function __construct(private EstrattoCsv940ImportService $importService)
    {
        $this->middleware('role:admin,contabile');
    }

    // ── Elenco estratti conto ─────────────────────────────────────────────

    public function index(): Response
    {
        $estratti = EstrattoContoModel::withCount(['movimenti', 'movimenti as movimenti_non_riconciliati_count' => fn ($q) => $q->where('riconciliato', false)])
            ->orderByDesc('periodo_al')
            ->get();

        return Inertia::render('Riconciliazione/Index', [
            'estratti' => $estratti,
        ]);
    }

    // ── Upload estratto conto ─────────────────────────────────────────────

    public function create(): Response
    {
        return Inertia::render('Riconciliazione/Upload');
    }

    public function previewUpload(Request $request)
    {
        $request->validate([
            'file'    => ['required', 'file', 'max:4096'],
            'formato' => ['required', 'in:csv,mt940'],
        ]);

        try {
            $contenuto = file_get_contents($request->file('file')->getRealPath());
            $movimenti = $this->importService->preview($contenuto, $request->formato);
            return response()->json(['ok' => true, 'movimenti' => $movimenti, 'count' => count($movimenti)]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'file'    => ['required', 'file', 'max:4096'],
            'formato' => ['required', 'in:csv,mt940'],
            'banca'   => ['nullable', 'string', 'max:80'],
            'iban'    => ['nullable', 'string', 'max:34'],
        ]);

        try {
            $contenuto = file_get_contents($request->file('file')->getRealPath());
            $estratto  = $this->importService->import(
                $contenuto,
                $data['formato'],
                $request->file('file')->getClientOriginalName(),
                $data['banca'] ?? null,
                $data['iban']  ?? null
            );

            return redirect()->route('riconciliazione.show', $estratto)
                ->with('success', "Importati {$estratto->movimenti->count()} movimenti.");
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }
    }

    // ── Visualizza estratto + match UI ───────────────────────────────────

    public function show(EstrattoContoModel $riconciliazione): Response
    {
        $movimenti = MovimentoBancario::where('estratto_conto_id', $riconciliazione->id)
            ->with('riconciliazioni')
            ->orderBy('data_valuta')
            ->get()
            ->map(fn ($m) => [
                'id'             => $m->id,
                'data_valuta'    => $m->data_valuta?->toDateString(),
                'data_contabile' => $m->data_contabile?->toDateString(),
                'descrizione'    => $m->descrizione,
                'importo'        => (float) $m->importo,
                'tipo'           => $m->tipo,
                'riferimento'    => $m->riferimento,
                'riconciliato'   => $m->riconciliato,
                'riconciliazioni' => $m->riconciliazioni->map(fn ($r) => [
                    'id'          => $r->id,
                    'entita_tipo' => $r->entita_tipo,
                    'entita_id'   => $r->entita_id,
                    'nota'        => $r->nota,
                ]),
            ]);

        $totaleAvere = $movimenti->where('tipo', 'avere')->sum('importo');
        $totaleDare  = $movimenti->where('tipo', 'dare')->sum('importo');
        $nonRiconciliati = $movimenti->where('riconciliato', false)->count();

        return Inertia::render('Riconciliazione/Show', [
            'estratto'       => $riconciliazione,
            'movimenti'      => $movimenti,
            'totaleAvere'    => round($totaleAvere, 2),
            'totaleDare'     => round($totaleDare,  2),
            'nonRiconciliati' => $nonRiconciliati,
        ]);
    }

    // ── Match manuale ─────────────────────────────────────────────────────

    public function match(Request $request, MovimentoBancario $movimento): RedirectResponse
    {
        $data = $request->validate([
            'entita_tipo' => ['nullable', 'string', 'in:prima_nota_entry,fattura_passiva,fattura_attiva,incasso'],
            'entita_id'   => ['nullable', 'string'],
            'nota'        => ['nullable', 'string', 'max:255'],
        ]);

        RiconciliazioneVoce::create([
            'movimento_bancario_id' => $movimento->id,
            'entita_tipo'           => $data['entita_tipo'] ?? null,
            'entita_id'             => $data['entita_id']   ?? null,
            'nota'                  => $data['nota']        ?? null,
        ]);

        $movimento->update(['riconciliato' => true]);

        return back()->with('success', 'Movimento riconciliato.');
    }

    public function unmatch(MovimentoBancario $movimento): RedirectResponse
    {
        $movimento->riconciliazioni()->delete();
        $movimento->update(['riconciliato' => false]);
        return back()->with('success', 'Riconciliazione rimossa.');
    }

    /**
     * Suggerisce corrispondenze automatiche per un movimento bancario
     * in base a importo e data.
     */
    public function suggerisci(MovimentoBancario $movimento)
    {
        $importo = (float) $movimento->importo;
        $data    = $movimento->data_valuta;
        $margine = 7; // giorni di tolleranza

        $suggerimenti = [];

        if ($movimento->tipo === MovimentoBancario::TIPO_DARE) {
            // Cerco fatture passive con importo simile
            $fps = FatturaPassiva::where('totale_documento', $importo)
                ->whereBetween('data_fattura', [$data->copy()->subDays($margine), $data->copy()->addDays($margine)])
                ->limit(5)
                ->get(['id', 'numero_fattura', 'data_fattura', 'totale_documento']);

            foreach ($fps as $f) {
                $suggerimenti[] = [
                    'entita_tipo' => 'fattura_passiva',
                    'entita_id'   => $f->id,
                    'label'       => "Fattura passiva {$f->numero_fattura} ({$f->data_fattura?->format('d/m/Y')}) — € {$f->totale_documento}",
                ];
            }
        } else {
            // Cerco fatture attive o incassi con importo simile
            $fas = FatturaAttiva::where('totale_documento', $importo)
                ->whereBetween('data_fattura', [$data->copy()->subDays($margine), $data->copy()->addDays($margine)])
                ->limit(5)
                ->get(['id', 'numero_fattura', 'data_fattura', 'totale_documento']);

            foreach ($fas as $f) {
                $suggerimenti[] = [
                    'entita_tipo' => 'fattura_attiva',
                    'entita_id'   => $f->id,
                    'label'       => "Fattura attiva {$f->numero_fattura} ({$f->data_fattura?->format('d/m/Y')}) — € {$f->totale_documento}",
                ];
            }
        }

        return response()->json($suggerimenti);
    }

    public function destroy(EstrattoContoModel $riconciliazione): RedirectResponse
    {
        $riconciliazione->delete();
        return redirect()->route('riconciliazione.index')->with('success', 'Estratto conto eliminato.');
    }
}
