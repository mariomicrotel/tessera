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
        $accounts = MailAccount::query()
            ->withCount('messages')
            ->orderBy('name')
            ->get()
            ->map(fn($a) => [
                'id'             => $a->id,
                'name'           => $a->name,
                'email'          => $a->email,
                'imap_host'      => $a->imap_host,
                'imap_port'      => $a->imap_port,
                'imap_encryption'=> $a->imap_encryption,
                'imap_username'  => $a->imap_username,
                'imap_folder'    => $a->imap_folder,
                'is_active'      => $a->is_active,
                'sync_days'      => $a->sync_days,
                'last_synced_at' => $a->last_synced_at?->toIso8601String(),
                'messages_count' => $a->messages_count,
                'unread_count'   => $a->unreadCount(),
            ]);

        return Inertia::render('Mail/Accounts/Index', [
            'accounts' => $accounts,
        ]);
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
            'name'            => 'required|string|max:100',
            'email'           => 'required|email|max:255',
            'imap_host'       => 'required|string|max:255',
            'imap_port'       => 'required|integer|min:1|max:65535',
            'imap_encryption' => 'required|in:ssl,tls,starttls,none',
            'imap_username'   => 'required|string|max:255',
            'imap_password'   => 'required|string|max:500',
            'imap_folder'     => 'required|string|max:100',
            'sync_days'       => 'integer|min:1|max:365',
            'is_active'       => 'boolean',
        ]);

        MailAccount::create($validated);

        return redirect()->route('mail.accounts.index')
            ->with('success', 'Casella email aggiunta.');
    }

    public function edit(MailAccount $mailAccount)
    {
        return Inertia::render('Mail/Accounts/Form', [
            'account' => [
                'id'              => $mailAccount->id,
                'name'            => $mailAccount->name,
                'email'           => $mailAccount->email,
                'imap_host'       => $mailAccount->imap_host,
                'imap_port'       => $mailAccount->imap_port,
                'imap_encryption' => $mailAccount->imap_encryption,
                'imap_username'   => $mailAccount->imap_username,
                'imap_password'   => '', // non esporre la password
                'imap_folder'     => $mailAccount->imap_folder,
                'sync_days'       => $mailAccount->sync_days,
                'is_active'       => $mailAccount->is_active,
            ],
        ]);
    }

    public function update(Request $request, MailAccount $mailAccount)
    {
        $rules = [
            'name'            => 'required|string|max:100',
            'email'           => 'required|email|max:255',
            'imap_host'       => 'required|string|max:255',
            'imap_port'       => 'required|integer|min:1|max:65535',
            'imap_encryption' => 'required|in:ssl,tls,starttls,none',
            'imap_username'   => 'required|string|max:255',
            'imap_folder'     => 'required|string|max:100',
            'sync_days'       => 'integer|min:1|max:365',
            'is_active'       => 'boolean',
        ];

        // La password è opzionale nell'update (se vuota, si conserva la precedente)
        if ($request->filled('imap_password')) {
            $rules['imap_password'] = 'string|max:500';
        }

        $validated = $request->validate($rules);

        if (empty($validated['imap_password'])) {
            unset($validated['imap_password']);
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
