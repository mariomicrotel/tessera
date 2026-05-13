<?php

namespace App\Http\Controllers;

use App\Models\Conto;
use App\Models\CooperativeShare;
use App\Models\Elezione;
use App\Models\Event;
use App\Models\ExpenseRefund;
use App\Models\FatturaPassiva;
use App\Models\Incasso;
use App\Models\Member;
use App\Models\Organo;
use App\Models\PrestitoSocialeLibretto;
use App\Models\Ristorno;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $tenant = app('current_tenant');

        // Utente senza alcun ruolo: nessun dato sensibile
        // Il superadmin bypassa questo check (non ha righe in role_user ma ha accesso completo)
        if (! $user->is_super_admin && ! $user->roles()->exists()) {
            return Inertia::render('Dashboard', [
                'forSocio' => false,
                'noRole' => true,
            ]);
        }

        // Consulente: reindirizza alla dashboard cross-tenant del consulente
        if ($user->hasRole('consultant') && ! $user->hasRole('admin', 'segreteria', 'contabile')) {
            return redirect()->route('consultant.dashboard');
        }

        // Socio con profilo collegato e stato attivo: dashboard semplificata (solo area personale)
        if ($user->hasRole('socio') && $user->member && ! $user->hasRole('admin', 'segreteria', 'contabile')) {
            $member = $user->member;
            if ($member->stato !== 'attivo') {
                return Inertia::render('Dashboard', [
                    'forSocio' => false,
                    'memberStatusBlocked' => true,
                ]);
            }
            $inRegolaConQuota = $member->isInRegolaConQuota();
            $today = now()->startOfDay();
            $votazioniAperte = Elezione::where('stato', Elezione::STATO_APERTA)
                ->where('data_elezione', '=', $today->toDateString())
                ->orderBy('data_elezione')
                ->get(['id', 'titolo']);

            return Inertia::render('Dashboard', [
                'forSocio' => true,
                'member' => [
                    'id' => $member->id,
                    'full_name' => $member->full_name,
                    'in_regola_con_quota' => $inRegolaConQuota,
                ],
                'votazioniAperte' => $votazioniAperte,
            ]);
        }

        $today = now()->startOfDay();
        $scadenzaEntro = $today->copy()->addDays(90);

        // Organi con mandato in scadenza: scadenza calcolata da ultime elezioni o data costituzione
        $organiInScadenza = Organo::query()
            ->whereNotNull('durata_mesi')
            ->where('durata_mesi', '>', 0)
            ->orderBy('nome')
            ->get()
            ->filter(function (Organo $organo) use ($today, $scadenzaEntro) {
                $s = $organo->mandatoScadenza();
                return $s && $s->gte($today) && $s->lte($scadenzaEntro);
            })
            ->take(10)
            ->values();
        foreach ($organiInScadenza as $o) {
            $o->setAttribute('mandato_da', $o->dataInizioMandato()?->toDateString());
            $o->setAttribute('mandato_scadenza', $o->mandatoScadenza()?->toDateString());
        }
        $organiInScadenza->load(['caricheSociali' => function ($q) {
            $q->orderBy('ordine')->with(['incarichi' => function ($q2) {
                $q2->with('member:id,cognome,nome');
            }]);
        }]);

        $canApproveRefunds = $user->hasRole('admin') || $user->hasRole('contabile');
        $refundsPendingApproval = $canApproveRefunds
            ? ExpenseRefund::where('status', 'richiesta')
                ->orderByDesc('refund_date')
                ->take(10)
                ->get(['id', 'refund_date', 'total', 'member_id'])
                ->load('member:id,cognome,nome')
            : [];

        // Saldi conti: una sola query con withSum (no N+1)
        $contiConSaldo = Conto::attivi()
            ->withSum('movimenti as saldo', 'amount')
            ->ordered()
            ->get(['id', 'name', 'type', 'saldo']);

        $saldiConti = $contiConSaldo->map(fn ($c) => [
            'id'    => $c->id,
            'name'  => $c->name,
            'type'  => $c->type,
            'saldo' => round((float) ($c->saldo ?? 0), 2),
        ])->values();

        $liquiditaTotale = round(
            $contiConSaldo->whereIn('type', ['cassa', 'banca'])->sum(fn ($c) => (float) ($c->saldo ?? 0)),
            2
        );

        $stats = [
            'forSocio' => false,
            'is_cooperativa' => $tenant && $tenant->isCooperativa(),
            'members_count' => Member::nonCessati()->inRegolaConQuota()->count(),
            'payments_this_month' => Incasso::whereMonth('paid_at', now()->month)->whereYear('paid_at', now()->year)->sum('amount'),
            'members_not_in_regola' => Member::nonCessati()->count() - Member::nonCessati()->inRegolaConQuota()->count(),
            'upcoming_events' => Event::where('start_at', '>=', now())->orderBy('start_at')->take(5)->get(['id', 'title', 'start_at']),
            'recent_incassi' => Incasso::with('member:id,cognome,nome')->orderByDesc('paid_at')->take(5)->get(),
            'organiInScadenza' => $organiInScadenza,
            'refunds_pending_approval' => $refundsPendingApproval,
            'saldi_conti' => $saldiConti,
            'liquidita_totale' => $liquiditaTotale,
        ];

        // KPI aggiuntivi per cooperative
        if ($tenant && $tenant->isCooperativa()) {
            // Capitale versato: somma totale_versato di quote non riscattate
            $capitaleVersato = (float) CooperativeShare::where('status', '!=', 'riscattata')
                ->sum('totale_versato');

            // Quote ancora da versare (sottoscritte ma non ancora completamente versate)
            $quoteDaVersare = (float) CooperativeShare::whereNotIn('status', ['versata', 'riscattata'])
                ->selectRaw('SUM(totale_sottoscritto - totale_versato) as diff')
                ->value('diff') ?? 0.0;

            // Saldo totale prestito sociale (libretti attivi)
            $totalePrestito = (float) PrestitoSocialeLibretto::where('status', 'attivo')
                ->sum('saldo_attuale');

            // Ristorni deliberati nell'anno precedente
            $ristornoUltimoAnno = (float) Ristorno::where('anno', now()->year - 1)
                ->sum('importo_totale_deliberato');

            // Soci lavoratori attivi (solo per coop di lavoro/sociale)
            $sociLavoratoriAttivi = Member::where('socio_lavoratore', true)
                ->where('stato', 'attivo')
                ->count();

            $stats['capitale_versato_totale']  = round($capitaleVersato, 2);
            $stats['quote_da_versare']          = round(max(0.0, $quoteDaVersare), 2);
            $stats['totale_prestito_sociale']   = round($totalePrestito, 2);
            $stats['ristorno_ultimo_anno']       = round($ristornoUltimoAnno, 2);
            $stats['soci_lavoratori_attivi']    = $sociLavoratoriAttivi;
            $stats['cooperative_type']          = $tenant->cooperative_type;

            // ── Scadenziario pagamenti (fatture in scadenza) ──────────────
            $fattureInScadenza = FatturaPassiva::query()
                ->where('stato_pagamento', '!=', FatturaPassiva::STATO_ANNULLATA)
                ->whereNull('deleted_at')
                ->with('supplier:id,name,ragione_sociale')
                ->get(['id', 'supplier_id', 'numero_fattura', 'totale_documento', 'data_scadenza', 'stato_pagamento'])
                ->map(function ($f) use ($today) {
                    $dataScadenza = $f->data_scadenza ? $f->data_scadenza->startOfDay() : null;
                    $giorniAllaScadenza = $dataScadenza
                        ? $dataScadenza->diffInDays($today, false) // false = non assoluto (nega se passato)
                        : 999;
                    return [
                        'id'                    => $f->id,
                        'fornitore_nome'        => $f->supplier?->ragione_sociale ?? $f->supplier?->name ?? '—',
                        'numero_fattura'        => $f->numero_fattura,
                        'totale'                => (float) $f->totale_documento,
                        'data_scadenza'         => $f->data_scadenza?->toDateString(),
                        'giorni_alla_scadenza'  => $giorniAllaScadenza,
                    ];
                })
                ->sortBy('data_scadenza')
                ->take(10)
                ->values();

            $stats['fatture_in_scadenza'] = $fattureInScadenza;
        }

        // ── Analytics grafici ─────────────────────────────────────────────
        $stats = array_merge($stats, $this->buildAnalytics($tenant));

        return Inertia::render('Dashboard', $stats);
    }

    private function buildAnalytics($tenant): array
    {
        $from = Carbon::now()->startOfMonth()->subMonths(11);
        $to   = Carbon::now()->endOfMonth();

        // Etichette mesi (12 mesi)
        $labels = [];
        for ($i = 11; $i >= 0; $i--) {
            $labels[] = Carbon::now()->startOfMonth()->subMonths($i)->locale('it')->isoFormat('MMM YY');
        }

        // Grafico 1 — Incassi per mese
        $incassiRaw = Incasso::selectRaw("DATE_FORMAT(paid_at, '%Y-%m') as mese, SUM(amount) as totale")
            ->whereBetween('paid_at', [$from, $to])
            ->groupBy('mese')
            ->pluck('totale', 'mese');

        // Grafico 1 — Uscite per mese (fatture passive non annullate)
        $usciteRaw = FatturaPassiva::selectRaw("DATE_FORMAT(data_fattura, '%Y-%m') as mese, SUM(totale_documento) as totale")
            ->where('stato_pagamento', '!=', FatturaPassiva::STATO_ANNULLATA)
            ->whereBetween('data_fattura', [$from, $to])
            ->groupBy('mese')
            ->pluck('totale', 'mese');

        $incassiMensili = [];
        $usciteMensili  = [];
        for ($i = 11; $i >= 0; $i--) {
            $key = Carbon::now()->startOfMonth()->subMonths($i)->format('Y-m');
            $incassiMensili[] = round((float) ($incassiRaw[$key] ?? 0), 2);
            $usciteMensili[]  = round((float) ($usciteRaw[$key] ?? 0), 2);
        }

        // Grafico 2 — Composizione soci per stato
        $sociPerStato = Member::selectRaw('stato, COUNT(*) as totale')
            ->groupBy('stato')
            ->pluck('totale', 'stato')
            ->toArray();

        // Grafico 5 — Andamento soci: iscrizioni vs cessazioni per mese
        $iscrizioniRaw = Member::selectRaw("DATE_FORMAT(data_iscrizione, '%Y-%m') as mese, COUNT(*) as totale")
            ->whereBetween('data_iscrizione', [$from, $to])
            ->groupBy('mese')
            ->pluck('totale', 'mese');

        $cessazioniRaw = Member::selectRaw("DATE_FORMAT(data_cessazione, '%Y-%m') as mese, COUNT(*) as totale")
            ->whereNotNull('data_cessazione')
            ->whereBetween('data_cessazione', [$from, $to])
            ->groupBy('mese')
            ->pluck('totale', 'mese');

        $iscrizioniMensili  = [];
        $cessazioniMensili  = [];
        for ($i = 11; $i >= 0; $i--) {
            $key = Carbon::now()->startOfMonth()->subMonths($i)->format('Y-m');
            $iscrizioniMensili[]  = (int) ($iscrizioniRaw[$key] ?? 0);
            $cessazioniMensili[]  = (int) ($cessazioniRaw[$key] ?? 0);
        }

        $analytics = [
            'grafico_labels'       => $labels,
            'grafico_incassi'      => $incassiMensili,
            'grafico_uscite'       => $usciteMensili,
            'grafico_soci_stato'   => $sociPerStato,
            'grafico_iscrizioni'   => $iscrizioniMensili,
            'grafico_cessazioni'   => $cessazioniMensili,
        ];

        // Grafico 6 — Distribuzione donazioni (solo non-cooperativa)
        if (! ($tenant && $tenant->isCooperativa())) {
            $donRaw = Incasso::selectRaw("type, SUM(amount) as totale")
                ->whereBetween('paid_at', [$from, $to])
                ->whereIn('type', [Incasso::TYPE_QUOTA, Incasso::TYPE_DONAZIONE, Incasso::TYPE_ALTRO])
                ->groupBy('type')
                ->pluck('totale', 'type');

            $analytics['grafico_donazioni'] = [
                'quote'     => round((float) ($donRaw[Incasso::TYPE_QUOTA] ?? 0), 2),
                'donazioni' => round((float) ($donRaw[Incasso::TYPE_DONAZIONE] ?? 0), 2),
                'altri'     => round((float) ($donRaw[Incasso::TYPE_ALTRO] ?? 0), 2),
            ];
        }

        return $analytics;
    }
}
