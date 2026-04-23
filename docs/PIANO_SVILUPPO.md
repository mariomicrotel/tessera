# 🗺️ Piano di Sviluppo – ETS-OK / ContabETS

> **Obiettivo:** colmare i gap rispetto ai software di contabilità aziendali (es. QuickMastro),
> mantenendo la specializzazione per ETS e Cooperative italiane.
>
> **Legenda modelli:**
> - 🧠 `opus` — architetture complesse, decisioni strutturali, schemi multi-tabella
> - ⚙️ `sonnet` — implementazione feature complete (migration + model + controller + Vue)
> - ⚡ `haiku` — task atomici: form, componenti UI, fix, CRUD semplici

---

## PRIORITÀ 1 — Critico (Cooperative: obblighi di legge)

---

### AREA A · Registri IVA e Liquidazione Periodica

Le cooperative sono soggetti IVA a tutti gli effetti. Senza registri IVA l'applicazione
non può essere usata come unico software gestionale da una cooperativa.

---

#### A1 · Architettura modulo IVA

**Modello:** 🧠 `opus`
**Perché:** decisione strutturale che impatta migration, modelli, servizi e UI di tutta l'area fiscale.

```
Sei un architetto software senior specializzato in applicazioni gestionali italiane con Laravel 12.

Il progetto ETS-OK (https://github.com/pfumarola/ETS-OK) gestisce ETS e Cooperative italiane.
Stack: PHP 8.4, Laravel 12, Vue 3, Inertia.js, MySQL 8.

Devo aggiungere un modulo IVA completo. Il sistema attuale ha:
- Tabella `prima_nota_entries` (semplificata, solo Modello D ETS, no dare/avere)
- Tabella `incassi` (incassi da soci/donatori)
- Tabella `spese` (uscite generiche)
- Tabella `conti` (cassa/banca)
- Trait `BelongsToTenant` su tutti i modelli (multi-tenant via tenant_id)

Progetta l'architettura completa per:
1. Registro IVA acquisti (fatture passive con IVA detraibile)
2. Registro IVA vendite (fatture attive con IVA a debito)
3. Liquidazione periodica IVA (mensile/trimestrale)
4. Codici IVA configurabili (22%, 10%, 4%, 0%, esente, fuori campo)
5. Integrazione con prima nota esistente (il movimento IVA genera anche la registrazione contabile)

Per ogni tabella indica: nome, colonne con tipo, indici, relazioni.
Per ogni servizio indica: nome classe, metodi principali, responsabilità.
Indica come gestire il multi-tenant (tenant_id) e il periodo di competenza IVA.
Non scrivere ancora il codice, solo l'architettura dettagliata con schema ER e diagramma dei servizi.
```

---

#### A2 · Migration e modelli IVA

**Modello:** ⚙️ `sonnet`
**Perché:** implementazione standard di migration + Eloquent, nessuna ambiguità architettonica.

```
Nel progetto ETS-OK (Laravel 12, PHP 8.4, multi-tenant con BelongsToTenant trait) devo
implementare le migration e i modelli Eloquent per il modulo IVA.

Basandoti sull'architettura definita, crea:

1. Migration `create_codici_iva_table` — codici aliquota IVA configurabili per tenant:
   - id, tenant_id, codice (varchar 10), descrizione, percentuale (decimal 5,2),
     tipo (enum: normale/esente/fuori_campo/non_imponibile), attivo (bool), timestamps

2. Migration `create_fatture_passive_table` — fatture fornitori ricevute:
   - id, tenant_id, supplier_id (FK suppliers), numero_fattura, data_fattura, data_ricezione,
     data_registrazione, imponibile (decimal 10,2), iva (decimal 10,2), totale (decimal 10,2),
     codice_iva_id (FK), stato (enum: da_pagare/pagata/parziale/stornata),
     note, timestamps, soft_deletes

3. Migration `create_fatture_attive_table` — fatture emesse:
   - id, tenant_id, member_id nullable (FK members), cliente_nome, cliente_cf_piva,
     numero_fattura, data_emissione, imponibile, iva, totale, codice_iva_id,
     stato (enum: emessa/pagata/stornata/sdi_inviata), sdi_id nullable,
     note, timestamps, soft_deletes

4. Migration `create_liquidazioni_iva_table`:
   - id, tenant_id, anno, periodo (tinyint), tipo_periodo (enum: mensile/trimestrale),
     iva_debito, iva_credito, saldo, credito_precedente, saldo_finale,
     status (enum: bozza/definitiva), data_chiusura nullable, timestamps

5. Modelli Eloquent per ognuna delle tabelle con: fillable, casts, relazioni, scope tenant,
   BelongsToTenant trait, eventuali computed attributes (es. `saldo_da_pagare`).

Usa le convenzioni del progetto: snake_case per DB, camelCase per PHP,
soft deletes dove indicato, tenant_id obbligatorio.
```

---

#### A3 · IvaService — liquidazione e calcolo saldi

**Modello:** ⚙️ `sonnet`
**Perché:** logica di business pura senza UI, ben delimitata.

```
Nel progetto ETS-OK (Laravel 12) crea la classe `App\Services\IvaService` con i seguenti metodi:

1. `calcolaLiquidazione(int $tenantId, int $anno, int $periodo, string $tipoPeriodo): array`
   - Recupera tutte le fatture passive e attive del periodo con IVA
   - Calcola: iva_debito (da fatture attive), iva_credito (da fatture passive)
   - Recupera credito_precedente dalla liquidazione precedente (se presente)
   - Ritorna array con: iva_debito, iva_credito, saldo, credito_precedente, saldo_finale
   - Se saldo_finale > 0: IVA a debito (da versare); se < 0: credito da riportare

2. `chiudiLiquidazione(int $tenantId, int $anno, int $periodo, string $tipoPeriodo): LiquidazioneIva`
   - Chiama calcolaLiquidazione e salva il record su DB con status='definitiva'
   - Genera le registrazioni in prima nota (IVA a debito → conto IVA, IVA a credito → conto IVA)
   - Idempotente: se liquidazione definitiva esiste già, lancia IvaAlreadyClosedException

3. `getRegistroAcquisti(int $tenantId, int $anno, int $periodo): Collection`
   - Ritorna fatture passive ordinate per data registrazione con join codice_iva

4. `getRegistroVendite(int $tenantId, int $anno, int $periodo): Collection`
   - Ritorna fatture attive ordinate per data emissione con join codice_iva

Includi: PHPDoc completo, gestione eccezioni custom, test unitario Pest con mock del DB.
```

---

#### A4 · Controller e UI Registri IVA

**Modello:** ⚙️ `sonnet`
**Perché:** pattern controller Inertia + Vue già ben consolidato nel progetto.

```
Nel progetto ETS-OK (Laravel 12 + Vue 3 + Inertia.js + TailwindCSS) crea il modulo UI
per i Registri IVA e la Liquidazione periodica.

CONTROLLER `App\Http\Controllers\IvaController`:
- `registroAcquisti(Request $request)` → Inertia render 'Iva/RegistroAcquisti'
  con paginazione fatture passive filtrabili per anno/periodo
- `registroVendite(Request $request)` → Inertia render 'Iva/RegistroVendite'
- `liquidazione(Request $request)` → Inertia render 'Iva/Liquidazione'
  con riepilogo IVA debito/credito del periodo selezionato
- `chiudiLiquidazione(Request $request)` → chiude il periodo via IvaService

VUE PAGES (resources/js/Pages/Iva/):

1. `RegistroAcquisti.vue` — tabella con colonne:
   Data reg. | Fornitore | N° Fattura | Imponibile | Aliquota | IVA | Totale | Stato
   Filtri: anno, mese/trimestre. Export PDF/CSV.

2. `RegistroVendite.vue` — stessa struttura lato vendite.

3. `Liquidazione.vue` — card riepilogativa:
   IVA a debito | IVA a credito | Credito precedente | SALDO FINALE
   Bottone "Chiudi periodo" con conferma modale. Storico liquidazioni chiuse.

Rispetta le convenzioni del progetto: layout AppLayout.vue, componenti Heroicons,
stile TailwindCSS già presente, gestione multi-tenant via `$page.props.tenant`.
Aggiungi le route in `routes/web.php` nel gruppo tenant.
```

---

### AREA B · Contabilità a Doppia Partita

Per le cooperative con volume d'affari oltre soglia è obbligatorio il bilancio civilistico
(Stato Patrimoniale + Conto Economico), che richiede la partita doppia.

---

#### B1 · Architettura Piano dei Conti e Partita Doppia

**Modello:** 🧠 `opus`
**Perché:** decisione critica e irreversibile — il modello della partita doppia condiziona
tutta la contabilità futura. Deve essere fatta bene al primo tentativo.

```
Sei un esperto di contabilità italiana e di Laravel 12.

Il progetto ETS-OK ha attualmente una prima nota semplificata (`prima_nota_entries`)
basata su Modello D ETS (non partita doppia). Devo aggiungere un modulo di
CONTABILITÀ A DOPPIA PARTITA per le cooperative.

I due sistemi devono coesistere:
- ETS continuano con Modello D (prima nota semplificata)
- Cooperative usano partita doppia con piano dei conti standard

Progetta l'architettura completa per:

1. PIANO DEI CONTI (CoA - Chart of Accounts):
   - Struttura gerarchica: Classe → Conto Mastro → Sottoconto → Conto Analitico
   - Codifiche standard italiane (1xxx Attivo, 2xxx Passivo, 3xxx Costi, 4xxx Ricavi, 5xxx/6xxx)
   - Piano dei conti template per cooperative (precaricato)
   - Personalizzabile per tenant

2. REGISTRAZIONI CONTABILI (Journal Entries):
   - Testata: data, numero progressivo, causale, descrizione, documento_riferimento
   - Righe: conto, segno (dare/avere), importo, centro_di_costo nullable
   - Vincolo: somma dare = somma avere (bilanciamento obbligatorio)
   - Collegamento a FatturaPassiva / FatturaAttiva / Incasso / Spesa (polymorphic)

3. BILANCIO:
   - Stato Patrimoniale (Attivo/Passivo/Netto)
   - Conto Economico (Ricavi - Costi = Utile/Perdita)
   - Saldi conti per periodo

4. INTEGRAZIONE con il sistema esistente:
   - Gli `Incassi` e `Spese` esistenti devono generare automaticamente la registrazione
     in partita doppia quando il tenant è una cooperativa
   - Migrazione dati storici dalla prima nota semplificata

Schema ER dettagliato, diagramma servizi, strategia di migrazione.
Non scrivere codice, solo architettura e decisioni motivate.
```

---

#### B2 · Migration e modelli Piano dei Conti

**Modello:** ⚙️ `sonnet`
**Perché:** implementazione diretta dell'architettura definita in B1.

```
Nel progetto ETS-OK (Laravel 12, multi-tenant) implementa le migration e i modelli
per il modulo di contabilità a doppia partita.

MIGRATION 1 — `create_chart_of_accounts_table` (Piano dei Conti):
  id, tenant_id, parent_id nullable (self-referential FK),
  codice (varchar 10, unique per tenant), nome (varchar 100),
  tipo (enum: attivo/passivo/patrimonio_netto/costo/ricavo),
  livello (tinyint 1-4), attivo (bool default true), di_sistema (bool),
  timestamps

MIGRATION 2 — `create_journal_entries_table` (Testate registrazioni):
  id, tenant_id, data_registrazione (date), numero_progressivo (int),
  causale (varchar 100), descrizione (text nullable),
  documento_riferimento (varchar 50 nullable),
  journalable_type nullable, journalable_id nullable (morphTo),
  chiusa (bool default false), timestamps

MIGRATION 3 — `create_journal_entry_lines_table` (Righe partita doppia):
  id, journal_entry_id (FK), conto_id (FK chart_of_accounts),
  segno (enum: dare/avere), importo (decimal 12,2),
  cost_center_id nullable (FK da implementare dopo),
  descrizione_riga (varchar 200 nullable), timestamps

MIGRATION 4 — `create_coa_templates_table` (Piani dei conti template):
  id, nome (es. 'Cooperativa Standard Italia'), tipo_organizzazione,
  dati (json con struttura completa), attivo, timestamps

MODELLI Eloquent:
- `ChartOfAccount`: BelongsToTenant, self-referential (parent/children),
  scope per tipo, metodo `saldoAl(Carbon $data): float`
- `JournalEntry`: BelongsToTenant, hasMany(JournalEntryLine), morphTo(journalable),
  validazione bilanciamento (dare == avere) nel metodo `save()` o via observer
- `JournalEntryLine`: belongsTo(JournalEntry), belongsTo(ChartOfAccount)

Include un CoaSeeder che popola il piano dei conti standard per cooperative italiane
(almeno 30 conti significativi nelle 5 classi).
```

---

#### B3 · Servizio Bilancio Civilistico

**Modello:** ⚙️ `sonnet`
**Perché:** logica contabile complessa ma ben delimitata, senza UI.

```
Nel progetto ETS-OK crea `App\Services\BilancioCivilisticoService` per generare
il bilancio civilistico delle cooperative.

Metodi richiesti:

1. `getStatoPatrimoniale(int $tenantId, Carbon $al): array`
   Ritorna struttura:
   {
     attivo: { corrente: [...conti con saldo], immobilizzato: [...], totale: float },
     passivo: { corrente: [...], lungo_termine: [...], patrimonio_netto: [...], totale: float },
     totale_attivo: float, totale_passivo: float, differenza: float (deve essere 0)
   }

2. `getContoEconomico(int $tenantId, Carbon $dal, Carbon $al): array`
   Ritorna struttura:
   {
     ricavi: { operativi: [...], finanziari: [...], totale: float },
     costi: { operativi: [...], personale: [...], ammortamenti: [...], finanziari: [...], totale: float },
     utile_perdita: float
   }

3. `getSaldiConti(int $tenantId, Carbon $dal, Carbon $al): Collection`
   Saldo dare/avere per ogni conto nel periodo.

4. `esportaPdf(int $tenantId, int $anno): string` — path del PDF generato con DomPDF.

Il saldo di ogni conto si calcola sommando le righe `journal_entry_lines` filtrate per periodo.
Per i conti di tipo attivo/costo: saldo = dare - avere.
Per i conti di tipo passivo/patrimonio/ricavo: saldo = avere - dare.

Includi: gestione errori, cache del bilancio per performance, PHPDoc.
```

---

## PRIORITÀ 2 — Importante (Completezza gestionale)

---

### AREA C · Ciclo Passivo Fornitori

Il modello `Supplier` esiste ma è stub (solo name, email, phone, senza tenant_id).
Serve un ciclo passivo completo.

---

#### C1 · Anagrafica Fornitori completa

**Modello:** ⚙️ `sonnet`
**Perché:** CRUD standard con campi specifici italiani (P.IVA, SDI, ecc.).

```
Nel progetto ETS-OK il modello `App\Models\Supplier` è stub (fillable: name, email, phone)
e non ha tenant_id. Estendilo e crea il modulo completo.

1. MIGRATION `upgrade_suppliers_table`:
   Aggiungi: tenant_id (FK tenants, dopo name), ragione_sociale, partita_iva (varchar 11),
   codice_fiscale (varchar 16), codice_sdi (varchar 7), pec (varchar),
   indirizzo, cap, citta, provincia, nazione (default 'IT'),
   iban (varchar 34 nullable), condizioni_pagamento (enum: immediato/30gg/60gg/90gg),
   categoria (enum: beni/servizi/professionista/altro), note (text nullable),
   attivo (bool default true), timestamps (già presenti), soft_deletes

2. MODELLO `Supplier` aggiornato:
   BelongsToTenant, fillable completo, casts, scope `attivi()`,
   relazione `fatturePassive()`, attributo `nomeCompleto()` (ragione_sociale ?: name)

3. CONTROLLER `SupplierController` (resource completo):
   index (paginato, filtro per nome/piva/categoria), create, store, show, edit, update, destroy
   Validation rules separate in `StoreSupplierRequest` e `UpdateSupplierRequest`

4. VUE PAGES (resources/js/Pages/Suppliers/):
   - `Index.vue`: tabella con ricerca, filtri, paginazione
   - `Create.vue` / `Edit.vue`: form completo con campi italiani
   - `Show.vue`: dettaglio fornitore + storico fatture passive

5. Route resource in routes/web.php nel gruppo tenant.

Segui le convenzioni del progetto (AppLayout, Heroicons, Tailwind, Inertia).
```

---

#### C2 · Registrazione Fatture Passive

**Modello:** ⚙️ `sonnet`
**Perché:** feature completa ma segue il pattern già usato per Incassi e Spese.

```
Nel progetto ETS-OK crea il modulo completo per la gestione delle fatture passive
(fatture di acquisto da fornitori).

Basati sulla migration `fatture_passive` già definita nell'Area A2. Crea:

1. CONTROLLER `FatturaPassivaController`:
   - `index`: lista fatture con filtri (fornitore, stato, periodo, importo min/max), paginazione
   - `create` / `store`: registrazione nuova fattura con selezione fornitore, codice IVA, conto di costo
   - `show`: dettaglio fattura con allegato PDF
   - `edit` / `update`: modifica fattura (solo se stato=da_pagare)
   - `paga`: marca come pagata, registra il pagamento in prima nota e partita doppia
   - `destroy`: soft delete (solo bozze)
   Ogni store/update genera automaticamente:
     - Registrazione in `journal_entries` (dare: conto di costo + IVA credito, avere: debito fornitore)
     - Voce nel registro IVA acquisti

2. VUE PAGES (resources/js/Pages/FatturePassive/):
   - `Index.vue`: tabella + filtri, badge stato colorati, totali piede pagina
   - `Create.vue`: form con autocomplete fornitore, calcolo automatico IVA (imponibile × aliquota)
   - `Show.vue`: dettaglio + bottone "Registra pagamento" con modale (data, conto, importo)

3. SCADENZIARIO PASSIVO: in `Index.vue` aggiungi tab "In scadenza" con fatture
   da_pagare ordinate per data scadenza (calcolata da condizioni_pagamento fornitore).

Includi: upload allegato PDF fattura (via Media/Attachment già presente nel progetto).
```

---

#### C3 · Scadenziario Pagamenti Dashboard Widget

**Modello:** ⚡ `haiku`
**Perché:** componente UI isolato, nessuna logica di business nuova.

```
Nel progetto ETS-OK (Vue 3 + Inertia.js + TailwindCSS) crea un componente Vue
`resources/js/Components/Dashboard/ScadenziarioPagamentiWidget.vue`.

Il componente riceve come prop `fatture` (array di oggetti con:
id, fornitore_nome, numero_fattura, totale, data_scadenza, giorni_alla_scadenza).

Renderizza una card con:
- Titolo "Pagamenti in scadenza" + icona ExclamationTriangle
- Lista delle fatture ordinate per data_scadenza crescente
- Per ogni fattura: nome fornitore, numero, importo €, data scadenza
- Badge colorato: rosso se scaduta, arancione se entro 7gg, giallo se entro 30gg, verde altrimenti
- Footer: totale da pagare e link "Vai allo scadenziario"
- Se lista vuota: messaggio "Nessun pagamento in scadenza"

Usa Heroicons e classi Tailwind già presenti nel progetto.
Esporta il componente e importalo in Dashboard.vue mostrandolo solo se
`$page.props.is_cooperativa` o se ci sono fatture passive nel sistema.
```

---

### AREA D · Cespiti e Ammortamenti

---

#### D1 · Architettura modulo Cespiti

**Modello:** 🧠 `opus`
**Perché:** il modulo cespiti ha logica fiscale specifica (coefficienti ministeriali, deducibilità)
e interagisce con bilancio e prima nota — decisione strutturale.

```
Progetta l'architettura del modulo Cespiti per il progetto ETS-OK (Laravel 12, multi-tenant).

Il progetto ha già un modello `Asset.php` (da verificare nel codice).
Devo implementare un Registro Cespiti conforme alla normativa italiana:

Requisiti funzionali:
1. Anagrafica cespite: categoria fiscale (es. Attrezzature 15%, Autovetture 25%,
   Software 33%, ecc.), data acquisto, costo storico, fornitore, fattura di acquisto
2. Piano di ammortamento: coefficiente (da normativa o personalizzato),
   percentuale primo anno ridotta al 50%, metodo (ordinario/accelerato/ridotto/anticipato)
3. Calcolo automatico quote annuali di ammortamento
4. Fondo ammortamento e valore residuo netto
5. Dismissione/vendita cespite (plusvalenza/minusvalenza)
6. Stampa Registro Cespiti (PDF)
7. Integrazione con partita doppia: la quota annuale genera registrazione automatica
   (dare: ammortamento, avere: fondo ammortamento)

Elenca: tabelle necessarie con colonne, servizi, relazioni con modelli esistenti (Asset, Supplier).
Verifica prima il contenuto attuale di `app/Models/Asset.php` per evitare duplicati.
Schema ER e diagramma servizi. Non scrivere codice.
```

---

#### D2 · CespitiService — calcolo ammortamenti

**Modello:** ⚙️ `sonnet`
**Perché:** logica matematica pura, ben testabile, nessuna ambiguità.

```
Nel progetto ETS-OK crea `App\Services\CespitiService`.

Metodi:

1. `calcolaPianoAmmortamento(Asset $cespite): array`
   Ritorna array di righe per ogni anno di vita utile:
   [{anno, quota_ammortamento, fondo_cumulato, valore_residuo}, ...]
   Regola: primo anno = 50% del coefficiente annuo (norma italiana).
   Ammortamento completo quando valore_residuo ≤ 0.

2. `registraQuotaAnnuale(Asset $cespite, int $anno): JournalEntry`
   Calcola la quota per l'anno dato e genera la registrazione in partita doppia:
   DARE: conto "Ammortamento [categoria]" (costo)
   AVERE: conto "Fondo Ammortamento [categoria]" (passivo)
   Aggiorna `fondo_ammortamento_cumulato` sul cespite.
   Idempotente: se la registrazione per quell'anno esiste già, ritorna quella esistente.

3. `registraAmmortamentiAnno(int $tenantId, int $anno): int`
   Esegue `registraQuotaAnnuale` per tutti i cespiti attivi del tenant.
   Ritorna il numero di cespiti processati.

4. `dismetti(Asset $cespite, Carbon $data, float $prezzoCessione, int $contoId): JournalEntry`
   Calcola plusvalenza/minusvalenza: valore_residuo - prezzo_cessione.
   Genera la registrazione di dismissione.
   Aggiorna stato cespite a 'dismesso'.

Includi PHPDoc, gestione eccezioni, test Pest per ogni metodo.
```

---

#### D3 · UI Registro Cespiti

**Modello:** ⚙️ `sonnet`
**Perché:** UI complessa (tabella + piano ammortamento visivo) ma pattern consolidato.

```
Nel progetto ETS-OK (Vue 3 + Inertia.js) crea l'interfaccia utente per il Registro Cespiti.

CONTROLLER `CespiteController`:
- `index`: lista cespiti con filtri (categoria, stato, anno acquisto), saldo fondo ammortamento
- `create/store`: form nuovo cespite con anteprima piano ammortamento in tempo reale
- `show`: dettaglio + piano ammortamento tabulare + pulsante "Registra quota anno corrente"
- `stampaRegistro`: genera PDF del Registro Cespiti completo

VUE PAGES (resources/js/Pages/Cespiti/):

1. `Index.vue`: tabella con colonne:
   Descrizione | Categoria | Data acquisto | Costo | Fondo amm. | Valore residuo | Stato
   KPI in testa: totale immobilizzazioni, totale fondi, valore netto contabile

2. `Create.vue`: form + pannello laterale che mostra in tempo reale il piano
   ammortamento (tabella anni × quote) aggiornato mentre l'utente inserisce i dati

3. `Show.vue`: card dettaglio + tabella piano ammortamento con evidenza anno corrente
   + storico registrazioni contabili + pulsante "Dismetti cespite" con modale

Includi il filtro per anno nel registro per permettere la stampa annuale.
```

---

### AREA E · Centri di Costo

---

#### E1 · Modulo Centri di Costo

**Modello:** ⚙️ `sonnet`
**Perché:** feature autonoma, pattern CRUD + tag sulle registrazioni esistenti.

```
Nel progetto ETS-OK aggiungi la gestione dei Centri di Costo (Cost Centers).

1. MIGRATION `create_cost_centers_table`:
   id, tenant_id, codice (varchar 10), nome (varchar 100), descrizione (text nullable),
   responsabile_id (FK members nullable), attivo (bool), timestamps

2. MIGRATION `add_cost_center_to_journal_entry_lines`:
   Aggiungi cost_center_id (FK nullable) a `journal_entry_lines`

3. MIGRATION `add_cost_center_to_prima_nota_entries`:
   Aggiungi cost_center_id (FK nullable) a `prima_nota_entries` (per ETS)

4. MODELLO `CostCenter`: BelongsToTenant, fillable, relazioni

5. CONTROLLER `CostCenterController` (resource):
   CRUD standard + metodo `report(Request $request, CostCenter $center)`:
   ritorna movimenti attribuiti al centro nel periodo selezionato, totali dare/avere, saldo

6. VUE PAGES (resources/js/Pages/CostCenters/):
   - `Index.vue`: tabella centri con saldo YTD per ognuno
   - `Create.vue` / `Edit.vue`: form semplice
   - `Show.vue`: movimenti del centro con filtro periodo + grafico a barre mensile

7. Aggiorna i form di `JournalEntry` e `PrimaNota` per permettere la selezione
   opzionale del centro di costo su ogni riga.

Rispetta convenzioni del progetto (AppLayout, BelongsToTenant, Heroicons, Tailwind).
```

---

### AREA F · Compensi a Terzi e Ritenute

---

#### F1 · Gestione Ritenute d'Acconto

**Modello:** ⚙️ `sonnet`
**Perché:** logica fiscale specifica ma pattern simile ai Ristorni già implementati.

```
Nel progetto ETS-OK aggiungi il modulo per la gestione dei compensi a terzi
(professionisti, collaboratori) con ritenuta d'acconto.

1. MIGRATION `create_compensi_table`:
   id, tenant_id, supplier_id (FK suppliers, tipo=professionista),
   data_compenso, descrizione_prestazione,
   importo_lordo (decimal 10,2), aliquota_ritenuta (decimal 5,2 default 20.00),
   importo_ritenuta (decimal 10,2 computed), importo_netto (decimal 10,2),
   data_pagamento_netto (date nullable), data_versamento_ritenuta (date nullable),
   anno_competenza, mese_competenza,
   status (enum: da_pagare/pagato/ritenuta_versata), note, timestamps

2. SERVIZIO `CompensaService`:
   - `calcola(float $lordo, float $aliquota): array` → {lordo, ritenuta, netto}
   - `registraPagamento(Compenso $c, Carbon $data, int $contoId): void`
     Genera journal entry: DARE compenso (costo), AVERE ritenuta da versare (passivo) + cassa/banca
   - `versaRitenuta(int $tenantId, int $anno, int $mese): void`
     Marca ritenute del periodo come versate, genera journal entry verso Erario

3. CONTROLLER + VUE `CompensaController`:
   - Lista compensi con filtri anno/mese/fornitore/status
   - Form nuovo compenso con calcolo automatico ritenuta
   - Report mensile "Ritenute da versare" (usato per F24)
   - Export CSV per precompilare la Certificazione Unica (CU)

4. WIDGET dashboard: "Ritenute da versare questo mese" con importo e bottone F24.
```

---

## PRIORITÀ 3 — Ottimizzazione (Valore aggiunto)

---

### AREA G · Business Intelligence e Reporting Avanzato

---

#### G1 · Dashboard Analytics con Grafici

**Modello:** ⚙️ `sonnet`
**Perché:** UI-heavy, usa librerie JS esistenti, nessuna logica di backend complessa.

```
Nel progetto ETS-OK (Vue 3 + Inertia.js + TailwindCSS) aggiungi grafici analitici
alla dashboard amministrativa.

Installa Chart.js via npm: `npm install chart.js vue-chartjs`

Crea il componente `resources/js/Components/Dashboard/AnalyticsCharts.vue` con:

1. GRAFICO 1 — Andamento Incassi/Uscite ultimi 12 mesi (BarChart):
   - Dataset 1: incassi mensili (verde)
   - Dataset 2: spese mensili (rosso)
   - Asse X: mesi, asse Y: importo €

2. GRAFICO 2 — Composizione Soci per stato (DoughnutChart):
   - Attivi / In attesa / Morosi / Cessati
   - Legenda con conteggio per categoria

3. GRAFICO 3 — Saldi Conti nel tempo (LineChart, solo cassa+banca):
   - Evoluzione saldo liquido ultimi 6 mesi
   - Una linea per conto (colori distinti)

4. GRAFICO 4 — KPI Cooperative (solo se is_cooperativa):
   - BarChart: Capitale versato vs Prestito Sociale vs Ristorni (confronto 3 anni)

Backend: aggiungi a `DashboardController` i dati aggregati mensili con query ottimizzate
(GROUP BY YEAR/MONTH), passali come props Inertia.
Mostra i grafici in fondo alla dashboard, con lazy load (v-if visibile solo quando
l'utente scrolla).
```

---

#### G2 · Report Esportazioni Avanzate

**Modello:** ⚡ `haiku`
**Perché:** aggiunta di export su feature già esistenti, pattern già presente nel progetto.

```
Nel progetto ETS-OK aggiungi l'esportazione avanzata in Excel (XLSX) e PDF
alle sezioni già esistenti che ora esportano solo CSV o PDF base.

Per ogni sezione seguente, aggiungi un pulsante "Esporta Excel":

1. Lista Soci → XLSX con fogli: "Soci Attivi", "Morosi", "Cessati"
   Colonne: Nome, Cognome, Email, CF, Data iscrizione, Quota pagata, Stato

2. Prima Nota → XLSX con colonne: Data, Conto, Voce rendiconto, Descrizione, Dare, Avere, Saldo progressivo

3. Registro IVA Acquisti/Vendite → XLSX conforme al formato richiesto dall'Agenzia delle Entrate

4. Situazione Capitale (Cooperative) → XLSX con: Socio, Quote, Versato, Da versare, Percentuale

Usa il package `maatwebsite/excel` (Laravel Excel). Crea una classe Export dedicata
per ognuna delle 4 sezioni in `app/Exports/`.
Aggiungi le route e i pulsanti nei componenti Vue esistenti.
Stile Excel: intestazione colorata (blu #1e40af), righe alternate grigio chiaro, totali in grassetto.
```

---

### AREA H · Fatturazione Elettronica SDI (Ciclo Attivo)

---

#### H1 · Architettura integrazione SDI

**Modello:** 🧠 `opus`
**Perché:** integrazione con sistema esterno (SDI Agenzia delle Entrate),
normativa complessa, scelte architetturali critiche.

```
Progetta l'integrazione del progetto ETS-OK con il Sistema di Interscambio (SDI)
dell'Agenzia delle Entrate per la fatturazione elettronica B2B e B2G italiana.

Contesto attuale:
- Il sistema può emettere fatture/ricevute PDF per soci e donazioni
- Non esiste ancora la generazione XML FatturaPA (formato 1.2)
- Non esiste la ricezione fatture passive XML da SDI

Progetta:

1. CICLO ATTIVO (emissione):
   - Generazione XML FatturaPA 1.2 da FatturaAttiva
   - Firma digitale (o integrazione con intermediario: Aruba, Namirial, ecc.)
   - Invio tramite API intermediario o canale diretto SDI
   - Gestione stati: inviata/consegnata/scartata/accettata/rifiutata
   - Conservazione sostitutiva 10 anni

2. CICLO PASSIVO (ricezione):
   - Ricezione XML fatture fornitori via webhook dell'intermediario
   - Parsing XML → FatturaPassiva su DB
   - Notifica admin per nuova fattura ricevuta

3. SCELTE ARCHITETTURALI da valutare:
   - Approccio diretto SDI vs intermediario API (pro/contro)
   - Package PHP consigliati per generazione XML FatturaPA
   - Gestione asincrona (queue) per invii e ricezioni
   - Storage sicuro degli XML per 10 anni

Fornisci: architettura completa, scelte motivate, package consigliati,
stima complessità implementativa (giorni/uomo).
Non scrivere codice, solo architettura e decisioni.
```

---

#### H2 · Generazione XML FatturaPA

**Modello:** ⚙️ `sonnet`
**Perché:** generazione XML con struttura fissa, ben documentata dalla normativa.

```
Nel progetto ETS-OK (Laravel 12) implementa la generazione di XML FatturaPA 1.2
per le fatture attive delle cooperative.

Usa il package `php-fatturapa/php-fatturapa` o genera l'XML manualmente tramite
SimpleXMLElement o DOMDocument (scegli l'approccio più manutenibile).

Crea `App\Services\FatturaPAService` con:

1. `generaXML(FatturaAttiva $fattura): string`
   Genera XML completo FatturaPA 1.2 con:
   - FatturaElettronicaHeader: DatiTrasmissione (progressivo, codice SDI destinatario),
     CedentePrestatore (tenant: CF, PIVA, indirizzo), CessionarioCommittente (cliente)
   - FatturaElettronicaBody: DatiGenerali (numero, data, tipo documento TD01),
     DatiBeniServizi (linee con descrizione, quantità, prezzo, aliquota IVA),
     DatiPagamento (modalità, importo, data scadenza)

2. `validaXML(string $xml): array` → lista errori di validazione contro schema XSD ufficiale

3. `salvaXML(FatturaAttiva $fattura): string` → salva su storage e ritorna il path

4. `nomeFile(FatturaAttiva $fattura): string`
   Formato: {PIVA_cedente}_{progressivo}.xml (es. IT12345678901_00001.xml)

Includi test Pest che verificano: XML ben formato, presenza campi obbligatori,
correttezza calcolo IVA, formato nome file.
```

---

## Riepilogo Priorità e Stime

| # | Area | Feature | Modello | Priorità | Stima |
|---|------|---------|---------|----------|-------|
| A1 | IVA | Architettura modulo IVA | 🧠 opus | 🔴 P1 | 2h |
| A2 | IVA | Migration e modelli IVA | ⚙️ sonnet | 🔴 P1 | 3h |
| A3 | IVA | IvaService liquidazione | ⚙️ sonnet | 🔴 P1 | 4h |
| A4 | IVA | UI Registri IVA | ⚙️ sonnet | 🔴 P1 | 5h |
| B1 | Contabilità | Architettura partita doppia | 🧠 opus | 🔴 P1 | 3h |
| B2 | Contabilità | Migration piano dei conti | ⚙️ sonnet | 🔴 P1 | 4h |
| B3 | Contabilità | BilancioCivilisticoService | ⚙️ sonnet | 🔴 P1 | 6h |
| C1 | Fornitori | Anagrafica fornitori | ⚙️ sonnet | 🟡 P2 | 3h |
| C2 | Fornitori | Fatture passive | ⚙️ sonnet | 🟡 P2 | 5h |
| C3 | Fornitori | Widget scadenziario | ⚡ haiku | 🟡 P2 | 1h |
| D1 | Cespiti | Architettura cespiti | 🧠 opus | 🟡 P2 | 2h |
| D2 | Cespiti | CespitiService ammortamenti | ⚙️ sonnet | 🟡 P2 | 4h |
| D3 | Cespiti | UI Registro Cespiti | ⚙️ sonnet | 🟡 P2 | 4h |
| E1 | Costi | Centri di costo | ⚙️ sonnet | 🟡 P2 | 4h |
| F1 | Fiscale | Compensi e ritenute | ⚙️ sonnet | 🟡 P2 | 5h |
| G1 | BI | Dashboard con grafici | ⚙️ sonnet | 🟢 P3 | 4h |
| G2 | BI | Export Excel avanzati | ⚡ haiku | 🟢 P3 | 3h |
| H1 | SDI | Architettura integrazione SDI | 🧠 opus | 🟢 P3 | 2h |
| H2 | SDI | Generazione XML FatturaPA | ⚙️ sonnet | 🟢 P3 | 6h |

**Totale stimato P1:** ~27h sviluppo AI-assistito
**Totale stimato P2:** ~28h
**Totale stimato P3:** ~15h
**Totale complessivo:** ~70h

---

## Come usare i prompt

1. Apri Claude Code nel progetto ETS-OK
2. Seleziona il modello indicato con `/model claude-[opus/sonnet/haiku]-4-5`
3. Copia il prompt della feature desiderata
4. Prima di ogni sessione leggi i file rilevanti con `Read` per dare contesto aggiornato
5. Dopo ogni feature esegui `npm run build` e `php artisan test` per verifica

> **Ordine consigliato di esecuzione:**
> A1 → A2 → A3 → A4 → B1 → B2 → B3 → C1 → C2 → D1 → D2 → E1 → F1 → G1 → H1 → H2

---

*Piano generato il 22 Aprile 2026 — ETS-OK v1.0.0*
