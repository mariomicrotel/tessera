<?php

/**
 * Test suite per il modulo Posta (mail client integrato).
 *
 * Copre:
 *  - MailAccount: cifratura password IMAP/SMTP, hasSmtp()
 *  - MailAccountController: store/update/destroy (role:admin)
 *  - MailController::index: filtro cartella ricevuti/inviati, conteggi
 *  - MailController::show: marca come letto, prev/next, protocollo collegato
 *  - MailController::send: dispatch SendMailJob con allegati
 *  - MailController::protocolla: crea Protocollo, evita duplicati
 *  - SendMailJob::saveSentMessage: salva in folder Sent
 */

use App\Jobs\SendMailJob;
use App\Models\MailAccount;
use App\Models\MailMessage;
use App\Models\Protocollo;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Queue;

// ─────────────────────────────────────────────────────────────────────────────
// Setup
// ─────────────────────────────────────────────────────────────────────────────

beforeEach(function () {
    // CSRF non rilevante per i test funzionali delle POST/PUT
    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

    $this->tenant = Tenant::create([
        'name'              => 'Ente Mail Test',
        'slug'              => 'ente-mail-test-' . uniqid(),
        'organization_type' => 'ets',
        'plan'              => 'free',
        'is_active'         => true,
    ]);

    app()->instance('current_tenant', $this->tenant);

    (new RoleSeeder)->run();

    $roleAdmin = Role::where('name', 'admin')->first();
    $this->admin = User::factory()->create(['email_verified_at' => now()]);
    $this->admin->roles()->attach($roleAdmin);
    $this->admin->tenants()->attach($this->tenant);

    // Utente senza ruoli abilitati
    $this->guest = User::factory()->create(['email_verified_at' => now()]);
    $this->guest->tenants()->attach($this->tenant);

    // Casella con IMAP + SMTP
    $this->account = MailAccount::create([
        'name'            => 'Casella Test',
        'email'           => 'info@test.it',
        'imap_host'       => 'imap.test.it',
        'imap_port'       => 993,
        'imap_encryption' => 'ssl',
        'imap_username'   => 'info@test.it',
        'imap_password'   => 'secret-imap',
        'imap_folder'     => 'INBOX',
        'sync_days'       => 30,
        'is_active'       => true,
        'smtp_host'       => 'smtp.test.it',
        'smtp_port'       => 587,
        'smtp_encryption' => 'tls',
        'smtp_username'   => 'info@test.it',
        'smtp_password'   => 'secret-smtp',
        'smtp_from_name'  => 'Ente Test',
        'smtp_from_email' => 'info@test.it',
    ]);
});

afterEach(function () {
    app()->forgetInstance('current_tenant');
});

// Helper per creare un messaggio
function makeMessage(array $overrides = []): MailMessage
{
    return MailMessage::create(array_merge([
        'mail_account_id' => test()->account->id,
        'uid'             => random_int(1, 100000),
        'folder'          => 'INBOX',
        'subject'         => 'Oggetto di prova',
        'from_name'       => 'Mario Rossi',
        'from_email'      => 'mario@example.com',
        'to_addresses'    => [['name' => 'Ente Test', 'email' => 'info@test.it']],
        'sent_at'         => now(),
        'body_text'       => 'Corpo del messaggio di prova.',
        'is_read'         => false,
        'is_flagged'      => false,
        'has_attachments' => false,
    ], $overrides));
}

// ─────────────────────────────────────────────────────────────────────────────
// MailAccount model
// ─────────────────────────────────────────────────────────────────────────────

describe('MailAccount model', function () {
    it('cifra la password IMAP a riposo e la decifra correttamente', function () {
        expect($this->account->getRawOriginal('imap_password'))->not->toBe('secret-imap');
        expect($this->account->getDecryptedPassword())->toBe('secret-imap');
    });

    it('cifra la password SMTP e la decifra correttamente', function () {
        expect($this->account->getRawOriginal('smtp_password'))->not->toBe('secret-smtp');
        expect($this->account->getDecryptedSmtpPassword())->toBe('secret-smtp');
    });

    it('hasSmtp è true quando host e username sono presenti', function () {
        expect($this->account->hasSmtp())->toBeTrue();
    });

    it('hasSmtp è false senza configurazione SMTP', function () {
        $acc = MailAccount::create([
            'name' => 'Solo IMAP', 'email' => 'solo@test.it',
            'imap_host' => 'imap.test.it', 'imap_port' => 993,
            'imap_encryption' => 'ssl', 'imap_username' => 'solo@test.it',
            'imap_password' => 'x', 'imap_folder' => 'INBOX',
            'sync_days' => 30, 'is_active' => true,
        ]);
        expect($acc->hasSmtp())->toBeFalse();
    });

    it('non espone le password nella serializzazione', function () {
        $arr = $this->account->toArray();
        expect($arr)->not->toHaveKey('imap_password');
        expect($arr)->not->toHaveKey('smtp_password');
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// MailAccountController (role:admin)
// ─────────────────────────────────────────────────────────────────────────────

describe('MailAccountController', function () {
    it('admin può creare una casella', function () {
        $this->actingAs($this->admin)
            ->post(route('mail.accounts.store', $this->tenant), [
                'name' => 'Nuova', 'email' => 'nuova@test.it',
                'imap_host' => 'imap.x.it', 'imap_port' => 993,
                'imap_encryption' => 'ssl', 'imap_username' => 'nuova@test.it',
                'imap_password' => 'pwd', 'imap_folder' => 'INBOX',
                'sync_days' => 30, 'is_active' => true,
            ])
            ->assertRedirect();

        expect(MailAccount::where('email', 'nuova@test.it')->exists())->toBeTrue();
    });

    it('un utente senza ruolo non può creare una casella', function () {
        $this->actingAs($this->guest)
            ->post(route('mail.accounts.store', $this->tenant), [
                'name' => 'X', 'email' => 'x@test.it',
                'imap_host' => 'i', 'imap_port' => 993,
                'imap_encryption' => 'ssl', 'imap_username' => 'x',
                'imap_password' => 'p', 'imap_folder' => 'INBOX',
            ])
            ->assertForbidden();
    });

    it('update senza password conserva quella precedente', function () {
        $original = $this->account->getRawOriginal('imap_password');

        $this->actingAs($this->admin)
            ->put(route('mail.accounts.update', [$this->tenant, $this->account]), [
                'name' => 'Rinominata', 'email' => 'info@test.it',
                'imap_host' => 'imap.test.it', 'imap_port' => 993,
                'imap_encryption' => 'ssl', 'imap_username' => 'info@test.it',
                'imap_password' => '', 'imap_folder' => 'INBOX',
                'sync_days' => 30, 'is_active' => true,
            ])
            ->assertRedirect();

        $this->account->refresh();
        expect($this->account->name)->toBe('Rinominata');
        expect($this->account->getRawOriginal('imap_password'))->toBe($original);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// MailController::index — filtro cartella
// ─────────────────────────────────────────────────────────────────────────────

describe('MailController index', function () {
    it('mostra solo i messaggi ricevuti per default (esclude Sent)', function () {
        makeMessage(['subject' => 'Ricevuta 1']);
        makeMessage(['subject' => 'Inviata 1', 'folder' => 'Sent', 'is_read' => true]);

        $this->actingAs($this->admin)
            ->get(route('mail.index', $this->tenant))
            ->assertInertia(fn ($page) => $page
                ->component('Mail/Inbox')
                ->where('messages.data', fn ($data) => collect($data)->pluck('subject')->contains('Ricevuta 1')
                    && ! collect($data)->pluck('subject')->contains('Inviata 1')
                )
            );
    });

    it('con box=sent mostra solo i messaggi inviati', function () {
        makeMessage(['subject' => 'Ricevuta 1']);
        makeMessage(['subject' => 'Inviata 1', 'folder' => 'Sent', 'is_read' => true]);

        $this->actingAs($this->admin)
            ->get(route('mail.index', ['tenant' => $this->tenant, 'box' => 'sent']))
            ->assertInertia(fn ($page) => $page
                ->where('messages.data', fn ($data) => collect($data)->pluck('subject')->contains('Inviata 1')
                    && ! collect($data)->pluck('subject')->contains('Ricevuta 1')
                )
            );
    });

    it('total_unread conta solo i ricevuti non letti', function () {
        makeMessage(['is_read' => false]);
        makeMessage(['folder' => 'Sent', 'is_read' => false]); // anomalo ma da escludere

        $this->actingAs($this->admin)
            ->get(route('mail.index', $this->tenant))
            ->assertInertia(fn ($page) => $page->where('total_unread', 1));
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// MailController::show
// ─────────────────────────────────────────────────────────────────────────────

describe('MailController show', function () {
    it('marca il messaggio come letto', function () {
        $msg = makeMessage(['is_read' => false]);

        $this->actingAs($this->admin)
            ->get(route('mail.show', [$this->tenant, $msg]))
            ->assertOk();

        expect($msg->fresh()->is_read)->toBeTrue();
    });

    it('fornisce prev_id e next_id per i messaggi adiacenti', function () {
        $older = makeMessage(['sent_at' => now()->subDays(2)]);
        $mid   = makeMessage(['sent_at' => now()->subDay()]);
        $newer = makeMessage(['sent_at' => now()]);

        $this->actingAs($this->admin)
            ->get(route('mail.show', [$this->tenant, $mid]))
            ->assertInertia(fn ($page) => $page
                ->where('prev_id', $newer->id)
                ->where('next_id', $older->id)
            );
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// MailController::send
// ─────────────────────────────────────────────────────────────────────────────

describe('MailController send', function () {
    it('dispatcha SendMailJob con i dati corretti', function () {
        Queue::fake();

        $this->actingAs($this->admin)
            ->post(route('mail.send', $this->tenant), [
                'account_id' => $this->account->id,
                'to'         => 'destinatario@example.com',
                'to_name'    => 'Destinatario',
                'subject'    => 'Test invio',
                'body'       => 'Corpo del messaggio.',
            ])
            ->assertRedirect(route('mail.index', $this->tenant));

        Queue::assertPushed(SendMailJob::class, fn ($job) =>
            $job->to === 'destinatario@example.com'
            && $job->subject === 'Test invio'
            && $job->mailAccountId === $this->account->id
        );
    });

    it('rifiuta l\'invio se la casella non ha SMTP', function () {
        $noSmtp = MailAccount::create([
            'name' => 'No SMTP', 'email' => 'nosmtp@test.it',
            'imap_host' => 'i', 'imap_port' => 993,
            'imap_encryption' => 'ssl', 'imap_username' => 'x',
            'imap_password' => 'p', 'imap_folder' => 'INBOX',
            'sync_days' => 30, 'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->post(route('mail.send', $this->tenant), [
                'account_id' => $noSmtp->id,
                'to'         => 'x@example.com',
                'subject'    => 'X', 'body' => 'Y',
            ])
            ->assertStatus(422);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// MailController::protocolla
// ─────────────────────────────────────────────────────────────────────────────

describe('MailController protocolla', function () {
    it('crea un Protocollo in entrata collegato al messaggio', function () {
        $msg = makeMessage();

        $this->actingAs($this->admin)
            ->post(route('mail.protocolla', [$this->tenant, $msg]))
            ->assertRedirect();

        $prot = Protocollo::where('linked_type', MailMessage::class)
            ->where('linked_id', $msg->id)
            ->first();

        expect($prot)->not->toBeNull();
        expect($prot->tipo)->toBe(Protocollo::TIPO_ENTRATA);
        expect($prot->oggetto)->toBe('Oggetto di prova');
    });

    it('un messaggio Sent viene protocollato in uscita', function () {
        $msg = makeMessage(['folder' => 'Sent', 'is_read' => true]);

        $this->actingAs($this->admin)
            ->post(route('mail.protocolla', [$this->tenant, $msg]));

        $prot = Protocollo::where('linked_id', $msg->id)->first();
        expect($prot->tipo)->toBe(Protocollo::TIPO_USCITA);
    });

    it('non crea protocolli duplicati per lo stesso messaggio', function () {
        $msg = makeMessage();

        $this->actingAs($this->admin)->post(route('mail.protocolla', [$this->tenant, $msg]));
        $this->actingAs($this->admin)->post(route('mail.protocolla', [$this->tenant, $msg]));

        expect(Protocollo::where('linked_id', $msg->id)->count())->toBe(1);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Bozze (Drafts)
// ─────────────────────────────────────────────────────────────────────────────

describe('Bozze', function () {
    it('salva una nuova bozza nella cartella Drafts', function () {
        $this->actingAs($this->admin)
            ->post(route('mail.draft', $this->tenant), [
                'account_id' => $this->account->id,
                'to'         => 'dest@example.com',
                'subject'    => 'Bozza di prova',
                'body'       => 'Testo della bozza.',
            ])
            ->assertRedirect();

        $draft = MailMessage::where('folder', 'Drafts')->where('subject', 'Bozza di prova')->first();
        expect($draft)->not->toBeNull();
        expect($draft->is_read)->toBeTrue();
        expect($draft->to_addresses)->toBe([['name' => null, 'email' => 'dest@example.com']]);
    });

    it('aggiorna una bozza esistente invece di crearne una nuova', function () {
        $draft = makeMessage(['folder' => 'Drafts', 'subject' => 'Originale', 'is_read' => true]);

        $this->actingAs($this->admin)
            ->post(route('mail.draft', $this->tenant), [
                'draft_id'   => $draft->id,
                'account_id' => $this->account->id,
                'subject'    => 'Modificata',
                'body'       => 'Nuovo testo.',
            ])
            ->assertRedirect();

        expect(MailMessage::where('folder', 'Drafts')->count())->toBe(1);
        expect($draft->fresh()->subject)->toBe('Modificata');
    });

    it('le bozze sono escluse dalla posta ricevuta', function () {
        makeMessage(['subject' => 'Ricevuta']);
        makeMessage(['folder' => 'Drafts', 'subject' => 'Bozza', 'is_read' => true]);

        $this->actingAs($this->admin)
            ->get(route('mail.index', $this->tenant))
            ->assertInertia(fn ($page) => $page
                ->where('messages.data', fn ($data) => ! collect($data)->pluck('subject')->contains('Bozza'))
                ->where('draft_count', 1)
            );
    });

    it('box=drafts mostra solo le bozze', function () {
        makeMessage(['subject' => 'Ricevuta']);
        makeMessage(['folder' => 'Drafts', 'subject' => 'Bozza', 'is_read' => true]);

        $this->actingAs($this->admin)
            ->get(route('mail.index', ['tenant' => $this->tenant, 'box' => 'drafts']))
            ->assertInertia(fn ($page) => $page
                ->where('messages.data', fn ($data) => collect($data)->pluck('subject')->contains('Bozza')
                    && ! collect($data)->pluck('subject')->contains('Ricevuta'))
            );
    });

    it('inviare da una bozza elimina la bozza', function () {
        Queue::fake();
        $draft = makeMessage(['folder' => 'Drafts', 'is_read' => true]);

        $this->actingAs($this->admin)
            ->post(route('mail.send', $this->tenant), [
                'account_id' => $this->account->id,
                'to'         => 'dest@example.com',
                'subject'    => 'Invio da bozza',
                'body'       => 'Corpo.',
                'draft_id'   => $draft->id,
            ])
            ->assertRedirect(route('mail.index', $this->tenant));

        Queue::assertPushed(SendMailJob::class);
        expect(MailMessage::withTrashed()->find($draft->id))->toBeNull();
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Threading / Conversazioni
// ─────────────────────────────────────────────────────────────────────────────

describe('Threading', function () {
    it('show carica tutti i messaggi dello stesso thread ordinati', function () {
        $root  = makeMessage(['subject' => 'Domanda', 'message_id' => 'root@x', 'thread_id' => 'root@x', 'sent_at' => now()->subDays(2)]);
        $reply = makeMessage(['subject' => 'Re: Domanda', 'message_id' => 'reply@x', 'in_reply_to' => 'root@x', 'thread_id' => 'root@x', 'sent_at' => now()->subDay()]);
        // Messaggio di un altro thread, non deve comparire
        makeMessage(['subject' => 'Altro', 'message_id' => 'other@x', 'thread_id' => 'other@x']);

        $this->actingAs($this->admin)
            ->get(route('mail.show', [$this->tenant, $reply]))
            ->assertInertia(fn ($page) => $page
                ->where('thread', fn ($t) => count($t) === 2
                    && collect($t)->pluck('subject')->contains('Domanda')
                    && collect($t)->pluck('subject')->contains('Re: Domanda')
                    && ! collect($t)->pluck('subject')->contains('Altro')
                )
            );
    });

    it('una risposta inviata eredita il thread_id del messaggio originale', function () {
        $orig = makeMessage(['message_id' => 'orig@x', 'thread_id' => 'orig@x']);

        $job = new SendMailJob(
            mailAccountId: $this->account->id,
            to:            'mario@example.com',
            toName:        'Mario',
            subject:       'Re: Oggetto',
            bodyHtml:      '',
            bodyText:      'Risposta',
            inReplyTo:     $orig->message_id,
            references:    [$orig->thread_id, $orig->message_id],
            threadId:      $orig->thread_id,
        );

        $ref = new ReflectionMethod($job, 'saveSentMessage');
        $ref->setAccessible(true);
        $ref->invoke($job, $this->account, 'info@test.it', 'Ente Test', 'ets-new@test.it');

        $sent = MailMessage::withoutGlobalScope('tenant')->where('folder', 'Sent')->latest('id')->first();
        expect($sent->thread_id)->toBe('orig@x');
        expect($sent->in_reply_to)->toBe('orig@x');
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// SendMailJob — salvataggio in Sent
// ─────────────────────────────────────────────────────────────────────────────

describe('SendMailJob', function () {
    it('salva il messaggio inviato nella cartella Sent', function () {
        // Il salvataggio in Sent avviene dopo l'invio. Testiamo direttamente
        // il metodo privato tramite reflection per non dipendere da SMTP reale.
        $job = new SendMailJob(
            mailAccountId: $this->account->id,
            to:            'dest@example.com',
            toName:        'Dest',
            subject:       'Salvato in Sent',
            bodyHtml:      '<p>Ciao</p>',
            bodyText:      'Ciao',
        );

        $ref    = new ReflectionMethod($job, 'saveSentMessage');
        $ref->setAccessible(true);
        $ref->invoke($job, $this->account, 'info@test.it', 'Ente Test', 'ets-msg@test.it');

        $sent = MailMessage::withoutGlobalScope('tenant')
            ->where('folder', 'Sent')
            ->where('subject', 'Salvato in Sent')
            ->first();

        expect($sent)->not->toBeNull();
        expect($sent->is_read)->toBeTrue();
        expect($sent->to_addresses)->toBe([['name' => 'Dest', 'email' => 'dest@example.com']]);
    });
});
