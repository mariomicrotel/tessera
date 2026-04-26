<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Visualizzazione e export del registro audit trail.
 * Accessibile solo agli utenti con ruolo admin.
 */
class AuditController extends Controller
{
    // ── Index ─────────────────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $tenant = app('current_tenant');

        $logs = AuditLog::forTenant($tenant->id)
            ->with('user:id,name,email')
            ->when($request->string('action')->isNotEmpty(),
                fn ($q) => $q->forAction($request->string('action')))
            ->when($request->string('entity_type')->isNotEmpty(),
                fn ($q) => $q->forEntity('App\\Models\\' . $request->string('entity_type')))
            ->when($request->string('user_email')->isNotEmpty(),
                fn ($q) => $q->where('user_email', 'like', '%' . $request->string('user_email') . '%'))
            ->when($request->string('from')->isNotEmpty(),
                fn ($q) => $q->whereDate('created_at', '>=', $request->string('from')))
            ->when($request->string('to')->isNotEmpty(),
                fn ($q) => $q->whereDate('created_at', '<=', $request->string('to')))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('Admin/Audit/Index', [
            'logs'         => $logs,
            'filters'      => $request->only('action', 'entity_type', 'user_email', 'from', 'to'),
            'actionLabels' => [
                AuditLog::ACTION_CREATED  => 'Creato',
                AuditLog::ACTION_UPDATED  => 'Modificato',
                AuditLog::ACTION_DELETED  => 'Eliminato',
                AuditLog::ACTION_RESTORED => 'Ripristinato',
            ],
            'entityTypes' => $this->entityTypes($tenant->id),
        ]);
    }

    // ── Show ─────────────────────────────────────────────────────────────────��

    public function show(AuditLog $auditLog): Response
    {
        // Verifica tenant isolation
        abort_unless($auditLog->tenant_id === app('current_tenant')->id, 403);

        $auditLog->load('user:id,name,email');

        return Inertia::render('Admin/Audit/Show', [
            'log'  => $auditLog,
            'diff' => $auditLog->diff(),
        ]);
    }

    // ── Per entità ────────────────────────────────────────────────────────────

    public function forEntity(Request $request, string $type, int $id): Response
    {
        $tenant = app('current_tenant');

        $entityClass = 'App\\Models\\' . $type;

        $logs = AuditLog::forTenant($tenant->id)
            ->forEntity($entityClass, $id)
            ->with('user:id,name,email')
            ->latest()
            ->get();

        return Inertia::render('Admin/Audit/Entity', [
            'logs'       => $logs,
            'entityType' => $type,
            'entityId'   => $id,
        ]);
    }

    // ── Export CSV ────────────────────────────────────────────────────────────

    public function export(Request $request): StreamedResponse
    {
        $tenant = app('current_tenant');

        $logs = AuditLog::forTenant($tenant->id)
            ->when($request->string('action')->isNotEmpty(),
                fn ($q) => $q->forAction($request->string('action')))
            ->when($request->string('from')->isNotEmpty(),
                fn ($q) => $q->whereDate('created_at', '>=', $request->string('from')))
            ->when($request->string('to')->isNotEmpty(),
                fn ($q) => $q->whereDate('created_at', '<=', $request->string('to')))
            ->with('user:id,name,email')
            ->latest()
            ->get();

        $filename = 'audit-' . $tenant->slug . '-' . now()->format('Ymd-Hi') . '.csv';

        return response()->streamDownload(function () use ($logs) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8

            fputcsv($handle, ['Data', 'Utente', 'Azione', 'Entità', 'ID', 'IP'], ';');

            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log->created_at->format('d/m/Y H:i:s'),
                    $log->user_email ?? '(sistema)',
                    $log->action,
                    $log->entityLabel(),
                    $log->entity_id,
                    $log->ip_address,
                ], ';');
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ── Helper privato ────────────────────────────────────────────────────────

    private function entityTypes(int $tenantId): array
    {
        return AuditLog::forTenant($tenantId)
            ->selectRaw('DISTINCT entity_type')
            ->pluck('entity_type')
            ->map(fn ($t) => class_basename($t))
            ->sort()
            ->values()
            ->toArray();
    }
}
