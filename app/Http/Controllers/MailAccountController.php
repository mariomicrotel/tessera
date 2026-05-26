<?php

namespace App\Http\Controllers;

use App\Models\MailAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;
use Webklex\IMAP\Facades\Client;

class MailAccountController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin');
    }

    public function index()
    {
        return redirect()->route('settings.index', ['tab' => 'posta']);
    }

    public function create()
    {
        return Inertia::render('Mail/Accounts/Form', [
            'account' => null,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:100',
            'email'            => 'required|email|max:255',
            'imap_host'        => 'required|string|max:255',
            'imap_port'        => 'required|integer|min:1|max:65535',
            'imap_encryption'  => 'required|in:ssl,tls,starttls,none',
            'imap_username'    => 'required|string|max:255',
            'imap_password'    => 'required|string|max:500',
            'imap_folder'      => 'required|string|max:100',
            'sync_days'        => 'integer|min:1|max:365',
            'is_active'        => 'boolean',
            // SMTP opzionale
            'smtp_host'        => 'nullable|string|max:255',
            'smtp_port'        => 'nullable|integer|min:1|max:65535',
            'smtp_encryption'  => 'nullable|in:ssl,tls,starttls,none',
            'smtp_username'    => 'nullable|string|max:255',
            'smtp_password'    => 'nullable|string|max:500',
            'smtp_from_name'   => 'nullable|string|max:150',
            'smtp_from_email'  => 'nullable|email|max:255',
        ]);

        MailAccount::create($validated);

        return redirect()->route('mail.accounts.index')
            ->with('success', 'Casella email aggiunta.');
    }

    public function edit(MailAccount $mailAccount)
    {
        return Inertia::render('Mail/Accounts/Form', [
            'account' => [
                'id'               => $mailAccount->id,
                'name'             => $mailAccount->name,
                'email'            => $mailAccount->email,
                'imap_host'        => $mailAccount->imap_host,
                'imap_port'        => $mailAccount->imap_port,
                'imap_encryption'  => $mailAccount->imap_encryption,
                'imap_username'    => $mailAccount->imap_username,
                'imap_password'    => '', // non esporre
                'imap_folder'      => $mailAccount->imap_folder,
                'sync_days'        => $mailAccount->sync_days,
                'is_active'        => $mailAccount->is_active,
                // SMTP
                'smtp_host'        => $mailAccount->smtp_host,
                'smtp_port'        => $mailAccount->smtp_port,
                'smtp_encryption'  => $mailAccount->smtp_encryption,
                'smtp_username'    => $mailAccount->smtp_username,
                'smtp_password'    => '', // non esporre
                'smtp_from_name'   => $mailAccount->smtp_from_name,
                'smtp_from_email'  => $mailAccount->smtp_from_email,
            ],
        ]);
    }

    public function update(Request $request, MailAccount $mailAccount)
    {
        $rules = [
            'name'             => 'required|string|max:100',
            'email'            => 'required|email|max:255',
            'imap_host'        => 'required|string|max:255',
            'imap_port'        => 'required|integer|min:1|max:65535',
            'imap_encryption'  => 'required|in:ssl,tls,starttls,none',
            'imap_username'    => 'required|string|max:255',
            'imap_folder'      => 'required|string|max:100',
            'sync_days'        => 'integer|min:1|max:365',
            'is_active'        => 'boolean',
            // SMTP opzionale
            'smtp_host'        => 'nullable|string|max:255',
            'smtp_port'        => 'nullable|integer|min:1|max:65535',
            'smtp_encryption'  => 'nullable|in:ssl,tls,starttls,none',
            'smtp_username'    => 'nullable|string|max:255',
            'smtp_from_name'   => 'nullable|string|max:150',
            'smtp_from_email'  => 'nullable|email|max:255',
        ];

        // Le password sono opzionali nell'update (vuote = conserva la precedente)
        if ($request->filled('imap_password')) {
            $rules['imap_password'] = 'string|max:500';
        }
        if ($request->filled('smtp_password')) {
            $rules['smtp_password'] = 'string|max:500';
        }

        $validated = $request->validate($rules);

        if (empty($validated['imap_password'])) {
            unset($validated['imap_password']);
        }
        if (empty($validated['smtp_password'])) {
            unset($validated['smtp_password']);
        }

        $mailAccount->update($validated);

        return redirect()->route('mail.accounts.index')
            ->with('success', 'Casella email aggiornata.');
    }

    public function destroy(MailAccount $mailAccount)
    {
        $mailAccount->messages()->delete();
        $mailAccount->delete();

        return redirect()->route('mail.accounts.index')
            ->with('success', 'Casella eliminata.');
    }

    /** Testa la connessione SMTP senza salvare */
    public function testSmtp(Request $request)
    {
        $request->validate([
            'smtp_host'       => 'required|string',
            'smtp_port'       => 'required|integer',
            'smtp_encryption' => 'required|string',
            'smtp_username'   => 'required|string',
            'smtp_password'   => 'required|string',
            'smtp_from_email' => 'required|email',
        ]);

        try {
            $enc = $request->smtp_encryption === 'none' ? null : $request->smtp_encryption;

            $transport = \Symfony\Component\Mailer\Transport::fromDsn(
                ($enc === 'ssl' ? 'smtps' : 'smtp') . '://'
                . urlencode($request->smtp_username) . ':'
                . urlencode($request->smtp_password) . '@'
                . $request->smtp_host . ':' . $request->smtp_port
                . ($enc === 'tls' || $enc === 'starttls' ? '?verify_peer=0' : '?verify_peer=0')
            );

            $mailer = new \Symfony\Component\Mailer\Mailer($transport);

            $email = (new \Symfony\Component\Mime\Email())
                ->from($request->smtp_from_email)
                ->to($request->smtp_from_email)
                ->subject('[ETS-OK] Test connessione SMTP')
                ->text('Test connessione SMTP da ETS-OK. Se ricevi questa email, la configurazione è corretta.');

            $mailer->send($email);

            return response()->json(['ok' => true, 'message' => 'Email di test inviata a ' . $request->smtp_from_email]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /** Testa la connessione IMAP senza salvare */
    public function test(Request $request)
    {
        $request->validate([
            'imap_host'       => 'required|string',
            'imap_port'       => 'required|integer',
            'imap_encryption' => 'required|string',
            'imap_username'   => 'required|string',
            'imap_password'   => 'required|string',
        ]);

        try {
            $client = Client::make([
                'host'          => $request->imap_host,
                'port'          => $request->imap_port,
                'encryption'    => $request->imap_encryption === 'none' ? false : $request->imap_encryption,
                'username'      => $request->imap_username,
                'password'      => $request->imap_password,
                'driver'        => 'Protocol',
                'validate_cert' => false,
            ]);
            $client->connect();
            $client->disconnect();

            return response()->json(['ok' => true, 'message' => 'Connessione riuscita.']);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
