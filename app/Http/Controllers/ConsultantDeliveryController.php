<?php

namespace App\Http\Controllers;

use App\Models\ConsultantAssignment;
use App\Models\ConsultantDelivery;
use App\Models\ConsultantDeliveryDocument;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ConsultantDeliveryCreatedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;

/**
 * Consegne del consulente all'ente (Fase 4a).
 *
 * Route pattern: /consultant/entities/{tenantSlug}/deliveries/*
 * (sempre tenant-scoped per allinearsi a Requests e Notes).
 */
class ConsultantDeliveryController extends Controller
{
    /**
     * Risolve tenant + verifica assignment attivo. Lancia 403 se non autorizzato.
     */
    private function resolveTenant(string $tenantSlug): Tenant
    {
        $tenant = Tenant::where('slug', $tenantSlug)->firstOrFail();

        $hasAssignment = ConsultantAssignment::query()
            ->where('consultant_user_id', Auth::id())
            ->where('tenant_id', $tenant->id)
            ->where('active', true)
            ->exists();

        abort_unless($hasAssignment, 403, 'Non sei assegnato come consulente a questo ente.');

        app()->instance('current_tenant', $tenant);

        return $tenant;
    }

    public function index(Request $request, string $tenantSlug)
    {
        $tenant = $this->resolveTenant($tenantSlug);

        $deliveries = ConsultantDelivery::query()
            ->where('tenant_id', $tenant->id)
            ->where('consultant_user_id', Auth::id())
            ->withCount('documenti')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Consultant/Deliveries/Index', [
            'entity' => [
                'id' => $tenant->id, 'name' => $tenant->name, 'slug' => $tenant->slug,
                'organization_type' => $tenant->organization_type,
            ],
            'deliveries' => $deliveries->through(fn ($d) => $this->serialize($d)),
        ]);
    }

    public function create(string $tenantSlug)
    {
        $tenant = $this->resolveTenant($tenantSlug);

        return Inertia::render('Consultant/Deliveries/Create', [
            'entity' => [
                'id' => $tenant->id, 'name' => $tenant->name, 'slug' => $tenant->slug,
            ],
            'tipi' => collect(ConsultantDelivery::TIPI)->map(fn ($t) => [
                'value' => $t,
                'label' => (new ConsultantDelivery(['tipo' => $t]))->tipoLabel(),
            ]),
        ]);
    }

    public function store(Request $request, string $tenantSlug)
    {
        $tenant = $this->resolveTenant($tenantSlug);

        $data = $request->validate([
            'titolo'      => ['required', 'string', 'max:200'],
            'descrizione' => ['nullable', 'string', 'max:5000'],
            'tipo'        => ['required', 'string', 'in:' . implode(',', ConsultantDelivery::TIPI)],
            'files'       => ['nullable', 'array', 'max:10'],
            'files.*'     => ['file', 'max:20480'], // 20MB per file
            'consegna_subito' => ['nullable', 'boolean'],
        ]);

        $delivery = ConsultantDelivery::create([
            'tenant_id'          => $tenant->id,
            'consultant_user_id' => Auth::id(),
            'titolo'             => $data['titolo'],
            'descrizione'        => $data['descrizione'] ?? null,
            'tipo'               => $data['tipo'],
            'stato'              => ConsultantDelivery::STATO_BOZZA,
        ]);

        // Upload allegati nella stessa request (se presenti)
        foreach ($request->file('files', []) as $file) {
            $this->storeFile($delivery, $file);
        }

        // Se richiesto, consegna immediatamente e notifica
        if (! empty($data['consegna_subito'])) {
            $delivery->consegna();
            $this->notifyTenantAdmins($delivery);
        }

        return redirect()->route('consultant.deliveries.show', [$tenantSlug, $delivery->id])
            ->with('flash', ['type' => 'success', 'message' => 'Consegna creata.']);
    }

    public function show(string $tenantSlug, string $deliveryId)
    {
        $tenant = $this->resolveTenant($tenantSlug);

        $delivery = ConsultantDelivery::query()
            ->with(['documenti', 'documenti.uploadedBy:id,name'])
            ->where('id', $deliveryId)
            ->where('tenant_id', $tenant->id)
            ->where('consultant_user_id', Auth::id())
            ->firstOrFail();

        return Inertia::render('Consultant/Deliveries/Show', [
            'entity'   => ['id' => $tenant->id, 'name' => $tenant->name, 'slug' => $tenant->slug],
            'delivery' => $this->serialize($delivery, includeDocs: true),
        ]);
    }

    /**
     * Aggiunta file a una consegna esistente (bozza o consegnata).
     */
    public function uploadFile(Request $request, string $tenantSlug, string $deliveryId)
    {
        $tenant = $this->resolveTenant($tenantSlug);

        $delivery = ConsultantDelivery::query()
            ->where('id', $deliveryId)
            ->where('tenant_id', $tenant->id)
            ->where('consultant_user_id', Auth::id())
            ->firstOrFail();

        $request->validate([
            'file' => ['required', 'file', 'max:20480'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->storeFile($delivery, $request->file('file'), $request->input('note'));

        return back()->with('flash', ['type' => 'success', 'message' => 'File caricato.']);
    }

    /**
     * Transizione bozza → consegnato (rende visibile al tenant + notifica).
     */
    public function consegna(string $tenantSlug, string $deliveryId)
    {
        $tenant = $this->resolveTenant($tenantSlug);

        $delivery = ConsultantDelivery::query()
            ->where('id', $deliveryId)
            ->where('tenant_id', $tenant->id)
            ->where('consultant_user_id', Auth::id())
            ->firstOrFail();

        if (! $delivery->isBozza()) {
            return back()->with('flash', ['type' => 'warning', 'message' => 'Consegna già inviata.']);
        }

        if ($delivery->documenti()->count() === 0) {
            return back()->with('flash', [
                'type' => 'error',
                'message' => 'Carica almeno un file prima di consegnare.',
            ]);
        }

        $delivery->consegna();
        $this->notifyTenantAdmins($delivery);

        return back()->with('flash', ['type' => 'success', 'message' => 'Consegna inviata all\'ente.']);
    }

    /**
     * Download di un singolo file (signed URL TTL 1h).
     */
    public function downloadFile(Request $request, string $tenantSlug, string $deliveryId, int $documentId)
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Link di download scaduto o non valido.');
        }

        $tenant   = Tenant::where('slug', $tenantSlug)->firstOrFail();

        $delivery = ConsultantDelivery::query()
            ->where('id', $deliveryId)
            ->where('tenant_id', $tenant->id)
            ->where('consultant_user_id', Auth::id())
            ->firstOrFail();

        $doc = ConsultantDeliveryDocument::query()
            ->where('id', $documentId)
            ->where('delivery_id', $delivery->id)
            ->firstOrFail();

        return Storage::disk($doc->disk)->download($doc->path, $doc->filename_originale);
    }

    /**
     * Elimina la consegna (solo bozze o consegne contestate).
     */
    public function destroy(string $tenantSlug, string $deliveryId)
    {
        $tenant = $this->resolveTenant($tenantSlug);

        $delivery = ConsultantDelivery::query()
            ->where('id', $deliveryId)
            ->where('tenant_id', $tenant->id)
            ->where('consultant_user_id', Auth::id())
            ->firstOrFail();

        if (! $delivery->isBozza() && ! $delivery->isContestato()) {
            return back()->with('flash', [
                'type' => 'error',
                'message' => 'Puoi eliminare solo bozze o consegne contestate.',
            ]);
        }

        // I documenti vengono auto-eliminati via observer del model
        $delivery->documenti()->get()->each(fn ($d) => $d->delete());
        $delivery->delete();

        return redirect()->route('consultant.deliveries.index', $tenantSlug)
            ->with('flash', ['type' => 'success', 'message' => 'Consegna eliminata.']);
    }

    /* ── Helpers ────────────────────────────────────────────────────────── */

    private function storeFile(ConsultantDelivery $delivery, $file, ?string $note = null): ConsultantDeliveryDocument
    {
        $disk = 'consultant_exchange';
        $dirPath = sprintf('deliveries/%s/%s', $delivery->tenant_id, $delivery->id);
        $path = $file->store($dirPath, $disk);

        return ConsultantDeliveryDocument::create([
            'delivery_id'         => $delivery->id,
            'uploaded_by_user_id' => Auth::id(),
            'filename_originale'  => $file->getClientOriginalName(),
            'disk'                => $disk,
            'path'                => $path,
            'size'                => $file->getSize(),
            'mime_type'           => $file->getMimeType(),
            'note'                => $note,
        ]);
    }

    /**
     * Notifica admin/segreteria del tenant alla consegna.
     */
    private function notifyTenantAdmins(ConsultantDelivery $delivery): void
    {
        $tenantId = $delivery->tenant_id;

        // Cerca tutti gli admin/segreteria del tenant
        $admins = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'segreteria']))
            ->whereHas('tenants', fn ($q) => $q->where('tenants.id', $tenantId))
            ->get();

        foreach ($admins as $admin) {
            $admin->notify(new ConsultantDeliveryCreatedNotification($delivery));
        }
    }

    /**
     * Serializza una delivery per Inertia.
     */
    private function serialize(ConsultantDelivery $d, bool $includeDocs = false): array
    {
        $base = [
            'id'              => $d->id,
            'titolo'          => $d->titolo,
            'descrizione'     => $d->descrizione,
            'tipo'            => $d->tipo,
            'tipo_label'      => $d->tipoLabel(),
            'stato'           => $d->stato,
            'stato_label'     => $d->statoLabel(),
            'stato_badge_color' => $d->statoBadgeColor(),
            'data_consegna'   => $d->data_consegna?->toIso8601String(),
            'data_lettura'    => $d->data_lettura?->toIso8601String(),
            'data_feedback'   => $d->data_feedback?->toIso8601String(),
            'feedback_note'   => $d->feedback_note,
            'created_at'      => $d->created_at?->toIso8601String(),
            'documents_count' => $d->documenti_count ?? $d->documenti()->count(),
            'is_bozza'        => $d->isBozza(),
            'is_consegnato'   => $d->isConsegnato(),
            'is_accettato'    => $d->isAccettato(),
            'is_contestato'   => $d->isContestato(),
        ];

        if ($includeDocs) {
            $base['documenti'] = $d->documenti->map(fn ($doc) => [
                'id'                  => $doc->id,
                'filename_originale'  => $doc->filename_originale,
                'size'                => $doc->size,
                'size_human'          => $doc->sizeHuman(),
                'mime_type'           => $doc->mime_type,
                'note'                => $doc->note,
                'uploaded_by'         => $doc->uploadedBy?->name,
                'uploaded_at'         => $doc->created_at?->toIso8601String(),
                'download_url'        => URL::temporarySignedRoute(
                    'consultant.deliveries.file.download',
                    now()->addHour(),
                    ['tenantSlug' => $d->tenant->slug ?? $d->tenant_id, 'deliveryId' => $d->id, 'documentId' => $doc->id],
                ),
            ])->values();
        }

        return $base;
    }
}
