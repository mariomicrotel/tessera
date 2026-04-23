# 🗺️ ROADMAP 2026 — Tessera / Network GTC
## Documento Unico: Gap Analysis + Roadmap Esecutiva

> **Progetto:** Tessera (Laravel 12 · PHP 8.4 · Vue 3 · Inertia.js · MySQL 8 · multi-tenant)
> **Riferimento:** QuickMastro Contabilità & Fatturazione + PIANO_SVILUPPO.md v1.0.0
> **Ultimo aggiornamento:** Aprile 2026
> **Stato:** Post completamento modulo Cespiti (D1-D6, commit 4a73089)

---

## LEGENDA

| Simbolo | Modello Claude | Quando usarlo |
|---------|---------------|---------------|
| 🧠 `sonnet` | `claude-sonnet-4-6` | Architetture complesse, integrazioni esterne, logica fiscale multi-layer, decisioni strutturali |
| ⚙️ `sonnet` | `claude-sonnet-4-6` | Feature complete (migration + model + service + controller + Vue), business logic, test suite |
| ⚡ `haiku` | `claude-haiku-4-5` | CRUD semplici, componenti UI isolati, migrazioni atomiche, form Vue, widget dashboard |

| Badge | Significato |
|-------|-------------|
| ✅ | Completato e commitato |
| 🔄 | Parzialmente implementato |
| ❌ | Non ancora iniziato |
| 🔴 P1 | Critico — bloccante per uso professionale |
| 🟠 P2 | Importante — completezza contabile |
| 🟡 P3 | Utile — valore aggiunto |
| ⚪ P4 | Bassa priorità per contesto ETS |

---

## STATO ATTUALE — Feature completate

Da `PIANO_SVILUPPO.md` v1.0.0 (aprile 2026), le seguenti aree sono **già completate**:

| ID Old | Area | Feature | Stato |
|--------|------|---------|-------|
| A1-A4 | IVA | Architettura + migration + IvaService + UI registri IVA | ✅ |
| B1-B3 | Contabilità | Piano dei conti (4 livelli), partita doppia, BilancioCivilisticoService | ✅ |
| C1-C2 | Fornitori | Anagrafica fornitori CRUD + fatture passive complete | ✅ |
| C3 | Scadenziario | Widget dashboard (solo 5 metodi, incompleto) | 🔄 |
| D1-D6 | Cespiti | Registro cespiti, 4 metodi ammortamento, dismissioni, PDF, 53 test Pest | ✅ |
| — | Soci/Membri | Lifecycle completo (22 metodi), tessere, Libro Soci | ✅ |
| — | Cooperative | Share capital, Prestito Sociale, Ristorni | ✅ |
| — | Governance | Elezioni, Verbali, Organi, Incarichi | ✅ |
| — | Incassi | Quote sociali, donazioni, ricevute PDF | ✅ |
| — | Bilancio ETS | Rendiconto cassa, RendicontoCassaService | ✅ |
| E1 | Centri di Costo | — | ❌ |
| F1 | Ritenute | Compensi a terzi e ritenute d'acconto | ❌ |
| G1 | Analytics | Dashboard con grafici Chart.js | ❌ |
| G2 | Export | Export Excel avanzati (maatwebsite/excel) | ❌ |
| H1-H2 | SDI | Architettura + generazione XML FatturaPA | ❌ |

---

## GAP ANALYSIS vs QuickMastro

### ❌ Feature mancanti per parità competitiva

| ID | Feature | Fonte | Priorità | Effort |
|----|---------|-------|----------|--------|
| E-SCA | Scadenzario completo (clienti/fornitori/generiche) | QuickMastro + old plan | 🔴 P1 | 5 gg |
| E-APE | Apertura/Chiusura automatica esercizio | QuickMastro | 🔴 P1 | 3 gg |
| E-RAT | Ratei e Risconti | QuickMastro | 🔴 P1 | 4 gg |
| E-LIB | Libro Giornale + Registro Vendite stampabile | QuickMastro | 🔴 P1 | 2 gg |
| E-LIP | LIPE XML (comunicazione liquidazioni periodiche) | QuickMastro | 🟠 P2 | 2 gg |
| F-ATT | Fatture Attive CRUD completo (oggi: solo view) | Critico interno | 🔴 P1 | 5 gg |
| F-NDC | Nota di Credito Attiva (TD04) | QuickMastro | 🔴 P1 | 2 gg |
| F-XML | Fattura Elettronica XML/SDI | QuickMastro + old plan H1-H2 | 🟠 P2 | 10 gg |
| F-TD7 | Fattura Semplificata TD07 | QuickMastro | 🟡 P3 | 2 gg |
| G-RIT | Ritenute d'Acconto + Compensi Terzi | QuickMastro + old plan F1 | 🟠 P2 | 5 gg |
| G-CU  | Certificazione Unica (CU) export | QuickMastro | 🟠 P2 | 3 gg |
| G-F24 | Modello F24 (compilazione + PDF) | QuickMastro | 🟠 P2 | 4 gg |
| G-REL | Relazione di Missione ETS (D.Lgs 117/17) | QuickMastro ETS | 🟠 P2 | 3 gg |
| G-ERL | Comunicazione Erogazioni Liberali | QuickMastro ETS | 🟠 P2 | 2 gg |
| G-CEE | Bilancio CEE strutturato (SP + CE) | QuickMastro | 🟠 P2 | 4 gg |
| H-CDC | Centri di Costo | QuickMastro + old plan E1 | 🟡 P3 | 4 gg |
| H-BI  | Dashboard Analytics + grafici | old plan G1 | 🟡 P3 | 4 gg |
| H-XLS | Export Excel avanzati | old plan G2 | 🟡 P3 | 3 gg |
| H-POL | Model Policies fine-grained | Security roadmap | 🟡 P3 | 5 gg |
| H-AUD | Audit Trail (chi ha fatto cosa) | Security roadmap | 🟡 P3 | 4 gg |
| H-CSO | Carica Sociale CRUD completo | Completamento moduli | 🟡 P3 | 2 gg |
| H-DIS | Dismissione Cespiti UI completa | Completamento moduli | 🟡 P3 | 2 gg |
| H-EML | Email Template Engine (builder + send) | Completamento moduli | 🟡 P3 | 3 gg |
| H-API | OpenAPI Documentation (Swagger UI) | Developer experience | ⚪ P4 | 3 gg |

### Feature QuickMastro a bassa priorità per ETS (escluse dal piano)
Regime del Margine, IVA di Cassa, Regime OSS/IOSS, Agenzie di Viaggio, RI.BA, Regime Forfettario, Fattura PA firma digitale, Sistema Tessera Sanitaria.

---

## TEST COVERAGE — Fase trasversale (prima di tutto)

**Prima di implementare nuove feature, stabilire baseline di test sui moduli esistenti.**

---

### TEST-1 · Test suite moduli Cooperative
**Modello:** ⚡ `haiku`
**Effort:** 4-5 giorni
**Dipendenze:** nessuna

```
Nel progetto Tessera (Laravel 12, Pest, multi-tenant) crea la suite di test per i
moduli cooperative che attualmente non hanno copertura.

Struttura del progetto già presente:
- app/Models/CooperativeShare.php — Quote sociali (versamenti/riscatti)
- app/Models/PrestitoSocialeLibretto.php — Libretti di risparmio soci
- app/Models/PrestitoSocialeMovimento.php — Movimenti (depositi/prelievi)
- app/Models/Ristorno.php, RistornoEntry.php — Distribuzione utili soci
- app/Services/PrestitoSocialeService.php — Logica interessi e movimenti
- Seeder necessari: PianoContiCooperativaSeeder, CausaliContabiliDiSistemaSeeder
- Pattern esistente: vedi tests/Feature/Cespiti/AmortizzamentoServiceTest.php per
  il pattern di setup (beforeEach crea Tenant, app()->instance('current_tenant'), seeders)

Crea 3 file di test:

1. tests/Feature/Cooperative/CooperativeShareTest.php (15 test):
   - Creazione quota con versamento iniziale
   - Aggiunta versamento incrementale
   - Riscatto totale (libretto chiuso)
   - Riscatto parziale
   - Balance tracking progressivo
   - Quota non può essere negativa
   - Un socio può avere più quote
   - Cancellazione solo se saldo zero
   - KPI totale capitale versato per tenant
   - Scope per stato (attiva/chiusa/in_attesa)
   - ... altri 5 test sulle relazioni e validazioni

2. tests/Feature/Cooperative/PrestitoSocialeTest.php (12 test):
   - Apertura libretto con versamento iniziale
   - Depositi successivi
   - Prelievo parziale
   - Prelievo totale → libretto chiuso
   - Saldo non può scendere sotto zero (eccezione)
   - Calcolo interessi (usa PrestitoSocialeService)
   - Storico movimenti paginato
   - Data di chiusura impostata al momento della chiusura
   - Report saldo al: query point-in-time

3. tests/Feature/Cooperative/RistorniTest.php (10 test):
   - Creazione ristorno per esercizio
   - Aggiunta entry per socio
   - Calcolo proporzionale (per quota/per operazioni)
   - Marcatura come pagato (singola entry)
   - Pagamento bulk tutte le entry
   - Annullamento ristorno (solo se non pagato)
   - Report riepilogativo per anno
   - Unicità ristorno per esercizio per tenant
   - Somma entries == totale ristorno
   - Export CSV entries

Segui il pattern tests/Feature/Cespiti/AmortizzamentoServiceTest.php:
- beforeEach: Tenant + seeders + app()->instance('current_tenant', $tenant)
- afterEach: app()->forgetInstance('current_tenant')
- describe/it annidati per raggruppamento logico
- markTestSkipped se prerequisiti mancano
```

---

### TEST-2 · Test suite Governance (Elezioni + Organi)
**Modello:** ⚙️ `sonnet`
**Effort:** 3-4 giorni
**Dipendenze:** nessuna

```
Nel progetto Tessera (Laravel 12, Pest) crea la test suite per i moduli di governance.

Modelli esistenti:
- app/Models/Elezione.php (stati: bozza/aperta/chiusa/invalidata)
- app/Models/Candidatura.php (collegata a Elezione + Member)
- app/Models/Voto.php (un voto per utente per elezione)
- app/Models/PartecipazioneVoto.php (tracking chi ha votato)
- app/Models/Organo.php (Consiglio Direttivo, Collegio Sindacale, ecc.)
- app/Models/Incarico.php (member assegnato a organo con ruolo e periodo)
- Controller: ElezioneController (15 metodi), IncaricoController (3 metodi)
- Seeder: RoleSeeder, MemberTypeSeeder (vedi tests/Feature/MemberTest.php per pattern HTTP test)

Crea 2 file:

1. tests/Feature/Governance/ElezioneTest.php (18 test):
   - Creazione elezione (bozza)
   - Apertura elezione (transizione stato bozza → aperta)
   - Chiusura elezione (aperta → chiusa) con calcolo risultati
   - Invalidazione elezione con motivazione
   - Non si può aprire elezione senza candidature
   - Aggiunta candidatura (member + elezione)
   - Rimozione candidatura (solo se elezione in bozza)
   - Voto singolo da utente autenticato
   - Un utente non può votare due volte (eccezione/409)
   - Conteggio voti per candidato
   - Risultati ordinati per voti decrescenti
   - Quorum non raggiunto (warning, non blocca)
   - Partecipazione: chi ha votato vs chi poteva votare
   - Storico elezioni per tenant
   - Elezione scaduta automaticamente (data_fine < now())
   - Lock vote dopo chiusura
   - PDF risultati (assertStatus 200)
   - Unicità voto per (user_id, elezione_id)

2. tests/Feature/Governance/IncaricoTest.php (8 test):
   - Assegnazione incarico a membro
   - Data_inizio e data_fine obbligatorie
   - Incarico attivo (data_fine > today o null)
   - Storico incarichi per membro
   - Storico incarichi per organo
   - Cancellazione incarico futuro
   - Non si può cancellare incarico con data_inizio passata
   - Riepilogo composizione organo per data

Segui il pattern HTTP tests/Feature/MemberTest.php per i test che richiedono actingAs
(con seed RoleSeeder e attach role a User).
```

---

### TEST-3 · Test integrazione flussi end-to-end
**Modello:** ⚙️ `sonnet`
**Effort:** 3-4 giorni
**Dipendenze:** TEST-1, TEST-2

```
Nel progetto Tessera (Laravel 12, Pest) crea i test di integrazione end-to-end
per i flussi più critici dell'applicazione.

Pattern di setup: vedi tests/Feature/Cespiti/AmmortamentoFlowTest.php
per il pattern completo (Tenant + seeders + conti + actingAs per HTTP test).

Crea 2 file:

1. tests/Feature/Integrations/FatturaPassivaPaymentFlowTest.php (12 test):
   FLUSSO: Fattura Passiva → Registrazione Prima Nota → Pagamento → Scadenziario
   - Fattura passiva crea voce in prima nota automaticamente (se configurata)
   - Fattura passiva appare nello scadenziario fornitori
   - Pagamento fattura → stato diventa "pagata"
   - Pagamento parziale → stato diventa "parziale"
   - Pagamento rimuove la scadenza dal "da pagare"
   - Prima nota generata è bilanciata (dare = avere)
   - Registro IVA acquisti include la fattura dopo registrazione
   - Liquidazione IVA include il credito IVA della fattura
   - Soft delete non cancella la prima nota associata (solo l'accesso)
   - Fornitore: somma fatture aperte rispecchia totale dovuto
   - Export PDF registro acquisti risponde 200
   - LIPE XML include il periodo della fattura

2. tests/Feature/Integrations/SocioLifecycleFlowTest.php (10 test):
   FLUSSO: Invito → Ammissione → Quota → Tessera → Cessazione
   - Creazione invito genera link pubblico
   - Richiesta di ammissione via link pubblico
   - Accettazione ammissione → stato diventa "attivo"
   - Rifiuto con motivazione → notifica
   - Registrazione quota sociale → Incasso creato
   - Incasso crea voce prima nota (o rendiconto ETS)
   - Generazione ricevuta PDF per quota
   - Diniego appello
   - Dimissioni volontarie → stato "cessato" con data
   - Registro soci aggiornato in tempo reale

Ogni test deve: creare un Tenant, eseguire i seeders necessari,
impostare app()->instance('current_tenant', $tenant), e verificare lo stato
finale tramite assert DB o assert response.
```

---

## FASE 2 — Contabilità Core Mancante

---

### E-SCA · Scadenziario Completo
**Modello:** ⚙️ `sonnet`
**Effort:** 5 giorni
**Priorità:** 🔴 P1
**Dipendenze:** FatturaPassiva (✅), FatturaAttiva (F-ATT)

```
Nel progetto Tessera (Laravel 12 + Vue 3 + Inertia.js, multi-tenant) implementa
lo scadenziario completo. L'attuale ScadenzarioController ha solo 5 metodi incompleti.

CONTESTO:
- app/Models/FatturaPassiva.php — fatture fornitori (stato: da_pagare/pagata/parziale/stornata)
- app/Models/FatturaAttiva.php — fatture emesse (stato: emessa/pagata/stornata)
- app/Http/Controllers/ScadenzarioController.php — attuale (verificare i 5 metodi esistenti)
- routes/web.php — gruppo tenant app/{tenant}/scadenzario
- Tenant risolto da middleware: app('current_tenant')

MIGRATION da creare:
`create_scadenze_generiche_table`:
  id, tenant_id, tipo (enum: entrata/uscita/adempimento),
  oggetto (varchar 200), soggetto (varchar 200 nullable),
  importo (decimal 12,2 nullable), data_scadenza (date),
  data_pagamento (date nullable), ricorrente (bool default false),
  frequenza_giorni (int nullable), note (text nullable),
  riferimento_type (nullable morphTo), riferimento_id (nullable),
  stato (enum: aperta/saldata/scaduta), timestamps

ESTENDERE ScadenzarioController (da 5 a 15+ metodi):
- `index(Request $r)` — dashboard scadenziario con filtri:
  tipo (clienti/fornitori/generiche/adempimenti), stato, periodo (mese/trimestre/anno)
  Inertia prop: scadenze_clienti, scadenze_fornitori, scadenze_generiche, totali
- `clienti()` — fatture attive non pagate ordinate per data scadenza
  (calcolata: data_emissione + giorni_pagamento della tipologia cliente)
- `fornitori()` — fatture passive non pagate ordinate per data scadenza
  (calcolata da condizioni_pagamento Supplier)
- `generiche()` — scadenze libere (tasse, canoni, abbonamenti)
- `creaGenerica(Request)` — store nuova scadenza generica
- `aggiornaGenerica(Request, Scadenza)` — update
- `salda(Request, Scadenza)` — marca come saldata con data_pagamento
- `eliminaGenerica(Scadenza)` — soft delete
- `riepilogoMese(int $anno, int $mese)` — totale in scadenza nel mese
- `exportPdf(Request)` — PDF scadenziario completo (usa DomPDF, vedi pattern in CespitiController::registroPdf)
- `riprendaAnnoPrec()` — copia scadenze ricorrenti dell'anno precedente

VUE PAGE resources/js/Pages/Scadenzario/Index.vue:
- Tabs: Clienti | Fornitori | Generiche | Adempimenti Fiscali
- Per ogni tab: tabella con colonne Soggetto | Oggetto | Importo | Scadenza | Giorni mancanti | Stato
- Badge colore giorni: rosso (scaduta), arancio (≤7gg), giallo (≤30gg), verde (>30gg)
- Filtri: periodo (mese selezionabile), stato (aperta/saldata)
- Footer: totale da incassare + totale da pagare + saldo netto
- Bottone "Nuova scadenza" → modal form inline (non nuova pagina)
- KPI card in testa: scaduto, in scadenza questa settimana, in scadenza questo mese

Aggiungi widget "Prossime scadenze" in Dashboard.vue (top 5 per data).
Aggiungi route nel gruppo tenant in routes/web.php.
Test: tests/Feature/ScadenzarioTest.php (12 test: creazione, saldo, filtri, export).
```

---

### E-APE · Apertura e Chiusura Esercizio
**Modello:** ⚙️ `sonnet`
**Effort:** 3 giorni
**Priorità:** 🔴 P1
**Dipendenze:** ContoContabile (✅), MovimentoContabileService (✅)

```
Nel progetto Tessera (Laravel 12, multi-tenant) implementa il modulo per la
apertura e chiusura contabile dell'esercizio.

CONTESTO ESISTENTE:
- app/Services/MovimentoContabileService.php — crea movimenti con righe dare/avere bilanciate
- app/Models/ContoContabile.php — piano dei conti gerarchico (4 livelli), con nature:
  attivo, passivo, patrimonio_netto, costo, ricavo
- app/Models/CausaleContabile.php — causali (vedi CausaliContabiliDiSistemaSeeder)
- Causali da aggiungere al seeder: 'CHI' (Chiusura), 'APE' (Apertura)

MIGRATION da creare:
`create_esercizi_contabili_table`:
  id, tenant_id, anno (smallint), data_inizio (date), data_fine (date),
  stato (enum: aperto/chiuso), data_chiusura (date nullable),
  note_chiusura (text nullable), timestamps
  UNIQUE (tenant_id, anno)

MIGRATION:
`add_esercizio_lock_to_movimenti_contabili`:
  Aggiunge colonna esercizio_chiuso (bool default false) a movimenti_contabili
  (quando true, il movimento non può essere modificato o cancellato)

SERVIZIO AperturaChiusuraService:
1. `creaEsercizio(Tenant $t, int $anno): EsercizioContabile`
   Crea il record esercizio se non esiste, stato=aperto.

2. `chiudiEsercizio(Tenant $t, int $anno, string $dataChiusura): EsercizioContabile`
   - Verifica no movimenti in bozza per l'anno
   - Calcola saldi finali di tutti i conti CE (costi/ricavi) → determina utile/perdita
   - Genera movimento di chiusura CE:
     DARE: tutti i conti ricavo (azzera saldo)
     AVERE: tutti i conti costo (azzera saldo)
     La differenza va a 'Utile/Perdita d'esercizio' (conto patrimonio netto)
   - Genera movimento di chiusura SP:
     DARE: tutti i conti passivo + patrimonio
     AVERE: tutti i conti attivo
     (bilanciamento dello Stato Patrimoniale)
   - Lock tutti i movimenti dell'anno: esercizio_chiuso = true
   - Aggiorna stato esercizio → chiuso

3. `apriEsercizioSuccessivo(Tenant $t, int $anno): EsercizioContabile`
   - Crea esercizio anno+1 se non esiste
   - Genera movimento di apertura: rispecchia lo SP finale dell'anno chiuso
     (saldi dei conti attivo/passivo/patrimonio riportati al 1° gennaio)

4. `getSaldiPerChiusura(Tenant $t, int $anno): array`
   → {costi: float, ricavi: float, utile_perdita: float, conti_sp: Collection}

CONTROLLER EsercizioContabileController:
- `index()` — lista esercizi del tenant con stato
- `store()` — crea nuovo esercizio
- `chiudi(int $anno)` — wizard chiusura con preview saldi
- `apriSuccessivo(int $anno)` — apertura esercizio successivo
- `show(int $anno)` — dettaglio saldi conti per anno

VUE PAGE resources/js/Pages/Contabilita/Esercizio/Index.vue:
- Card per ogni esercizio: anno, stato (badge), data chiusura
- Bottone "Chiudi esercizio" → modale con preview: CE (utile/perdita), SP finale
- Bottone "Apri esercizio successivo" (disponibile solo dopo chiusura)
- Warning se ci sono movimenti in bozza nell'anno da chiudere

Test: tests/Feature/Contabilita/EsercizioContabileTest.php (10 test).
Route in routes/web.php nel gruppo tenant, prefix: esercizio-contabile.
```

---

### E-RAT · Ratei e Risconti
**Modello:** ⚙️ `sonnet`
**Effort:** 4 giorni
**Priorità:** 🔴 P1
**Dipendenze:** ContoContabile (✅), MovimentoContabileService (✅)

```
Nel progetto Tessera (Laravel 12, multi-tenant) implementa il modulo Ratei e Risconti
per la corretta contabilizzazione per competenza economica.

DEFINIZIONI:
- Rateo attivo: ricavo di competenza non ancora incassato (es. affitto da ricevere)
- Rateo passivo: costo di competenza non ancora pagato (es. interessi maturati)
- Risconto attivo: costo già pagato ma di competenza futura (es. assicurazione anticipata)
- Risconto passivo: ricavo già incassato ma di competenza futura (es. abbonamento anticipato)

MIGRATION `create_ratei_risconti_table`:
  id, tenant_id, tipo (enum: rateo_attivo/rateo_passivo/risconto_attivo/risconto_passivo),
  descrizione (varchar 200), importo_totale (decimal 12,2),
  data_inizio_competenza (date), data_fine_competenza (date),
  data_registrazione (date), esercizio_competenza (smallint),
  conto_rettifica_id (FK conti_contabili), conto_costo_ricavo_id (FK conti_contabili),
  movimento_creazione_id (FK movimenti_contabili nullable),
  movimento_storno_id (FK movimenti_contabili nullable),
  stato (enum: attivo/stornato), note (text nullable), timestamps

SERVIZIO RateiRiscontiService:
1. `calcola(date $dataInizio, date $dataFine, float $importoTotale, int $esercizio): array`
   → {quota_esercizio: float, quota_futuro: float, giorni_competenza: int, giorni_totali: int}
   Usa pro-rata giornaliero: quota = totale × (giorni competenza / giorni totali)

2. `creaRateo(Tenant $t, array $data): RateoRisconto`
   Crea il record e genera il movimento contabile di rettifica:
   - Rateo attivo: DARE conto credito (es. "Ratei Attivi"), AVERE conto ricavo
   - Rateo passivo: DARE conto costo, AVERE conto debito (es. "Ratei Passivi")
   - Risconto attivo: DARE conto risconto (es. "Risconti Attivi"), AVERE costo anticipato
   - Risconto passivo: DARE ricavo anticipato, AVERE conto risconto (es. "Risconti Passivi")

3. `stornaRateo(RateoRisconto $r, string $dataStorno): MovimentoContabile`
   Genera il movimento inverso all'inizio dell'esercizio successivo.

4. `generaRateiAutomatici(Tenant $t, int $esercizio): Collection`
   Per tutti i RateoRisconto attivi, calcola e storna quelli di esercizi precedenti.

CONTROLLER RateiRiscontiController:
- `index()` — lista con filtri (tipo, stato, esercizio)
- `create/store()` — form con preview calcolo pro-rata in tempo reale
- `show()` — dettaglio con movimenti collegati
- `storna(RateoRisconto)` — storno manuale

VUE: resources/js/Pages/Contabilita/RateiRisconti/
- `Index.vue`: tabella con raggruppamento per tipo + totali per categoria
- `Create.vue`: form con slider/date picker che mostra in tempo reale
  la quota dell'esercizio corrente vs futura

Test: tests/Feature/Contabilita/RateiRiscontiTest.php (12 test: calcolo pro-rata,
creazione movimenti, storno, idempotenza).
```

---

### E-LIB · Libro Giornale e Registro Vendite
**Modello:** ⚡ `haiku`
**Effort:** 2 giorni
**Priorità:** 🔴 P1
**Dipendenze:** MovimentoContabile (✅), FatturaAttiva (F-ATT)

```
Nel progetto Tessera (Laravel 12) aggiungi le stampe dei libri contabili obbligatori.

CONTESTO:
- app/Http/Controllers/AccountingReportController.php — già presente, estendilo
- resources/views/pdf/ — template PDF esistenti (usa come modello)
- app/Support/PdfLetterheadData.php — helper letterhead per PDF

Aggiungi al AccountingReportController i seguenti metodi:

1. `libroGiornale(Request $request)`:
   Parametri GET: esercizio (int), pagina_inizio (int default 1)
   Recupera tutti i MovimentoContabile del tenant per l'esercizio selezionato,
   ordinati per data_registrazione + id progressivo.
   Ogni riga mostra: numero_progressivo, data, causale, descrizione, conti dare/avere, importo.
   Genera PDF landscape A4 con DomPDF.
   Numerazione progressiva pagine (obbligatoria per legge).
   View: resources/views/contabilita/libro-giornale.blade.php

2. `registroVendite(Request $request)`:
   Parametri GET: anno (int), mese (int nullable)
   Recupera FatturaAttiva del tenant per il periodo, con codice IVA.
   Colonne: progressivo, data emissione, cliente, numero fattura, imponibile, aliquota, IVA, totale.
   Subtotali per aliquota IVA.
   Totale complessivo in fondo.
   Genera PDF A4 portrait.
   View: resources/views/iva/registro-vendite.blade.php

3. `libroInventari(Request $request)`:
   Elenco beni in rimanenza (da Warehouse stock) + cespiti (da Asset ammortizzati)
   con valorizzazione al costo storico e al netto degli ammortamenti.
   Genera PDF.

Aggiungi le route nel gruppo tenant in routes/web.php:
  GET app/{tenant}/contabilita/libro-giornale → AccountingReportController@libroGiornale
  GET app/{tenant}/iva/registro-vendite → AccountingReportController@registroVendite

Aggiungi i link nei menu esistenti:
- In AppLayout.vue, sezione "Contabilità": link "Libro Giornale"
- In AppLayout.vue, sezione "IVA": link "Registro Vendite"

Test: assertStatus(200) e assertHeader('Content-Type', 'application/pdf') per entrambe le route.
```

---

### E-LIP · LIPE XML + Acconto IVA Dicembre
**Modello:** ⚙️ `sonnet`
**Effort:** 2 giorni
**Priorità:** 🟠 P2
**Dipendenze:** LiquidazioneIva (✅)

```
Nel progetto Tessera (Laravel 12) aggiungi la generazione del file XML LIPE
(Comunicazione Liquidazioni Periodiche IVA) e il calcolo dell'acconto IVA di dicembre.

CONTESTO:
- app/Models/LiquidazioneIva.php — record liquidazioni per anno/periodo
- app/Http/Controllers/IvaController.php — già presente, estendilo
- Formato LIPE: schema XSD ufficiale AdE (Comunicazione_Liquidazioni_IVA_v1.1.xsd)

Aggiungi a IvaController:

1. `lipeXml(Request $request)`:
   Parametri GET: anno (int), trimestre (int 1-4)
   Genera il file XML LIPE per il trimestre selezionato con struttura:
   ```xml
   <ns2:ComunicazioneIVA>
     <ns2:Intestazione>
       <ns2:CodiceFiscale>...</ns2:CodiceFiscale>
       <ns2:Anno>2026</ns2:Anno>
       <ns2:Trimestre>1</ns2:Trimestre>
     </ns2:Intestazione>
     <ns2:DatiContributivo>
       <ns2:IvaDovuta>...</ns2:IvaDovuta>
       <ns2:IvaCredito>...</ns2:IvaCredito>
       <ns2:DebCred>D|C</ns2:DebCred>
       <ns2:ImportoDovuto>...</ns2:ImportoDovuto>
     </ns2:DatiContributivo>
   </ns2:ComunicazioneIVA>
   ```
   Calcola i dati aggregando le LiquidazioneIva del trimestre (tipo mensile: 3 mesi).
   Risponde con download del file XML (Content-Type: application/xml).

2. `accontoIva(Request $request)`:
   Parametri GET: anno (int), metodo (storico|previsionale|analitico)
   - Storico: 88% dell'IVA versata a dicembre anno precedente
   - Previsionale: IVA stimata dicembre anno corrente
   Ritorna JSON: {metodo, base_calcolo, acconto, codice_tributo: '6013'}
   Vue: mostra card con i 3 metodi a confronto, l'utente sceglie quello più conveniente.

3. Aggiungi pulsante "Esporta LIPE" nella pagina Iva/Liquidazione.vue:
   Usa <a :href="lipeUrl"> con computed URL (include anno e trimestre corrente).

Test: tests/Feature/Iva/LipeXmlTest.php (6 test: struttura XML, dati corretti,
acconto storico vs previsionale, download response).
```

---

## FASE 3 — Fatturazione Attiva Completa

---

### F-ATT · Fatture Attive CRUD Completo
**Modello:** ⚙️ `sonnet`
**Effort:** 5 giorni
**Priorità:** 🔴 P1
**Dipendenze:** CodiceIva (✅), MovimentoContabileService (✅), Supplier (✅)

```
Nel progetto Tessera (Laravel 12 + Vue 3 + Inertia) implementa la gestione
completa delle fatture attive (ciclo di vendita). Attualmente FatturaAttivaController
ha 1 solo metodo (show view-only). Implementa il ciclo completo.

CONTESTO ESISTENTE:
- app/Models/FatturaAttiva.php — verifica fillable, casts, relazioni attuali
- app/Models/RigaFatturaAttiva.php — se esiste, verificarne struttura
- app/Models/CodiceIva.php — codici IVA con percentuale
- app/Services/MovimentoContabileService.php — per prima nota automatica
- Pattern di riferimento: app/Http/Controllers/FatturaPassivaController.php (12 metodi)
- Pattern Vue: resources/js/Pages/Iva/FatturePassive/ (Create.vue, Edit.vue)

MIGRATION (se non esiste): `create_righe_fattura_attiva_table`:
  id, fattura_attiva_id (FK), tenant_id,
  descrizione (varchar 500), quantita (decimal 8,2 default 1),
  prezzo_unitario (decimal 12,2), sconto_percentuale (decimal 5,2 default 0),
  imponibile (decimal 12,2), codice_iva_id (FK), iva (decimal 12,2),
  totale (decimal 12,2), ordine (int default 0), timestamps

SERVIZIO FatturaAttivaService:
1. `crea(Tenant $t, array $data, array $righe): FatturaAttiva`
   - Valida dati testata (cliente, data, numero progressivo)
   - Crea righe con calcolo imponibile/IVA per riga
   - Ricalcola totali testata
   - Genera movimento contabile: DARE Crediti Clienti, AVERE Ricavi + IVA a debito
   - Crea scadenza in scadenziario clienti

2. `aggiorna(FatturaAttiva $f, array $data, array $righe): FatturaAttiva`
   - Solo se stato=emessa (non pagata/stornata)
   - Storna il movimento precedente, crea il nuovo

3. `registraPagamento(FatturaAttiva $f, array $pagamento): void`
   - data_pagamento, importo, conto_incasso_id
   - Movimento: DARE Cassa/Banca, AVERE Crediti Clienti
   - Aggiorna stato: pagata o parziale (se importo < totale)

4. `storna(FatturaAttiva $f): FatturaAttiva`
   - Solo se stato=emessa
   - Crea nota di credito collegata automaticamente (vedi F-NDC)

5. `calcolaNumeroProgressivo(Tenant $t, int $anno): string`
   → 'FT-2026-0001' (formato configurabile da Settings)

CONTROLLER FatturaAttivaController (da 1 a 12 metodi):
- index — lista con filtri (cliente, stato, periodo, importo)
- create — form nuova fattura
- store — chiama FatturaAttivaService::crea
- show — dettaglio (già presente, aggiorna)
- edit — form modifica (solo stato=emessa)
- update — chiama ::aggiorna
- paga — modal pagamento → chiama ::registraPagamento
- storna — conferma → chiama ::storna
- destroy — soft delete (solo bozze)
- exportPdf — genera PDF fattura singola
- exportLista — PDF lista fatture filtrata

VUE PAGES resources/js/Pages/Iva/FattureAttive/:
- Index.vue: tabella con colonne Data | Numero | Cliente | Imponibile | IVA | Totale | Stato | Azioni
  KPI: totale emesse, totale incassate, totale da incassare
  Badge stati: emessa (blu), pagata (verde), parziale (giallo), stornata (grigio)
- Create.vue / Edit.vue:
  Form testata: cliente (autocomplete da Member + campo libero), data, numero (auto)
  Sezione righe: tabella dinamica (aggiungi/rimuovi riga) con:
    Descrizione | Quantità | Prezzo | Sconto% | Imponibile | Aliquota IVA | IVA | Totale
  Ogni riga aggiorna il totale in tempo reale (computed Vue)
  Totale fattura: subtotale, IVA per aliquota, totale
  Bottone "Salva bozza" e "Emetti fattura"
- Show.vue: dettaglio + sezione pagamenti + pulsanti azione

Aggiungi route nel gruppo tenant in routes/web.php.
Aggiungi voce "Fatture Attive" nel menu IVA in AppLayout.vue.

Test: tests/Feature/Iva/FatturaAttivaTest.php (15 test):
  - Creazione con righe, calcolo IVA, numero progressivo
  - Pagamento totale e parziale
  - Storno (crea nota di credito)
  - Modifica bloccata dopo pagamento
  - Prima nota bilanciata
  - PDF risponde 200
```

---

### F-NDC · Nota di Credito Attiva (TD04)
**Modello:** ⚡ `haiku`
**Effort:** 2 giorni
**Priorità:** 🔴 P1
**Dipendenze:** F-ATT

```
Nel progetto Tessera (Laravel 12) aggiungi la gestione delle note di credito attive
come estensione del modulo FatturaAttiva.

CONTESTO: FatturaAttivaService::storna() esiste già (F-ATT). Formalizza come TD04.

MIGRATION `add_nota_credito_fields_to_fatture_attive`:
  - tipo_documento (enum: TD01/TD04/TD07, default 'TD01')
  - fattura_collegata_id (FK fatture_attive nullable, self-referential)
  - motivo_nota_credito (text nullable)

Aggiungi a FatturaAttivaService:
`creaNdiCredito(FatturaAttiva $fatturaOriginale, array $data): FatturaAttiva`
  - tipo_documento = 'TD04'
  - fattura_collegata_id = $fatturaOriginale->id
  - Importo a storno (totale o parziale)
  - Genera movimento inverso: DARE Ricavi (storno) + IVA a debito (storno), AVERE Crediti Clienti
  - Aggiorna stato fattura originale: stornata (se storno totale) o parziale

Aggiungi a FatturaAttivaController:
`creaNotaCredito(Request $r, FatturaAttiva $fattura)` — form + store
`showNotaCredito(FatturaAttiva $nota)` — show con link alla fattura originale

VUE: modal dentro Show.vue con form:
  - Importo da stornare (default: totale fattura originale, modificabile)
  - Motivo nota di credito
  - Anteprima: mostra righe originali precompilate
  - Pulsante "Emetti Nota di Credito"

In Index.vue: badge "NC" per le note di credito, link alla fattura originale nel dettaglio.

Test: 5 test in FatturaAttivaTest.php (sezione nota di credito):
  storno totale, storno parziale, nota collegata a fattura origine, movimento inverso bilanciato.
```

---

### F-XML · Fattura Elettronica XML / SDI
**Modello:** 🧠 `sonnet`
**Effort:** 10 giorni
**Priorità:** 🟠 P2
**Dipendenze:** F-ATT, F-NDC

```
Nel progetto Tessera (Laravel 12) progetta e implementa l'integrazione con il
Sistema di Interscambio (SDI) per la fatturazione elettronica.

FASE 1 — ARCHITETTURA (giorno 1-2, risposta solo pianificazione):
Analizza le opzioni e fornisci una raccomandazione su:
1. Package PHP: `fatturapa/fatturapa` vs `salvagiotto/fatturapa` vs generazione XML raw con DOMDocument
2. Intermediario SDI: integrazione diretta AdE (richiede accreditamento) vs provider API
   (Aruba, Sdi.io, Fattura24, Abletech) — valuta costo/complessità/tempo
3. Gestione asincrona: Queue per invii/ricezioni (driver: database o Redis)
4. Storage XML: 10 anni → filesystem locale + backup S3/Wasabi
5. Stati del ciclo vita: bozza → inviata → consegnata → accettata/rifiutata/scaduta
6. Firma digitale: obbligatoria per PA, opzionale B2B (firma provider intermediario)

FASE 2 — IMPLEMENTAZIONE (giorno 3-10):

MIGRATION `add_sdi_fields_to_fatture_attive`:
  numero_sdi (varchar 20 nullable), data_invio_sdi (datetime nullable),
  stato_sdi (enum: non_inviata/inviata/consegnata/accettata/rifiutata/scaduta nullable),
  percorso_xml (varchar 500 nullable), errori_sdi (json nullable),
  codice_destinatario (varchar 7 nullable), pec_destinatario (varchar nullable)

SERVIZIO FatturaXmlService:
1. `genera(FatturaAttiva $f): string` — XML FatturaPA formato 1.2
   Header: DatiTrasmissione (IdTrasmittente con IdPaese='IT' e IdCodice=PIVA tenant,
   ProgressivoInvio, FormatoTrasmissione='FPR12'|'FPA12', CodiceDestinatario)
   CedentePrestatore: dati tenant (PIVA, CF, ragione_sociale, indirizzo, REA se presente)
   CessionarioCommittente: dati cliente (da FatturaAttiva: cliente_nome, cliente_cf_piva)
   DatiGenerali: TipoDocumento (TD01|TD04|TD07), Data, Numero
   DatiBeniServizi: righe (da RigaFatturaAttiva: descrizione, quantita, prezzoUnitario, aliquotaIVA)
   DatiPagamento: ModalitaPagamento (MP05=bonifico default), DataScadenzaPagamento, ImportoPagamento

2. `valida(string $xml): array` — errori contro schema XSD ufficiale (usa DOMDocument::schemaValidate)

3. `salva(FatturaAttiva $f, string $xml): string` — salva in storage/fatture-xml/{tenant}/{anno}/

4. `invia(FatturaAttiva $f): array` — chiama API intermediario, aggiorna stato_sdi

5. `verificaEsiti(Tenant $t): int` — polling API intermediario per fatture inviata→stato_finale

6. `nomeFile(FatturaAttiva $f): string` — IT{PIVA}_{progressivo:05d}.xml

CONTROLLER SdiController:
- `index()` — cruscotto SDI: fatture per stato, anomalie, da ritrasmettere
- `invia(FatturaAttiva $f)` — invia al SDI
- `verificaEsiti()` — polling manuale
- `scaricaXml(FatturaAttiva $f)` — download XML
- `webhook(Request $r)` — ricezione notifiche intermediario (aggiorna stato)

QUEUE JOB: InviaFatturaAlSdi (ritardo 2s, retry 3x con backoff esponenziale)
QUEUE JOB: VerificaEsitiSdi (schedulabile via Scheduler ogni 6h)

VUE: in FattureAttive/Show.vue aggiunge sezione "Fattura Elettronica":
- Stato SDI con badge colorato
- Pulsante "Invia al SDI" (se non_inviata)
- Pulsante "Scarica XML"
- Storico stati con timestamp
- Eventuali errori SDI con descrizione leggibile

In FattureAttive/Index.vue: colonna aggiuntiva "Stato SDI" con badge.

Configurazione in Settings: codice_sdi_default, pec_sdi, credenziali_intermediario (env vars).

Test: tests/Feature/Iva/FatturaXmlTest.php (12 test):
  - XML ben formato (DOMDocument::loadXML senza errori)
  - Presenza tutti i campi obbligatori
  - Correttezza calcolo IVA nell'XML
  - Nome file formato corretto
  - Validazione XSD (skip se schema non disponibile)
  - Mocking chiamata API intermediario
  - Aggiornamento stato dopo risposta mock
```

---

### F-TD7 · Fattura Semplificata TD07
**Modello:** ⚡ `haiku`
**Effort:** 2 giorni
**Priorità:** 🟡 P3
**Dipendenze:** F-ATT

```
Nel progetto Tessera (Laravel 12) aggiungi la fattura semplificata (TD07)
come variante di FatturaAttiva per importi ≤ €400.

Modifica FatturaAttivaService:
- In `crea()`: se tipo_documento='TD07' e totale > 400, lancia eccezione con messaggio
  "La fattura semplificata non può superare €400"
- In `genera()` (FatturaXmlService): branch per TD07 con dati ridotti:
  CessionarioCommittente: solo denominazione (CF/PIVA opzionali)
  DatiBeniServizi: descrizione aggregata ammessa (no righe dettaglio)

In FattureAttive/Create.vue:
- Checkbox "Fattura semplificata (TD07)" che appare solo se totale ≤ €400
- Quando selezionato: nasconde campo CF/PIVA cliente (opzionale), mostra badge "TD07"
- Toggle ripristina a TD01 se si supera €400 durante la compilazione

In FattureAttive/Index.vue: badge "TD07" per le fatture semplificate.

Test: 3 test (TD07 con totale ≤400, errore con totale >400, XML corretto con dati ridotti).
```

---

## FASE 4 — Adempimenti Fiscali + ETS Compliance

---

### G-RIT · Ritenute d'Acconto e Compensi a Terzi
**Modello:** ⚙️ `sonnet`
**Effort:** 5 giorni
**Priorità:** 🟠 P2
**Dipendenze:** Supplier (✅), MovimentoContabileService (✅), ContoContabile (✅)

```
Nel progetto Tessera (Laravel 12) implementa il modulo per la gestione dei compensi
a terzi (collaboratori, professionisti) con ritenuta d'acconto.

NORMATIVA:
- Ritenuta d'acconto 20% sull'imponibile (art. 25 DPR 600/73)
- Versamento mensile entro il 16 del mese successivo (codice tributo F24: 1040)
- Obblighi: Certificazione Unica (CU), registrazione nel Libro Unico del Lavoro (per CO.CO.CO)

MIGRATION `create_compensi_terzi_table`:
  id, tenant_id, supplier_id (FK suppliers),
  tipo_rapporto (enum: occasionale/professionale/provvigione/diritto_autore),
  data_competenza (date), descrizione_prestazione (varchar 500),
  imponibile (decimal 12,2),
  aliquota_ritenuta (decimal 5,2 default 20.00),
  ritenuta (decimal 12,2), — calcolata: imponibile × aliquota / 100
  enasarco (decimal 12,2 default 0), — solo per agenti
  netto_liquidare (decimal 12,2), — imponibile - ritenuta - enasarco
  data_pagamento_netto (date nullable),
  data_versamento_ritenuta (date nullable),
  riferimento_fattura (varchar 100 nullable),
  movimento_pagamento_id (FK movimenti_contabili nullable),
  movimento_versamento_id (FK movimenti_contabili nullable),
  stato (enum: bozza/liquidato/ritenuta_versata), note (text nullable), timestamps

SERVIZIO CompensaTerziService:
1. `crea(Tenant $t, array $data): CompensaTerzi`
   Calcola automaticamente: ritenuta = imponibile × aliquota / 100, netto = imponibile - ritenuta.

2. `registraPagamento(CompensaTerzi $c, string $data, int $contoId): void`
   Genera movimento: DARE Compensi (costo), AVERE Ritenuta da versare (passivo) + Cassa/Banca
   Aggiorna stato → liquidato, imposta data_pagamento_netto.

3. `versaRitenute(Tenant $t, int $anno, int $mese, string $dataVersamento): int`
   Per tutti i compensi liquidati nel periodo con ritenuta non ancora versata:
   Genera movimento: DARE Ritenuta da versare, AVERE Erario
   Aggiorna data_versamento_ritenuta, stato → ritenuta_versata.
   Ritorna numero compensi aggiornati.

4. `riepilogoPeriodo(Tenant $t, int $anno, int $mese): array`
   → {totale_imponibile, totale_ritenute, da_versare, gia_versato, compensi: Collection}

5. `generaCsvCU(Tenant $t, int $anno): string`
   CSV precompilato per Certificazione Unica con campi standard per ogni percipiente:
   CF_percipiente, cognome, nome, codice_comune, importo_erogato, ritenuta_operata,
   tipo_reddito (lavoro_autonomo/altro)

CONTROLLER CompensaTerziController (8 metodi):
- index — lista con filtri anno/mese/fornitore/stato
- create / store
- show — dettaglio con movimenti collegati
- paga — registra pagamento netto
- versaRitenute — versamento mensile (con conferma importo)
- riepilogoMensile — report per F24
- exportCsvCU — download CSV per Certificazione Unica
- exportPdf — riepilogo per anno/mese

VUE resources/js/Pages/CompensaTerzi/:
- Index.vue: tabella + KPI card "Ritenute da versare questo mese"
  Filtri: anno, mese, tipo_rapporto, stato
  Colonne: Data | Percipiente | Prestazione | Imponibile | Ritenuta | Netto | Stato
  Pulsante "Versa ritenute [mese]" per batch versamento
- Create.vue: form con:
  - Autocomplete fornitore (tipo=professionista)
  - Calcolo automatico ritenuta e netto mentre si inserisce imponibile
  - Campo aliquota modificabile (default 20%)
- Show.vue: dettaglio + bottone "Registra pagamento" → modale

Widget in Dashboard.vue: "Ritenute da versare" (importo totale mese corrente + link).
Route nel gruppo tenant, prefix: compensi-terzi.

Test: tests/Feature/Fiscale/CompensaTerziTest.php (12 test):
  calcolo ritenuta, pagamento, versamento batch, CSV CU, movimento bilanciato, riepilogo periodo.
```

---

### G-CU · Certificazione Unica
**Modello:** ⚡ `haiku`
**Effort:** 3 giorni
**Priorità:** 🟠 P2
**Dipendenze:** G-RIT

```
Nel progetto Tessera (Laravel 12) aggiungi la generazione della Certificazione Unica
come estensione del modulo CompensaTerzi.

La CU è la dichiarazione annuale che ogni sostituto d'imposta deve rilasciare
ai percipienti entro il 16 marzo dell'anno successivo e trasmettere all'AdE.

Aggiungi a CompensaTerziController:
`generaCU(Request $r)`:
  Parametri: anno (int)
  Per ogni fornitore con compensi nell'anno:
    Genera PDF Certificazione Unica (modello semplificato per lavoro autonomo)
    con: dati sostituto (tenant), dati percipiente (supplier),
    quadro LAV (lavoro autonomo): importo_erogato, ritenuta_operata, anno
  Risponde con ZIP di tutti i PDF (uno per percipiente) o PDF unico multi-pagina.

Aggiungi view: resources/views/fiscale/certificazione-unica.blade.php
  Struttura simile al modulo ufficiale CU (non necessita essere identico al modello AdE,
  deve contenere tutti i dati obbligatori e avere valore di quietanza per il percipiente).

Aggiungi sezione "Certificazione Unica" in CompensaTerzi/Index.vue:
  - Bottone "Genera CU [anno]"
  - Lista CU già generate con link download
  - Data limite di consegna (16 marzo anno successivo)

Test: 3 test (PDF risponde 200, contiene CF percipiente, dati importo corretti).
```

---

### G-F24 · Modello F24
**Modello:** ⚙️ `sonnet`
**Effort:** 4 giorni
**Priorità:** 🟠 P2
**Dipendenze:** LiquidazioneIva (✅), G-RIT

```
Nel progetto Tessera (Laravel 12) implementa la compilazione e stampa del Modello F24
per i principali tributi delle ETS e cooperative.

MIGRATION `create_modelli_f24_table`:
  id, tenant_id, anno (smallint), periodo (varchar 10, es. '01', '2T', '12'),
  tipo (enum: iva/ritenute/irap/inps/altro),
  data_scadenza (date), data_versamento (date nullable),
  stato (enum: bozza/versato), note (text nullable), timestamps

MIGRATION `create_righe_f24_table`:
  id, f24_id (FK), sezione (enum: erario/inps/regioni/imu/altri_enti),
  codice_tributo (varchar 10), anno_riferimento (smallint), periodo_riferimento (varchar 10),
  importo_debiti (decimal 12,2), importo_crediti (decimal 12,2 default 0), timestamps

SERVIZIO ModelloF24Service:
1. `creaF24Iva(Tenant $t, LiquidazioneIva $liq): ModelloF24`
   Sezione Erario: codice_tributo = '6001'-'6012' (mese) o '6031'-'6034' (trimestre)
   Importo = saldo_finale della liquidazione (solo se positivo → IVA a debito)

2. `creaF24Ritenute(Tenant $t, int $anno, int $mese): ModelloF24`
   Sezione Erario: codice_tributo '1040' (lavoro autonomo)
   Importo = somma ritenute da versare del periodo (da CompensaTerzi)

3. `compilaManuale(Tenant $t, array $data): ModelloF24`
   Crea F24 con righe manuali per casi non automatizzabili

4. `generaPdf(ModelloF24 $f24): string`
   Layout conforme al modello ufficiale (grafico/testo fedele all'originale):
   - Sezione contribuente: CF, denominazione, domicilio
   - Sezione erario: righe tributi con codice, periodo, importo debiti/crediti
   - Totale importi sezione + totale generale da versare
   Usa DomPDF. Path salvato su storage.

CONTROLLER ModelloF24Controller (7 metodi):
- index — lista F24 con filtri anno/tipo/stato
- create — form compilazione manuale
- store — salva
- show — dettaglio righe
- generaDaLiquidazione — crea F24 da LiquidazioneIva selezionata
- generaDaRitenute — crea F24 da riepilogo ritenute mensili
- exportPdf — download PDF modello compilato
- versato — marca come versato con data

VUE resources/js/Pages/Fiscale/F24/:
- Index.vue: tabella + KPI: totale da versare questo mese/trimestre
- Create.vue: form con sezioni (Erario, INPS, Regioni) con righe dinamiche
- Show.vue: anteprima modello + bottone "Genera PDF" + "Marca come versato"

Aggiunge integrazione in:
- IvaController: dopo chiudi liquidazione → bottone "Genera F24"
- CompensaTerziController: dopo versa ritenute → bottone "Genera F24"

Test: tests/Feature/Fiscale/ModelloF24Test.php (10 test):
  crea da liquidazione, crea da ritenute, calcolo importi, PDF risponde 200, stato versato.
```

---

### G-REL · Relazione di Missione ETS
**Modello:** ⚙️ `sonnet`
**Effort:** 3 giorni
**Priorità:** 🟠 P2
**Dipendenze:** Member (✅), Incasso (✅), AccountingReportController (✅)

```
Nel progetto Tessera (Laravel 12) implementa la Relazione di Missione,
documento obbligatorio per ETS con entrate > €220.000 (art. 13 D.Lgs. 117/2017).

La Relazione di Missione descrive: attività istituzionale svolta, modalità di perseguimento
del bene comune, risorse finanziarie e patrimoniali, variazioni patrimoniali significative.

MIGRATION `create_relazioni_missione_table`:
  id, tenant_id, anno (smallint), bozza (bool default true),
  data_approvazione (date nullable), data_assemblea (date nullable),
  sezione_attivita (longtext nullable, HTML rich text),
  sezione_patrimonio (longtext nullable, HTML rich text),
  sezione_rendiconto (longtext nullable, HTML rich text),
  sezione_raccolta_fondi (longtext nullable, HTML rich text),
  sezione_enti_controllati (longtext nullable, HTML rich text),
  totale_soci (int nullable), totale_volontari (int nullable),
  totale_dipendenti (int nullable), note (text nullable), timestamps
  UNIQUE (tenant_id, anno)

SERVIZIO RelazioneMissioneService:
1. `precompila(Tenant $t, int $anno): array`
   Recupera automaticamente i dati dell'anno per pre-popolare le sezioni:
   - totale_soci: Member::where(stato='attivo')->count()
   - totale_volontari: Member::where(tipo=volontario)->count()
   - totale_entrate: sum incassi anno
   - totale_uscite: sum spese anno
   - saldo_cassa: rendiconto cassa anno
   - Genera HTML iniziale per sezione_rendiconto con tabella dati contabili

2. `generaPdf(RelazioneMissione $r): string`
   PDF con: intestazione tenant, anno esercizio, sezioni strutturate,
   dati quantitativi in evidenza, spazio per firma dell'organo direttivo

CONTROLLER RelazioneMissioneController (5 metodi):
- index — lista per anno con stato (bozza/approvata)
- createOrEdit (unica per anno, idempotente) — editor con precompila automatica
- save — salva bozza
- approva — marca come approvata con data assemblea
- exportPdf — download PDF

VUE resources/js/Pages/Bilancio/RelazioneMissione/:
- Index.vue: card per anno con stato + link
- Editor.vue: form con 5 sezioni in accordion, editor rich text (usa textarea con markdown
  o <textarea> HTML semplice se non è presente un editor nel progetto),
  pannello laterale con i KPI auto-calcolati (soci, entrate, uscite)
  Pulsante "Pre-compila automaticamente" che popola le sezioni con dati reali

In AppLayout.vue aggiungi voce "Relazione di Missione" nella sezione Bilancio.
Aggiungi nel menu solo se tenant.organization_type include ETS/APS/ODV.

Test: tests/Feature/Bilancio/RelazioneMissioneTest.php (8 test):
  precompila dati, salva bozza, approva, unicità per anno, PDF risponde 200.
```

---

### G-ERL · Comunicazione Erogazioni Liberali
**Modello:** ⚡ `haiku`
**Effort:** 2 giorni
**Priorità:** 🟠 P2
**Dipendenze:** Incasso (✅), Member (✅)

```
Nel progetto Tessera (Laravel 12) implementa il modulo per la comunicazione
annuale delle erogazioni liberali ricevute dalle ETS (art. 23 D.Lgs. 117/2017).

CONTESTO:
- app/Models/Incasso.php — tipo: donazione (campo tipo='donazione')
- I donatori sono Soci (Member) o soggetti anonimi (campo donatore libero)
- Le ETS devono comunicare all'AdE le donazioni ricevute entro il 16 marzo

MIGRATION `add_erogazione_liberale_to_incassi`:
  - Aggiunge: deducibile_donatore (bool default false) — se true, il donatore può detrarre
  - ammontare_deducibile (decimal 12,2 nullable) — può essere diverso dall'importo totale
  - codice_fiscale_donatore (varchar 16 nullable) — se non socio

Aggiungi a IncassoController:
`comunicazioneErogazioniLiberali(Request $r)`:
  Parametri GET: anno (int)
  Recupera tutte le donazioni dell'anno con importo >= 1€ (soglia comunicazione AdE)
  Genera:
  1. PDF riepilogativo: lista donatori con CF, importo, data, eventuale causale
  2. CSV nel formato richiesto per upload su portale AdE (verifica formato attuale AdE)
     Colonne: codice_fiscale_donatore, cognome_nome, data_erogazione, importo, deducibile (S/N)

In Incasso/Create.vue (per tipo=donazione): aggiungi checkbox "Deducibile per il donatore"
e campo "Codice Fiscale donatore" (se non selezionato un socio con CF noto).

Aggiungi in AppLayout.vue voce "Erogazioni Liberali" in sezione Bilancio (solo per ETS).

Test: 4 test (CSV con formato corretto, PDF risponde 200, filtraggio per anno, donazioni senza CF).
```

---

### G-CEE · Bilancio CEE Strutturato
**Modello:** ⚙️ `sonnet`
**Effort:** 4 giorni
**Priorità:** 🟠 P2
**Dipendenze:** ContoContabile (✅), MovimentoContabile (✅)

```
Nel progetto Tessera (Laravel 12) implementa il Bilancio CEE strutturato
(Stato Patrimoniale + Conto Economico IV Direttiva) per le cooperative.

CONTESTO ESISTENTE:
- app/Http/Controllers/AccountingReportController.php — estendere
- app/Models/ContoContabile.php — natura: attivo/passivo/patrimonio_netto/costo/ricavo
- app/Services/MovimentoContabileService.php

MAPPING CEE (da implementare come config/bilancio_cee.php):
Stato Patrimoniale ATTIVO:
  A) Crediti verso soci (saldi conti 1.50.*)
  B) Immobilizzazioni: I-Immateriali (1.10.*), II-Materiali (1.20.*), III-Finanziarie (1.30.*)
  C) Attivo Circolante: Rimanenze (1.40.*), Crediti (1.50.*), Disponibilità (1.60.*)
  D) Ratei e Risconti (1.70.*)

Stato Patrimoniale PASSIVO:
  A) Patrimonio Netto (3.*)
  B) Fondi per rischi (2.10.*)
  C) TFR (2.20.*)
  D) Debiti (2.30.*)
  E) Ratei e Risconti passivi (2.40.*)

Conto Economico:
  A) Valore della produzione: Ricavi (4.*)
  B) Costi della produzione: Acquisti, Servizi, Godimento beni terzi, Personale, Amm.ti (5.*)
  C) Proventi e oneri finanziari (6.*)
  D) Rettifiche di valore (7.*)

SERVIZIO BilancioService:
1. `getStatoPatrimoniale(Tenant $t, Carbon $al): array`
   Struttura CEE con saldi per ogni voce, subtotali, totale attivo == passivo.
   Ogni voce include: codici conti, saldo corrente, saldo anno precedente (comparativo).

2. `getContoEconomico(Tenant $t, Carbon $dal, Carbon $al): array`
   Struttura CEE con ricavi, costi per natura, utile/perdita.

3. `getSaldiConti(Tenant $t, Carbon $dal, Carbon $al): Collection`
   Saldo per ogni conto = sum(dare) - sum(avere) delle righe nel periodo.
   Caching 10 minuti per performance.

4. `esportaPdf(Tenant $t, int $anno): StreamedResponse`
   Layout A4 portrait, due sezioni: SP e CE, intestazione con dati tenant + anno.

CONTROLLER BilancioController (aggiunge al AccountingReportController):
- `statoPatrimoniale(Request $r)` — render Inertia 'Bilancio/StatoPatrimoniale'
- `contoEconomico(Request $r)` — render Inertia 'Bilancio/ContoEconomico'
- `esportaPdf(Request $r)` — download PDF

VUE resources/js/Pages/Bilancio/:
- StatoPatrimoniale.vue:
  Layout a due colonne (Attivo | Passivo), sezioni collassabili per voce CEE,
  saldi con colonna anno corrente + anno precedente (comparativo)
  Riga in fondo: Totale Attivo | Totale Passivo (devono coincidere, badge verde/rosso)
  Filtro: data di riferimento (default: 31/12 anno corrente)

- ContoEconomico.vue:
  Layout a colonna singola, sezioni A-D, subtotali per sezione
  Riga finale: Utile/Perdita d'esercizio (verde se positivo, rosso se negativo)
  Filtro: dal/al date

Aggiungi in AppLayout.vue: voce "Stato Patrimoniale" e "Conto Economico" in sezione Bilancio.
Queste sezioni visibili solo se il tenant ha movimenti in partita doppia (ContoContabile count > 0).

Test: tests/Feature/Bilancio/BilancioTest.php (10 test):
  saldi corretti per natura conto, totale attivo == passivo, utile = ricavi - costi,
  comparativo anno precedente, PDF risponde 200, caching saldi.
```

---

## FASE 5 — Security, Analytics e Completamento

---

### H-CDC · Centri di Costo
**Modello:** ⚙️ `sonnet`
**Effort:** 4 giorni
**Priorità:** 🟡 P3
**Dipendenze:** ContoContabile (✅), MovimentoContabile (✅)

```
Nel progetto Tessera (Laravel 12) implementa i Centri di Costo (già pianificato in
PIANO_SVILUPPO.md Area E1).

CONTESTO: il prompt originale E1 in PIANO_SVILUPPO.md è ancora valido.
Leggi il file docs/PIANO_SVILUPPO.md sezione "AREA E · Centri di Costo → E1"
e implementa esattamente quanto descritto lì, con i seguenti adattamenti:

1. Usa `conti_contabili` invece di `chart_of_accounts` (nome tabella attuale nel progetto)
2. Aggiungi cost_center_id anche a `asset_depreciation_schedules` (per ammortamenti per centro)
3. Il report `show()` deve includere anche le quote ammortamento del centro
4. Il grafico mensile in Show.vue: usa Chart.js se disponibile,
   altrimenti barre CSS pure (non installare librerie aggiuntive senza verificare package.json)

Aggiorna il seeder PianoContiCooperativaSeeder per includere centri di costo di esempio:
  CC01 - Sede Principale, CC02 - Progetto A, CC03 - Area Commerciale

Test: tests/Feature/Contabilita/CentriDiCostoTest.php (10 test):
  CRUD, assegnazione a riga movimento, report saldo per periodo, cespiti per centro.
```

---

### H-BI · Dashboard Analytics con Grafici
**Modello:** ⚙️ `sonnet`
**Effort:** 4 giorni
**Priorità:** 🟡 P3
**Dipendenze:** AccountingReportController (✅), Member (✅)

```
Nel progetto Tessera (Laravel 12 + Vue 3) implementa la dashboard analytics con grafici.
Il prompt originale è in PIANO_SVILUPPO.md Area G1 (Dashboard Analytics con Grafici).

Prima di procedere:
1. Verifica se Chart.js è già installato: leggi package.json
2. Se non presente, installa: npm install chart.js vue-chartjs
3. Verifica se vue-chartjs è già usato nel progetto (cerca 'vue-chartjs' nei file Vue)

Implementa esattamente il prompt G1 del PIANO_SVILUPPO.md con questi adattamenti:
- GRAFICO 5 (aggiuntivo): "Andamento Soci" (LineChart ultimi 12 mesi):
  Nuove iscrizioni per mese (verde) vs Cessazioni per mese (rosso)
- GRAFICO 6 (solo ETS): "Distribuzione Donazioni per tipo":
  DoughnutChart: quote_sociali vs donazioni vs incassi_generici (ultimi 12 mesi)

DashboardController: aggiungi i metodi per i nuovi grafici (endpoint JSON separati
o props Inertia, scegli il pattern già usato nel progetto).

Test: 3 test (DashboardController response 200, props grafici presenti, dati non vuoti con fixture).
```

---

### H-XLS · Export Excel Avanzati
**Modello:** ⚡ `haiku`
**Effort:** 3 giorni
**Priorità:** 🟡 P3
**Dipendenze:** nessuna nuova

```
Nel progetto Tessera (Laravel 12) aggiungi l'export in XLSX delle sezioni principali.
Il prompt originale è in PIANO_SVILUPPO.md Area G2 (Report Esportazioni Avanzate).

Prima di procedere:
1. Verifica se maatwebsite/excel è già installato: leggi composer.json
2. Se non presente, installa: composer require maatwebsite/excel

Implementa esattamente il prompt G2 del PIANO_SVILUPPO.md con questi adattamenti:
- Aggiungi EXPORT 5: "Lista Cespiti" — XLSX con:
  Colonne: Codice | Nome | Categoria | Data acquisto | Costo storico | Fondo amm. | VNC | Stato
  Foglio separato "Piano Ammortamento" con schedule per ogni cespite
- Aggiungi EXPORT 6: "Compensi Terzi" (solo se modulo G-RIT implementato):
  Un foglio per percipiente con: data, descrizione, imponibile, ritenuta, netto, stato versamento

Crea le classi Export in app/Exports/:
  SociExport.php, PrimaNotaExport.php, RegistroIvaExport.php,
  CapitaleSocialeExport.php, CespitiExport.php, CompensaTerziExport.php

Test: 3 test (response 200, Content-Type xlsx, riga header presente nel file).
```

---

### H-POL · Model Policies Fine-Grained
**Modello:** ⚙️ `sonnet`
**Effort:** 5 giorni
**Priorità:** 🟡 P3
**Dipendenze:** nessuna nuova

```
Nel progetto Tessera (Laravel 12) implementa le Policy Laravel per il controllo
accessi fine-grained, riducendo la dipendenza dal solo middleware role:.

CONTESTO:
- app/Models/Role.php — ruoli: admin, contabile, segreteria, socio
- app/Models/User.php — hasRole(string ...$roles): bool
- app/Http/Middleware/EnsureUserHasRole.php — middleware attuale
- app/Policies/MemberPolicy.php — esiste già, prendila come modello

Crea le seguenti Policy (in app/Policies/):

Per ognuna implementa: viewAny, view, create, update, delete, restore, forceDelete.
Regole comuni: admin può tutto; contabile può CRUD ma non delete; segreteria può solo read;
  il record deve appartenere al tenant dell'utente (tenant_id check).

1. CespitiPolicy.php (per Asset):
   - contabile: tutti i CRUD + registra ammortamento
   - segreteria: solo viewAny e view
   - Extra: `registraAmmortamento` (solo admin/contabile)
   - Extra: `dismetti` (solo admin)

2. FatturaPolicy.php (per FatturaPassiva e FatturaAttiva):
   - contabile: tutti i CRUD + emetti + paga
   - segreteria: viewAny + view
   - Extra: `emetti` (solo contabile/admin)
   - Extra: `paga` (solo contabile/admin)

3. IncassoPolicy.php (per Incasso):
   - segreteria: viewAny + view + create
   - contabile: tutti i CRUD
   - Extra: `annulla` (solo admin/contabile)

4. MovimentoContabilePolicy.php (per MovimentoContabile):
   - contabile: viewAny + view + create
   - Extra: `annulla` (solo admin, solo se esercizio aperto)

5. CooperativeSharePolicy.php (per CooperativeShare):
   - admin: tutti i CRUD
   - contabile: viewAny + view + update (versa/riscatta)
   - segreteria: viewAny + view

6. VerbalePolicy.php (per Verbale):
   - admin/segreteria: tutti i CRUD
   - contabile: viewAny + view
   - socio: viewAny solo se pubblici

Aggiungi la registrazione in app/Providers/AuthServiceProvider.php
(o in boot() se usa Gate::policy).

Aggiorna i controllers principali:
Sostituisci le middleware inline (`$this->middleware('role:admin')`) con:
`$this->authorize('create', Asset::class)` nei metodi store/create
`$this->authorize('update', $asset)` nei metodi edit/update
etc.

Test: tests/Feature/Security/PoliciesTest.php (20 test):
  ogni Policy × ogni ruolo × ogni metodo. Usa actingAs() con utente che ha il ruolo.
  Verifica 403 per azioni non autorizzate, 200 per autorizzate.
```

---

### H-AUD · Audit Trail
**Modello:** ⚙️ `sonnet`
**Effort:** 4 giorni
**Priorità:** 🟡 P3
**Dipendenze:** H-POL

```
Nel progetto Tessera (Laravel 12) implementa un sistema di Audit Trail per tracciare
chi ha fatto cosa e quando (obbligatorio per compliance e debug in sistemi contabili).

MIGRATION `create_audit_logs_table`:
  id (bigint), tenant_id (FK, indexed), user_id (FK users nullable),
  auditable_type (varchar 255), auditable_id (bigint),
  event (enum: created/updated/deleted/restored/custom),
  old_values (json nullable), new_values (json nullable),
  ip_address (varchar 45 nullable), user_agent (varchar 500 nullable),
  url (varchar 1000 nullable), tags (json nullable), created_at (timestamp)
  INDEXES: (tenant_id, auditable_type, auditable_id), (tenant_id, user_id), (created_at)
  NOTA: NO updated_at (il log non viene mai modificato)

TRAIT AuditsChanges (app/Traits/AuditsChanges.php):
  Implementa boot() con static::created, updated, deleted observers.
  Ogni evento chiama AuditLog::registra(model, event, oldValues, newValues).
  Esclude automaticamente i campi: password, remember_token, two_factor_*.
  Configurabile: $auditExclude = [] su ogni model.

MODEL AuditLog (app/Models/AuditLog.php):
  - Non usa BelongsToTenant (è un log, non è modificabile)
  - `registra(Model $m, string $event, ?array $old, ?array $new): self`
    Recupera automaticamente: user_id (Auth::id()), tenant_id (app('current_tenant')->id),
    ip_address (Request::ip()), user_agent (Request::userAgent()), url (Request::fullUrl())
  - Scope: `perTenant`, `perModello`, `perUtente`, `nelPeriodo`

Applica il trait ai seguenti modelli:
  Asset, AssetDepreciationSchedule, AssetDisposal,
  FatturaPassiva, FatturaAttiva, Incasso, Spesa,
  MovimentoContabile, Member, CooperativeShare, PrestitoSocialeLibretto

CONTROLLER AuditLogController:
- `index(Request $r)` — paginated log con filtri:
  tipo_modello, utente, periodo, evento (created/updated/deleted)
  Solo admin può accedere.
- `show(AuditLog $log)` — dettaglio con diff old_values vs new_values

VUE resources/js/Pages/Admin/Audit/Index.vue:
- Tabella: Data | Utente | Modello | ID | Evento | IP
- Badge evento: verde=created, giallo=updated, rosso=deleted, blu=restored
- Click su riga → mostra pannello laterale con diff JSON (evidenzia campi modificati)
- Filtri: modello (select), utente (autocomplete), periodo (date range), evento

Aggiungi voce "Audit Log" in AppLayout.vue nella sezione Admin (solo admin).
Route: GET /app/{tenant}/admin/audit con middleware role:admin.

Test: tests/Feature/Security/AuditTrailTest.php (12 test):
  log su created/updated/deleted, esclusione campi sensibili, filtri per modello/utente,
  tenant isolation (utente A non vede log tenant B).
```

---

### H-CSO · Carica Sociale CRUD Completo
**Modello:** ⚡ `haiku`
**Effort:** 2 giorni
**Priorità:** 🟡 P3
**Dipendenze:** Member (✅), Organo (✅)

```
Nel progetto Tessera (Laravel 12) espandi il modulo CaricaSociale da 1 a 7 metodi.

Prima di procedere:
1. Leggi app/Http/Controllers/CaricaSocialeController.php (1 metodo attuale)
2. Leggi app/Models/Incarico.php (relazione tra Member e Organo)
3. Verifica se esiste un Model CaricaSociale o se è solo un enum/campo di Incarico

Se CaricaSociale è solo un campo enum in Incarico (non una tabella separata):
  Crea migration `create_cariche_sociali_table`:
    id, tenant_id, organo_id (FK organi), codice (varchar 20), denominazione (varchar 100),
    descrizione (text nullable), obbligatoria (bool default false),
    max_titolari (int nullable), attiva (bool default true), timestamps

Crea CaricaSociale model con BelongsToTenant, relazioni.

CaricaSocialeController (7 metodi):
- index — lista cariche per organo con count incarichi attivi
- create — form
- store — valida codice univoco per organo+tenant, salva
- show — dettaglio + lista titolari attuali (Incarichi attivi)
- edit — form
- update — aggiorna (solo se non è di sistema)
- destroy — soft delete (solo se nessun incarico attivo)

VUE resources/js/Pages/Governance/CaricheSociali/:
- Index.vue: raggruppate per organo, con badge "obbligatoria", count titolari
- Create.vue / Edit.vue: form semplice
- (Show integrato in Index come pannello espandibile)

Integra in Incarico/Create.vue: select "Tipo carica" con CaricaSociale del relativo organo.

Test: tests/Feature/Governance/CaricaSocialeTest.php (8 test):
  CRUD, unicità codice, delete bloccato con incarichi attivi, count titolari.
```

---

### H-DIS · Dismissione Cespiti UI Completa
**Modello:** ⚡ `haiku`
**Effort:** 2 giorni
**Priorità:** 🟡 P3
**Dipendenze:** Cespiti D1-D6 (✅), DismissioneCespitiService (✅)

```
Nel progetto Tessera (Laravel 12 + Vue 3) completa la UI del workflow di dismissione cespiti.

CONTESTO ESISTENTE (da leggere prima):
- app/Http/Controllers/DismissioneCespitiController.php (3 metodi: preview, store, ?)
- app/Services/DismissioneCespitiService.php — logica già implementata
- resources/js/Pages/Cespiti/Show.vue — tab "Dismissione" già presente
- app/Models/AssetDisposal.php — già presente con tipo, realizzo, plusvalenza

Mancano:
1. Show dismissione — pagina di dettaglio dopo dismissione avvenuta
2. Bulk dismissioni — seleziona più cespiti e dismetti con stesso tipo/data
3. Lista dismissioni — index di tutte le dismissioni avvenute con filtri

Aggiungi a DismissioneCespitiController:
- `show(AssetDisposal $disposal)` — dettaglio dismissione con movimento contabile collegato
- `index(Request $r)` — lista tutte le dismissioni del tenant
  filtri: tipo, anno, range importo realizzo, plus/minus
- `bulkPreview(Request $r)` — preview per lista di asset selezionati
- `bulkStore(Request $r)` — batch dismissione

VUE resources/js/Pages/Cespiti/Dismissioni/:
- Index.vue: tabella dismissioni con colonne:
  Data | Cespite | Tipo | VNC al momento | Realizzo | Plus/Minus | Stato
  Badge Plus/Minus: verde (plusvalenza), rosso (minusvalenza), grigio (pari)
  KPI: totale plusvalenze anno, totale minusvalenze anno, valore realizzi
- Show.vue: dettaglio dismissione + link al movimento contabile generato + PDF

In Cespiti/Index.vue aggiungi:
- Checkbox multi-selezione righe
- Pulsante "Dismetti selezionati" → mostra modal con form batch:
  tipo (rottamazione/vendita/donazione), data_dismissione, note
  Per tipo=vendita: campo valore_realizzo per asset (uguale per tutti o personalizzabile)

Test: 5 test (show, index con filtri, bulk preview, bulk store, movimenti bilanciati).
```

---

### H-EML · Email Template Engine Completo
**Modello:** ⚙️ `sonnet`
**Effort:** 3 giorni
**Priorità:** 🟡 P3
**Dipendenze:** Member (✅), PlaceholderResolver (✅)

```
Nel progetto Tessera (Laravel 12 + Vue 3) completa il modulo Email Template
(attualmente 4 metodi di CRUD base).

CONTESTO ESISTENTE:
- app/Http/Controllers/EmailTemplateController.php (4 metodi, leggi prima)
- app/Models/EmailTemplate.php (leggi fillable e relazioni)
- app/Services/PlaceholderResolver.php — sostituisce {{variabili}} nel testo
- Mailable esistenti (cerca 'extends Mailable' nel progetto per capire il pattern)

Variabili disponibili (da PlaceholderResolver):
  {{nome_socio}}, {{cognome_socio}}, {{email_socio}}, {{numero_tessera}},
  {{nome_organizzazione}}, {{data_oggi}}, {{importo}}, {{scadenza}}

Aggiungi a EmailTemplateController:
1. `preview(Request $r, EmailTemplate $template)`:
   Riceve un member_id opzionale, sostituisce le variabili con dati reali o esempi,
   ritorna JSON: {soggetto: string, corpo_html: string}
   Usato per preview live nel builder Vue.

2. `sendTest(Request $r, EmailTemplate $template)`:
   Invia email di test all'indirizzo dell'utente loggato.
   Ritorna JSON: {successo: bool, errore: string|null}

3. `sendBulk(Request $r, EmailTemplate $template)`:
   Parametri POST: {destinatari: 'tutti_soci'|'soci_morosi'|'volontari'|array_di_member_id}
   Invia email usando queue (dispatch). Ritorna JSON: {accodati: int}
   Usa Laravel Queue (driver: database). Job: InviaEmailMassiva.

4. `storico(Request $r)`:
   Lista email inviate: template usato, destinatario, data invio, stato (inviata/errore)
   MIGRATION `create_email_invii_table`:
     id, tenant_id, template_id (FK), member_id (FK nullable), email_destinatario,
     soggetto, corpo (text), stato (enum: accodata/inviata/errore), errore_messaggio (text nullable),
     inviata_at (datetime nullable), timestamps

VUE resources/js/Pages/Settings/EmailTemplates/:
(sostituisce le pagine esistenti, aggiungi feature mancanti)
- Index.vue: lista templates con badge (tipo, ultima modifica) + colonna "Invii" con count
- Builder.vue (o Edit.vue con builder integrato):
  Pannello sinistro: editor textarea (HTML o testo) per soggetto + corpo
  Pannello destro: "Preview live" che chiama endpoint preview ogni 1s di debounce
  Sidebar "Variabili disponibili": click su {{variabile}} per inserirla nel cursore
  Pulsante "Invia email di test" (chiama sendTest)
- Invio.vue (o modal in Index.vue):
  Selezione destinatari (radio: tutti/morosi/volontari o lista manuale)
  Preview soggetto, count destinatari
  Pulsante "Invia ora"
- Storico.vue: tabella invii con filtri template/periodo/stato

Test: tests/Feature/Settings/EmailTemplateTest.php (8 test):
  preview sostituisce variabili, sendBulk accoda il job, storico registra invii,
  errore SMTP registrato in storico.
```

---

### H-API · OpenAPI Documentation
**Modello:** ⚡ `haiku`
**Effort:** 3 giorni
**Priorità:** ⚪ P4
**Dipendenze:** tutte le fasi precedenti (preferibilmente dopo)

```
Nel progetto Tessera (Laravel 12) implementa la documentazione API con OpenAPI/Swagger.

1. Installa: composer require darkaonline/l5-swagger

2. Configura in config/l5-swagger.php:
   - title: "Tessera API — Network GTC"
   - api.default.routes.api: "/api/docs"
   - ui: swagger, version: "3.0"

3. Aggiungi annotazioni PHPDoc (formato OpenAPI 3.0) ai controller principali:
   Inizia con i 10 endpoint più importanti:
   - GET /cespiti (index)
   - POST /cespiti (store)
   - GET /cespiti/{id} (show)
   - GET /iva/registro-acquisti
   - GET /iva/liquidazione
   - GET /members
   - POST /members
   - GET /fatture-passive
   - GET /scadenzario/clienti
   - GET /bilancio/stato-patrimoniale

4. Crea la sezione "API" in AppLayout.vue (solo admin):
   Link a /api/docs (Swagger UI)

5. Aggiungi API route group per futuri endpoint JSON:
   routes/api.php — route::apiResource per i principali modelli
   Middleware: auth:sanctum + check tenant da API token

Test: assertStatus(200) su /api/docs, assertStatus(200) su /api/documentation.json.
```

---

## RIEPILOGO ESECUTIVO

### Tabella master tutti i task

| ID | Feature | Modello | Priorità | Effort | Dipende da |
|----|---------|---------|----------|--------|------------|
| TEST-1 | Test Cooperative modules | ⚡ haiku | Pre-requisito | 4-5 gg | — |
| TEST-2 | Test Governance | ⚙️ sonnet | Pre-requisito | 3-4 gg | — |
| TEST-3 | Test integrazione flussi | ⚙️ sonnet | Pre-requisito | 3-4 gg | TEST-1,2 |
| E-SCA | Scadenziario completo | ⚙️ sonnet | 🔴 P1 | 5 gg | F-ATT |
| E-APE | Apertura/Chiusura esercizio | ⚙️ sonnet | 🔴 P1 | 3 gg | — |
| E-RAT | Ratei e Risconti | ⚙️ sonnet | 🔴 P1 | 4 gg | — |
| E-LIB | Libro Giornale + Registro Vendite | ⚡ haiku | 🔴 P1 | 2 gg | F-ATT |
| E-LIP | LIPE XML + Acconto IVA | ⚙️ sonnet | 🟠 P2 | 2 gg | — |
| F-ATT | Fatture Attive CRUD | ⚙️ sonnet | 🔴 P1 | 5 gg | — |
| F-NDC | Nota di Credito Attiva | ⚡ haiku | 🔴 P1 | 2 gg | F-ATT |
| F-XML | Fattura Elettronica XML/SDI | 🧠 sonnet | 🟠 P2 | 10 gg | F-ATT, F-NDC |
| F-TD7 | Fattura Semplificata TD07 | ⚡ haiku | 🟡 P3 | 2 gg | F-ATT |
| G-RIT | Ritenute d'Acconto / Compensi | ⚙️ sonnet | 🟠 P2 | 5 gg | — |
| G-CU | Certificazione Unica | ⚡ haiku | 🟠 P2 | 3 gg | G-RIT |
| G-F24 | Modello F24 | ⚙️ sonnet | 🟠 P2 | 4 gg | G-RIT, E-LIP |
| G-REL | Relazione di Missione ETS | ⚙️ sonnet | 🟠 P2 | 3 gg | — |
| G-ERL | Erogazioni Liberali | ⚡ haiku | 🟠 P2 | 2 gg | — |
| G-CEE | Bilancio CEE strutturato | ⚙️ sonnet | 🟠 P2 | 4 gg | — |
| H-CDC | Centri di Costo | ⚙️ sonnet | 🟡 P3 | 4 gg | — |
| H-BI | Dashboard Analytics + Grafici | ⚙️ sonnet | 🟡 P3 | 4 gg | — |
| H-XLS | Export Excel avanzati | ⚡ haiku | 🟡 P3 | 3 gg | — |
| H-POL | Model Policies | ⚙️ sonnet | 🟡 P3 | 5 gg | — |
| H-AUD | Audit Trail | ⚙️ sonnet | 🟡 P3 | 4 gg | H-POL |
| H-CSO | Carica Sociale CRUD | ⚡ haiku | 🟡 P3 | 2 gg | — |
| H-DIS | Dismissione Cespiti UI | ⚡ haiku | 🟡 P3 | 2 gg | — |
| H-EML | Email Template Engine | ⚙️ sonnet | 🟡 P3 | 3 gg | — |
| H-API | OpenAPI Documentation | ⚡ haiku | ⚪ P4 | 3 gg | — |

### Stima effort totale

| Fase | Task | Effort totale | Modello prevalente |
|------|------|--------------|-------------------|
| Pre-requisiti (Test) | TEST-1,2,3 | 10-13 gg | haiku + sonnet |
| Fase 2 (Contabilità core) | E-SCA, E-APE, E-RAT, E-LIB, E-LIP | 16 gg | sonnet |
| Fase 3 (Fatturazione) | F-ATT, F-NDC, F-XML, F-TD7 | 19 gg | sonnet (🧠 per SDI) |
| Fase 4 (Adempimenti+ETS) | G-RIT, G-CU, G-F24, G-REL, G-ERL, G-CEE | 21 gg | sonnet + haiku |
| Fase 5 (Security+Completamento) | H-CDC, H-BI, H-XLS, H-POL, H-AUD, H-CSO, H-DIS, H-EML, H-API | 30 gg | sonnet + haiku |
| **TOTALE** | **27 task** | **~96 giorni** | **~60% sonnet, 40% haiku** |

### Ordine di esecuzione consigliato

```
TEST-1 → TEST-2 → TEST-3
    ↓
F-ATT → F-NDC → E-SCA → E-LIB
    ↓             ↓
E-APE         E-LIP → G-F24
E-RAT
    ↓
G-RIT → G-CU → G-F24
G-REL → G-ERL
G-CEE
    ↓
F-XML (dopo F-ATT+F-NDC completi)
    ↓
H-POL → H-AUD
H-CDC → H-BI → H-XLS
H-CSO, H-DIS, H-EML, F-TD7
    ↓
H-API (ultimo)
```

---

## ISTRUZIONI D'USO

### Come avviare una sessione di lavoro

```
1. Apri Claude Code nel progetto ETS-OK-main
2. Seleziona il modello con /model:
   - Architetture complesse (🧠): /model claude-sonnet-4-6
   - Feature standard (⚙️): /model claude-sonnet-4-6
   - Task semplici (⚡): /model claude-haiku-4-5
3. Prima di incollare il prompt, leggi sempre i file rilevanti indicati nel task
4. Copia il blocco prompt dalla sezione corrispondente
5. Dopo l'implementazione: npm run build && php artisan test
6. Commit con git add [file specifici] + git commit -m "feat(ID): descrizione"
```

### Checklist pre-commit per ogni task

- [ ] Migration: `php artisan migrate` senza errori
- [ ] Model: `php artisan tinker` → Model::count() senza eccezioni
- [ ] Controller: route definita e raggiungibile (`php artisan route:list --name=<prefix>`)
- [ ] Vue: `npm run build` senza errori TypeScript/Vue
- [ ] Test: `php artisan test --filter=<NomeTest>` tutto verde
- [ ] Tenant isolation: record creati con tenant_id corretto

---

*Documento generato Aprile 2026 — Versione 2.0 (post Cespiti D1-D6)*
*Sostituisce PIANO_SVILUPPO.md v1.0.0 come documento di riferimento*
