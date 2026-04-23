<?php

namespace App\Http\Controllers;

use Illuminate\Encryption\Encrypter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class InstallController extends Controller
{
    /**
     * Mostra lo step corrente: database → SMTP (opzionale) → utente admin.
     */
    public function show(Request $request)
    {
        if (! $request->session()->has('install.db')) {
            return view('install.database');
        }
        if (! $request->session()->get('install.smtp_seen', false)) {
            return view('install.smtp');
        }

        return view('install.admin');
    }

    /**
     * Mostra il form database (per "Indietro" dallo step SMTP), con dati in sessione se presenti.
     */
    public function showDatabaseForm(Request $request)
    {
        $prefill = $request->session()->get('install.db');
        if (! $prefill) {
            return redirect()->route('install.show');
        }

        return view('install.database', ['prefill' => $prefill]);
    }

    /**
     * Torna allo step precedente: cancella solo SMTP in sessione (da admin si torna a SMTP).
     * Per tornare da SMTP al form database usare install.database.form.
     */
    public function back(Request $request)
    {
        $request->session()->forget('install.smtp');
        $request->session()->forget('install.smtp_seen');

        return redirect()->route('install.show');
    }

    /**
     * Salta lo step SMTP (mantieni default .env).
     */
    public function skipSmtp(Request $request)
    {
        $request->session()->put('install.smtp_seen', true);
        $request->session()->forget('install.smtp');

        return redirect()->route('install.show');
    }

    /**
     * Valida e salva la configurazione database in sessione; testa la connessione.
     */
    public function storeDatabase(Request $request)
    {
        $connection = $request->input('db_connection', 'mysql');

        $rules = [
            'db_connection' => 'required|in:mysql,sqlite',
        ];

        if ($connection === 'mysql') {
            $rules['db_host'] = 'required|string|max:255';
            $rules['db_port'] = 'nullable|string|max:10';
            $rules['db_database'] = 'required|string|max:255';
            $rules['db_username'] = 'required|string|max:255';
            $rules['db_password'] = 'nullable|string';
        } else {
            $rules['db_database_sqlite'] = 'nullable|string|max:500';
        }

        $validated = $request->validate($rules);

        $sqlitePath = trim($validated['db_database_sqlite'] ?? '');
        $dbConfig = [
            'connection' => $validated['db_connection'],
            'host' => $validated['db_host'] ?? '127.0.0.1',
            'port' => $validated['db_port'] ?? ($connection === 'mysql' ? '3306' : null),
            'database' => $connection === 'sqlite'
                ? ($sqlitePath !== '' ? self::absoluteSqlitePath($sqlitePath) : database_path('database.sqlite'))
                : $validated['db_database'],
            'username' => $validated['db_username'] ?? '',
            'password' => $validated['db_password'] ?? '',
        ];

        if ($connection === 'sqlite') {
            $path = $dbConfig['database'];
            if ($path !== ':memory:') {
                $dir = dirname($path);
                if (! is_dir($dir)) {
                    if (! @mkdir($dir, 0755, true)) {
                        throw ValidationException::withMessages([
                            'db_database' => ['Impossibile creare la directory per il database SQLite.'],
                        ]);
                    }
                }
                if (! file_exists($path)) {
                    if (@touch($path) === false) {
                        throw ValidationException::withMessages([
                            'db_database' => ['Impossibile creare il file database SQLite. Verifica i permessi della directory.'],
                        ]);
                    }
                }
            }
        }

        $this->testDatabaseConnection($dbConfig);

        $request->session()->put('install.db', $dbConfig);

        return redirect()->route('install.show');
    }

    /**
     * Salva la configurazione SMTP in sessione (step opzionale).
     */
    public function storeSmtp(Request $request)
    {
        $validated = $request->validate([
            'mail_mailer' => 'required|in:smtp,log',
            'mail_host' => 'nullable|string|max:255',
            'mail_port' => 'nullable|string|max:10',
            'mail_scheme' => 'nullable|string|in:tls,ssl',
            'mail_username' => 'nullable|string|max:255',
            'mail_password' => 'nullable|string|max:255',
            'mail_from_address' => 'nullable|string|max:255',
            'mail_from_name' => 'nullable|string|max:255',
            'skip_smtp_test' => 'nullable|boolean',
        ]);

        if ($validated['mail_mailer'] === 'log') {
            $request->session()->put('install.smtp_seen', true);
            $request->session()->put('install.smtp', ['mailer' => 'log']);
            return redirect()->route('install.show');
        }

        $smtp = [
            'mailer' => 'smtp',
            'host' => $validated['mail_host'] ?? '127.0.0.1',
            'port' => $validated['mail_port'] ?? '587',
            'scheme' => $validated['mail_scheme'] ?? 'tls',
            'username' => $validated['mail_username'] ?? '',
            'password' => $validated['mail_password'] ?? '',
            'from_address' => $validated['mail_from_address'] ?? '',
            'from_name' => $validated['mail_from_name'] ?? '',
        ];

        $skipTest = filter_var($validated['skip_smtp_test'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (! $skipTest) {
            try {
                $this->testSmtpConnection($smtp);
            } catch (ValidationException $e) {
                throw $e;
            } catch (\Throwable $e) {
                $message = $this->smtpTestErrorMessage($e);
                return redirect()->back()
                    ->withErrors(['mail_host' => $message])
                    ->withInput($request->only(array_keys($validated)));
            }
        }

        $request->session()->put('install.smtp_seen', true);
        $request->session()->put('install.smtp', $smtp);

        return redirect()->route('install.show');
    }

    /**
     * Completa l'installazione: scrive .env, migra, seed, redirect al login.
     */
    public function complete(Request $request)
    {
        $db = $request->session()->get('install.db');
        if (! $db) {
            return redirect()->route('install.show')->withErrors(['session' => 'Configurazione database non trovata. Ricomincia dallo step 1.']);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $envPath = base_path('.env');
        if (! file_exists($envPath)) {
            $example = base_path('.env.example');
            if (file_exists($example)) {
                copy($example, $envPath);
            } else {
                return redirect()->back()->withErrors(['env' => 'File .env non trovato e .env.example assente.'])->withInput();
            }
        }

        $this->writeEnvDatabase($envPath, $db);
        $this->writeEnvAppKey($envPath);
        $smtp = $request->session()->get('install.smtp');
        if ($smtp) {
            $this->writeEnvMail($envPath, $smtp);
        }
        $this->setRuntimeDatabaseConfig($db);

        try {
            Artisan::call('migrate', ['--force' => true]);
        } catch (\Throwable $e) {
            return redirect()->back()->withErrors(['migrate' => 'Migrazioni fallite: '.$e->getMessage()])->withInput();
        }

        Config::set('install.admin', [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);
        try {
            Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\InstallSeeder', '--force' => true]);
        } catch (\Throwable $e) {
            return redirect()->back()->withErrors(['seed' => 'Seed fallito: '.$e->getMessage()])->withInput();
        }

        $request->session()->forget('install');

        return redirect()->route('login')->with('flash', [
            'type' => 'success',
            'message' => 'Installazione completata. Accedi con le credenziali inserite.',
        ]);
    }

    /**
     * Imposta la configurazione database a runtime così migrate/seed usano i valori appena scritti in .env.
     */
    private function setRuntimeDatabaseConfig(array $db): void
    {
        Config::set('database.default', $db['connection']);
        if ($db['connection'] === 'sqlite') {
            Config::set('database.connections.sqlite', [
                'driver' => 'sqlite',
                'url' => null,
                'database' => $db['database'],
                'prefix' => '',
                'foreign_key_constraints' => true,
            ]);
        } else {
            Config::set('database.connections.mysql', [
                'driver' => 'mysql',
                'host' => $db['host'],
                'port' => $db['port'] ?? '3306',
                'database' => $db['database'],
                'username' => $db['username'],
                'password' => $db['password'],
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
                'engine' => null,
            ]);
        }
    }

    /**
     * Testa la connessione al database con la configurazione fornita.
     */
    private function testDatabaseConnection(array $db): void
    {
        $connection = $db['connection'];

        if ($connection === 'sqlite') {
            $config = [
                'driver' => 'sqlite',
                'database' => $db['database'],
                'prefix' => '',
            ];
        } else {
            $config = [
                'driver' => 'mysql',
                'host' => $db['host'],
                'port' => $db['port'] ?? '3306',
                'database' => $db['database'],
                'username' => $db['username'],
                'password' => $db['password'],
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
                'engine' => null,
            ];
        }

        Config::set('database.connections.install_test', $config);

        try {
            DB::connection('install_test')->getPdo();
        } catch (\Throwable $e) {
            DB::purge('install_test');
            throw ValidationException::withMessages([
                'db_database' => ['Connessione al database non riuscita: '.$e->getMessage()],
            ]);
        }

        DB::purge('install_test');
    }

    /**
     * Testa la connessione SMTP inviando un'email di prova.
     *
     * @throws ValidationException se il test fallisce
     */
    private function testSmtpConnection(array $smtp): void
    {
        $fromAddress = trim($smtp['from_address'] ?? '');
        $fromName = trim($smtp['from_name'] ?? '');
        $to = $fromAddress !== '' && filter_var($fromAddress, FILTER_VALIDATE_EMAIL)
            ? $fromAddress
            : 'test@example.com';

        $scheme = self::smtpSchemeForTransport($smtp['scheme'] ?? null);

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp', [
            'transport' => 'smtp',
            'scheme' => $scheme,
            'host' => $smtp['host'] ?? '127.0.0.1',
            'port' => (int) ($smtp['port'] ?? 587),
            'username' => $smtp['username'] ?? null,
            'password' => $smtp['password'] ?? null,
            'timeout' => 10,
        ]);
        Config::set('mail.from', [
            'address' => $fromAddress !== '' ? $fromAddress : 'noreply@example.com',
            'name' => $fromName !== '' ? $fromName : config('app.name'),
        ]);

        Mail::raw(
            "Questo è un messaggio di test dall'installer Infotel Sistemi. La configurazione SMTP risulta corretta.",
            function ($message) use ($to): void {
                $message->to($to)->subject('Test SMTP – Installazione Infotel Sistemi');
            }
        );
    }

    /**
     * Scrive APP_KEY nel .env (generata come fa key:generate).
     * Necessario perché con APP_KEY= vuoto key:generate non trova la riga da sostituire.
     */
    private function writeEnvAppKey(string $envPath): void
    {
        $cipher = config('app.cipher', 'AES-256-CBC');
        $key = 'base64:'.base64_encode(Encrypter::generateKey($cipher));
        $content = file_get_contents($envPath);
        $content = $this->setEnvVariable($content, 'APP_KEY', $key);
        file_put_contents($envPath, $content);
    }

    /**
     * Scrive le variabili MAIL_* nel file .env (da step SMTP opzionale).
     */
    private function writeEnvMail(string $envPath, array $smtp): void
    {
        $content = file_get_contents($envPath);
        $vars = [
            'MAIL_MAILER' => $smtp['mailer'] ?? 'log',
        ];
        if (($smtp['mailer'] ?? '') === 'smtp') {
            $vars['MAIL_HOST'] = $smtp['host'] ?? '127.0.0.1';
            $vars['MAIL_PORT'] = $smtp['port'] ?? '587';
            $vars['MAIL_SCHEME'] = self::smtpSchemeForTransport($smtp['scheme'] ?? null);
            $vars['MAIL_USERNAME'] = $smtp['username'] ?? 'null';
            $vars['MAIL_PASSWORD'] = $smtp['password'] !== '' ? $this->envValue($smtp['password']) : 'null';
            $vars['MAIL_FROM_ADDRESS'] = $smtp['from_address'] !== '' ? $this->envValue($smtp['from_address']) : '"hello@example.com"';
            $vars['MAIL_FROM_NAME'] = $smtp['from_name'] !== '' ? $this->envValue($smtp['from_name']) : '"${APP_NAME}"';
        }
        foreach ($vars as $key => $value) {
            $content = $this->setEnvVariable($content, $key, $value);
        }
        file_put_contents($envPath, $content);
    }

    /**
     * Aggiorna o inserisce le variabili DB nel file .env.
     */
    private function writeEnvDatabase(string $envPath, array $db): void
    {
        $content = file_get_contents($envPath);
        $vars = [
            'DB_CONNECTION' => $db['connection'],
        ];

        if ($db['connection'] === 'mysql') {
            $vars['DB_HOST'] = $db['host'];
            $vars['DB_PORT'] = $db['port'] ?? '3306';
            $vars['DB_DATABASE'] = $db['database'];
            $vars['DB_USERNAME'] = $db['username'];
            $vars['DB_PASSWORD'] = $this->envValue($db['password']);
        } else {
            $vars['DB_DATABASE'] = $db['database'];
        }

        foreach ($vars as $key => $value) {
            $content = $this->setEnvVariable($content, $key, $value);
        }

        file_put_contents($envPath, $content);
    }

    private function envValue(string $value): string
    {
        if (preg_match('/\s|#|$/', $value)) {
            return '"'.str_replace('"', '\\"', $value).'"';
        }

        return $value;
    }

    private function setEnvVariable(string $content, string $key, string $value): string
    {
        $line = $key.'='.$value;
        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
        if (preg_match($pattern, $content)) {
            return preg_replace($pattern, $line, $content);
        }

        return $content."\n".$line."\n";
    }

    /**
     * Restituisce un messaggio di errore leggibile per il fallimento del test SMTP.
     */
    private function smtpTestErrorMessage(\Throwable $e): string
    {
        $msg = $e->getMessage();
        if (stripos($msg, '535') !== false || stripos($msg, 'authentication') !== false || stripos($msg, 'authenticate') !== false) {
            return 'Il server SMTP ha rifiutato utente o password. Verifica le credenziali oppure spunta "Salva senza testare" per procedere e configurare dopo.';
        }
        if (stripos($msg, 'connection') !== false || stripos($msg, 'connect') !== false || stripos($msg, 'timeout') !== false) {
            return 'Impossibile raggiungere il server SMTP. Verifica host, porta e crittografia, oppure spunta "Salva senza testare".';
        }

        return 'Test SMTP fallito: ' . $msg;
    }

    /**
     * Mappa il valore scelto nel form (tls/ssl) allo scheme richiesto dal mailer: "smtp" o "smtps".
     */
    private static function smtpSchemeForTransport(?string $scheme): string
    {
        return $scheme === 'ssl' ? 'smtps' : 'smtp';
    }

    /**
     * Restituisce il percorso assoluto per il file SQLite (richiesto dal connector Laravel).
     */
    private static function absoluteSqlitePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        if ($path === '' || $path === ':memory:') {
            return database_path('database.sqlite');
        }
        if (! str_starts_with($path, '/')) {
            $path = base_path($path);
        }

        return $path;
    }
}
