<?php

namespace App\Http\Controllers;

use App\Models\ConsultantDelivery;
use App\Models\ConsultantDeliveryDocument;
use App\Models\ConsultantRequest;
use App\Models\ConsultantRequestDocument;
use App\Notifications\ConsultantDeliveryFeedbackNotification;
use App\Notifications\ConsultantRequestResponseReceivedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;

/**
 * Inbox del tenant per lo scambio bidirezionale con il consulente (Fase 4a).
 *
 * Lato ente: l'amministratore o la segreteria del tenant vede le richieste
 * documentali del commercialista (da soddisfare) e le consegne (da prendere
 * in carico). Può caricare file in risposta o dare feedback.
 *
 * Route prefix: /app/{tenant}/consulente
 * Middleware: auth + tenant + role:admin|segreteria
 */
class TenantConsultantInboxController extends Controller
{
    /**
     * Lista richieste pendenti dal consulente per il tenant corrente.
     */
    public function requestsIndex()
    {
        $tenant = app('current_tenant');

        $richieste = ConsultantRequest::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('stato', [
                ConsultantRequest::STATO_APERTA,
                ConsultantRequest::STATO_IN_ATTESA_RISPOSTA,
                ConsultantRequest::STATO_RISPOSTA_RICEVUTA,
            ])
            ->with('consultant:id,name,email')
            ->withCount('documenti')
            ->orderByRaw("FIELD(priorita, 'urgente','alta','normale','bassa')")
            ->orderBy('data_scadenza')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Tenant/ConsultantInbox/RequestsIndex', [
            'richieste' => $richieste->through(fn ($r) => [
                'id'             => $r->id,
                'titolo'         => $r->titolo,
                'priorita'       => $r->priorita,
                'priorita_color' => $r->prioritaBadgeColor(),
                'stato'          => $r->stato,
                'badge_color'    => $r->badgeColor(),
                'data_scadenza'  => $r->data_scadenza?->toDateString(),
                'consulente'     => $r->consultant?->name,
                'documenti_count' => $r->documenti_count,
                'created_at'     => $r->created_at?->toDateString(),
            ]),
        ]);
    }

    /**
     * Dettaglio richiesta + form upload risposta.
     */
    public function requestShow(string $tenant, string $requestId)
    {
        $tenant = app('current_tenant');

        $richiesta = ConsultantRequest::query()
            ->where('id', $requestId)
            ->where('tenant_id', $tenant->id)
            ->with('consultant:id,name,email', 'documenti.uploadedBy:id,name')
            ->firstOrFail();

        return Inertia::render('Tenant/ConsultantInbox/RequestsShow', [
            'richiesta' => [
                'id'             => $richiesta->id,
                'titolo'         => $richiesta->titolo,
                'descrizione'    => $richiesta->descrizione,
                'priorita'       => $richiesta->priorita,
                'priorita_color' => $richiesta->prioritaBadgeColor(),
                'stato'          => $richiesta->stato,
                'badge_color'    => $richiesta->badgeColor(),
                'data_scadenza'  => $richiesta->data_scadenza?->toDateString(),
                'chiusa_il'      => $richiesta->chiusa_il?->toDateString(),
                'consulente'     => [
                    'name'  => $richiesta->consultant?->name,
                    'email' => $richiesta->consultant?->email,
                ],
                'documenti' => $richiesta->documenti->map(fn ($d) => [
                    'id'                  => $d->id,
                    'filename_originale'  => $d->filename_originale,
                    'size_human'          => $d->sizeHuman(),
                    'mime_type'           => $d->mime_type,
                    'note'                => $d->note,
                    'uploaded_by'         => $d->uploadedBy?->name,
                    'uploaded_at'         => $d->created_at?->toIso8601String(),
                    'uploaded_as_response' => (bool) $d->uploaded_as_response,
                ])->values(),
            ],
        ]);
    }

    /**
     * Upload di un file in risposta a una richiesta.
     */
    public function requestUploadResponse(Request $request, string $tenant, string $requestId)
    {
        $tenant = app('current_tenant');

        $richiesta = ConsultantRequest::query()
            ->where('id', $requestId)
            ->where('tenant_id', $tenant->id)
            ->with('consultant')
            ->firstOrFail();

        $request->validate([
            'file' => ['required', 'file', 'max:20480'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $disk = 'consultant_exchange';
        $dirPath = sprintf('requests/%s/%s', $tenant->id, $richiesta->id);
        $path = $request->file('file')->store($dirPath, $disk);

        $doc = ConsultantRequestDocument::create([
            'consultant_request_id' => $richiesta->id,
            'tenant_id'             => $tenant->id,
            'uploaded_by_user_id'   => Auth::id(),
            'filename_originale'    => $request->file('file')->getClientOriginalName(),
            'disk'                  => $disk,
            'path'                  => $path,
            'size'                  => $request->file('file')->getSize(),
            'mime_type'             => $request->file('file')->getMimeType(),
            'note'                  => $request->input('note'),
            'uploaded_as_response'  => true,  // flag che distingue dalla request originaria
        ]);

        // Transizione stato: aperta → risposta_ricevuta
        if ($richiesta->stato === ConsultantRequest::STATO_APERTA
            || $richiesta->stato === ConsultantRequest::STATO_IN_ATTESA_RISPOSTA) {
            $richiesta->stato = ConsultantRequest::STATO_RISPOSTA_RICEVUTA;
            $richiesta->save();
        }

        // Notifica il consulente
        if ($richiesta->consultant) {
            $richiesta->consultant->notify(
                new ConsultantRequestResponseReceivedNotification($richiesta, $doc)
            );
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'File caricato. Il consulente è stato notificato.']);
    }

    /* ── Delivery (consegne dal consulente all'ente) ───────────────────── */

    public function deliveriesIndex()
    {
        $tenant = app('current_tenant');

        $deliveries = ConsultantDelivery::query()
            ->where('tenant_id', $tenant->id)
            ->visibiliAlTenant() // niente bozze
            ->with('consultant:id,name,email')
            ->withCount('documenti')
            ->orderByDesc('data_consegna')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Tenant/ConsultantInbox/DeliveriesIndex', [
            'deliveries' => $deliveries->through(fn ($d) => [
                'id'              => $d->id,
                'titolo'          => $d->titolo,
                'tipo'            => $d->tipo,
                'tipo_label'      => $d->tipoLabel(),
                'stato'           => $d->stato,
                'stato_label'     => $d->statoLabel(),
                'stato_badge_color' => $d->statoBadgeColor(),
                'consulente'      => $d->consultant?->name,
                'documenti_count' => $d->documenti_count,
                'data_consegna'   => $d->data_consegna?->toIso8601String(),
                'data_lettura'    => $d->data_lettura?->toIso8601String(),
                'is_unread'       => $d->isConsegnato(),  // letto già?
            ]),
        ]);
    }

    public function deliveriesShow(string $tenant, string $deliveryId)
    {
        $tenant = app('current_tenant');

        $delivery = ConsultantDelivery::query()
            ->where('id', $deliveryId)
            ->where('tenant_id', $tenant->id)
            ->visibiliAlTenant()
            ->with(['consultant:id,name,email', 'documenti.uploadedBy:id,name'])
            ->firstOrFail();

        // Marca come letta alla prima apertura
        $delivery->markAsRead();

        return Inertia::render('Tenant/ConsultantInbox/DeliveriesShow', [
            'delivery' => [
                'id'              => $delivery->id,
                'titolo'          => $delivery->titolo,
                'descrizione'     => $delivery->descrizione,
                'tipo'            => $delivery->tipo,
                'tipo_label'      => $delivery->tipoLabel(),
                'stato'           => $delivery->stato,
                'stato_label'     => $delivery->statoLabel(),
                'stato_badge_color' => $delivery->statoBadgeColor(),
                'data_consegna'   => $delivery->data_consegna?->toIso8601String(),
                'data_lettura'    => $delivery->data_lettura?->toIso8601String(),
                'data_feedback'   => $delivery->data_feedback?->toIso8601String(),
                'feedback_note'   => $delivery->feedback_note,
                'consulente'      => [
                    'name'  => $delivery->consultant?->name,
                    'email' => $delivery->consultant?->email,
                ],
                'documenti' => $delivery->documenti->map(fn ($doc) => [
                    'id'                 => $doc->id,
                    'filename_originale' => $doc->filename_originale,
                    'size_human'         => $doc->sizeHuman(),
                    'mime_type'          => $doc->mime_type,
                    'note'               => $doc->note,
                    'uploaded_by'        => $doc->uploadedBy?->name,
                    'uploaded_at'        => $doc->created_at?->toIso8601String(),
                    'download_url'       => URL::temporarySignedRoute(
                        'tenant.consultant-inbox.deliveries.download',
                        now()->addHour(),
                        ['tenant' => $tenant->slug, 'deliveryId' => $delivery->id, 'documentId' => $doc->id],
                    ),
                ])->values(),
                'can_accept' => $delivery->isLetto() || $delivery->isConsegnato(),
                'can_contest' => $delivery->isLetto() || $delivery->isConsegnato(),
            ],
        ]);
    }

    public function deliveryAccept(Request $request, string $tenant, string $deliveryId)
    {
        $tenant   = app('current_tenant');

        $delivery = ConsultantDelivery::query()
            ->where('id', $deliveryId)
            ->where('tenant_id', $tenant->id)
            ->with('consultant')
            ->firstOrFail();

        $note = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ])['note'] ?? null;

        $delivery->accetta($note);

        if ($delivery->consultant) {
            $delivery->consultant->notify(new ConsultantDeliveryFeedbackNotification($delivery));
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Consegna accettata.']);
    }

    public function deliveryContest(Request $request, string $tenant, string $deliveryId)
    {
        $tenant   = app('current_tenant');

        $delivery = ConsultantDelivery::query()
            ->where('id', $deliveryId)
            ->where('tenant_id', $tenant->id)
            ->with('consultant')
            ->firstOrFail();

        $note = $request->validate([
            'note' => ['required', 'string', 'max:500'],
        ])['note'];

        $delivery->contesta($note);

        if ($delivery->consultant) {
            $delivery->consultant->notify(new ConsultantDeliveryFeedbackNotification($delivery));
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Consegna contestata. Il consulente è stato notificato.']);
    }

    public function deliveryDownload(Request $request, string $tenant, string $deliveryId, int $documentId)
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Link di download scaduto o non valido.');
        }

        $currentTenant = app('current_tenant');

        $delivery = ConsultantDelivery::query()
            ->where('id', $deliveryId)
            ->where('tenant_id', $currentTenant->id)
            ->visibiliAlTenant()
            ->firstOrFail();

        $doc = ConsultantDeliveryDocument::query()
            ->where('id', $documentId)
            ->where('delivery_id', $delivery->id)
            ->firstOrFail();

        return Storage::disk($doc->disk)->download($doc->path, $doc->filename_originale);
    }
}
