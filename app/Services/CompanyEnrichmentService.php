<?php

namespace App\Services;

use App\Exceptions\OpenApiCompanyException;
use App\Models\CompanyEnrichmentCache;
use Carbon\Carbon;

class CompanyEnrichmentService
{
    private const PROVIDER = 'openapi';

    public function __construct(
        private OpenApiCompanyClient $client,
        private ApiUsageCounterService $counter,
    ) {}

    public function enrichFromItStart(string $identifier, bool $useCache = true, bool $forceRefresh = false): array
    {
        $endpoint = 'IT-start';
        $lookupKey = $this->normalizeLookupKey($identifier);

        if ($useCache && ! $forceRefresh) {
            $cached = $this->getCached($lookupKey, $endpoint);
            if ($cached) {
                return [
                    'success' => true,
                    'source' => 'cache',
                    'usage' => $this->counter->getUsage(self::PROVIDER, $endpoint),
                    'data' => $cached->normalized_json,
                    'cached_at' => $cached->fetched_at->toIso8601String(),
                ];
            }
        }

        if (! $this->counter->canMakeCall(self::PROVIDER, $endpoint)) {
            return [
                'success' => false,
                'error' => 'Limite giornaliero di chiamate API raggiunto.',
                'usage' => $this->counter->getUsage(self::PROVIDER, $endpoint),
            ];
        }

        $raw = $this->client->getItalianCompanyStart($identifier);
        $usage = $this->counter->increment(self::PROVIDER, $endpoint);
        $mapped = self::mapItStartToAnagrafica($raw);

        $this->storeCache($lookupKey, $endpoint, $raw, $mapped);

        return [
            'success' => true,
            'source' => 'api',
            'usage' => $usage,
            'data' => $mapped,
        ];
    }

    public function getUsage(string $endpoint = 'IT-start'): array
    {
        return $this->counter->getUsage(self::PROVIDER, $endpoint);
    }

    /**
     * Arricchimento "completo": combina IT-start + IT-advanced + IT-pec
     * e restituisce un'unica struttura mappata sui campi anagrafica del tenant.
     *
     * Ogni endpoint usa la cache (TTL 30gg) se disponibile. Il counter API
     * viene incrementato solo per le chiamate effettivamente effettuate.
     *
     * @param string $identifier  P.IVA, codice fiscale o ID azienda
     * @param bool   $includeAdvanced  Recupera anche IT-advanced (ATECO, REA, dipendenti, fatturato)
     * @param bool   $includePec  Recupera anche IT-pec (PEC ufficiale)
     */
    public function enrichItalianCompany(
        string $identifier,
        bool $includeAdvanced = true,
        bool $includePec = false,
    ): array {
        $lookupKey = $this->normalizeLookupKey($identifier);
        $endpointsCalled = [];
        $endpointsFromCache = [];
        $merged = [];

        // ── IT-start (base) ─────────────────────────────────────────────
        $startResult = $this->fetchOrCache(
            $lookupKey,
            'IT-start',
            fn () => $this->client->getItalianCompanyStart($identifier),
            fn (array $raw) => self::mapItStartToAnagrafica($raw),
        );
        if (! $startResult['success']) {
            return $startResult;
        }
        $merged = array_merge($merged, array_filter($startResult['data']));
        $startResult['fromCache'] ? $endpointsFromCache[] = 'IT-start' : $endpointsCalled[] = 'IT-start';

        // ── IT-advanced (opzionale) ─────────────────────────────────────
        if ($includeAdvanced) {
            try {
                $advResult = $this->fetchOrCache(
                    $lookupKey,
                    'IT-advanced',
                    fn () => $this->client->getItalianCompanyAdvanced($identifier),
                    fn (array $raw) => self::mapItAdvancedToAnagrafica($raw),
                );
                if ($advResult['success']) {
                    $merged = array_merge($merged, array_filter($advResult['data']));
                    $advResult['fromCache'] ? $endpointsFromCache[] = 'IT-advanced' : $endpointsCalled[] = 'IT-advanced';
                }
            } catch (OpenApiCompanyException $e) {
                // IT-advanced opzionale: non blocchiamo la chiamata principale
            }
        }

        // ── IT-pec (opzionale) ──────────────────────────────────────────
        if ($includePec) {
            try {
                $pecResult = $this->fetchOrCache(
                    $lookupKey,
                    'IT-pec',
                    fn () => $this->client->getItalianCompanyPec($identifier),
                    fn (array $raw) => self::mapItPecToAnagrafica($raw),
                );
                if ($pecResult['success']) {
                    $merged = array_merge($merged, array_filter($pecResult['data']));
                    $pecResult['fromCache'] ? $endpointsFromCache[] = 'IT-pec' : $endpointsCalled[] = 'IT-pec';
                }
            } catch (OpenApiCompanyException $e) {
                // IT-pec opzionale
            }
        }

        return [
            'success'              => true,
            'source'               => empty($endpointsCalled) ? 'cache' : (empty($endpointsFromCache) ? 'api' : 'mixed'),
            'endpoints_called'     => $endpointsCalled,
            'endpoints_from_cache' => $endpointsFromCache,
            'usage'                => $this->counter->getUsage(self::PROVIDER, 'IT-start'),
            'data'                 => $merged,
        ];
    }

    /**
     * Helper: prova a leggere dalla cache, altrimenti chiama l'API e salva.
     * Restituisce ['success', 'data', 'fromCache'].
     */
    private function fetchOrCache(string $lookupKey, string $endpoint, \Closure $apiCall, \Closure $mapper): array
    {
        $cached = $this->getCached($lookupKey, $endpoint);
        if ($cached) {
            return [
                'success'   => true,
                'data'      => $cached->normalized_json,
                'fromCache' => true,
            ];
        }

        if (! $this->counter->canMakeCall(self::PROVIDER, $endpoint)) {
            return [
                'success' => false,
                'error'   => "Limite giornaliero raggiunto per {$endpoint}.",
                'usage'   => $this->counter->getUsage(self::PROVIDER, $endpoint),
            ];
        }

        $raw = $apiCall();
        $this->counter->increment(self::PROVIDER, $endpoint);
        $mapped = $mapper($raw);
        $this->storeCache($lookupKey, $endpoint, $raw, $mapped);

        return [
            'success'   => true,
            'data'      => $mapped,
            'fromCache' => false,
        ];
    }

    /**
     * Mapping IT-advanced → campi anagrafica tenant.
     * IT-advanced fornisce ATECO, REA, dipendenti, fatturato, forma giuridica.
     */
    public static function mapItAdvancedToAnagrafica(array $response): array
    {
        $company = $response['data'][0] ?? $response;

        // Codice ATECO
        $ateco = null;
        if (isset($company['atecoClassification']['ateco']['code'])) {
            $ateco = $company['atecoClassification']['ateco']['code'];
        } elseif (isset($company['ateco']['code'])) {
            $ateco = $company['ateco']['code'];
        } elseif (isset($company['atecoCode'])) {
            $ateco = $company['atecoCode'];
        }

        // REA: numero e città (provincia)
        $reaNumber = null;
        $reaCity   = null;
        if (isset($company['reaCode'])) {
            // formato "MI-1234567"
            $reaCode = $company['reaCode'];
            if (is_string($reaCode) && str_contains($reaCode, '-')) {
                [$reaCity, $reaNumber] = explode('-', $reaCode, 2);
            } else {
                $reaNumber = $reaCode;
            }
        } elseif (isset($company['rea']['number'])) {
            $reaNumber = $company['rea']['number'];
            $reaCity   = $company['rea']['province'] ?? $company['rea']['city'] ?? null;
        }

        return [
            'attivita_ateco' => $ateco,
            'rea_numero'     => $reaNumber,
            'rea_citta'      => $reaCity,
            'forma_giuridica' => $company['legalForm']['code'] ?? $company['legalForm'] ?? null,
            'dipendenti'      => $company['employees'] ?? $company['employeesNumber'] ?? null,
            'fatturato'       => $company['turnover']['amount'] ?? $company['turnover'] ?? null,
            'sito_web'        => $company['website'] ?? null,
            'telefono'        => $company['phoneNumber'] ?? $company['phone'] ?? null,
        ];
    }

    /**
     * Mapping IT-pec → campo PEC.
     */
    public static function mapItPecToAnagrafica(array $response): array
    {
        $company = $response['data'][0] ?? $response;
        return [
            'pec' => $company['pec'] ?? $company['pecAddress'] ?? null,
        ];
    }

    /**
     * Esegue una ricerca aziendale via endpoint IT-search.
     *
     * Per la ricerca NON si usa la cache (i criteri possono variare ad ogni chiamata).
     * Il counter API viene comunque incrementato di 1 per ogni chiamata effettuata.
     */
    public function searchItalianCompanies(
        array $criteria,
        int $skip = 0,
        int $limit = 10,
        bool $dryRun = false,
        array $enrichWith = []
    ): array {
        $endpoint = 'IT-search';

        if (! $this->counter->canMakeCall(self::PROVIDER, $endpoint)) {
            return [
                'success' => false,
                'error'   => 'Limite giornaliero di chiamate API raggiunto.',
                'usage'   => $this->counter->getUsage(self::PROVIDER, $endpoint),
            ];
        }

        $raw = $this->client->searchItalianCompanies($criteria, $skip, $limit, $dryRun, $enrichWith);
        $usage = $this->counter->increment(self::PROVIDER, $endpoint);

        $items   = $raw['data'] ?? [];
        $mapped  = array_map(fn (array $c) => self::mapItStartToAnagrafica(['data' => [$c]]), $items);

        return [
            'success' => true,
            'source'  => 'api',
            'usage'   => $usage,
            'data'    => $mapped,
            'raw'     => $items,
            'meta'    => [
                'skip'    => $skip,
                'limit'   => $limit,
                'count'   => count($items),
                'dryRun'  => $dryRun,
                'total'   => $raw['totalCount'] ?? $raw['total'] ?? null,
            ],
        ];
    }

    public static function mapItStartToAnagrafica(array $response): array
    {
        $company = $response['data'][0] ?? $response;

        $address = $company['address'] ?? [];
        $registeredOffice = $address['registeredOffice'] ?? [];
        $gps = $registeredOffice['gps'] ?? [];

        return [
            'provider_company_id' => $company['id'] ?? null,
            'ragione_sociale' => $company['companyName'] ?? null,
            'partita_iva' => $company['vatCode'] ?? null,
            'codice_fiscale' => $company['taxCode'] ?? null,
            'stato_attivita' => $company['activityStatus'] ?? null,
            'data_registrazione' => $company['registrationDate'] ?? null,
            'codice_sdi' => $company['sdiCode'] ?? null,
            'indirizzo' => self::buildIndirizzo($registeredOffice),
            'comune' => $registeredOffice['town'] ?? null,
            'provincia' => $registeredOffice['province'] ?? null,
            'cap' => $registeredOffice['zipCode'] ?? null,
            'nazione' => $registeredOffice['country'] ?? $address['country'] ?? 'IT',
            'latitudine' => $gps['lat'] ?? $gps['latitude'] ?? null,
            'longitudine' => $gps['lng'] ?? $gps['longitude'] ?? null,
            'data_ultimo_aggiornamento_fonte' => $company['lastUpdateTimestamp'] ?? null,
        ];
    }

    private static function buildIndirizzo(array $office): ?string
    {
        $parts = array_filter([
            $office['street'] ?? $office['streetName'] ?? null,
            $office['streetNumber'] ?? null,
        ]);

        return $parts ? implode(' ', $parts) : ($office['fullAddress'] ?? null);
    }

    private function normalizeLookupKey(string $identifier): string
    {
        return preg_replace('/\s+/', '', strtoupper(trim($identifier)));
    }

    private function getCached(string $lookupKey, string $endpoint): ?CompanyEnrichmentCache
    {
        $record = CompanyEnrichmentCache::where('lookup_key', $lookupKey)
            ->where('endpoint', $endpoint)
            ->first();

        if (! $record || $record->isExpired()) {
            return null;
        }

        return $record;
    }

    private function storeCache(string $lookupKey, string $endpoint, array $raw, array $mapped): void
    {
        $ttlDays = config('openapi_company.cache_ttl_days', 30);
        $now = Carbon::now();

        CompanyEnrichmentCache::updateOrCreate(
            [
                'tenant_id' => app('current_tenant')->id,
                'lookup_key' => $lookupKey,
                'endpoint' => $endpoint,
            ],
            [
                'response_json' => $raw,
                'normalized_json' => $mapped,
                'fetched_at' => $now,
                'expires_at' => $now->copy()->addDays($ttlDays),
                'source_provider' => self::PROVIDER,
                'response_hash' => hash('sha256', json_encode($raw)),
            ]
        );
    }
}
