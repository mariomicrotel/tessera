<?php

namespace App\Http\Controllers;

use App\Models\Conto;
use App\Models\Incasso;
use App\Models\Member;
use App\Models\PrimaNotaEntry;
use App\Models\Ristorno;
use App\Models\RistornoEntry;
use App\Models\Verbale;
use App\Services\RendicontoCassaSchemaCooperativa;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Gestione dei ristorni ai soci (art. 2545-sexies c.c.).
 *
 * Workflow:
 *  1. L'assemblea delibera un ristorno annuale (status=deliberato)
 *  2. Le entries per socio vengono calcolate proporzionalmente
 *  3. Alla liquidazione (markPagato) vengono generate:
 *     - PrimaNotaEntry B07 per il totale lordo
 *     - Incassi tipo 'altro' se ritenuta trattenuta (non implementato qui)
 *
 * Tutte le route sono protette da middleware 'cooperative'.
 */
class RistorniController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,contabile');
    }

    // ── Elenco ───────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $query = Ristorno::with(['verbale', 'entries'])
            ->orderByDesc('anno')
            ->orderByDesc('id');

        if ($request->filled('anno')) {
            $query->where('anno', (int) $request->anno);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $ristorni = $query->paginate(20)->withQueryString();

        return Inertia::render('Ristorni/Index', [
            'ristorni' => $ristorni,
            'filters'  => $request->only('anno', 'status'),
        ]);
    }

    // ── Creazione ────────────────────────────────────────────────────────────

    public function create(Request $request)
    {
        $members = Member::orderBy('cognome')->orderBy('nome')
            ->get(['id', 'nome', 'cognome', 'ragione_sociale']);

        $verbali = Verbale::orderByDesc('data')
            ->limit(50)
            ->get(['id', 'data', 'titolo']);

        return Inertia::render('Ristorni/Create', [
            'members' => $members,
            'verbali' => $verbali,
            'annoDefault' => (int) date('Y') - 1,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'anno'                      => 'required|integer|min:2000|max:2100',
            'importo_totale_deliberato' => 'required|numeric|min:0.01',
            'aliquota_ritenuta'         => 'required|numeric|min:0|max:1',
            'data_delibera_assemblea'   => 'required|date',
            'verbale_id'                => 'nullable|exists:verbali,id',
            'note'                      => 'nullable|string|max:2000',
            'entries'                   => 'required|array|min:1',
            'entries.*.member_id'       => 'required|exists:members,id',
            'entries.*.importo_lordo'   => 'required|numeric|min:0.01',
        ]);

        // Controllo somma entries coerente col totale deliberato (tolleranza 1 cent)
        $sumLordo = array_sum(array_column($data['entries'], 'importo_lordo'));
        if (abs($sumLordo - (float) $data['importo_totale_deliberato']) > 0.01) {
            return redirect()->back()->withInput()->withErrors([
                'entries' => sprintf(
                    'La somma dei ristorni individuali (€%.2f) non coincide con il totale deliberato (€%.2f).',
                    $sumLordo,
                    (float) $data['importo_totale_deliberato']
                ),
            ]);
        }

        // Controllo soci distinti
        $memberIds = array_column($data['entries'], 'member_id');
        if (count($memberIds) !== count(array_unique($memberIds))) {
            return redirect()->back()->withInput()->withErrors([
                'entries' => 'Un socio compare più volte: ogni socio può ricevere un solo ristorno per delibera.',
            ]);
        }

        $ristorno = DB::transaction(function () use ($data) {
            $r = Ristorno::create([
                'anno'                      => $data['anno'],
                'importo_totale_deliberato' => $data['importo_totale_deliberato'],
                'aliquota_ritenuta'         => $data['aliquota_ritenuta'],
                'data_delibera_assemblea'   => $data['data_delibera_assemblea'],
                'verbale_id'                => $data['verbale_id'] ?? null,
                'note'                      => $data['note'] ?? null,
                'status'                    => Ristorno::STATUS_DELIBERATO,
            ]);

            foreach ($data['entries'] as $entry) {
                $calc = RistornoEntry::calcolaFromLordo(
                    (float) $entry['importo_lordo'],
                    (float) $data['aliquota_ritenuta']
                );
                RistornoEntry::create(array_merge($calc, [
                    'ristorno_id' => $r->id,
                    'member_id'   => $entry['member_id'],
                    'status'      => RistornoEntry::STATUS_DELIBERATO,
                ]));
            }

            return $r;
        });

        return redirect()->route('ristorni.show', $ristorno)
            ->with('flash', ['type' => 'success', 'message' => 'Ristorno deliberato.']);
    }

    // ── Dettaglio ────────────────────────────────────────────────────────────

    public function show(Ristorno $ristorno)
    {
        $ristorno->load([
            'verbale',
            'entries.member:id,nome,cognome,ragione_sociale,codice_fiscale',
        ]);

        return Inertia::render('Ristorni/Show', [
            'ristorno' => $ristorno,
            'totali'   => [
                'lordo'    => round((float) $ristorno->entries->sum('importo_lordo'), 2),
                'ritenuta' => round((float) $ristorno->entries->sum('importo_ritenuta'), 2),
                'netto'    => round((float) $ristorno->entries->sum('importo_netto'), 2),
            ],
        ]);
    }

    // ── Liquidazione (marca come pagato) ─────────────────────────────────────

    public function markPagato(Request $request, Ristorno $ristorno)
    {
        $data = $request->validate([
            'data_pagamento' => 'required|date',
            'conto_id'       => 'required|exists:conti,id',
        ]);

        if (! $ristorno->isDeliberato()) {
            return redirect()->back()->with('flash', [
                'type'    => 'error',
                'message' => 'Solo i ristorni in stato "deliberato" possono essere liquidati.',
            ]);
        }

        DB::transaction(function () use ($ristorno, $data) {
            $paidAt = Carbon::parse($data['data_pagamento']);

            $ristorno->update([
                'status'         => Ristorno::STATUS_PAGATO,
                'data_pagamento' => $paidAt,
            ]);

            $ristorno->entries()->update([
                'status'         => RistornoEntry::STATUS_PAGATO,
                'data_pagamento' => $paidAt,
            ]);

            // Prima nota: uscita totale lordo sul codice B07 ristorni soci
            $totaleLordo = (float) $ristorno->entries()->sum('importo_lordo');
            PrimaNotaEntry::create([
                'conto_id'         => $data['conto_id'],
                'rendiconto_code'  => RendicontoCassaSchemaCooperativa::CODE_RISTORNI_SOCI,
                'entryable_type'   => Ristorno::class,
                'entryable_id'     => $ristorno->id,
                'date'             => $paidAt->toDateString(),
                'amount'           => -abs($totaleLordo),
                'description'      => sprintf('Ristorno ai soci anno %d (delibera %s)',
                    $ristorno->anno,
                    $ristorno->data_delibera_assemblea?->format('d/m/Y') ?? ''
                ),
                'gestione'         => 'istituzionale',
                'competenza_cassa' => true,
            ]);
        });

        return redirect()->route('ristorni.show', $ristorno)
            ->with('flash', ['type' => 'success', 'message' => 'Ristorno liquidato con successo.']);
    }

    // ── Annulla ──────────────────────────────────────────────────────────────

    public function annulla(Ristorno $ristorno)
    {
        if ($ristorno->isPagato()) {
            return redirect()->back()->with('flash', [
                'type'    => 'error',
                'message' => 'Non si può annullare un ristorno già pagato.',
            ]);
        }
        $ristorno->update(['status' => Ristorno::STATUS_ANNULLATO]);

        return redirect()->route('ristorni.index')
            ->with('flash', ['type' => 'success', 'message' => 'Ristorno annullato.']);
    }

    // ── Export CSV ───────────────────────────────────────────────────────────

    public function export(Ristorno $ristorno): StreamedResponse
    {
        $ristorno->load(['entries.member']);

        $filename = 'ristorno-' . $ristorno->anno . '-' . $ristorno->id . '-' . now()->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($ristorno) {
            $out = fopen('php://output', 'w');
            // BOM per Excel
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Socio', 'Codice Fiscale', 'Lordo', 'Aliquota Ritenuta', 'Ritenuta', 'Netto', 'Status'], ';');

            foreach ($ristorno->entries as $e) {
                $m = $e->member;
                $nome = $m
                    ? ($m->ragione_sociale ?: trim(($m->cognome ?? '') . ' ' . ($m->nome ?? '')))
                    : 'N/D';
                fputcsv($out, [
                    $nome,
                    $m->codice_fiscale ?? '',
                    number_format((float) $e->importo_lordo, 2, ',', '.'),
                    number_format((float) $e->aliquota_ritenuta * 100, 2, ',', '.') . '%',
                    number_format((float) $e->importo_ritenuta, 2, ',', '.'),
                    number_format((float) $e->importo_netto, 2, ',', '.'),
                    $e->status,
                ], ';');
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
