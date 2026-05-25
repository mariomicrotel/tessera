<?php

namespace App\Http\Controllers;

use App\Models\AdempimentoItem;
use App\Models\AdempimentoTemplate;
use App\Models\ConsultantAssignment;
use App\Models\Tenant;
use App\Services\Consultant\AdempimentoGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Checklist adempimenti (Fase 4b) — lato consulente.
 *
 * Tre viste principali:
 *  - dashboard cross-tenant: KPI di compliance su tutti gli enti
 *  - lista per ente: tutte le scadenze del singolo ente per anno
 *  - aggiornamento singolo item (stato, note, data invio)
 */
class ConsultantAdempimentoController extends Controller
{
    public function __construct(private AdempimentoGeneratorService $generator) {}

    private function resolveTenant(string $tenantSlug): Tenant
    {
        $tenant = Tenant::where('slug', $tenantSlug)->firstOrFail();

        abort_unless(
            ConsultantAssignment::query()
                ->where('consultant_user_id', Auth::id())
                ->where('tenant_id', $tenant->id)
                ->where('active', true)
                ->exists(),
            403,
        );

        return $tenant;
    }

    /**
     * Cross-tenant: KPI di compliance su tutti gli enti del consulente.
     */
    public function dashboard(Request $request)
    {
        $userId = Auth::id();
        $anno   = (int) $request->input('anno', now()->year);

        // Enti assegnati al consulente
        $tenants = Tenant::query()
            ->whereIn('id', function ($q) use ($userId) {
                $q->from('consultant_assignments')
                    ->select('tenant_id')
                    ->where('consultant_user_id', $userId)
                    ->where('active', true);
            })
            ->get(['id', 'name', 'slug', 'organization_type']);

        $tenantIds = $tenants->pluck('id');

        // Aggregato per stato di ogni tenant (anno corrente)
        $statsPerTenant = AdempimentoItem::query()
            ->whereIn('tenant_id', $tenantIds)
            ->where('anno', $anno)
            ->selectRaw('
                tenant_id,
                COUNT(*) as totali,
                SUM(CASE WHEN stato = "da_fare" THEN 1 ELSE 0 END) as da_fare,
                SUM(CASE WHEN stato = "in_lavorazione" THEN 1 ELSE 0 END) as in_lavorazione,
                SUM(CASE WHEN stato = "consegnato" THEN 1 ELSE 0 END) as consegnato,
                SUM(CASE WHEN stato = "completato" THEN 1 ELSE 0 END) as completato,
                SUM(CASE WHEN stato = "non_applicabile" THEN 1 ELSE 0 END) as non_applicabile,
                SUM(CASE WHEN data_scadenza < CURDATE() AND stato IN ("da_fare","in_lavorazione") THEN 1 ELSE 0 END) as scaduti,
                SUM(CASE WHEN data_scadenza BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY) AND stato IN ("da_fare","in_lavorazione") THEN 1 ELSE 0 END) as in_scadenza
            ')
            ->groupBy('tenant_id')
            ->get()
            ->keyBy('tenant_id');

        // In scadenza nelle prossime 2 settimane (cross-tenant)
        $inScadenza = AdempimentoItem::query()
            ->whereIn('tenant_id', $tenantIds)
            ->where('anno', $anno)
            ->whereIn('stato', [AdempimentoItem::STATO_DA_FARE, AdempimentoItem::STATO_IN_LAVORAZIONE])
            ->whereBetween('data_scadenza', [now()->toDateString(), now()->addDays(14)->toDateString()])
            ->with('template:id,codice,nome,priorita', 'tenant:id,name,slug')
            ->orderBy('data_scadenza')
            ->take(50)
            ->get();

        return Inertia::render('Consultant/Adempimenti/Dashboard', [
            'anno' => $anno,
            'anni_disponibili' => [$anno - 1, $anno, $anno + 1],
            'tenants' => $tenants->map(function ($t) use ($statsPerTenant) {
                $s = $statsPerTenant->get($t->id);
                $totali = (int) ($s->totali ?? 0);
                $completati = (int) ($s->completato ?? 0) + (int) ($s->non_applicabile ?? 0);
                return [
                    'id'              => $t->id,
                    'name'            => $t->name,
                    'slug'            => $t->slug,
                    'organization_type' => $t->organization_type,
                    'totali'          => $totali,
                    'da_fare'         => (int) ($s->da_fare ?? 0),
                    'in_lavorazione'  => (int) ($s->in_lavorazione ?? 0),
                    'consegnato'      => (int) ($s->consegnato ?? 0),
                    'completati'      => $completati,
                    'scaduti'         => (int) ($s->scaduti ?? 0),
                    'in_scadenza'     => (int) ($s->in_scadenza ?? 0),
                    'compliance_pct'  => $totali > 0 ? round(($completati / $totali) * 100, 1) : 0,
                ];
            }),
            'in_scadenza_imminenti' => $inScadenza->map(fn ($it) => [
                'id'              => $it->id,
                'tenant'          => ['id' => $it->tenant?->id, 'name' => $it->tenant?->name, 'slug' => $it->tenant?->slug],
                'template_codice' => $it->template?->codice,
                'template_nome'   => $it->template?->nome,
                'priorita'        => $it->template?->priorita,
                'periodo'         => $it->periodo,
                'data_scadenza'   => $it->data_scadenza?->toDateString(),
                'giorni_alla_scadenza' => $it->giorniAllaScadenza(),
                'stato'           => $it->stato,
            ]),
        ]);
    }

    /**
     * Lista adempimenti del singolo ente per anno.
     */
    public function index(Request $request, string $tenantSlug)
    {
        $tenant = $this->resolveTenant($tenantSlug);
        $anno   = (int) $request->input('anno', now()->year);

        $items = AdempimentoItem::query()
            ->where('tenant_id', $tenant->id)
            ->where('anno', $anno)
            ->with('template')
            ->orderBy('data_scadenza')
            ->get();

        $byCategoria = $items->groupBy(fn ($i) => $i->template->categoria);

        // Statistiche
        $totali     = $items->count();
        $completati = $items->whereIn('stato', [
            AdempimentoItem::STATO_COMPLETATO, AdempimentoItem::STATO_NON_APPLICABILE,
        ])->count();
        $scaduti = $items->filter(fn ($i) => $i->isScaduto())->count();

        return Inertia::render('Consultant/Adempimenti/Index', [
            'entity' => [
                'id' => $tenant->id, 'name' => $tenant->name, 'slug' => $tenant->slug,
                'organization_type' => $tenant->organization_type,
            ],
            'anno' => $anno,
            'anni_disponibili' => [$anno - 1, $anno, $anno + 1],
            'stats' => [
                'totali'         => $totali,
                'completati'     => $completati,
                'scaduti'        => $scaduti,
                'compliance_pct' => $totali > 0 ? round(($completati / $totali) * 100, 1) : 0,
            ],
            'items_per_categoria' => $byCategoria->map(function ($items, $cat) {
                return [
                    'categoria' => $cat,
                    'items'     => $items->map(fn ($it) => [
                        'id'             => $it->id,
                        'codice'         => $it->template->codice,
                        'nome'           => $it->template->nome,
                        'descrizione'    => $it->template->descrizione,
                        'riferimento'    => $it->template->riferimento_normativo,
                        'priorita'       => $it->template->priorita,
                        'periodo'        => $it->periodo,
                        'data_scadenza'  => $it->data_scadenza?->toDateString(),
                        'giorni_alla_scadenza' => $it->giorniAllaScadenza(),
                        'stato'          => $it->stato,
                        'stato_label'    => $it->statoLabel(),
                        'stato_badge_color' => $it->statoBadgeColor(),
                        'is_scaduto'     => $it->isScaduto(),
                        'data_completamento' => $it->data_completamento?->toIso8601String(),
                        'data_invio_telematico' => $it->data_invio_telematico?->toDateString(),
                        'protocollo_invio' => $it->protocollo_invio,
                        'note'           => $it->note,
                        'documenti_richiesti' => $it->template->documenti_richiesti,
                    ])->values(),
                ];
            })->values(),
        ]);
    }

    /**
     * Genera/aggiorna gli adempimenti per il tenant nell'anno.
     */
    public function generate(Request $request, string $tenantSlug)
    {
        $tenant = $this->resolveTenant($tenantSlug);
        $anno   = (int) $request->input('anno', now()->year);

        $stats = $this->generator->generaPerAnno($tenant, $anno);

        return back()->with('flash', [
            'type'    => 'success',
            'message' => sprintf(
                'Generati %d nuovi adempimenti (skippati %d già presenti) per l\'anno %d.',
                $stats['created'], $stats['skipped'], $anno,
            ),
        ]);
    }

    /**
     * Aggiorna singolo item.
     */
    public function update(Request $request, string $tenantSlug, int $itemId)
    {
        $tenant = $this->resolveTenant($tenantSlug);

        $item = AdempimentoItem::query()
            ->where('id', $itemId)
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        $data = $request->validate([
            'stato'                 => ['required', 'in:' . implode(',', AdempimentoItem::STATI)],
            'note'                  => ['nullable', 'string', 'max:2000'],
            'data_invio_telematico' => ['nullable', 'date'],
            'protocollo_invio'      => ['nullable', 'string', 'max:100'],
        ]);

        // Auto-set data_completamento quando si raggiunge stato terminale
        if (in_array($data['stato'], [
            AdempimentoItem::STATO_COMPLETATO,
            AdempimentoItem::STATO_NON_APPLICABILE,
        ], true)) {
            $data['data_completamento'] = now();
        } else {
            $data['data_completamento'] = null;
        }

        $item->update($data);

        return back()->with('flash', ['type' => 'success', 'message' => 'Adempimento aggiornato.']);
    }
}
