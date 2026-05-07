<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMemberRequest;
use App\Http\Requests\UpdateMemberRequest;
use App\Models\Conto;
use App\Models\EmailTemplate;
use App\Models\Member;
use App\Models\MemberType;
use App\Models\Role;
use App\Models\Tessera;
use App\Models\Settings;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * CRUD anagrafica soci, volontari e collaboratori.
 */
class MemberController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Member::class);

        $query = Member::query()->with(['memberType']);

        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($qry) use ($q) {
                $qry->where('nome', 'like', "%{$q}%")
                    ->orWhere('cognome', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('codice_fiscale', 'like', "%{$q}%");
            });
        }
        if ($request->filled('stato')) {
            $query->where('stato', $request->stato);
        }
        if ($request->filled('member_type_id')) {
            $query->where('member_type_id', $request->member_type_id);
        }

        $today = now()->startOfDay();
        if ($request->filled('in_regola')) {
            if ($request->in_regola === '1') {
                $query->inRegolaConQuota();
            } elseif ($request->in_regola === '0') {
                $query->nonInRegolaConQuota();
            }
        }

        $members = $query->orderBy('cognome')->orderBy('nome')->paginate(15)->withQueryString();
        $memberTypes = MemberType::orderBy('name')->get();

        $inRegolaIds = Member::query()
            ->whereIn('id', $members->pluck('id'))
            ->inRegolaConQuota()
            ->pluck('id')
            ->flip();

        $members->getCollection()->transform(function ($member) use ($inRegolaIds) {
            $member->in_regola_con_quota = $inRegolaIds->has($member->id);
            return $member;
        });

        return Inertia::render('Members/Index', [
            'members' => $members,
            'memberTypes' => $memberTypes,
            'filters' => $request->only('search', 'stato', 'member_type_id', 'in_regola'),
        ]);
    }

    public function create()
    {
        $this->authorize('create', Member::class);
        $nextNumeroTessera = (int) Member::max('numero_tessera') + 1;
        return Inertia::render('Members/Create', [
            'memberTypes' => MemberType::orderBy('name')->get(),
            'nextNumeroTessera' => $nextNumeroTessera,
        ]);
    }

    public function store(StoreMemberRequest $request)
    {
        $data = $request->validated();
        if (empty($data['numero_tessera'])) {
            $data['numero_tessera'] = (int) Member::max('numero_tessera') + 1;
        }
        if ($request->boolean('presentare_domanda')) {
            $data['stato'] = 'aspirante';
            $data['domanda_presentata_at'] = now();
            $data['data_iscrizione'] = null;
        } else {
            $data['stato'] = $data['stato'] ?? 'attivo';
            if (empty($data['data_iscrizione'])) {
                $data['data_iscrizione'] = now();
            }
        }
        Member::create($data);
        $message = $request->boolean('presentare_domanda')
            ? 'Domanda di ammissione registrata.'
            : 'Socio creato.';
        return redirect()->route('members.index')->with('flash', ['type' => 'success', 'message' => $message]);
    }

    public function show(Member $member)
    {
        // Tenant isolation: route model binding bypasses global scope, so we verify explicitly
        $currentTenant = app('current_tenant');
        if ($member->tenant_id !== $currentTenant->id) {
            abort(404);
        }

        $this->authorize('view', $member);
        $member->load([
            'memberType',
            'user.roles',
            'incassi' => fn ($q) => $q->orderByDesc('paid_at')->with('conto'),
            'incarichi.caricaSociale.organo',
        ]);
        $member->in_regola_con_quota = $member->isInRegolaConQuota();
        $caricheSociali = \App\Models\CaricaSociale::with('organo')->orderBy('ordine')->orderBy('nome')->get(['id', 'organo_id', 'nome', 'ordine']);
        $roles = Role::orderBy('name')->get(['id', 'name', 'display_name']);
        $tessere = Tessera::where('member_id', $member->id)->orderByDesc('anno')->get();

        return Inertia::render('Members/Show', [
            'member' => $member,
            'caricheSociali' => $caricheSociali,
            'roles' => $roles,
            'tessere' => $tessere,
        ]);
    }

    public function edit(Member $member)
    {
        $this->authorize('update', $member);
        return Inertia::render('Members/Edit', [
            'member' => $member,
            'memberTypes' => MemberType::orderBy('name')->get(),
        ]);
    }

    public function update(UpdateMemberRequest $request, Member $member)
    {
        $member->update($request->validated());
        return redirect()->route('members.show', $member)->with('flash', ['type' => 'success', 'message' => 'Aggiornato.']);
    }

    /**
     * Attiva l'accesso area soci: crea o collega un User, assegna ruolo socio, invia email per reimpostare password.
     */
    public function enableMemberAccess(Member $member)
    {
        $this->authorize('update', $member);

        if ($member->user_id) {
            return $this->memberShowWithAccessError($member, 'Accesso area soci già attivo.');
        }

        if ($member->stato !== 'attivo') {
            return $this->memberShowWithAccessError($member, 'L\'accesso area soci può essere attivato solo per soci con stato Attivo.');
        }

        if (empty($member->email)) {
            return $this->memberShowWithAccessError($member, 'Il socio deve avere un indirizzo email per attivare l\'accesso.');
        }

        $socioRole = Role::where('name', 'socio')->first();
        if (! $socioRole) {
            return $this->memberShowWithAccessError($member, 'Ruolo "socio" non trovato. Esegui i seed del database.');
        }

        try {
            $user = User::where('email', $member->email)->first();

            if ($user) {
                if (! $user->roles()->where('name', 'socio')->exists()) {
                    $user->roles()->attach($socioRole);
                }
                $member->update(['user_id' => $user->id]);
                Password::sendResetLink(['email' => $user->email]);

                return $this->memberShowWithAccessSuccess($member->fresh(), 'Accesso attivato (utente esistente). È stata inviata un\'email per reimpostare la password.');
            }

            $user = User::create([
                'name' => trim($member->nome . ' ' . $member->cognome) ?: $member->email,
                'email' => $member->email,
                'password' => bcrypt(Str::random(12)),
            ]);
            $user->roles()->attach($socioRole);
            $member->update(['user_id' => $user->id]);
            Password::sendResetLink(['email' => $user->email]);

            return $this->memberShowWithAccessSuccess($member->fresh(), 'Accesso attivato. È stata inviata un\'email al socio per reimpostare la password e accedere.');
        } catch (\Throwable $e) {
            report($e);

            return $this->memberShowWithAccessError($member, 'Errore durante l\'attivazione: ' . $e->getMessage());
        }
    }

    /**
     * Revoca l'accesso area soci: scollega il User dal Member e rimuove il ruolo socio.
     */
    public function revokeMemberAccess(Member $member)
    {
        $this->authorize('update', $member);

        if (! $member->user_id) {
            return $this->memberShowWithAccessError($member, 'Il socio non ha accesso attivo.');
        }

        if ($member->user_id === request()->user()->id) {
            return $this->memberShowWithAccessError($member, 'Non puoi revocare il tuo stesso accesso.');
        }

        $user = $member->user;
        $member->update(['user_id' => null]);
        if ($user) {
            $socioRole = Role::where('name', 'socio')->first();
            if ($socioRole) {
                $user->roles()->detach($socioRole->id);
            }
        }

        return $this->memberShowWithAccessSuccess($member->fresh(), 'Accesso revocato.');
    }

    /**
     * Aggiorna i ruoli dell'utente collegato al socio. Solo admin.
     */
    public function updateMemberUserRoles(Request $request, Member $member)
    {
        if (! $request->user()->hasRole('admin')) {
            abort(403, 'Solo gli amministratori possono assegnare i ruoli utente.');
        }

        if (! $member->user_id) {
            return redirect()->route('members.show', $member)->with('flash', ['type' => 'error', 'message' => 'Il socio non ha un account collegato.']);
        }

        $validIds = Role::pluck('id')->toArray();
        $request->validate([
            'role_ids' => 'nullable|array',
            'role_ids.*' => 'integer|in:'.implode(',', $validIds),
        ]);

        $member->user->roles()->sync($request->input('role_ids', []));

        return redirect()->route('members.show', $member)->with('flash', ['type' => 'success', 'message' => 'Ruoli aggiornati.']);
    }

    /**
     * Restituisce la pagina Show del member con messaggio di successo nella sezione Accesso area soci.
     */
    private function memberShowWithAccessSuccess(Member $member, string $message): \Symfony\Component\HttpFoundation\Response
    {
        $member->load([
            'memberType',
            'user.roles',
            'incassi' => fn ($q) => $q->orderByDesc('paid_at')->with('conto'),
            'incarichi.caricaSociale.organo',
        ]);
        $member->in_regola_con_quota = $member->isInRegolaConQuota();
        $caricheSociali = \App\Models\CaricaSociale::with('organo')->orderBy('ordine')->orderBy('nome')->get(['id', 'organo_id', 'nome', 'ordine']);
        $roles = Role::orderBy('name')->get(['id', 'name', 'display_name']);
        $tessere = Tessera::where('member_id', $member->id)->orderByDesc('anno')->get();

        return Inertia::render('Members/Show', [
            'member' => $member,
            'caricheSociali' => $caricheSociali,
            'roles' => $roles,
            'tessere' => $tessere,
            'accessSuccess' => $message,
        ])->toResponse(request());
    }

    /**
     * Restituisce la pagina Show del member con messaggio di errore nella sezione Accesso area soci.
     */
    private function memberShowWithAccessError(Member $member, string $message): \Symfony\Component\HttpFoundation\Response
    {
        $member->load([
            'memberType',
            'user.roles',
            'incassi' => fn ($q) => $q->orderByDesc('paid_at')->with('conto'),
            'incarichi.caricaSociale.organo',
        ]);
        $member->in_regola_con_quota = $member->isInRegolaConQuota();
        $caricheSociali = \App\Models\CaricaSociale::with('organo')->orderBy('ordine')->orderBy('nome')->get(['id', 'organo_id', 'nome', 'ordine']);
        $roles = Role::orderBy('name')->get(['id', 'name', 'display_name']);
        $tessere = Tessera::where('member_id', $member->id)->orderByDesc('anno')->get();

        return Inertia::render('Members/Show', [
            'member' => $member,
            'caricheSociali' => $caricheSociali,
            'roles' => $roles,
            'tessere' => $tessere,
            'accessError' => $message,
        ])->toResponse(request())->setStatusCode(422);
    }

    public function destroy(Member $member)
    {
        $this->authorize('delete', $member);
        $member->delete();
        return redirect()->route('members.index')->with('flash', ['type' => 'success', 'message' => 'Socio eliminato.']);
    }

    // --- Parte B: azioni ammissione (solo admin/segreteria) ---

    /**
     * Approva un socio aspirante: update stato/date e opzionalmente invio email notifica.
     *
     * @param  bool  $sendEmail  se inviare l'email di notifica approvazione
     * @return array{email_sent: bool}
     */
    private function approveOneMember(Member $member, bool $sendEmail = true): array
    {
        $member->update([
            'ammissione_esito' => 'accolta',
            'ammissione_decisa_at' => now(),
            'data_iscrizione' => now(),
            'stato' => 'attivo',
        ]);

        $emailSent = false;
        if ($sendEmail && ! empty(trim((string) $member->email))) {
            $quota = (float) Settings::get('quota_annuale', 0);
            $quotaImporto = number_format($quota, 2, ',', '.');
            $contoBanca = Conto::where('type', 'banca')->where('attivo', true)->ordered()->first();
            $iban = $contoBanca && ! empty(trim((string) $contoBanca->iban)) ? trim($contoBanca->iban) : '';
            $replacements = [
                'appName' => Settings::get('nome_associazione', config('app.name')),
                'member_name' => $member->full_name,
                'quota_importo' => $quotaImporto,
                'iban' => $iban,
                'year' => (string) now()->year,
            ];
            $rendered = EmailTemplate::render('notifica_approvazione_socio', $replacements);
            if ($rendered) {
                try {
                    Mail::html($rendered['body'], function ($message) use ($member, $rendered) {
                        $message->to($member->email)->subject($rendered['subject']);
                    });
                    $emailSent = true;
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        return ['email_sent' => $emailSent];
    }

    public function acceptAdmission(Request $request, Member $member)
    {
        $this->authorize('update', $member);
        if ($member->stato !== 'aspirante') {
            return redirect()->route('members.show', $member)->with('flash', ['type' => 'error', 'message' => 'Solo un aspirante può essere ammesso.']);
        }
        $sendEmail = $request->boolean('invia_email', true);
        $result = $this->approveOneMember($member, $sendEmail);
        $flashMessage = 'Domanda accolta. Socio iscritto nel libro soci.';
        if ($result['email_sent']) {
            $flashMessage .= ' È stata inviata un\'email al socio con l\'invito a saldare la quota.';
        }

        return redirect()->route('members.show', $member)->with('flash', ['type' => 'success', 'message' => $flashMessage]);
    }

    public function acceptAdmissionBulk(Request $request)
    {
        $this->authorize('viewAny', Member::class);

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:members,id',
        ]);

        $members = Member::whereIn('id', $request->ids)->where('stato', 'aspirante')->get();
        $approved = 0;
        $emailsSent = 0;

        $sendEmail = $request->boolean('invia_email', true);
        foreach ($members as $member) {
            try {
                $this->authorize('update', $member);
            } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
                continue;
            }
            $result = $this->approveOneMember($member, $sendEmail);
            $approved++;
            if ($result['email_sent']) {
                $emailsSent++;
            }
        }

        if ($approved === 0) {
            $message = count($request->ids) > 0
                ? 'Nessuno dei selezionati è un aspirante approvabile.'
                : 'Nessun socio selezionato.';
            return redirect()->route('members.index', $request->only('search', 'stato', 'member_type_id', 'in_regola'))
                ->with('flash', ['type' => 'info', 'message' => $message]);
        }

        $message = $approved === 1
            ? '1 domanda accolta.'
            : "{$approved} domande accolte.";
        if ($emailsSent > 0) {
            $message .= $emailsSent === 1 ? ' 1 email inviata.' : " {$emailsSent} email inviate.";
        }

        return redirect()->route('members.index', $request->only('search', 'stato', 'member_type_id', 'in_regola'))
            ->with('flash', ['type' => 'success', 'message' => $message]);
    }

    public function rejectAdmission(Request $request, Member $member)
    {
        $this->authorize('update', $member);
        if ($member->stato !== 'aspirante') {
            return redirect()->route('members.show', $member)->with('flash', ['type' => 'error', 'message' => 'Operazione non applicabile.']);
        }
        $request->validate(['rigetto_motivo' => 'required|string|max:2000']);
        $member->update([
            'ammissione_esito' => 'rigettata',
            'ammissione_decisa_at' => now(),
            'rigetto_motivo' => $request->rigetto_motivo,
            'stato' => 'rigettato',
        ]);
        return redirect()->route('members.show', $member)->with('flash', ['type' => 'success', 'message' => 'Domanda rigettata. Comunicare per iscritto entro 60 giorni.']);
    }

    public function communicateRejection(Member $member)
    {
        $this->authorize('update', $member);
        if ($member->stato !== 'rigettato' || ! $member->ammissione_decisa_at) {
            return redirect()->route('members.show', $member)->with('flash', ['type' => 'error', 'message' => 'Operazione non applicabile.']);
        }
        $member->update(['rigetto_comunicato_at' => now()]);
        return redirect()->route('members.show', $member)->with('flash', ['type' => 'success', 'message' => 'Rigetto comunicato per iscritto. L\'aspirante può presentare ricorso entro 60 giorni.']);
    }

    public function registerAppeal(Member $member)
    {
        $this->authorize('update', $member);
        if ($member->stato !== 'rigettato') {
            return redirect()->route('members.show', $member)->with('flash', ['type' => 'error', 'message' => 'Operazione non applicabile.']);
        }
        $member->update([
            'ricorso_presentato_at' => now(),
            'stato' => 'in_ricorso',
        ]);
        return redirect()->route('members.show', $member)->with('flash', ['type' => 'success', 'message' => 'Ricorso registrato. Esaminare in assemblea.']);
    }

    public function assemblyOutcome(Request $request, Member $member)
    {
        $this->authorize('update', $member);
        if ($member->stato !== 'in_ricorso') {
            return redirect()->route('members.show', $member)->with('flash', ['type' => 'error', 'message' => 'Operazione non applicabile.']);
        }
        $request->validate(['esito' => 'required|in:accolto,rigettato']);
        if ($request->esito === 'accolto') {
            $member->update([
                'data_iscrizione' => now(),
                'stato' => 'attivo',
                'assemblea_esame_data' => now(),
            ]);
            return redirect()->route('members.show', $member)->with('flash', ['type' => 'success', 'message' => 'Ricorso accolto. Socio iscritto.']);
        }
        $member->update([
            'stato' => 'rigettato',
            'assemblea_esame_data' => now(),
        ]);
        return redirect()->route('members.show', $member)->with('flash', ['type' => 'success', 'message' => 'Ricorso rigettato dall\'assemblea.']);
    }

    // --- Parte C: azioni perdita qualità socio ---

    /**
     * Revoca e elimina l'account utente collegato al socio (alla perdita qualità).
     */
    private function revokeAndDeleteUserForMember(Member $member): void
    {
        $user = $member->user;
        if (! $user) {
            return;
        }
        $member->update(['user_id' => null]);
        $user->deleteProfilePhoto();
        $user->tokens->each->delete();
        $user->delete();
    }

    public function registerDeath(Request $request, Member $member)
    {
        $this->authorize('update', $member);
        $request->validate(['deceduto_at' => 'required|date']);
        $member->update([
            'deceduto_at' => $request->deceduto_at,
            'data_cessazione' => $request->deceduto_at,
            'cessazione_causa' => 'decesso',
            'stato' => 'decesso',
        ]);
        $this->revokeAndDeleteUserForMember($member);
        return redirect()->route('members.show', $member)->with('flash', ['type' => 'success', 'message' => 'Decesso registrato.']);
    }

    public function registerMorosita(Member $member)
    {
        $this->authorize('update', $member);
        $member->update([
            'data_cessazione' => now(),
            'cessazione_causa' => 'morosita',
            'stato' => 'moroso',
        ]);
        $this->revokeAndDeleteUserForMember($member);
        return redirect()->route('members.show', $member)->with('flash', ['type' => 'success', 'message' => 'Perdita qualità per morosità registrata.']);
    }

    public function registerDimissioni(Request $request, Member $member)
    {
        $this->authorize('update', $member);
        $request->validate(['dimissioni_presentate_at' => 'required|date']);
        $member->update([
            'dimissioni_presentate_at' => $request->dimissioni_presentate_at,
            'data_cessazione' => $request->dimissioni_presentate_at,
            'cessazione_causa' => 'dimissioni',
            'stato' => 'dimesso',
        ]);
        $this->revokeAndDeleteUserForMember($member);
        return redirect()->route('members.show', $member)->with('flash', ['type' => 'success', 'message' => 'Dimissioni registrate. Resta l\'obbligo quota anno in corso.']);
    }

    public function registerEsclusione(Request $request, Member $member)
    {
        $this->authorize('update', $member);
        $request->validate([
            'motivo_esclusione' => 'required|string|max:2000',
            'data_cessazione' => 'required|date',
        ]);
        $member->update([
            'motivo_esclusione' => $request->motivo_esclusione,
            'data_cessazione' => $request->data_cessazione,
            'cessazione_causa' => 'esclusione',
            'stato' => 'escluso',
        ]);
        $this->revokeAndDeleteUserForMember($member);
        return redirect()->route('members.show', $member)->with('flash', ['type' => 'success', 'message' => 'Esclusione registrata.']);
    }

    /** Libro soci: elenco soci ammessi (con data_iscrizione) non cessati. */
    public function libroSoci(Request $request)
    {
        $this->authorize('viewAny', Member::class);

        $tenant        = app()->bound('current_tenant') ? app('current_tenant') : null;
        $isCooperativa = $tenant?->isCooperativa() ?? false;

        $query = Member::query()
            ->whereNotNull('data_iscrizione')
            ->nonCessati()
            ->with('memberType')
            ->orderBy('data_iscrizione')
            ->orderBy('cognome')
            ->orderBy('nome');

        // Filtro tipo socio (solo cooperative)
        if ($isCooperativa && $request->filled('tipo_socio')) {
            match ($request->tipo_socio) {
                'lavoratori' => $query->where('socio_lavoratore', true),
                'sovventori' => $query->where('socio_sovventore', true),
                'onorari'    => $query->where('socio_onorario', true),
                default      => null,
            };
        }

        $members = $query->paginate(20)->withQueryString();

        $data = [
            'members' => $members,
            'filters' => $request->only('tipo_socio'),
        ];

        // Dati aggiuntivi per cooperative
        if ($isCooperativa) {
            $data['totale_quote'] = Member::query()
                ->whereNotNull('data_iscrizione')
                ->nonCessati()
                ->sum('numero_quote_capitale');
        }

        return Inertia::render('Members/LibroSoci', $data);
    }

    /** Export CSV libro soci per cooperative (normativa). */
    public function exportLibroSociCoop(Request $request)
    {
        $this->authorize('viewAny', Member::class);

        $tenant = app()->bound('current_tenant') ? app('current_tenant') : null;
        if (! $tenant?->isCooperativa()) {
            abort(403, 'Export disponibile solo per cooperative.');
        }

        $members = Member::query()
            ->whereNotNull('data_iscrizione')
            ->nonCessati()
            ->orderBy('data_iscrizione')
            ->orderBy('cognome')
            ->orderBy('nome')
            ->get();

        $filename = 'libro-soci-' . Str::slug($tenant->name ?? 'cooperativa') . '-' . now()->format('Ymd') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($members) {
            $file = fopen('php://output', 'w');
            // BOM UTF-8 per compatibilità Excel
            fputs($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'N. progressivo',
                'Tipo persona',
                'Cognome / Ragione sociale',
                'Nome',
                'Codice fiscale / P.IVA',
                'Data iscrizione',
                'Data ammissione CDA',
                'N. quote capitale',
                'Stato',
                'Socio lavoratore',
                'Socio sovventore',
                'Socio onorario',
            ], ';');

            foreach ($members as $i => $m) {
                $isGiuridica = $m->tipo_persona === 'giuridica';
                fputcsv($file, [
                    $i + 1,
                    $isGiuridica ? 'Giuridica' : 'Fisica',
                    $isGiuridica ? ($m->ragione_sociale ?? '') : ($m->cognome ?? ''),
                    $isGiuridica ? (trim(($m->referente_nome ?? '') . ' ' . ($m->referente_cognome ?? ''))) : ($m->nome ?? ''),
                    $isGiuridica ? ($m->partita_iva ?? '') : ($m->codice_fiscale ?? ''),
                    $m->data_iscrizione?->format('d/m/Y') ?? '',
                    $m->data_ammissione_cda?->format('d/m/Y') ?? '',
                    $m->numero_quote_capitale ?? 0,
                    $m->stato ?? '',
                    $m->socio_lavoratore ? 'Sì' : 'No',
                    $m->socio_sovventore  ? 'Sì' : 'No',
                    $m->socio_onorario    ? 'Sì' : 'No',
                ], ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ──────────────────────────────────────────────────────────────────────
    // TESSERE PDF
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Stampa la tessera PDF di un singolo socio.
     */
    public function tesseraPdf(Member $member): Response
    {
        $currentTenant = app('current_tenant');
        if ($member->tenant_id !== $currentTenant->id) {
            abort(404);
        }
        $this->authorize('view', $member);
        $member->load('memberType');

        return $this->buildTesserePdf([$member]);
    }

    /**
     * Stampa le tessere PDF in blocco.
     * Accetta `ids[]` (soci selezionati) o tutti gli attivi se nessun ID passato.
     */
    public function tessereMultiplePdf(Request $request): Response
    {
        $this->authorize('viewAny', Member::class);

        $query = Member::query()->with('memberType');

        if ($request->filled('ids')) {
            $query->whereIn('id', (array) $request->ids);
        } else {
            $query->where('stato', 'attivo');
        }

        $members = $query->orderBy('cognome')->orderBy('nome')->get();

        if ($members->isEmpty()) {
            abort(422, 'Nessun socio trovato.');
        }

        return $this->buildTesserePdf($members->all());
    }

    private function buildTesserePdf(array $members): Response
    {
        $nomeOrg = Settings::get('nome_associazione', config('app.name'));
        $colore  = Settings::get('tessera_colore', '#1e40af');
        $anno    = now()->year;

        $data = array_map(fn (Member $m) => [
            'cognome'        => $m->cognome ?? '',
            'nome'           => $m->nome    ?? '',
            'ragione_sociale' => $m->ragione_sociale ?? null,
            'numero_tessera' => $m->numero_tessera,
            'tipo'           => $m->memberType?->display_name ?? $m->memberType?->name ?? 'Socio',
            'data_iscrizione' => $m->data_iscrizione,
            'codice_fiscale' => $m->codice_fiscale ?? null,
        ], $members);

        $pdf = Pdf::loadView('pdf.tessera', [
            'members'            => $data,
            'nomeOrganizzazione' => $nomeOrg,
            'colore'             => $colore,
            'anno'               => $anno,
        ])->setPaper('A4', 'portrait');

        $filename = count($members) === 1
            ? 'tessera_' . str_replace(' ', '_', strtolower($members[0]->cognome ?? 'socio')) . '.pdf'
            : 'tessere_' . $anno . '_' . now()->format('Ymd') . '.pdf';

        return $pdf->download($filename);
    }
}
