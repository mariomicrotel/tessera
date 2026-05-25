# Roadmap — Tessera/ETS-OK come "Data Layer" per il commercialista

> **Status**: proposta (2026-05-25) · **Owner**: Mario De Vita

---

## 1. Vision

Tessera/ETS-OK **non sostituisce** il software contabile del commercialista (TeamSystem, Zucchetti, Profis, OmniaWeb, …). Lo **alimenta**.

L'ente gestisce sul gestionale la propria operatività quotidiana (soci, quote, donazioni, ricevute, spese, fatture, RIBA, riconciliazioni bancarie, atti). Il commercialista — già modellato come "consulente esterno" con accesso cross-tenant — preleva i dati nei formati che gli servono per chiudere i suoi adempimenti.

Eliminiamo dal menu visibile tutta la **contabilità in partita doppia** (troppo onerosa da mantenere correttamente, sovrapposizione col lavoro del commercialista). Manteniamo gli output **obbligatori per legge** (Bilancio CEE, Relazione di Missione, sezione Cooperativa) perché sono adempimenti che spesso transitano dall'ente prima della firma del professionista.

---

## 2. Decisioni di scope

### ❌ Rimosso dal menu (feature flag off di default)

| Voce | Route | Motivazione |
|------|-------|-------------|
| Conti tesoreria | `conti.*` | Gestito nel software contabile |
| Prima nota | `prima-nota.*` | Partita doppia → commercialista |
| Esercizi Contabili | `esercizi.*` | Chiusura esercizi → commercialista |
| Ratei e Risconti | `ratei-risconti.*` | Tecniche contabili pure |
| Centri di Costo | `centri-di-costo.*` | Analitica → commercialista |
| Scadenzario contabile | `scadenze.*` | Scadenze prima nota |
| Report contabilità | `reports.accounting` | Output partita doppia |
| Conto Economico | `reports.conto-economico` | Output bilancio |
| Rendiconto per cassa | `reports.rendiconto-cassa` | Lo produce il commercialista |
| Libro Giornale | `reports.libro-giornale` | Output partita doppia |
| Registro Vendite (contabile) | `reports.registro-vendite` | Duplicato del registro IVA |

→ **Sparisce la sezione "Contabilità" dal menu** (codice mantenuto, route attive, ma non navigabili da UI).

### ✅ Mantenuto e valorizzato

| Area | Cosa contiene | Perché serve al commercialista |
|------|---------------|--------------------------------|
| **Soci** | Anagrafiche, Libro soci, Tipologie, Tessere, Scadenzario quote | Base dati associativa |
| **Documenti / Verbali / Atti fondativi** | Documenti, verbali assemblee, statuto, atto costitutivo | Governance + adempimenti RUNTS |
| **Organi e votazioni** | Organi sociali, elezioni | Governance |
| **Patrimonio** | Immobili, sedi, magazzino, articoli, **Cespiti (registro)** | Inventario per IMU / ammortamenti |
| **Cassa** | Quote, donazioni, incassi, ricevute, spese, rimborsi | Movimenti operativi |
| **Cooperativa** ⭐ | Capitale Sociale, Prestito, Ristorni, Situazione Capitale, CE Coop | Obbligatori per legge coop |
| **IVA e Fatturazione** | Fatture Attive/Passive, Import XML FE, Fornitori, (coop: registri IVA, LIPE, acconto) | Dati IVA + ciclo fatture |
| **Adempimenti fiscali** | Compensi a Terzi, F24 | Dati per CU/770 e versamenti |
| **Banca** | RI.BA / CBI, Riconciliazione bancaria | Movimenti bancari |
| **Movimenti Amministrativi** | Movimenti semplificati (entrate/uscite) | Alternativa leggera per micro-ETS |
| **Bilancio ETS** ⭐ | Bilancio CEE, Relazione di Missione | **Obbligatori RUNTS** — l'ente li redige, il commercialista li valida |
| **Anagrafica ente** | Dati ente, Ricerca aziende (OpenAPI), Costi API | Setup |

### ⭐ Voci obbligatorie per legge mantenute esplicitamente

- **Bilancio CEE** + **Relazione di Missione** → obbligatorie per ETS (Codice Terzo Settore, art. 13)
- **Capitale Sociale, Prestito Sociale, Ristorni, Situazione Capitale, CE Coop** → obbligatori per cooperative

Queste rimangono nel menu sotto una nuova sezione **"Bilancio ETS"** (al posto di "Contabilità"), insieme a Erogazioni Liberali ETS (dato obbligatorio per la dichiarazione dei donatori).

---

## 3. Stato attuale dell'area consulente (già implementato)

L'infrastruttura cross-tenant **esiste già**:

### Modelli
- `ConsultantAssignment` — assegnazione consulente ↔ tenant (no `BelongsToTenant`, cross-tenant)
- `ConsultantRequest` — richieste documenti/info dal commercialista all'ente
- `ConsultantNote` — note del commercialista su un ente

### Backend
- `User::hasRole()` — consulente attivo ottiene ruoli admin/contabile/segreteria sul tenant corrente
- `ResolveTenant` middleware — gestisce contesto cross-tenant
- `ConsultantController` — dashboard, lista enti, richieste CRUD, note CRUD
- `HandleInertiaRequests` — espone `isConsultantInTenant`, `userRoles` derivati

### Route (`/consultant/*`)
```
GET  /consultant/dashboard
GET  /consultant/entities
GET  /consultant/entities/{slug}
GET  /consultant/entities/{slug}/requests
POST /consultant/entities/{slug}/requests
GET  /consultant/entities/{slug}/requests/{id}
PUT  /consultant/entities/{slug}/requests/{id}
GET  /consultant/entities/{slug}/notes
POST /consultant/entities/{slug}/notes
PUT  /consultant/entities/{slug}/notes/{id}
DELETE /consultant/entities/{slug}/notes/{id}
```

### Frontend
- Pagine Vue3 sotto `resources/js/Pages/Consultant/`
- Banner "Modalità consulente" in `AppLayout.vue` quando il commercialista entra in un tenant
- Voce "Area Consulente" nel menu (visibile solo a `role:consultant`)

### Gap da colmare
Manca tutto il layer **"dati esportabili"** verso il commercialista:
- ❌ Export massivi (CSV/Excel/XML/ZIP) di fatture/movimenti/spese per periodo
- ❌ Cruscotto dati operativi consolidato (oggi mostra solo richieste/note)
- ❌ Checklist adempimenti (cosa è stato consegnato per periodo)
- ❌ Calendario consegne documentali con notifiche
- ❌ Scambio file bidirezionale (consulente carica → ente, ente carica → consulente)
- ❌ Formati specifici per software esterni (TeamSystem CSV, Profis XML, ecc.)

---

## 4. Roadmap (4 fasi)

### 🔵 Fase 1 — Cleanup menu Contabilità · `~30 min`

**Obiettivo:** togliere dal menu visibile tutta la sezione "Contabilità" (codice e route restano).

**Tasks:**
- [ ] Disattivare di default i feature flag `double_entry_accounting`, `chart_of_accounts`, `accounting_reports`, `fiscal_year_closing`, `accruals_deferrals` su tutti i tenant
- [ ] Spostare Bilancio CEE, Relazione di Missione, Erogazioni Liberali ETS in una **nuova sezione "Bilancio ETS"** (sostituisce "Contabilità")
- [ ] Sezione Bilancio ETS visibile sotto flag `ets_balance_reports` (on di default per ETS, off per coop che usa CE Coop)
- [ ] Aggiornare `sectionForRoute` in `AppLayout.vue`
- [ ] Aggiornare separatori visivi e ordine sezioni

**Risultato:** menu più snello, gli utenti non vedono più voci "contabili pesanti" che li confondono.

**Deliverable:** commit con AppLayout aggiornato + migrazione/seeder per i feature flag.

---

### 🟢 Fase 2 — Cruscotto dati per il commercialista · `~3 giorni`

**Obiettivo:** dare al consulente una vista d'insieme dell'attività operativa di ciascun ente per periodo (mese/trimestre/anno).

**Tasks:**
- [ ] Pagina `Consultant/Entities/Dashboard.vue` con KPI per periodo selezionabile:
  - N° fatture emesse / ricevute · totale imponibile · IVA
  - N° ricevute / donazioni / incassi · totale
  - N° spese / rimborsi · totale
  - N° movimenti bancari riconciliati / non riconciliati
  - Saldo Capitale Sociale (solo coop) · variazioni
  - Stato Bilancio (bozza/approvato)
- [ ] Service `ConsultantStatsService::aggregateForPeriod(tenant, from, to)` con caching
- [ ] Grafici trend mensili (chart.js, già in stack)
- [ ] Quick links a ogni area per drill-down

**Modelli/migrazioni:** nessuna nuova, solo aggregati on-the-fly.

**Deliverable:** dashboard navigabile da `/consultant/entities/{slug}` con tab "Cruscotto dati".

---

### 🟡 Fase 3 — Export strutturati · `~4 giorni`

**Obiettivo:** dare al consulente un'unica pagina da cui generare bundle dati per periodo nel formato del suo software.

**Tasks:**
- [ ] Pagina `Consultant/Entities/Exports.vue` con form: periodo + selezione formati + selezione dati
- [ ] `ExportBundleService` con strategie per formato:
  - **CSV generico** (tutte le tabelle, una per file, zip)
  - **XML Agenzia Entrate** (registri IVA, LIPE) — già parzialmente esistente
  - **TeamSystem CSV** (formato specifico per import)
  - **Zucchetti CSV** (formato specifico)
  - **Profis XML** (formato specifico)
- [ ] Dati esportabili: fatture attive/passive, ricevute, donazioni, spese, compensi terzi, F24, movimenti bancari, anagrafiche fornitori/clienti, cespiti
- [ ] Job in coda `GenerateExportBundleJob` con notifica al consulente quando pronto
- [ ] Storage `consultant-exports/{tenant_id}/{date}-{format}.zip` con TTL 30 giorni
- [ ] Audit log di ogni export (chi, cosa, quando)

**Modelli/migrazioni:**
- `consultant_export_bundles` (id, tenant_id, consultant_id, period_from, period_to, formats[], status, file_path, expires_at)

**Deliverable:** export funzionante per almeno 2 formati (CSV generico + Agenzia Entrate XML).

---

### 🟠 Fase 4 — Scambio bidirezionale e checklist · `~5 giorni`

**Obiettivo:** chiudere il loop comunicativo tra ente e commercialista.

**Tasks:**

#### 4a. Scambio file
- [ ] Estendere `ConsultantRequest` con allegati: l'ente carica i file richiesti come risposta
- [ ] Nuova entità `ConsultantDelivery` — il consulente carica documenti per l'ente (es. F24 da pagare, bilancio firmato)
- [ ] Notifiche email/in-app a entrambi i lati

#### 4b. Checklist adempimenti per periodo
- [ ] Modello `AdempimentoChecklistItem` (template + istanza per ente/periodo)
- [ ] Template predefiniti: IVA trimestrale, LIPE, CU, 770, Bilancio annuale, Modello EAS, ecc.
- [ ] Stato per item: da fare / in lavorazione / consegnato / completato
- [ ] Vista commercialista: tutte le checklist di tutti gli enti per periodo (KPI "compliance")
- [ ] Vista ente: la sua checklist con call-to-action

#### 4c. Calendario consegne
- [ ] Pagina `Consultant/Calendar.vue` con vista mensile delle scadenze
- [ ] Scadenze derivate da template adempimenti + dati ente (tipologia, regime fiscale, dimensione)
- [ ] Notifiche T-7, T-1, scaduto

**Modelli/migrazioni:**
- `consultant_deliveries`
- `adempimenti_templates` + `adempimenti_items`
- estensione `consultant_request_attachments`

**Deliverable:** workflow completo richiesta → upload → notifica → checklist aggiornata.

---

## 5. Architettura tecnica

### Principi
- **Cross-tenant** — i modelli `Consultant*` non hanno `BelongsToTenant`, le query filtrano per `consultant_id`
- **Audit trail** — ogni export, ogni delivery, ogni cambio stato checklist va in `audit_logs`
- **Performance** — export pesanti in coda (`queue:work`), no inline
- **Sicurezza** — i file in `consultant-exports/` sono S3-style storage privato con signed URL temporanei
- **Multi-formato** — un'astrazione `ExportFormatStrategy` permette di aggiungere nuovi software senza toccare il core

### Stack invariato
- Laravel 12 + PHP 8.4 + MySQL 8 + Vue 3 + Inertia + Vite + Tailwind + Pest

### Nuove dipendenze (eventuali)
- `maatwebsite/excel` per export Excel nativi (CSV ok con built-in)
- `simplexml` (built-in) per XML
- `zip` per bundle

---

## 6. Compatibilità retroattiva

Tutte le route `prima-nota.*`, `conti.*`, `reports.libro-giornale`, ecc. **rimangono attive** ma non linkate. Questo permette:
- A consulenti tecnici di accedervi via URL diretto per audit
- A test esistenti di continuare a funzionare
- A future feature flag di riattivarle per tenant specifici che vogliono la contabilità interna

---

## 7. Decisioni aperte da chiarire prima di iniziare F2

1. **Formati export prioritari**: quali 2-3 software contabili sono usati dai commercialisti dei nostri tenant? (TeamSystem, Zucchetti, Profis, OmniaWeb, …)
2. **Storage export**: locale o S3? (per ora locale + TTL 30gg sembra ok)
3. **Notifiche**: solo email o anche in-app real-time? (Laravel Echo è già configurato?)
4. **Modello pricing**: la funzione "Export per commercialista" è inclusa o add-on premium?
5. **Numero commercialisti per tenant**: 1 solo o multipli? (oggi `ConsultantAssignment` permette N:M)

---

## 8. Stima complessiva

| Fase | Effort | Calendar (1 dev) |
|------|--------|------------------|
| F1 — Cleanup menu | 30 min | 1 commit |
| F2 — Cruscotto dati | 3 gg | 1 settimana |
| F3 — Export strutturati | 4 gg | 1-2 settimane |
| F4 — Scambio + Checklist + Calendario | 5 gg | 2 settimane |
| **Totale** | **~12-15 gg** | **~5-6 settimane** |

---

## 9. Modelli Claude consigliati per fase

Guida per scegliere il modello giusto in base a complessità/costo/velocità.

### 9.1 Mappa rapida

| Modello | Quando usarlo |
|---------|---------------|
| **Opus 4.7** | Decisioni architetturali, scelte di design irreversibili, reasoning su dominio fiscale/normativo, refactor multi-file critici, code review di security |
| **Sonnet 4.6** | 90% del coding quotidiano: implementazione feature ben specificate, CRUD, test, refactor mirati, debugging |
| **Haiku 4.5** | Edit ripetitivi e meccanici, rinomine, aggiornamento docs, formattazione, batch su molti file con specifica chiara |

### 9.2 Mappa per fase

#### Fase 1 — Cleanup menu
| Task | Modello | Motivo |
|------|---------|--------|
| Disattivare feature flags + spostare voci menu | **Haiku** | Lavoro meccanico ben specificato (questo documento è la spec) |
| Creazione sezione "Bilancio ETS" | **Sonnet** | Richiede coerenza con pattern AppLayout esistente |

→ **Modello consigliato per l'intera fase: Sonnet** (basso costo, evita di switchare)

---

#### Fase 2 — Cruscotto dati commercialista
| Task | Modello | Motivo |
|------|---------|--------|
| Design schema KPI + Service aggregator | **Opus** | Decisione architetturale (caching, query plan, scalabilità) |
| Implementazione `ConsultantStatsService` | **Sonnet** | Coding standard una volta definito lo schema |
| Vue3 dashboard + chart.js | **Sonnet** | UI con pattern già presenti in `Anagrafica/CompanyUsageStats` |
| Test Pest del service | **Sonnet** | Test ben strutturati |

→ **Mix consigliato**: Opus per il kickoff (1-2 ore), poi Sonnet per il grosso

---

#### Fase 3 — Export strutturati ⚠️ FASE CRITICA
| Task | Modello | Motivo |
|------|---------|--------|
| Design `ExportFormatStrategy` (interfaccia + factory) | **Opus** | Astrazione che durerà anni — sbagliarla costa caro |
| Mapping formato **TeamSystem CSV** | **Opus** | Conoscenza specifica del formato, edge cases fiscali |
| Mapping formato **Zucchetti / Profis** | **Opus** | Idem — domain reasoning |
| Mapping **XML Agenzia Entrate** (LIPE, registri IVA) | **Opus** | Schema XSD ufficiali, validazione obbligatoria |
| Implementazione `GenerateExportBundleJob` (queue, zip, storage) | **Sonnet** | Pattern Laravel standard |
| CRUD `ConsultantExportBundle` + migrazione | **Sonnet** | Standard |
| Pagina Vue3 di richiesta export | **Sonnet** | UI form + polling status |
| Audit log + signed URL temporanei | **Sonnet** | Pattern Laravel standard |
| Test di formato (fixture XML/CSV) | **Sonnet** | Comparazione output |

→ **Mix consigliato**: Opus per ogni nuovo formato + interfaccia. Sonnet per scaffolding/jobs/UI. Verifica finale dei formati con Opus.

> ⚠️ Per ogni formato esterno (TeamSystem, Zucchetti, Profis) procurarsi **un file di esempio reale validato** prima di iniziare. Senza, anche Opus inventerà i campi.

---

#### Fase 4 — Scambio bidirezionale, checklist, calendario

| Task | Modello | Motivo |
|------|---------|--------|
| Estensione `ConsultantRequest` con allegati | **Sonnet** | Estensione modello esistente |
| Nuovo `ConsultantDelivery` (CRUD + UI) | **Sonnet** | Pattern analogo a Request |
| Notifiche email/in-app | **Sonnet** | Laravel notifications standard |
| Design **template adempimenti** (IVA trimestrale, CU, 770, EAS, Bilancio…) | **Opus** | Richiede conoscenza precisa scadenze normative ETS/coop e tipologie regime |
| Implementazione `AdempimentoChecklistItem` + seeder template | **Sonnet** | Una volta che Opus ha definito gli item |
| Calendario consegne (Vue3 component) | **Sonnet** | Standard UI |
| Logica scadenze derivate da tipologia ente | **Opus** | Domain knowledge — sbagliare scadenze ha impatto reale |
| Notifiche T-7/T-1/scaduto | **Sonnet** | Standard |

→ **Mix consigliato**: Opus per i due punti di dominio (template + derivazione scadenze). Sonnet per tutto il resto.

---

### 9.3 Task trasversali

| Task | Modello |
|------|---------|
| Code review pre-merge security-critical (export, auth, file upload) | **Opus** (subagent `code-reviewer`) |
| Code review feature standard | **Sonnet** |
| Esplorazione codebase / ricerca pattern esistenti | **Haiku** o **Sonnet** (subagent `Explore`) |
| Aggiornamento README/CHANGELOG/docs | **Haiku** |
| Rinomine globali, batch refactor meccanici | **Haiku** |
| Debugging bug non banali | **Sonnet** (escalation a **Opus** se rimane bloccato >30 min) |
| Scrittura test Pest | **Sonnet** |
| Migrazioni Laravel | **Sonnet** |

### 9.4 Regola pratica

```
Costo stimato di sbagliare il task >> costo del modello migliore?
  → Usa Opus

Task ben definito, output verificabile, basso rischio architetturale?
  → Sonnet va bene (default)

Task meccanico, ripetitivo, output ovvio?
  → Haiku (risparmia 3-5x)
```

### 9.5 Stima costi roadmap (ordine di grandezza)

Assumendo Sonnet 4.6 come baseline (~$3/M input · $15/M output):

| Fase | Modello prevalente | Costo stimato |
|------|--------------------|---------------|
| F1 | Sonnet | trascurabile (<$1) |
| F2 | 20% Opus + 80% Sonnet | $10-20 |
| F3 | 40% Opus + 60% Sonnet | $40-80 (dipende da n° formati) |
| F4 | 30% Opus + 70% Sonnet | $30-60 |
| **Totale roadmap** | — | **~$80-160** |

Con prompt caching attivo (Sonnet) i costi di iterazione sui file grandi si dimezzano.

---

## 10. Cosa partire SUBITO

Dopo conferma di questo documento:
1. ✅ Fase 1 (cleanup menu) — eseguibile in 30 min con **Sonnet**
2. Definire risposte alle 5 decisioni aperte (sezione 7)
3. Procurarsi file di esempio dei formati export target (sez. 9.2 F3)
4. Iniziare Fase 2 con kickoff su **Opus** (1-2h architettura) → poi switch a **Sonnet**

---

*Documento generato con Claude · 2026-05-25*
