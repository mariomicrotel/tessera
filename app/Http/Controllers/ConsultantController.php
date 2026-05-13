<?php

namespace App\Http\Controllers;

use App\Models\ConsultantAssignment;
use App\Models\ConsultantNote;
use App\Models\ConsultantRequest;
use App\Models\ConsultantRequestDocument;
use App\Models\Member;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/**
 * Controller area consulente.
 *
 * Tutte le route sono sotto /consultant/* (nessun {tenant} nell'URL).
 * Quando serve accedere ai dati di un tenant specifico, il controller
 * risolve il tenant dal parametro slug e lo injetta manualmente nel container
 * (app()->instance('current_tenant', $tenant)) così che BelongsToTenant funzioni.
 *
 * Il middleware 'role:consultant' (o superadmin) protegge tutto il gruppo.
 */
class ConsultantController extends Controller
{
    /* ── Dashboard ─────────────────────────────────────────────────────── */

    /**
     * Dashboard cross-tenant: lista enti assegnati + richieste aperte pendenti.
     */
    public function dashboard(Request $request)
    {
        $user = $request->user();

        $assignments = ConsultantAssignment::with('tenant')
            ->forConsultant($user)
            ->active()
            ->orderBy('started_at', 'desc')
            ->get();

        // Raccoglie le richieste aperte di TUTTI i tenant assegnati
        $tenantIds = $assignments->pluck('tenant_id')->toArray();

        $richiesteAperte = ConsultantRequest::withoutGlobalScope('tenant')
            ->whereIn('tenant_id', $tenantIds)
            ->where('consultant_user_id', $user->id)
            ->aperte()
            ->orderByRaw("FIELD(priorita, 'urgente','alta','normale','bassa')")
            ->orderBy('data_scadenza')
            ->take(20)
            ->get(['id', 'tenant_id', 'titolo', 'priorita', 'stato', 'data_scadenza']);

        // Mappa tenant_id → nome per le richieste
        $tenantsMap = $assignments->pluck('tenant')->keyBy('id')
            ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'slug' => $t->slug]);

        $entities = $assignments->map(fn ($a) => [
            'id'         => $a->tenant->id,
            'name'       => $a->tenant->name,
            'slug'       => $a->tenant->slug,
            'ruolo'      => $a->ruolo,
            'started_at' => $a->started_at?->toDateString(),
            'richieste_aperte' => $richiesteAperte->where('tenant_id', $a->tenant->id)->count(),
        ]);

        return Inertia::render('Consultant/Dashboard', [
            'entities'         => $entities->values(),
            'richieste_aperte' => $richiesteAperte->map(fn ($r) => [
                'id'           => $r->id,
                'titolo'       => $r->titolo,
                'priorita'     => $r->priorita,
                'stato'        => $r->stato,
                'data_scadenza' => $r->data_scadenza?->toDateString(),
                'tenant'       => $tenantsMap[$r->tenant_id] ?? null,
                'badge_color'  => $r->badgeColor(),
                'priorita_color' => $r->prioritaBadgeColor(),
            ])->values(),
        ]);
    }

    /* ── Enti (lista + dettaglio) ──────────────────────────────────────── */

    /**
     * Lista di tutti gli enti assegnati a questo consulente.
     */
    public function entities(Request $request)
    {
        $user = $request->user();

        $assignments = ConsultantAssignment::with('tenant')
            ->forConsultant($user)
            ->active()
            ->orderBy('started_at', 'desc')
            ->get();

        return Inertia::render('Consultant/Entities/Index', [
            'entities' => $assignments->map(fn ($a) => [
                'id'         => $a->tenant->id,
                'name'       => $a->tenant->name,
                'slug'       => $a->tenant->slug,
                'plan'       => $a->tenant->plan,
                'ruolo'      => $a->ruolo,
                'started_at' => $a->started_at?->toDateString(),
            ])->values(),
        ]);
    }

    /**
     * Pagina di riepilogo di un ente specifico.
     * Risolve il tenant, verifica l'assegnazione, imposta il contesto.
     */
    public function entityShow(Request $request, string $tenantSlug)
    {
        [$user, $tenant] = $this->resolveConsultantTenant($request, $tenantSlug);

        // Richieste aperte per questo ente
        $richieste = ConsultantRequest::aperte()
            ->perConsulente($user->id)
            ->orderByRaw("FIELD(priorita, 'urgente','alta','normale','bassa')")
            ->orderBy('data_scadenza')
            ->take(10)
            ->get(['id', 'titolo', 'priorita', 'stato', 'data_scadenza']);

        // Note fissate
        $noteFissate = ConsultantNote::fissate()
            ->perConsulente($user->id)
            ->orderByDesc('updated_at')
            ->take(5)
            ->get(['id', 'testo', 'visibilita', 'fissata', 'updated_at']);

        // KPI base ente
        $membriAttivi = Member::where('stato', 'attivo')->count();

        return Inertia::render('Consultant/Entities/Show', [
            'entity' => [
                'id'                => $tenant->id,
                'name'              => $tenant->name,
                'slug'              => $tenant->slug,
                'organization_type' => $tenant->organization_type,
                'cooperative_type'  => $tenant->cooperative_type,
                'plan'              => $tenant->plan,
                'active'            => $tenant->active,
            ],
            'richieste'     => $richieste->map(fn ($r) => [
                'id'           => $r->id,
                'titolo'       => $r->titolo,
                'priorita'     => $r->priorita,
                'stato'        => $r->stato,
                'data_scadenza' => $r->data_scadenza?->toDateString(),
                'badge_color'  => $r->badgeColor(),
                'priorita_color' => $r->prioritaBadgeColor(),
            ])->values(),
            'note_fissate'  => $noteFissate->map(fn ($n) => [
                'id'         => $n->id,
                'testo'      => $n->testo,
                'visibilita' => $n->visibilita,
                'fissata'    => $n->fissata,
                'updated_at' => $n->updated_at?->toDateString(),
            ])->values(),
            'kpi' => [
                'membri_attivi' => $membriAttivi,
            ],
        ]);
    }

    /* ── Richieste ─────────────────────────────────────────────────────── */

    public function requestsIndex(Request $request, string $tenantSlug)
    {
        [$user, $tenant] = $this->resolveConsultantTenant($request, $tenantSlug);

        $richieste = ConsultantRequest::perConsulente($user->id)
            ->withCount('documenti')
            ->orderByRaw("FIELD(stato, 'aperta','in_attesa_risposta','risposta_ricevuta','chiusa','annullata')")
            ->orderByRaw("FIELD(priorita, 'urgente','alta','normale','bassa')")
            ->orderBy('data_scadenza')
            ->paginate(25);

        return Inertia::render('Consultant/Requests/Index', [
            'entity'    => ['id' => $tenant->id, 'name' => $tenant->name, 'slug' => $tenant->slug],
            'richieste' => $richieste->through(fn ($r) => [
                'id'              => $r->id,
                'titolo'          => $r->titolo,
                'priorita'        => $r->priorita,
                'stato'           => $r->stato,
                'data_scadenza'   => $r->data_scadenza?->toDateString(),
                'documenti_count' => $r->documenti_count,
                'badge_color'     => $r->badgeColor(),
                'priorita_color'  => $r->prioritaBadgeColor(),
            ]),
        ]);
    }

    public function requestCreate(Request $request, string $tenantSlug)
    {
        [$user, $tenant] = $this->resolveConsultantTenant($request, $tenantSlug);

        return Inertia::render('Consultant/Requests/Create', [
            'entity' => ['id' => $tenant->id, 'name' => $tenant->name, 'slug' => $tenant->slug],
        ]);
    }

    public function requestStore(Request $request, string $tenantSlug)
    {
        [$user, $tenant] = $this->resolveConsultantTenant($request, $tenantSlug);

        $validated = $request->validate([
            'titolo'       => 'required|string|max:255',
            'descrizione'  => 'nullable|string|max:5000',
            'priorita'     => 'required|in:bassa,normale,alta,urgente',
            'data_scadenza' => 'nullable|date|after_or_equal:today',
        ]);

        $richiesta = ConsultantRequest::create([
            ...$validated,
            'consultant_user_id' => $user->id,
            'stato'              => ConsultantRequest::STATO_APERTA,
        ]);

        return redirect()
            ->route('consultant.requests.show', [$tenantSlug, $richiesta->id])
            ->with('flash', ['type' => 'success', 'message' => 'Richiesta creata con successo.']);
    }

    public function requestShow(Request $request, string $tenantSlug, ConsultantRequest $consultantRequest)
    {
        [$user, $tenant] = $this->resolveConsultantTenant($request, $tenantSlug);

        // Verifica che la richiesta appartenga al tenant e al consulente
        abort_unless($consultantRequest->consultant_user_id === $user->id, 403);

        $consultantRequest->load('documenti.uploadedBy:id,name,email');

        return Inertia::render('Consultant/Requests/Show', [
            'entity'    => ['id' => $tenant->id, 'name' => $tenant->name, 'slug' => $tenant->slug],
            'richiesta' => [
                'id'           => $consultantRequest->id,
                'titolo'       => $consultantRequest->titolo,
                'descrizione'  => $consultantRequest->descrizione,
                'priorita'     => $consultantRequest->priorita,
                'stato'        => $consultantRequest->stato,
                'data_scadenza' => $consultantRequest->data_scadenza?->toDateString(),
                'chiusa_il'    => $consultantRequest->chiusa_il?->toDateString(),
                'badge_color'  => $consultantRequest->badgeColor(),
                'priorita_color' => $consultantRequest->prioritaBadgeColor(),
                'documenti'    => $consultantRequest->documenti->map(fn ($d) => [
                    'id'                => $d->id,
                    'filename_originale' => $d->filename_originale,
                    'size_human'        => $d->sizeHuman(),
                    'mime_type'         => $d->mime_type,
                    'note'              => $d->note,
                    'uploaded_by'       => $d->uploadedBy?->name,
                    'created_at'        => $d->created_at->toDateString(),
                ])->values(),
            ],
        ]);
    }

    public function requestUpdate(Request $request, string $tenantSlug, ConsultantRequest $consultantRequest)
    {
        [$user, $tenant] = $this->resolveConsultantTenant($request, $tenantSlug);

        abort_unless($consultantRequest->consultant_user_id === $user->id, 403);

        $validated = $request->validate([
            'stato'        => 'required|in:aperta,in_attesa_risposta,risposta_ricevuta,chiusa,annullata',
            'priorita'     => 'required|in:bassa,normale,alta,urgente',
            'data_scadenza' => 'nullable|date',
            'descrizione'  => 'nullable|string|max:5000',
        ]);

        if (in_array($validated['stato'], [ConsultantRequest::STATO_CHIUSA, ConsultantRequest::STATO_ANNULLATA])) {
            $validated['chiusa_il'] = now();
        } else {
            $validated['chiusa_il'] = null;
        }

        $consultantRequest->update($validated);

        return back()->with('flash', ['type' => 'success', 'message' => 'Richiesta aggiornata.']);
    }

    /* ── Note ──────────────────────────────────────────────────────────── */

    public function notesIndex(Request $request, string $tenantSlug)
    {
        [$user, $tenant] = $this->resolveConsultantTenant($request, $tenantSlug);

        $note = ConsultantNote::perConsulente($user->id)
            ->orderByDesc('fissata')
            ->orderByDesc('updated_at')
            ->get(['id', 'testo', 'visibilita', 'fissata', 'created_at', 'updated_at']);

        return Inertia::render('Consultant/Notes/Index', [
            'entity' => ['id' => $tenant->id, 'name' => $tenant->name, 'slug' => $tenant->slug],
            'note'   => $note->map(fn ($n) => [
                'id'         => $n->id,
                'testo'      => $n->testo,
                'visibilita' => $n->visibilita,
                'fissata'    => $n->fissata,
                'created_at' => $n->created_at->toDateString(),
                'updated_at' => $n->updated_at->toDateString(),
            ])->values(),
        ]);
    }

    public function noteStore(Request $request, string $tenantSlug)
    {
        [$user, $tenant] = $this->resolveConsultantTenant($request, $tenantSlug);

        $validated = $request->validate([
            'testo'      => 'required|string|max:10000',
            'visibilita' => 'required|in:interna,condivisa',
            'fissata'    => 'boolean',
        ]);

        ConsultantNote::create([
            ...$validated,
            'consultant_user_id' => $user->id,
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Nota salvata.']);
    }

    public function noteUpdate(Request $request, string $tenantSlug, ConsultantNote $consultantNote)
    {
        [$user] = $this->resolveConsultantTenant($request, $tenantSlug);

        abort_unless($consultantNote->consultant_user_id === $user->id, 403);

        $validated = $request->validate([
            'testo'      => 'required|string|max:10000',
            'visibilita' => 'required|in:interna,condivisa',
            'fissata'    => 'boolean',
        ]);

        $consultantNote->update($validated);

        return back()->with('flash', ['type' => 'success', 'message' => 'Nota aggiornata.']);
    }

    public function noteDestroy(Request $request, string $tenantSlug, ConsultantNote $consultantNote)
    {
        [$user] = $this->resolveConsultantTenant($request, $tenantSlug);

        abort_unless($consultantNote->consultant_user_id === $user->id, 403);

        $consultantNote->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Nota eliminata.']);
    }

    /* ── Helper privato ─────────────────────────────────────────────────── */

    /**
     * Risolve tenant da slug, verifica che l'utente sia consulente assegnato,
     * e injetta il tenant nel container così che BelongsToTenant global scope
     * filtra correttamente per quel tenant.
     *
     * @return array{0: \App\Models\User, 1: \App\Models\Tenant}
     */
    private function resolveConsultantTenant(Request $request, string $tenantSlug): array
    {
        $user   = $request->user();
        $tenant = Tenant::where('slug', $tenantSlug)->firstOrFail();

        // Verifica assegnazione attiva
        $assigned = ConsultantAssignment::forConsultant($user)
            ->forTenantId($tenant->id)
            ->active()
            ->exists();

        // I superadmin saltano il check di assegnazione
        if (! $assigned && ! $user->is_super_admin) {
            abort(403, 'Non sei assegnato a questo ente come consulente.');
        }

        // Injetta il tenant nel container → BelongsToTenant global scope lo usa
        app()->instance('current_tenant', $tenant);

        return [$user, $tenant];
    }
}
