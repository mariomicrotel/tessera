# Roadmap Implementazione: Estensione Cooperative

**Progetto:** ETS-OK → ETS + Cooperative  
**Architettura:** Multi-tenant Laravel 12 + Vue 3 + Inertia.js  
**Data:** 2026-04-15  
**Stima totale:** ~18-25 giorni di sviluppo

---

## Schema Architetturale Target

```
Tenant
├── organization_type: 'ets' | 'cooperative'
├── cooperative_type: 'lavoro' | 'sociale_a' | 'sociale_b' | 'agricola' | 'comunita' | 'consumo' | 'abitazione' | 'consortile'
│
├── [ETS] Soci → Subscription (annuale) → Incasso (quota)
│
└── [COOP]
    ├── Soci → CooperativeShare (quota capitale permanente)
    ├── Soci → PrestitoSocialeLibretto → PrestitoSocialeMovimento
    ├── Ristorno → RistornoEntry (per socio)
    └── Riserve (legale 30%, indivisibile, rivalutazione)
```

---

## Legenda Modelli Raccomandati

| Modello | Quando usarlo |
|---------|--------------|
| `opus` | Architetture complesse, design pattern, decisioni critiche |
| `sonnet` | Implementazione standard, controller, vue pages, service |
| `haiku` | Migrations ripetitive, CRUD semplici, fix minori |

---

---

# FASE A — Foundation: Tenant Multi-Tipo
**Stima:** 2 giorni  
**Dipendenze:** nessuna  
**Modello:** `sonnet`

---

## Prompt A1 — Migration: Estendi Tenants per Cooperative

**Modello:** `haiku`  
**File da creare:** `database/migrations/YYYY_MM_DD_extend_tenants_for_cooperatives.php`

```
Sono in un progetto Laravel 12 multi-tenant (ETS-OK). Crea una nuova migration
`extend_tenants_for_cooperatives` che aggiunge alla tabella `tenants` i seguenti campi:

- `organization_type` enum('ets','cooperative') default 'ets', after 'slug'
- `cooperative_type` enum('lavoro','sociale_a','sociale_b','agricola','comunita','consumo','abitazione','consortile') nullable, after 'organization_type'
- `codice_fiscale` string(16) nullable unique, after 'domain'
- `partita_iva` string(11) nullable unique, after 'codice_fiscale'
- `numero_iscrizione_albo_coop` string(50) nullable unique, after 'partita_iva'
- `capitale_sottoscritto` decimal(14,2) nullable default null
- `capitale_versato` decimal(14,2) nullable default null

Aggiungi un index su `organization_type`.
Nel down() rimuovi tutte queste colonne.
Rispetta lo stile delle migration esistenti nel progetto (Schema::table, Blueprint $table).
```

---

## Prompt A2 — Estendi Tenant Model e Settings

**Modello:** `sonnet`  
**File da modificare:** `app/Models/Tenant.php`, `app/Models/Settings.php`

```
Nel progetto Laravel 12 ETS-OK, modifica:

1. `app/Models/Tenant.php`:
   - Aggiungi i nuovi fillable: organization_type, cooperative_type, codice_fiscale,
     partita_iva, numero_iscrizione_albo_coop, capitale_sottoscritto, capitale_versato
   - Aggiungi i casts appropriati (decimal:2 per i capitali)
   - Aggiungi metodo helper `isCooperativa(): bool` che restituisce `$this->organization_type === 'cooperative'`
   - Aggiungi metodo `isETS(): bool`
   - Aggiungi metodo `cooperativeTypeLabel(): string` con match su tutti i tipi (lavoro, sociale_a, ecc.)
   - Aggiorna `memberLimit()` e `staffLimit()` per essere uguali ai piani correnti
     (le cooperative useranno gli stessi limiti degli ETS)

2. `app/Models/Settings.php` — metodo statico `get()`:
   Aggiungi le nuove chiavi cooperative a float cast:
   - quota_valore_unitario_coop
   - quota_minima_quote_coop
   - ristorno_percentuale_max
   - riserva_legale_percentuale (default 30)
   - riserva_indivisibile_percentuale (default 3)
   - tasso_interesse_prestito (default 0)

   Aggiorna anche `set()` nessun cambiamento necessario (è generico).
```

---

## Prompt A3 — Shared Props Inertia + Middleware

**Modello:** `sonnet`  
**File da modificare:** `app/Http/Middleware/HandleInertiaRequests.php`

```
In ETS-OK (Laravel 12 + Inertia.js), il file HandleInertiaRequests.php condivide
props globali con tutte le pagine Vue. Il tenant corrente è disponibile tramite
`app('current_tenant')`.

Modifica `share()` per aggiungere ai props condivisi:
- `organization_type`: stringa ('ets' o 'cooperative'), oppure null se no tenant
- `cooperative_type`: stringa nullable (es. 'lavoro', 'sociale_a', ...)
- `is_cooperativa`: bool (helper per Vue)

Assicurati che sia lazy-loaded (usa closure) per non eseguire query inutili
nelle route pubbliche (landing, login) dove non c'è tenant.

Esempio di come recuperare il tenant:
```php
$tenant = app()->bound('current_tenant') ? app('current_tenant') : null;
```

Aggiungi a tutti e tre i valori.
```

---

## Prompt A4 — Settings Controller + Vue: Tab Cooperative

**Modello:** `sonnet`  
**File da modificare:** `app/Http/Controllers/SettingsController.php`, `resources/js/Pages/Settings/Index.vue`

```
In ETS-OK, la pagina Settings/Index.vue gestisce le impostazioni del tenant
tramite tab (Generale, Quote, Email, ecc.).

Modifica:

1. `SettingsController::index()`:
   Aggiungi ai props le nuove impostazioni cooperative:
   - quota_valore_unitario_coop (float, default 50.00)
   - quota_minima_quote_coop (int, default 1)
   - ristorno_percentuale_max (float, default 100)
   - riserva_legale_percentuale (float, default 30)
   - riserva_indivisibile_percentuale (float, default 3)
   - tasso_interesse_prestito (float, default 0)
   Passa anche `is_cooperativa: Settings::get('is_cooperativa', false)`.

2. `SettingsController::update()`:
   Aggiungi validazione e salvataggio per le nuove chiavi solo se
   il tenant è cooperativa (controlla `$tenant->isCooperativa()`).

3. `Settings/Index.vue`:
   Aggiungi un tab "Cooperativa" visibile solo quando `$page.props.is_cooperativa`
   (o `organization_type === 'cooperative'`).
   Il tab contiene:
   - Card "Capitale Sociale": valore_unitario_coop (€), quota_minima_quote_coop (n°)
   - Card "Ristorni": ristorno_percentuale_max (%), riserva_legale_percentuale (%),
     riserva_indivisibile_percentuale (%)
   - Card "Prestito Sociale": tasso_interesse_prestito (%, con nota "0 = nessun prestito attivo")
   Usa lo stesso stile delle card esistenti nel tab Quote (bg-white, shadow, grid, ecc.)
```

---

## Prompt A5 — Onboarding: Selezione Tipo Organizzazione

**Modello:** `sonnet`  
**File da modificare:** Pagina di registrazione tenant o wizard install

```
In ETS-OK (SaaS multi-tenant), durante la registrazione di un nuovo tenant
(o nel wizard di installazione in `/install`), occorre chiedere il tipo di
organizzazione.

Modifica il form di registrazione/onboarding per aggiungere:
1. Campo `organization_type` con radio button stilizzati:
   - "🏛️ ETS / ODV / APS" → value 'ets'
   - "🤝 Cooperativa" → value 'cooperative'

2. Se viene selezionato 'cooperative', mostra un ulteriore select
   per `cooperative_type`:
   - Cooperativa di Lavoro
   - Cooperativa Sociale (Tipo A)
   - Cooperativa Sociale (Tipo B)
   - Cooperativa Agricola
   - Cooperativa di Comunità
   - Cooperativa di Consumo
   - Cooperativa di Abitazione
   - Cooperativa Consortile

3. Nel controller corrispondente, salva `organization_type` e
   `cooperative_type` nel tenant appena creato.

Usa `v-show` per la visibilità condizionale del select cooperative_type.
Stile coerente con il resto del form (Tailwind, dark mode, label/input).
```

---

---

# FASE B — Anagrafica Soci Cooperativa
**Stima:** 2 giorni  
**Dipendenze:** Fase A completata  
**Modello:** `sonnet`

---

## Prompt B1 — Migration: Estendi Members per Cooperative

**Modello:** `haiku`  
**File da creare:** migration `add_cooperative_fields_to_members_table`

```
In ETS-OK (Laravel 12), la tabella `members` gestisce i soci di un ETS.
Per supportare le cooperative, aggiungi queste colonne alla tabella `members`:

- `tipo_persona` enum('fisica','giuridica') default 'fisica', after 'tenant_id'
- `ragione_sociale` string(200) nullable (per persone giuridiche), after 'cognome'
- `partita_iva` string(11) nullable, after 'ragione_sociale'
- `referente_nome` string(100) nullable (nome referente se persona giuridica)
- `referente_cognome` string(100) nullable
- `socio_lavoratore` boolean default false (per cooperative di lavoro)
- `socio_sovventore` boolean default false (per soci che solo investono)
- `socio_onorario` boolean default false
- `data_ammissione_cda` date nullable (data approvazione CDA, diversa da data_iscrizione)
- `numero_quote_capitale` unsignedInteger default 0 (aggiornato automaticamente)

Aggiungi index su (tenant_id, tipo_persona) e (tenant_id, socio_lavoratore).
```

---

## Prompt B2 — Member Model + MemberController: Supporto Persone Giuridiche

**Modello:** `sonnet`  
**File da modificare:** `app/Models/Member.php`, `app/Http/Controllers/MemberController.php`  
**File da modificare:** `resources/js/Pages/Members/Create.vue`, `Edit.vue`

```
In ETS-OK, il modello Member rappresenta solo persone fisiche. Le cooperative
ammettono anche soci persone giuridiche (es. in cooperative consortili).

1. `app/Models/Member.php`:
   - Aggiungi i nuovi fillable: tipo_persona, ragione_sociale, partita_iva,
     referente_nome, referente_cognome, socio_lavoratore, socio_sovventore,
     socio_onorario, data_ammissione_cda, numero_quote_capitale
   - Aggiungi cast: socio_lavoratore, socio_sovventore, socio_onorario (boolean),
     numero_quote_capitale (integer), data_ammissione_cda (date)
   - Aggiungi scope: `scopeLavoratori($q)` → where socio_lavoratore = true
   - Aggiungi metodo `nomeCompleto(): string` che restituisce:
     - Persona fisica: "Cognome Nome"
     - Persona giuridica: ragione_sociale (con referente in parentesi se presente)
   - Aggiungi relazione `cooperativeShares(): HasMany` verso il modello
     `CooperativeShare` (che creeremo nella Fase C)

2. `MemberController::store()` e `update()`:
   - Aggiungi validazione condizionale: se `is_cooperativa` del tenant:
     - tipo_persona required|in:fisica,giuridica
     - ragione_sociale: required_if:tipo_persona,giuridica
     - partita_iva: nullable|digits:11
     - socio_lavoratore: boolean (solo per tipo coop 'lavoro')
   - Se tipo_persona = 'giuridica', nome/cognome diventano nullable

3. `Members/Create.vue` e `Edit.vue`:
   - Aggiungi (visibile solo se `$page.props.is_cooperativa`):
     - Radio "Persona fisica / Persona giuridica" per tipo_persona
     - Se giuridica: mostra campo ragione_sociale, partita_iva,
       referente_nome, referente_cognome (nascondi nome/cognome)
     - Se fisica: mostra i campi normali esistenti
   - Aggiungi checkbox (visibile per coop di lavoro):
     "Socio lavoratore", "Socio sovventore", "Socio onorario"
   - Usa v-show per la visibilità condizionale
```

---

## Prompt B3 — Libro Soci Cooperativa (Estensione LibroSoci)

**Modello:** `sonnet`  
**File da modificare/creare:** `app/Http/Controllers/LibroSociController.php` (esiste)  
**File da modificare/creare:** `resources/js/Pages/LibroSoci.vue` (esiste)

```
In ETS-OK esiste già una pagina LibroSoci che elenca tutti i soci.
Per le cooperative, il libro soci ha requisiti aggiuntivi (normativa cooperative):

1. `LibroSociController`:
   Aggiungi query condizionale: se il tenant è cooperativa, includi
   anche le colonne cooperative (tipo_persona, ragione_sociale, socio_lavoratore,
   numero_quote_capitale) nei dati passati alla view.

   Aggiungi metodo `exportLibroSociCoop(Request $request)` che genera
   un CSV/PDF con le colonne richieste dalla normativa:
   - n° progressivo, tipo_persona, cognome/ragione_sociale, nome,
     codice_fiscale/partita_iva, data_iscrizione, data_ammissione_cda,
     numero_quote, capitale_versato_totale, stato

2. `LibroSoci.vue`:
   - Se `is_cooperativa`, aggiungi colonne extra nella tabella:
     "Tipo" (badge Fisica/Giuridica), "Quote capitale", "Tipo socio"
     (badge colorati: Lavoratore, Sovventore, Onorario, Ordinario)
   - Aggiungi filtro "Tipo socio" (tutti / lavoratori / sovventori / onorari)
   - Mostra totale quote sottoscritte in fondo alla pagina

Route da aggiungere in web.php:
GET app/{tenant}/libro-soci/export-coop → LibroSociController@exportLibroSociCoop
```

---

---

# FASE C — Capitale Sociale (Quote Permanenti)
**Stima:** 3 giorni  
**Dipendenze:** Fase B completata  
**Modello:** `sonnet`

---

## Prompt C1 — Migration: cooperative_shares

**Modello:** `haiku`  
**File da creare:** migration `create_cooperative_shares_table`

```
In ETS-OK (Laravel 12 multi-tenant), crea la migration per la tabella
`cooperative_shares`. Questa tabella rappresenta le quote di capitale
sociale sottoscritte da ciascun socio di una cooperativa.

Schema:
- id (bigIncrements)
- tenant_id (char 36, FK → tenants.id, onDelete cascade)
- member_id (bigInteger, FK → members.id, onDelete cascade)
- numero_quote (unsignedInteger, default 1) — numero di quote possedute
- valore_unitario (decimal 10,2) — valore di una singola quota in €
- totale_sottoscritto (decimal 12,2) — numero_quote × valore_unitario
- totale_versato (decimal 12,2, default 0) — quanto effettivamente versato
- data_sottoscrizione (date)
- data_versamento (date nullable) — quando il versamento è completato
- data_riscatto (date nullable) — quando il socio esce e riscatta
- motivo_riscatto (string nullable)
- status (enum: 'sottoscritta','parzialmente_versata','versata','riscattata','annullata', default 'sottoscritta')
- note (text nullable)
- timestamps

Indici:
- UNIQUE(tenant_id, member_id) — un record per socio (aggiornato incrementalmente)
- INDEX(tenant_id, status)

Scope BelongsToTenant (tenant_id).
```

---

## Prompt C2 — Model CooperativeShare + Service

**Modello:** `sonnet`  
**File da creare:** `app/Models/CooperativeShare.php`, `app/Services/CapitaleSocialeService.php`

```
In ETS-OK (Laravel 12), crea:

1. `app/Models/CooperativeShare.php`:
   - use BelongsToTenant trait
   - fillable: tutti i campi della migration (C1)
   - casts: valore_unitario/totale_sottoscritto/totale_versato (decimal:2),
     data_sottoscrizione/data_versamento/data_riscatto (date),
     status (string)
   - Relations:
     - `member(): BelongsTo` → Member
   - Scopes:
     - `scopeAttive($q)`: whereIn('status', ['sottoscritta','parzialmente_versata','versata'])
     - `scopeVersate($q)`: where('status', 'versata')
   - Accessor `isVersata(): bool`
   - Accessor `isRiscattata(): bool`

2. `app/Services/CapitaleSocialeService.php`:
   Metodi:
   
   a) `sottoscriviQuote(Member $member, int $numeroQuote, float $valoreUnitario, Carbon $data): CooperativeShare`
      - Crea o aggiorna il record CooperativeShare del socio
      - Calcola totale_sottoscritto = numero_quote × valore_unitario
      - Crea un Incasso di tipo 'capitale' per la sottoscrizione
        (solo quota non ancora versata, se crei prima nota)
      - Genera PrimaNotaEntry con rendiconto_code = 'capitale_sociale'
      - Aggiorna members.numero_quote_capitale
   
   b) `versaQuote(CooperativeShare $share, float $importoVersato, Carbon $data): CooperativeShare`
      - Aggiorna totale_versato
      - Se totale_versato >= totale_sottoscritto → status = 'versata'
      - Altrimenti → status = 'parzialmente_versata'
      - Crea Incasso tipo 'capitale' + PrimaNotaEntry
   
   c) `riscattaQuote(CooperativeShare $share, string $motivo, Carbon $data): CooperativeShare`
      - Imposta status = 'riscattata', data_riscatto, motivo_riscatto
      - Crea Spesa per il rimborso del capitale al socio (o nota manuale)
      - Aggiorna members.numero_quote_capitale = 0

   d) `getSituazioneCapitale(): array`
      - Restituisce: totale_sottoscritto, totale_versato, numero_soci_con_quote,
        capitale_da_versare (totale_sottoscritto - totale_versato)

Usa transazioni DB (DB::transaction) in ogni metodo che crea più record.
```

---

## Prompt C3 — Controller + Routes: Gestione Quote Capitale

**Modello:** `sonnet`  
**File da creare:** `app/Http/Controllers/CooperativeShareController.php`  
**File da modificare:** `routes/web.php`

```
In ETS-OK crea `CooperativeShareController` con i seguenti metodi:

- `index(Request $request)`: lista quote di tutti i soci con filtri
  (status, ricerca socio). Paginazione 50. Passa anche
  `situazione_capitale` dall'CapitaleSocialeService.
  Inertia::render('CooperativeShares/Index', [...])

- `create()`: form per sottoscrivere quote a un socio.
  Passa `members` (solo attivi, select), `valore_unitario_default`
  da Settings::get('quota_valore_unitario_coop', 50).
  Inertia::render('CooperativeShares/Create', [...])

- `store(Request $request)`: valida e chiama CapitaleSocialeService::sottoscriviQuote().
  Redirect a index con flash success.

- `show(CooperativeShare $share)`: dettaglio quota singolo socio.
  Inertia::render('CooperativeShares/Show', [...])

- `versa(Request $request, CooperativeShare $share)`: POST.
  Valida importo_versato (numeric, min:0.01).
  Chiama CapitaleSocialeService::versaQuote().
  Redirect a show con flash success.

- `riscatta(Request $request, CooperativeShare $share)`: POST.
  Valida motivo_riscatto (required).
  Chiama CapitaleSocialeService::riscattaQuote().
  Redirect a index con flash success.

- `export(Request $request)`: StreamedResponse CSV con BOM UTF-8.
  Colonne: socio, tipo_persona, data_sottoscrizione, numero_quote,
  valore_unitario, totale_sottoscritto, totale_versato, status, data_riscatto.

Middleware: nega accesso se `!$tenant->isCooperativa()`.

Aggiungi in routes/web.php dentro il gruppo tenant:
```php
Route::middleware(['auth', 'tenant', 'cooperative'])->prefix('capitale-sociale')->name('capitale-sociale.')->group(function () {
    Route::get('/', [CooperativeShareController::class, 'index'])->name('index');
    Route::get('/create', [CooperativeShareController::class, 'create'])->name('create');
    Route::post('/', [CooperativeShareController::class, 'store'])->name('store');
    Route::get('/export', [CooperativeShareController::class, 'export'])->name('export');
    Route::get('/{share}', [CooperativeShareController::class, 'show'])->name('show');
    Route::post('/{share}/versa', [CooperativeShareController::class, 'versa'])->name('versa');
    Route::post('/{share}/riscatta', [CooperativeShareController::class, 'riscatta'])->name('riscatta');
});
```

Crea anche il middleware `cooperative` in `app/Http/Middleware/RequireCooperativa.php`
che verifica `app('current_tenant')->isCooperativa()` e fa abort(403) altrimenti.
```

---

## Prompt C4 — Vue Pages: CooperativeShares

**Modello:** `sonnet`  
**File da creare:** `resources/js/Pages/CooperativeShares/Index.vue`, `Create.vue`, `Show.vue`

```
In ETS-OK (Vue 3 + Inertia + Tailwind), crea le pagine Vue per la gestione
delle quote di capitale sociale (FASE C):

1. `CooperativeShares/Index.vue`:
   Props: shares (paginated), situazione_capitale, filters
   - Header con 4 KPI card: Totale Sottoscritto, Totale Versato,
     Da Versare (in arancio se > 0), N° Soci con Quote
   - Filtri: ricerca per nome socio, filtro per status (tutti/attive/riscattate)
   - Tabella: Socio, Tipo, N° Quote, Valore Unit., Sottoscritto, Versato,
     Status (badge colorato), Azioni (Dettaglio, Versa, Riscatta)
   - Bottone "Nuova Sottoscrizione" + "Esporta CSV"

2. `CooperativeShares/Create.vue`:
   Props: members (per select), valore_unitario_default
   Form con useForm:
   - SearchableMemberSelect per member_id (componente esistente)
   - numero_quote (input number, min 1)
   - valore_unitario (input number, pre-compilato da default)
   - totale_calcolato (computed, readonly: numero_quote × valore_unitario)
   - data_sottoscrizione (date, default today)
   - note (textarea)
   Tasto "Sottoscrivi Quote"

3. `CooperativeShares/Show.vue`:
   Props: share (con member caricato)
   - Card informativa: socio, quote, sottoscritto, versato, status
   - Storico versamenti (se disponibile)
   - Sezione "Versa quota": form inline con importo_versato se non versata
   - Sezione "Riscatta quota": form con motivo_riscatto, data_riscatto
     (visibile solo se status != 'riscattata')

Usa AppLayout, ArrowLeftIcon, CheckCircleIcon, BanknotesIcon da heroicons.
Bottoni, badge, card nello stile esistente del progetto (dark mode, rounded-lg).
```

---

---

# FASE D — Prestito Sociale
**Stima:** 3 giorni  
**Dipendenze:** Fase C completata  
**Modello:** `sonnet`

> **Cos'è:** Le cooperative possono raccogliere prestiti dai propri soci
> (con limiti di legge), remunerandoli con interessi. Ogni socio ha un
> "libretto" personale con movimenti di deposito/prelievo e interessi periodici.

---

## Prompt D1 — Migration: prestito_sociale_libretti + movimenti

**Modello:** `haiku`  
**File da creare:** migration `create_prestito_sociale_tables`

```
In ETS-OK, crea UNA SOLA migration che crea due tabelle:

1. `prestito_sociale_libretti`:
   - id (bigIncrements)
   - tenant_id (char 36, FK tenants cascade)
   - member_id (bigInteger, FK members cascade)
   - numero_libretto (string 20) — codice univoco per tenant
   - saldo_attuale (decimal 14,2, default 0)
   - tasso_interesse_annuo (decimal 5,4, default 0) — es. 0.0200 = 2%
   - data_apertura (date)
   - data_chiusura (date nullable)
   - status (enum: 'attivo','sospeso','chiuso', default 'attivo')
   - note (text nullable)
   - timestamps
   - UNIQUE(tenant_id, numero_libretto)
   - INDEX(tenant_id, member_id)
   - INDEX(tenant_id, status)

2. `prestito_sociale_movimenti`:
   - id (bigIncrements)
   - tenant_id (char 36, FK tenants cascade)
   - libretto_id (bigInteger, FK prestito_sociale_libretti cascade)
   - tipo (enum: 'deposito','prelievo','interessi','ritenuta_fiscale','rettifica')
   - importo (decimal 14,2) — sempre positivo
   - segno (enum: 'dare','avere') — dire = prelievo/uscita, avere = deposito/entrata
   - saldo_dopo (decimal 14,2) — saldo libretto dopo questo movimento
   - data_valuta (date)
   - data_registrazione (date)
   - anno_competenza (smallInteger nullable) — per calcolo interessi
   - mese_competenza (tinyInteger nullable)
   - aliquota_ritenuta (decimal 5,4 nullable) — es. 0.2600 = 26%
   - importo_ritenuta (decimal 14,2 nullable)
   - importo_netto (decimal 14,2 nullable) — importo - ritenuta
   - descrizione (string 255 nullable)
   - incasso_id (bigInteger nullable FK incassi, per depositi)
   - spesa_id (bigInteger nullable FK spese, per prelievi)
   - timestamps
   - INDEX(tenant_id, libretto_id, data_valuta)
   - INDEX(tenant_id, tipo)
```

---

## Prompt D2 — PrestitoSocialeService

**Modello:** `opus`  
**File da creare:** `app/Services/PrestitoSocialeService.php`  
**File da creare:** `app/Models/PrestitoSocialeLibretto.php`, `PrestitoSocialeMovimento.php`

```
In ETS-OK, crea il servizio per gestire il prestito sociale cooperativo.
La ritenuta fiscale sugli interessi è 26% (DPR 600/73 art. 26).

1. Modelli:

   `PrestitoSocialeLibretto`:
   - BelongsToTenant, fillable completo, casts appropriati
   - Relations: member() BelongsTo, movimenti() HasMany ordinati per data_valuta
   - Metodo `saldoAggiornatoAl(Carbon $data): decimal`

   `PrestitoSocialeMovimento`:
   - BelongsToTenant, fillable completo, casts
   - Relations: libretto() BelongsTo

2. `PrestitoSocialeService`:

   a) `apriLibretto(Member $member, float $tassoAnnuo, Carbon $data): PrestitoSocialeLibretto`
      - Genera numero_libretto: "PS-{ANNO}-{5 cifre sequenziali per tenant}"
      - Salva con saldo_attuale = 0

   b) `deposita(PrestitoSocialeLibretto $lib, float $importo, Carbon $data, string $desc = ''): PrestitoSocialeMovimento`
      - Crea movimento tipo 'deposito', segno 'avere'
      - Aggiorna saldo_attuale del libretto
      - Crea Incasso tipo 'prestito_sociale' con genera_prima_nota = true
      - PrimaNotaEntry con rendiconto_code = 'prestito_sociale'

   c) `preleva(PrestitoSocialeLibretto $lib, float $importo, Carbon $data, string $desc = ''): PrestitoSocialeMovimento`
      - Verifica che saldo >= importo (lancia ValidationException altrimenti)
      - Crea movimento tipo 'prelievo', segno 'dare'
      - Aggiorna saldo_attuale
      - Crea Spesa per il prelievo con genera_prima_nota = true
      NOTA: per legge i prelievi superiori a €5000 richiedono prenotazione
      (preavviso 24h). Aggiungi parametro `$prenotato = false` e lancia
      ValidationException se importo > 5000 e !$prenotato.

   d) `calcolaInteressi(int $anno, int $mese): Collection`
      - Per ogni libretto attivo del tenant: calcola interessi maturati
        nel mese (saldo medio × tasso_annuo / 12)
      - Calcola ritenuta = interessi × 0.26
      - Crea movimenti tipo 'interessi' e 'ritenuta_fiscale'
      - Restituisce Collection con riepilogo
      - Usa DB::transaction

   e) `chiudiLibretto(PrestitoSocialeLibretto $lib, Carbon $data): void`
      - Verifica saldo = 0 (altrimenti errore)
      - Imposta status = 'chiuso', data_chiusura

   f) `getEstrattoConto(PrestitoSocialeLibretto $lib, Carbon $dal, Carbon $al): array`
      - Restituisce movimenti nel periodo + saldo iniziale + saldo finale

Usa DB::transaction in tutti i metodi che scrivono più tabelle.
Lancia eccezioni tipizzate (\RuntimeException per regole di business).
```

---

## Prompt D3 — Controller + Vue: Prestito Sociale

**Modello:** `sonnet`  
**File da creare:** `app/Http/Controllers/PrestitoSocialeController.php`  
**File da creare:** `resources/js/Pages/PrestitoSociale/Index.vue`, `Show.vue`

```
In ETS-OK crea il modulo Prestito Sociale per le cooperative.

1. `PrestitoSocialeController`:
   - `index()`: lista libretti attivi (con saldo, socio, tasso).
     Aggiungi totale_depositi e totale_interessi_maturati nell'anno.
     Inertia::render('PrestitoSociale/Index')

   - `create()`: form apertura nuovo libretto.
     Passa members (solo attivi senza libretto esistente).
     Inertia::render('PrestitoSociale/Create')

   - `store(Request)`: chiama PrestitoSocialeService::apriLibretto()

   - `show(PrestitoSocialeLibretto $libretto)`: dettaglio con movimenti
     paginati e estratto conto. Inertia::render('PrestitoSociale/Show')

   - `deposita(Request, PrestitoSocialeLibretto $libretto)`: POST.
     Chiama service::deposita()

   - `preleva(Request, PrestitoSocialeLibretto $libretto)`: POST.
     Campo `prenotato` (bool) nel form.
     Chiama service::preleva()

   - `calcolaInteressi(Request)`: POST.
     Valida anno (int), mese (1-12).
     Chiama service::calcolaInteressi().
     Restituisce flash con riepilogo (N libretti, totale interessi, totale ritenute).

   - `exportEstrattoConto(Request, PrestitoSocialeLibretto)`: StreamedResponse PDF
     oppure CSV (per ora CSV va bene).

2. `PrestitoSociale/Index.vue`:
   - KPI card: N° Libretti Attivi, Totale Depositi, Interessi Anno, Ritenute Anno
   - Tabella libretti: Socio, N° Libretto, Saldo, Tasso, Data Apertura, Azioni
   - Bottone "Calcola Interessi Mese" → modal con anno/mese select

3. `PrestitoSociale/Show.vue`:
   - Info libretto (socio, tasso, saldo)
   - Form "Deposita" e form "Preleva" affiancati
   - Tabella movimenti (tipo badge, importo, saldo dopo, data)
   - Link "Scarica estratto conto (CSV)"

Route nel gruppo cooperative di web.php:
GET/POST /prestito-sociale con naming prefix 'prestito-sociale.'
```

---

---

# FASE E — Ristorni
**Stima:** 2 giorni  
**Dipendenze:** Fase C completata  
**Modello:** `sonnet`

> **Cos'è:** I ristorni sono la restituzione ai soci di una quota degli utili
> proporzionale alle operazioni effettuate con la cooperativa.
> Tassati al 30% fisso (L. 142/2001 art. 3 c. 2).

---

## Prompt E1 — Migration: ristorni + ristorno_entries

**Modello:** `haiku`

```
In ETS-OK, crea migration `create_ristorni_tables` con due tabelle:

1. `ristorni`:
   - id (bigIncrements)
   - tenant_id (char 36, FK tenants cascade)
   - anno (smallInteger unsigned) — anno fiscale del ristorno
   - importo_totale_deliberato (decimal 14,2) — deliberato in assemblea
   - aliquota_ritenuta (decimal 5,4, default 0.3000) — 30%
   - data_delibera_assemblea (date)
   - data_pagamento (date nullable)
   - verbale_id (bigInteger nullable FK verbali) — verbale assemblea collegato
   - status (enum: 'deliberato','in_pagamento','pagato','annullato', default 'deliberato')
   - note (text nullable)
   - timestamps
   - INDEX(tenant_id, anno), INDEX(tenant_id, status)

2. `ristorno_entries`:
   - id (bigIncrements)
   - tenant_id (char 36, FK tenants cascade)
   - ristorno_id (bigInteger FK ristorni cascade)
   - member_id (bigInteger FK members)
   - importo_lordo (decimal 12,2)
   - aliquota_ritenuta (decimal 5,4) — copiata da ristorno
   - importo_ritenuta (decimal 12,2) — lordo × aliquota
   - importo_netto (decimal 12,2) — lordo - ritenuta
   - data_pagamento (date nullable)
   - status (enum: 'deliberato','pagato', default 'deliberato')
   - timestamps
   - UNIQUE(ristorno_id, member_id) — un'entry per socio per ristorno
   - INDEX(tenant_id, member_id)
```

---

## Prompt E2 — Ristorno Model + RistorniController + Vue

**Modello:** `sonnet`  
**File da creare:** `app/Models/Ristorno.php`, `app/Models/RistornoEntry.php`  
**File da creare:** `app/Http/Controllers/RistorniController.php`  
**File da creare:** `resources/js/Pages/Ristorni/Index.vue`, `Create.vue`, `Show.vue`

```
In ETS-OK, implementa il modulo Ristorni completo:

1. Modelli:
   `Ristorno`:
   - BelongsToTenant, fillable, casts (date, decimal:2)
   - entries(): HasMany → RistornoEntry
   - verbale(): BelongsTo → Verbale (nullable)
   - Accessor `importoNettoTotale(): float` = sum entries.importo_netto
   - Accessor `importoRitenutaTotale(): float` = sum entries.importo_ritenuta

   `RistornoEntry`:
   - BelongsToTenant, fillable, casts
   - ristorno(): BelongsTo → Ristorno
   - member(): BelongsTo → Member

2. `RistorniController`:
   - `index()`: lista ristorni per anno (filtro anno). KPI: totale deliberato,
     totale pagato, totale ritenute versate.
   - `create()`: form per nuova delibera.
     Passa: anni_disponibili (ultimi 5), membri_attivi (per distribuzione),
     importo_utile_netto (da contabilità dell'anno, se disponibile).
   - `store(Request)`:
     Valida: anno, importo_totale_deliberato, data_delibera_assemblea,
     array entries[member_id, importo_lordo] (almeno 1).
     In DB::transaction:
     - Crea Ristorno
     - Per ogni entry: calcola ritenuta e netto, crea RistornoEntry
     - Crea PrimaNotaEntry per il totale (rendiconto_code = 'ristorni_soci')
   - `show(Ristorno)`: dettaglio con entries, totali, status pagamento.
   - `markPagato(Request, Ristorno)`: POST. Imposta status pagato su entries
     selezionate (checkbox), data_pagamento.
   - `export(Ristorno)`: StreamedResponse CSV con: socio, CF/PIVA,
     importo_lordo, ritenuta 30%, importo_netto.

3. Vue Pages:
   `Ristorni/Index.vue`:
   - KPI: Ultimo ristorno anno, Totale deliberato, Da pagare, Ritenute da versare
   - Tabella: Anno, Data delibera, Importo deliberato, Netto, Status, Azioni
   - Bottone "Nuova Delibera"

   `Ristorni/Create.vue`:
   - Campi: anno (select), importo_totale (number), data_delibera (date),
     aliquota_ritenuta (number, default 30, readonly con nota "fisso per legge")
   - Sezione distribuzione: tabella soci con importo_lordo inseribile per ciascuno.
     Mostra in tempo reale: lordo, ritenuta (30%), netto per ogni riga.
     Totale row in fondo.
   - Bottone "Registra delibera"

   `Ristorni/Show.vue`:
   - Riepilogo ristorno (anno, date, totali)
   - Tabella entries con checkbox per "segna come pagato"
   - Download CSV

Routes nel gruppo cooperative.
```

---

---

# FASE F — Contabilità Cooperativa
**Stima:** 3 giorni  
**Dipendenze:** Fasi C, D, E completate  
**Modello:** `sonnet`

---

## Prompt F1 — Nuovo Tipo Incasso 'capitale' e 'prestito_sociale'

**Modello:** `haiku`  
**File da modificare:** `app/Models/Incasso.php`, `app/Http/Controllers/IncassoController.php`  
**File da modificare:** `resources/js/Pages/Incassi/Create.vue`

```
In ETS-OK, il modello Incasso ha un campo `type` con valori
'quota', 'donazione', 'altro'. Per le cooperative aggiungi:
- 'capitale' — versamento quota capitale sociale
- 'prestito_sociale' — deposito in libretto prestito sociale

1. `app/Models/Incasso.php`:
   - Aggiungi costanti TYPE_CAPITALE = 'capitale' e TYPE_PRESTITO_SOCIALE = 'prestito_sociale'
   - Aggiungi scopi scopeCapitale() e scopePrestitoSociale()

2. `app/Http/Controllers/IncassoController.php`:
   - In `store()`, accetta i nuovi tipi nella validazione
   - Se type = 'capitale', usa rendiconto_code = 'A01' (Entrate da soci per quote)
     o crea una voce apposita 'capitale_sociale'
   - Se type = 'prestito_sociale', usa rendiconto_code dedicato

3. `Incassi/Create.vue`:
   - Nel select del tipo, aggiungi (visibile solo se is_cooperativa):
     - "Versamento quota capitale" (value: capitale)
     - "Deposito prestito sociale" (value: prestito_sociale)
   - Aggiungi v-if per mostrare il campo per selezionare il libretto
     prestito (select libretto_id) quando type = 'prestito_sociale'
```

---

## Prompt F2 — Schema Rendiconto per Cooperative

**Modello:** `opus`  
**File da modificare:** `app/Services/RendicontoCassaSchema.php` (esiste)  
**File da creare:** `app/Services/RendicontoCassaSchemaCooperativa.php`

```
In ETS-OK, la classe `RendicontoCassaSchema` contiene lo schema del rendiconto
per cassa degli ETS (Modello D, GU 18-04-2020). Questo schema è statico e
specifico per gli ETS.

Per le cooperative, il rendiconto è diverso (Bilancio di esercizio semplificato):
le voci sono organizzate diversamente, includono capitale sociale, ristorni, riserve.

Crea `app/Services/RendicontoCassaSchemaCooperativa.php` come classe statica
analoga a `RendicontoCassaSchema`, con schema adatto alle cooperative:

SCHEMA ENTRATE:
- A01: Quote e contributi soci (capitale versato nell'anno)
- A02: Proventi da prestazioni/servizi (ricavi mutualistici)
- A03: Proventi da attività commerciale
- A04: Contributi pubblici e privati
- A05: Rimborsi e recuperi
- A06: Interessi attivi e proventi finanziari
- A07: Rivalutazioni attivi
- A08: Proventi straordinari
- A09: Utilizzo riserve

SCHEMA USCITE:
- B01: Acquisto beni e materiali
- B02: Servizi e prestazioni esterne
- B03: Personale (salari, stipendi, contributi)
- B04: Affitti e locazioni
- B05: Ammortamenti (nota: in cassa = acquisti beni)
- B06: Oneri finanziari (interessi passivi prestito sociale)
- B07: Ristorni ai soci (con ritenuta fiscale)
- B08: Accantonamento riserva legale (30% utili)
- B09: Accantonamento riserva indivisibile (3% utili min)
- B10: Tasse e imposte (IRAP, IRES agevolata)
- B11: Rimborso capitale ai soci uscenti
- B12: Uscite straordinarie

Ogni voce deve avere: codice, label, sezione (A=entrate/B=uscite), descrizione.
Il formato deve essere identico a quello usato in RendicontoCassaSchema.
```

---

## Prompt F3 — Report: Conto Economico + Situazione Capitale Cooperativa

**Modello:** `sonnet`  
**File da modificare:** `app/Http/Controllers/AccountingReportController.php`  
**File da creare:** `resources/js/Pages/Reports/SituazioneCapitale.vue`

```
In ETS-OK, il controller AccountingReportController ha già il metodo
`contoEconomico()` per gli ETS. 

Per le cooperative aggiungi:

1. `contoEconomicoCooperativa(Request $request)`:
   Funziona come contoEconomico() ma usa RendicontoCassaSchemaCooperativa.
   Aggiunge alle props:
   - capitale_versato_totale (da cooperative_shares)
   - ristorni_anno (da ristorni per anno selezionato)
   - totale_prestito_sociale (somma saldi libretti)
   Inertia::render('Reports/ContoEconomicoCooperativa', [...])

2. `situazioneCapitale(Request $request)`:
   Report di situazione patrimoniale della cooperativa:
   - capitale_sottoscritto_totale (sum cooperative_shares.totale_sottoscritto)
   - capitale_versato_totale (sum cooperative_shares.totale_versato)
   - capitale_da_versare (diff)
   - soci_per_status (count per status quote)
   - storico_capitale (per anno: da data_sottoscrizione)
   - totale_prestito_sociale (saldo totale libretti attivi)
   - riserve_accantonate (da prima_nota_entries con rendiconto_code in ['riserva_legale', 'riserva_indivisibile'])
   Inertia::render('Reports/SituazioneCapitale', [...])

3. Vue `Reports/SituazioneCapitale.vue`:
   - Sezione "Capitale Sociale": progress bar versato/sottoscritto,
     numero soci, valore medio per socio
   - Sezione "Prestito Sociale": totale depositi, interessi nell'anno
   - Sezione "Riserve": legale vs indivisibile vs totale utili
   - Bottone export CSV

Routes da aggiungere:
- GET reports/conto-economico-coop → contoEconomicoCooperativa (solo cooperative)
- GET reports/situazione-capitale → situazioneCapitale (solo cooperative)
```

---

---

# FASE G — Navigation & Dashboard Cooperativa
**Stima:** 1 giorno  
**Dipendenze:** Tutte le fasi precedenti  
**Modello:** `haiku`

---

## Prompt G1 — AppLayout: Menu Condizionale ETS vs Cooperativa

**Modello:** `haiku`  
**File da modificare:** `resources/js/Layouts/AppLayout.vue`

```
In ETS-OK, il file AppLayout.vue contiene la navigazione principale.
Attualmente ci sono NavLink per Quote Sociali, Donazioni, Scadenzario, ecc.

Modifica AppLayout.vue:

Il componente riceve dai shared props di Inertia `organization_type` e `is_cooperativa`.
Usa `const { organization_type } = usePage().props` per accedervi.

1. Sostituisci i link esistenti di "Quote sociali" e "Scadenzario quote" con:
   ```
   <!-- Solo ETS -->
   <NavLink v-if="!is_cooperativa" href="route quote-sociali.index" ...>Quote sociali</NavLink>
   <NavLink v-if="!is_cooperativa" href="route scadenzario-quote.index" ...>Scadenzario quote</NavLink>
   <!-- Solo Cooperative -->
   <NavLink v-if="is_cooperativa" href="route capitale-sociale.index" ...>Capitale Sociale</NavLink>
   <NavLink v-if="is_cooperativa" href="route prestito-sociale.index" ...>Prestito Sociale</NavLink>
   <NavLink v-if="is_cooperativa" href="route ristorni.index" ...>Ristorni</NavLink>
   ```

2. Nella sezione "Report":
   ```
   <!-- Solo ETS -->
   <NavLink v-if="!is_cooperativa" ...>Scadenzario quote</NavLink>
   <!-- Solo Cooperative -->
   <NavLink v-if="is_cooperativa" ...>Situazione Capitale</NavLink>
   ```

3. Applica le stesse modifiche ai `ResponsiveNavLink` nel menu mobile.

4. In nessun caso rimuovere i link comuni (Prima Nota, Spese, Conti, Soci, Verbali, ecc.).
```

---

## Prompt G2 — Dashboard: KPI Cooperative

**Modello:** `sonnet`  
**File da modificare:** `app/Http/Controllers/DashboardController.php`  
**File da modificare:** `resources/js/Pages/Dashboard.vue`

```
In ETS-OK, la Dashboard mostra KPI come: incassi mese, spese mese, soci attivi,
saldi conti. Modifica per aggiungere KPI specifici per cooperative.

1. `DashboardController::index()`:
   Se $tenant->isCooperativa():
   - Aggiungi ai props:
     - capitale_versato_totale: sum(cooperative_shares.totale_versato) where status != 'riscattata'
     - totale_prestito_sociale: sum(libretti.saldo_attuale) where status = 'attivo'
     - ristorno_ultimo_anno: Ristorno::where('anno', now()->year-1)->sum('importo_totale_deliberato')
     - soci_lavoratori_attivi: Member::where('socio_lavoratore', true)->where('stato', 'attivo')->count()
     - quote_da_versare: sum(cooperative_shares.totale_sottoscritto - totale_versato) where status != 'versata'

2. `Dashboard.vue`:
   Aggiungi sezione KPI cooperativa dopo i KPI standard (visibile solo se is_cooperativa):
   ```
   <div v-if="is_cooperativa" class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-4">
     <!-- Capitale Versato (verde) -->
     <!-- Prestito Sociale (blu) -->
     <!-- Ultimo Ristorno (indigo) -->
     <!-- Soci Lavoratori (emerald, solo per coop di lavoro) -->
   </div>
   ```
   Ogni KPI card ha: icona, label, valore formattato (€ per importi, numero per conteggi).
   
   Aggiungi anche card "Quote da versare" con alert in arancio se > 0.
```

---

---

# FASE H — Compilazione e Test Finale
**Stima:** 1 giorno  
**Dipendenze:** Tutte le fasi  
**Modello:** `haiku`

---

## Prompt H1 — Seeder: Dati Demo per Cooperative

**Modello:** `haiku`

```
In ETS-OK, crea `database/seeders/CooperativaSeeder.php` che:
1. Trova o crea un tenant con organization_type = 'cooperative',
   cooperative_type = 'lavoro', name = 'Cooperativa Demo'
2. Crea 5 soci lavoratori (socio_lavoratore = true)
3. Per ognuno sottoscrive 10 quote da €50 (usa CapitaleSocialeService)
4. Apre 3 libretti prestito sociale con un deposito iniziale di €1000
5. Crea un ristorno demo per l'anno scorso di €500 totali distribuiti ai soci
6. Imposta Settings: quota_valore_unitario_coop = 50,
   quota_minima_quote_coop = 10, riserva_legale_percentuale = 30

Mostra `$this->command->info()` per ogni step.
Registra il seeder in DatabaseSeeder.php condizionalmente
(APP_ENV = 'local' or 'testing').
```

---

## Prompt H2 — Build Finale

**Modello:** `haiku`

```
Esegui il build del frontend nel container Docker:
docker compose exec app npm run build

Se ci sono errori di importazione (componenti non trovati, props mancanti),
elencali e proponi i fix necessari.

Dopo il build, verifica che le nuove route siano registrate correttamente:
docker compose exec app php artisan route:list --path=capitale-sociale
docker compose exec app php artisan route:list --path=prestito-sociale
docker compose exec app php artisan route:list --path=ristorni
```

---

---

## Riepilogo Fasi e Timeline

| Fase | Descrizione | Giorni | Modello | Dipendenze |
|------|-------------|--------|---------|------------|
| A | Foundation: Tenant multi-tipo | 2 | sonnet/haiku | — |
| B | Anagrafica soci cooperativa | 2 | sonnet | A |
| C | Capitale Sociale (quote permanenti) | 3 | sonnet | B |
| D | Prestito Sociale (libretti) | 3 | sonnet/opus | C |
| E | Ristorni | 2 | sonnet | C |
| F | Contabilità cooperativa | 3 | sonnet/opus | C+D+E |
| G | Navigation & Dashboard | 1 | haiku | F |
| H | Seeder + Build finale | 1 | haiku | G |
| **TOT** | | **17** | | |

---

## File Critici Modificati/Creati per Fase

```
Fase A:
  M  database/migrations/..._extend_tenants_for_cooperatives.php
  M  app/Models/Tenant.php
  M  app/Models/Settings.php
  M  app/Http/Middleware/HandleInertiaRequests.php
  M  app/Http/Controllers/SettingsController.php
  M  resources/js/Pages/Settings/Index.vue
  +  app/Http/Middleware/RequireCooperativa.php

Fase B:
  M  database/migrations/..._add_cooperative_fields_to_members.php
  M  app/Models/Member.php
  M  app/Http/Controllers/MemberController.php
  M  resources/js/Pages/Members/Create.vue
  M  resources/js/Pages/Members/Edit.vue
  M  app/Http/Controllers/LibroSociController.php
  M  resources/js/Pages/LibroSoci.vue

Fase C:
  +  database/migrations/..._create_cooperative_shares_table.php
  +  app/Models/CooperativeShare.php
  +  app/Services/CapitaleSocialeService.php
  +  app/Http/Controllers/CooperativeShareController.php
  +  resources/js/Pages/CooperativeShares/Index.vue
  +  resources/js/Pages/CooperativeShares/Create.vue
  +  resources/js/Pages/CooperativeShares/Show.vue
  M  routes/web.php

Fase D:
  +  database/migrations/..._create_prestito_sociale_tables.php
  +  app/Models/PrestitoSocialeLibretto.php
  +  app/Models/PrestitoSocialeMovimento.php
  +  app/Services/PrestitoSocialeService.php
  +  app/Http/Controllers/PrestitoSocialeController.php
  +  resources/js/Pages/PrestitoSociale/Index.vue
  +  resources/js/Pages/PrestitoSociale/Show.vue
  M  routes/web.php

Fase E:
  +  database/migrations/..._create_ristorni_tables.php
  +  app/Models/Ristorno.php
  +  app/Models/RistornoEntry.php
  +  app/Http/Controllers/RistorniController.php
  +  resources/js/Pages/Ristorni/Index.vue
  +  resources/js/Pages/Ristorni/Create.vue
  +  resources/js/Pages/Ristorni/Show.vue
  M  routes/web.php

Fase F:
  M  app/Models/Incasso.php
  M  app/Http/Controllers/IncassoController.php
  M  resources/js/Pages/Incassi/Create.vue
  +  app/Services/RendicontoCassaSchemaCooperativa.php
  M  app/Http/Controllers/AccountingReportController.php
  +  resources/js/Pages/Reports/SituazioneCapitale.vue
  M  routes/web.php

Fase G:
  M  resources/js/Layouts/AppLayout.vue
  M  app/Http/Controllers/DashboardController.php
  M  resources/js/Pages/Dashboard.vue

Fase H:
  +  database/seeders/CooperativaSeeder.php
  M  database/seeders/DatabaseSeeder.php
```

---

## Note Legali da Tenere Presenti

1. **Prestito sociale**: il limite massimo raccoglibile da una cooperativa è
   fissato da Banca d'Italia (attualmente €300.000 per socio ordinario).
   Aggiungere warning in UI se il saldo supera soglie di attenzione.

2. **Ristorni**: tassazione al 30% fisso (non variabile). Non confondere con
   dividendi (vietati nelle cooperative a mutualità prevalente).

3. **Riserva legale**: obbligatoria al 30% degli utili netti (L. 59/1992 art. 11).

4. **Riserva indivisibile**: obbligatoria al 3% degli utili netti (L. 59/1992 art. 11).

5. **Voto**: nelle cooperative vige il principio "1 testa 1 voto" — NON
   proporzionale al capitale. Il modulo elezioni esistente è già corretto.

6. **Albo Cooperative**: obbligatorio per tutte le cooperative (D.Lgs. 220/2002).
   Il numero_iscrizione_albo_coop è dati obbligatori per tenant cooperativa.
