<?php

namespace App\Services;

use App\Exceptions\OpenApiCompanyException;
use App\Models\Settings;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenApiCompanyClient
{
    private string $baseUrl;
    private string $token;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('openapi_company.base_url'), '/');
        $this->token = $this->resolveToken();
        $this->timeout = config('openapi_company.timeout', 15);
    }

    private function resolveToken(): string
    {
        $enc = Settings::get('openapi_company_token', '');

        if (! $enc) {
            $enc = DB::table('settings')
                ->where('key', 'openapi_company_token')
                ->whereNull('tenant_id')
                ->value('value') ?? '';
        }

        if ($enc) {
            try {
                return Crypt::decryptString($enc);
            } catch (\Throwable $e) {
                // fallback a .env
            }
        }

        return config('openapi_company.token', '');
    }

    public function getItalianCompanyStart(string $identifier): array
    {
        return $this->call('IT-start', $identifier);
    }

    public function getItalianCompanyAdvanced(string $identifier): array
    {
        return $this->call('IT-advanced', $identifier);
    }

    public function getItalianCompanyPec(string $identifier): array
    {
        return $this->call('IT-pec', $identifier);
    }

    public function getItalianCompanySdiCode(string $identifier): array
    {
        return $this->call('IT-sdicode', $identifier);
    }

    /**
     * Ricerca aziende italiane per criteri multipli.
     *
     * $criteria può contenere (subset di IT-search):
     *  - companyName (string, supporta wildcard *)
     *  - autocomplete (string, per ricerche realtime UI)
     *  - province (string, sigla provincia es. "MI")
     *  - municipality (string, codice catastale Belfiore)
     *  - atecoCode (string)
     *  - reaCode (string)
     *  - sdiCode (string)
     *  - legalForm (string)
     *  - activityStatus (string: ACTIVE|CEASED|REGISTERED|INACTIVE|SUSPENDED|UNDER_REGISTRATION)
     *  - pec (string)
     *  - taxCodeOwner (string, codice fiscale proprietario)
     *  - employeesMin (int), employeesMax (int)
     *  - turnoverMin (int), turnoverMax (int)
     *  - creationDateFrom (Y-m-d), creationDateTo (Y-m-d)
     *  - updateDateFrom (Y-m-d), updateDateTo (Y-m-d)
     *  - lat, lng, radius (geospaziale)
     *
     * @param array $criteria  Filtri di ricerca
     * @param int   $skip      Offset paginazione
     * @param int   $limit     Numero massimo risultati (max 100 lato provider)
     * @param bool  $dryRun    Se true ritorna solo conteggio
     * @param array $enrichWith  Endpoint per dataEnrichment (es. ['IT-advanced'])
     */
    public function searchItalianCompanies(
        array $criteria,
        int $skip = 0,
        int $limit = 10,
        bool $dryRun = false,
        array $enrichWith = []
    ): array {
        if (empty($this->token)) {
            throw new OpenApiCompanyException('Token OpenAPI Company non configurato.', 0);
        }

        $query = array_filter($criteria, fn ($v) => $v !== null && $v !== '');
        $query['skip']  = max(0, $skip);
        $query['limit'] = max(1, min(100, $limit));

        if ($dryRun) {
            $query['dryRun'] = 'true';
        }

        if (! empty($enrichWith)) {
            $query['dataEnrichment'] = implode(',', $enrichWith);
        }

        $url = "{$this->baseUrl}/IT-search";

        try {
            $response = Http::withToken($this->token)
                ->accept('application/json')
                ->timeout($this->timeout)
                ->get($url, $query);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('OpenAPI Company timeout', ['endpoint' => 'IT-search', 'query' => $query]);
            throw new OpenApiCompanyException('Timeout nella connessione al servizio OpenAPI.', 408);
        }

        return match ($response->status()) {
            200 => $response->json(),
            204 => ['data' => [], 'message' => 'Nessun risultato trovato.'],
            400 => throw new OpenApiCompanyException('Criteri di ricerca non validi.', 400),
            401 => throw new OpenApiCompanyException('Token API non valido o scaduto.', 401),
            402 => throw new OpenApiCompanyException('Credito API insufficiente.', 402),
            429 => throw new OpenApiCompanyException('Troppe richieste, riprovare più tardi.', 429),
            default => throw new OpenApiCompanyException(
                "Errore API: HTTP {$response->status()}",
                $response->status()
            ),
        };
    }

    private function call(string $endpoint, string $identifier): array
    {
        if (empty($this->token)) {
            throw new OpenApiCompanyException('Token OpenAPI Company non configurato.', 0);
        }

        $identifier = $this->normalize($identifier);
        $this->validateIdentifier($identifier);

        $url = "{$this->baseUrl}/{$endpoint}/{$identifier}";

        try {
            $response = Http::withToken($this->token)
                ->accept('application/json')
                ->timeout($this->timeout)
                ->get($url);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('OpenAPI Company timeout', ['endpoint' => $endpoint, 'identifier' => $identifier]);
            throw new OpenApiCompanyException('Timeout nella connessione al servizio OpenAPI.', 408);
        }

        return match ($response->status()) {
            200 => $response->json(),
            204 => throw new OpenApiCompanyException('Nessun dato trovato per l\'identificativo fornito.', 204),
            400 => throw new OpenApiCompanyException('Identificativo non valido.', 400),
            401 => throw new OpenApiCompanyException('Token API non valido o scaduto.', 401),
            402 => throw new OpenApiCompanyException('Credito API insufficiente.', 402),
            404 => throw new OpenApiCompanyException('Azienda non trovata.', 404),
            406 => throw new OpenApiCompanyException('Richiesta non accettabile dal provider.', 406),
            417 => throw new OpenApiCompanyException('Errore del provider dati.', 417),
            429 => throw new OpenApiCompanyException('Troppe richieste, riprovare più tardi.', 429),
            default => throw new OpenApiCompanyException(
                "Errore API: HTTP {$response->status()}",
                $response->status()
            ),
        };
    }

    private function normalize(string $identifier): string
    {
        return preg_replace('/\s+/', '', strtoupper(trim($identifier)));
    }

    private function validateIdentifier(string $identifier): void
    {
        if (strlen($identifier) < 6 || ! preg_match('/^[A-Z0-9]+$/', $identifier)) {
            throw new OpenApiCompanyException('Identificativo non valido: inserire una P.IVA, un CF o un ID azienda.', 400);
        }
    }
}
