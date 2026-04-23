<?php

namespace App\Http\Controllers;

use App\Models\EmailTemplate;
use App\Models\Member;
use App\Models\MemberInvite;
use App\Models\MemberType;
use App\Models\Settings;
use App\Services\PlaceholderResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;

class MemberInviteController extends Controller
{
    /**
     * Invio invito per domanda di ammissione (solo admin/segreteria).
     */
    public function create()
    {
        $this->authorize('create', Member::class);

        MemberInvite::runCleanupIfDue();

        return Inertia::render('Members/Invite');
    }

    /**
     * Invia email con link per compilare la domanda di ammissione.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Member::class);

        $request->validate([
            'email' => 'required|email|max:255',
        ]);

        $email = $request->input('email');

        $invite = MemberInvite::create([
            'email' => $email,
            'token' => MemberInvite::generateUniqueToken(),
            'expires_at' => now()->addDays((int) config('member-invite.expiry_days', 7)),
            'invited_by' => $request->user()->id,
        ]);

        $link = URL::route('members.admission-request.form', ['token' => $invite->token], true);
        $expiryDays = (int) config('member-invite.expiry_days', 7);
        $appName = Settings::get('nome_associazione', config('app.name'));

        try {
            $rendered = EmailTemplate::render('invito_ammissione', [
                'link' => $link,
                'expiry_days' => (string) $expiryDays,
                'appName' => $appName,
                'year' => (string) now()->year,
            ]);

            if ($rendered) {
                Mail::html($rendered['body'], function ($message) use ($email, $rendered) {
                    $message->to($email)->subject($rendered['subject']);
                });
            } else {
                Mail::raw(
                    "Buongiorno,\n\n"
                    . "Sei stato/a invitato/a a presentare domanda di ammissione come socio presso {$appName}.\n\n"
                    . "Clicca sul link qui sotto per compilare il modulo (il link è personale e a uso singolo):\n\n"
                    . "{$link}\n\n"
                    . "Il link è valido per {$expiryDays} giorni. Non condividerlo con altri.\n\n"
                    . "Cordiali saluti,\n{$appName}",
                    function ($message) use ($email, $appName) {
                        $message->to($email)
                            ->subject("[{$appName}] Invito a presentare domanda di ammissione");
                    }
                );
            }
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('members.invites.create')
                ->with('flash', ['type' => 'error', 'message' => 'Errore nell\'invio dell\'email: ' . $e->getMessage()])
                ->withInput();
        }

        return redirect()->route('members.index')
            ->with('flash', ['type' => 'success', 'message' => "Invito inviato a {$email}. Il destinatario riceverà il link per compilare la domanda di ammissione."]);
    }

    /**
     * Mostra il form pubblico per la domanda di ammissione (link dall'email).
     */
    public function showAdmissionRequestForm(string $token)
    {
        MemberInvite::runCleanupIfDue();

        $invite = MemberInvite::where('token', $token)->first();

        if (! $invite || ! $invite->isValid()) {
            return Inertia::render('Members/AdmissionRequestForm', [
                'token' => $token,
                'invalid' => true,
                'appName' => Settings::get('nome_associazione', config('app.name')),
            ]);
        }

        $privacyRaw = Settings::get('informativa_privacy_domanda_ammissione', '');
        return Inertia::render('Members/AdmissionRequestForm', [
            'token' => $token,
            'email' => $invite->email,
            'memberTypes' => MemberType::orderBy('name')->get(['id', 'name', 'display_name']),
            'appName' => Settings::get('nome_associazione', config('app.name')),
            'invalid' => false,
            'privacy_informativa' => PlaceholderResolver::resolve($privacyRaw, []),
        ]);
    }

    /**
     * Salva la domanda di ammissione inviata dal form pubblico.
     */
    public function storeAdmissionRequest(Request $request, string $token)
    {
        $invite = MemberInvite::where('token', $token)->first();

        if (! $invite || ! $invite->isValid()) {
            return redirect()->route('members.admission-request.form', ['token' => $token])
                ->withErrors(['token' => 'Link non valido o scaduto.']);
        }

        $rules = [
            'member_type_id' => 'required|exists:member_types,id',
            'nome' => 'required|string|max:255',
            'cognome' => 'required|string|max:255',
            'email' => 'required|email|max:255|in:' . $invite->email,
            'codice_fiscale' => 'nullable|string|max:64',
            'data_nascita' => 'nullable|date',
            'indirizzo' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:50',
            'note' => 'nullable|string',
            'privacy_accepted' => 'required|accepted',
        ];
        $request->validate($rules);

        $numeroTessera = (int) Member::max('numero_tessera') + 1;
        Member::create([
            'member_type_id' => $request->member_type_id,
            'numero_tessera' => $numeroTessera,
            'nome' => $request->nome,
            'cognome' => $request->cognome,
            'email' => $request->email,
            'codice_fiscale' => $request->filled('codice_fiscale') ? $request->codice_fiscale : null,
            'data_nascita' => $request->filled('data_nascita') ? $request->data_nascita : null,
            'stato' => 'aspirante',
            'domanda_presentata_at' => now(),
            'data_iscrizione' => null,
            'indirizzo' => $request->input('indirizzo'),
            'telefono' => $request->input('telefono'),
            'note' => $request->input('note'),
        ]);

        $invite->markAsUsed();

        return Inertia::render('Members/AdmissionRequestForm', [
            'token' => $token,
            'submitted' => true,
            'appName' => Settings::get('nome_associazione', config('app.name')),
        ]);
    }
}
