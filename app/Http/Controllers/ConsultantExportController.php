<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateExportBundleJob;
use App\Models\ConsultantAssignment;
use App\Models\ConsultantExportBundle;
use App\Models\Tenant;
use App\Services\Consultant\Export\ExportFormatRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Gestione export bundle per il consulente.
 *
 * Tutte le route filtrano per `consultant_user_id = Auth::id()` per garantire
 * che un consulente possa vedere SOLO i propri bundle, anche se altri consulenti
 * del tenant ne hanno generati di simili.
 */
class ConsultantExportController extends Controller
{
    public function __construct(private ExportFormatRegistry $registry) {}

    /**
     * Lista bundle del consulente loggato (cross-tenant, su tutti gli enti assegnati).
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = ConsultantExportBundle::query()
            ->with('tenant:id,name,slug')
            ->where('consultant_user_id', $user->id);

        // Filtri opzionali
        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->input('tenant_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $bundles = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        // Tenants su cui ha bundle (per il filtro UI)
        $tenantsWithBundles = ConsultantExportBundle::query()
            ->select('tenant_id')
            ->where('consultant_user_id', $user->id)
            ->distinct()
            ->with('tenant:id,name,slug')
            ->get()
            ->map(fn ($b) => $b->tenant)
            ->filter()
            ->unique('id')
            ->values();

        return Inertia::render('Consultant/Exports/Index', [
            'bundles' => $bundles->through(fn ($b) => [
                'id'              => $b->id,
                'tenant'          => $b->tenant ? ['id' => $b->tenant->id, 'name' => $b->tenant->name, 'slug' => $b->tenant->slug] : null,
                'period_from'     => $b->period_from?->toDateString(),
                'period_to'       => $b->period_to?->toDateString(),
                'period_label'    => $b->periodLabel(),
                'formats'         => $b->formats,
                'data_types'      => $b->data_types,
                'status'          => $b->status,
                'file_size_human' => $b->fileSizeHuman(),
                'download_count'  => $b->download_count,
                'error_message'   => $b->error_message,
                'created_at'      => $b->created_at?->toIso8601String(),
                'completed_at'    => $b->completed_at?->toIso8601String(),
                'expires_at'      => $b->expires_at?->toIso8601String(),
                'is_downloadable' => $b->isReady() && ! $b->expires_at?->isPast(),
            ]),
            'filters' => [
                'tenant_id' => $request->input('tenant_id'),
                'status'    => $request->input('status'),
            ],
            'tenants_with_bundles' => $tenantsWithBundles->map(fn ($t) => [
                'id' => $t->id, 'name' => $t->name, 'slug' => $t->slug,
            ]),
        ]);
    }

    /**
     * Form per nuovo export — tenant pre-selezionato via querystring `?tenant=slug`.
     */
    public function create(Request $request)
    {
        $user = Auth::user();

        // Lista tenant a cui il consulente è assegnato (attivo)
        $assignedTenants = ConsultantAssignment::query()
            ->where('consultant_user_id', $user->id)
            ->where('active', true)
            ->with('tenant:id,name,slug,organization_type')
            ->get()
            ->map(fn ($a) => $a->tenant)
            ->filter()
            ->unique('id')
            ->values()
            ->map(fn ($t) => [
                'id'                => $t->id,
                'name'              => $t->name,
                'slug'              => $t->slug,
                'organization_type' => $t->organization_type,
            ]);

        return Inertia::render('Consultant/Exports/Create', [
            'tenants'      => $assignedTenants,
            'preselected_tenant_slug' => $request->input('tenant'),
            'formats'      => $this->registry->formatsForUi(),
            'data_sources' => $this->registry->dataSourcesForUi(),
        ]);
    }

    /**
     * Crea il bundle e dispatch del job.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'tenant_id'      => ['required', 'uuid', 'exists:tenants,id'],
            'period_from'    => ['required', 'date'],
            'period_to'      => ['required', 'date', 'after_or_equal:period_from'],
            'formats'        => ['required', 'array', 'min:1'],
            'formats.*'      => ['string'],
            'data_types'     => ['array'],  // condizionalmente required: vedi sotto
            'data_types.*'   => ['string'],
        ]);

        // Se almeno un formato selezionato richiede DataSource, data_types deve avere almeno 1 elemento
        $allFormats        = $this->registry->formats();
        $selectedFormatObj = array_filter(array_map(fn ($k) => $allFormats[$k] ?? null, $data['formats']));
        $anyRequires       = false;
        foreach ($selectedFormatObj as $f) {
            if ($f->requiresDataSources()) {
                $anyRequires = true;
                break;
            }
        }
        if ($anyRequires && empty($data['data_types'] ?? [])) {
            return back()->withErrors([
                'data_types' => 'Seleziona almeno una tabella per i formati che la richiedono.',
            ])->withInput();
        }

        // Verifica assignment attivo
        $hasAssignment = ConsultantAssignment::query()
            ->where('consultant_user_id', $user->id)
            ->where('tenant_id', $data['tenant_id'])
            ->where('active', true)
            ->exists();

        if (! $hasAssignment) {
            abort(403, 'Non sei assegnato come consulente a questo ente.');
        }

        // Sanitize: tieni solo i formati e DataSource che esistono nel registry
        $validFormats    = array_keys($this->registry->formats());
        $validDataTypes  = array_keys($this->registry->dataSources());
        $formats         = array_values(array_intersect($data['formats'], $validFormats));
        $dataTypes       = array_values(array_intersect($data['data_types'] ?? [], $validDataTypes));

        if (empty($formats)) {
            return back()->withErrors(['formats' => 'Selezione formati non valida.'])->withInput();
        }
        if ($anyRequires && empty($dataTypes)) {
            return back()->withErrors(['data_types' => 'Tabelle selezionate non valide.'])->withInput();
        }

        $bundle = ConsultantExportBundle::create([
            'tenant_id'          => $data['tenant_id'],
            'consultant_user_id' => $user->id,
            'period_from'        => $data['period_from'],
            'period_to'          => $data['period_to'],
            'formats'            => $formats,
            'data_types'         => $dataTypes,
            'status'             => ConsultantExportBundle::STATUS_PENDING,
            'expires_at'         => now()->addDays(ConsultantExportBundle::DEFAULT_TTL_DAYS),
        ]);

        // Dispatch in coda
        GenerateExportBundleJob::dispatch($bundle->id);

        return redirect()->route('consultant.exports.show', $bundle->id)
            ->with('flash', ['type' => 'success', 'message' => 'Export richiesto. Riceverai una mail quando sarà pronto.']);
    }

    /**
     * Dettaglio bundle + polling status.
     */
    public function show(string $bundleId)
    {
        $bundle = ConsultantExportBundle::query()
            ->with('tenant:id,name,slug')
            ->where('id', $bundleId)
            ->where('consultant_user_id', Auth::id())
            ->firstOrFail();

        // Signed URL per il download (1h)
        $downloadUrl = null;
        if ($bundle->isReady() && ! $bundle->expires_at?->isPast()) {
            $downloadUrl = URL::temporarySignedRoute(
                'consultant.exports.download',
                now()->addHour(),
                ['bundle' => $bundle->id],
            );
        }

        return Inertia::render('Consultant/Exports/Show', [
            'bundle' => [
                'id'              => $bundle->id,
                'tenant'          => $bundle->tenant ? ['id' => $bundle->tenant->id, 'name' => $bundle->tenant->name, 'slug' => $bundle->tenant->slug] : null,
                'period_from'     => $bundle->period_from?->toDateString(),
                'period_to'       => $bundle->period_to?->toDateString(),
                'period_label'    => $bundle->periodLabel(),
                'formats'         => $bundle->formats,
                'data_types'      => $bundle->data_types,
                'status'          => $bundle->status,
                'file_size_human' => $bundle->fileSizeHuman(),
                'download_count'  => $bundle->download_count,
                'error_message'   => $bundle->error_message,
                'started_at'      => $bundle->started_at?->toIso8601String(),
                'completed_at'    => $bundle->completed_at?->toIso8601String(),
                'expires_at'      => $bundle->expires_at?->toIso8601String(),
                'is_downloadable' => $bundle->isReady() && ! $bundle->expires_at?->isPast(),
                'download_url'    => $downloadUrl,
            ],
        ]);
    }

    /**
     * Download del file ZIP (route firmata, TTL 1h).
     * Lo signed URL garantisce che solo chi ha cliccato il link dalla pagina
     * Show o dalla mail possa scaricare (no enumeration tramite bundle_id).
     */
    public function download(Request $request, string $bundle)
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Link di download scaduto o non valido.');
        }

        $bundleModel = ConsultantExportBundle::query()
            ->where('id', $bundle)
            ->where('consultant_user_id', Auth::id())
            ->firstOrFail();

        if (! $bundleModel->isDownloadable()) {
            abort(410, 'Bundle non più disponibile.');
        }

        // Incrementa download count
        $bundleModel->increment('download_count');

        $disk = Storage::disk(ConsultantExportBundle::storageDisk());

        // Streaming response (no in-memory buffer)
        return new StreamedResponse(function () use ($disk, $bundleModel) {
            $stream = $disk->readStream($bundleModel->file_path);
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type'        => 'application/zip',
            'Content-Length'      => $bundleModel->file_size_bytes ?? '',
            'Content-Disposition' => sprintf(
                'attachment; filename="export-%s-%s.zip"',
                $bundleModel->tenant?->slug ?? 'bundle',
                now()->format('Y-m-d'),
            ),
        ]);
    }

    /**
     * Cancel cooperativo: marca il bundle come `cancelled`.
     * Il job in coda controlla lo stato e si arresta gracefully.
     */
    public function cancel(string $bundleId)
    {
        $bundle = ConsultantExportBundle::query()
            ->where('id', $bundleId)
            ->where('consultant_user_id', Auth::id())
            ->firstOrFail();

        if ($bundle->isTerminal()) {
            return back()->with('flash', [
                'type' => 'warning',
                'message' => 'Il bundle è già in stato finale, non posso cancellarlo.',
            ]);
        }

        $bundle->status       = ConsultantExportBundle::STATUS_CANCELLED;
        $bundle->completed_at = now();
        $bundle->save();

        return back()->with('flash', ['type' => 'success', 'message' => 'Export cancellato.']);
    }

    /**
     * Elimina manualmente un bundle (file + record).
     * Permesso solo se in stato terminale (ready/failed/cancelled/expired).
     */
    public function destroy(string $bundleId)
    {
        $bundle = ConsultantExportBundle::query()
            ->where('id', $bundleId)
            ->where('consultant_user_id', Auth::id())
            ->firstOrFail();

        if (! $bundle->isTerminal()) {
            return back()->with('flash', [
                'type' => 'error',
                'message' => 'Non puoi eliminare un export in corso. Annullalo prima.',
            ]);
        }

        if ($bundle->file_path) {
            Storage::disk(ConsultantExportBundle::storageDisk())->delete($bundle->file_path);
        }
        $bundle->delete();

        return redirect()->route('consultant.exports.index')
            ->with('flash', ['type' => 'success', 'message' => 'Export eliminato.']);
    }
}
