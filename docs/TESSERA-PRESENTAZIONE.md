# Tessera — Presentazione del software

> **Versione documento:** 1.0 · **Data:** 2026-05-25 · **Autore:** Mario De Vita

---

## Indice

1. [Cos'è Tessera](#1-cosè-tessera)
2. [A chi si rivolge](#2-a-chi-si-rivolge)
3. [Finalità del software](#3-finalità-del-software)
4. [Caratteristiche funzionali](#4-caratteristiche-funzionali)
5. [Architettura e tecnologia](#5-architettura-e-tecnologia)
6. [Sicurezza e conformità](#6-sicurezza-e-conformità)
7. [Il modello SaaS multi-tenant](#7-il-modello-saas-multi-tenant)
8. [L'area consulente — collaborazione con il commercialista](#8-larea-consulente--collaborazione-con-il-commercialista)
9. [Cosa Tessera NON è](#9-cosa-tessera-non-è)
10. [Sviluppi futuri](#10-sviluppi-futuri)
11. [Informazioni tecniche per l'installazione](#11-informazioni-tecniche-per-linstallazione)

---

## 1. Cos'è Tessera

**Tessera** è una piattaforma gestionale SaaS (Software as a Service) **multi-tenant**, sviluppata specificamente per le realtà del **Terzo Settore italiano** e per le **Cooperative**.

Il software nasce dall'esigenza concreta di dotare associazioni, ODV, APS, fondazioni e cooperative di uno strumento **operativo e completo** che copra tutte le attività quotidiane di gestione: dai soci alla cassa, dai documenti agli organi sociali, fino agli adempimenti di legge.

Tessera è progettato per **coesistere** con i software contabili dei professionisti (commercialisti, consulenti del lavoro) — non per sostituirli. Il suo ruolo è quello di **data layer operativo**: l'ente raccoglie, organizza e gestisce i dati; il professionista li preleva nei formati che gli servono.

---

## 2. A chi si rivolge

### Enti del Terzo Settore (ETS)
- Associazioni di Promozione Sociale (APS)
- Organizzazioni di Volontariato (ODV)
- Fondazioni
- Enti di culto e religiosi
- Qualsiasi ente iscritto o iscrivibile al RUNTS

### Cooperative
- Cooperative di lavoro, sociali (A e B), agricole, di comunità, di consumo, di abitazione, consortili
- Tutte le tipologie previste dalla normativa italiana

### Professionisti
- Commercialisti e consulenti del lavoro che seguono più enti/cooperative
- Studio professionale con accesso coordinato ai dati di ciascun cliente

---

## 3. Finalità del software

### 3.1 Gestione associativa completa

Tessera digitalizza e centralizza l'intera vita amministrativa dell'ente:

- **Registro soci** — anagrafica, tipologie, stati (domanda, ammissione, cessazione, morosità), libro soci ufficiale, tessere con numerazione progressiva
- **Organi sociali** — Consiglio Direttivo, Assemblea, Organo di Controllo, incarichi, durate, mandati
- **Democrazia interna** — gestione elezioni, candidature, voti e scrutinio
- **Documenti e verbali** — archivio documentale strutturato, template, verbali assemblee
- **Atti fondativi** — statuto e atto costitutivo digitalizzati e accessibili

### 3.2 Gestione economica operativa

Tutto il flusso di entrate e uscite che l'ente genera quotidianamente:

- **Cassa** — incassi quote, donazioni, incassi generici, spese, rimborsi
- **Ricevute e quietanze** — emissione automatizzata con template personalizzati
- **Fatturazione** — fatture attive e passive, Fattura Elettronica (XML SDI), note di credito, fatture semplificate
- **IVA** — registri acquisti/vendite, liquidazioni mensili/trimestrali, LIPE XML, acconto IVA
- **Banca** — RI.BA/CBI, riconciliazione bancaria, movimenti estratto conto

### 3.3 Adempimenti obbligatori ETS/Coop

Gli output che la legge impone agli ETS e alle cooperative, prodotti direttamente dalla piattaforma:

- **Bilancio CEE** — Stato Patrimoniale e Conto Economico in formato IV Direttiva, export XBRL per CCIAA
- **Relazione di Missione** — obbligatoria per ETS ai sensi dell'art. 13 D.Lgs. 117/2017, con template PDF
- **Erogazioni Liberali** — tracciamento 5x1000 e detrazioni, dati per la dichiarazione dei donatori
- **Cooperative**: Capitale Sociale, Prestito Sociale, Ristorni, Situazione Capitale, Conto Economico specifico
- **Adempimenti fiscali** — compensi a terzi, ritenute d'acconto, Certificazione Unica (PDF), Modello F24
- **Cespiti** — registro beni ammortizzabili, piani di ammortamento, dismissioni (per IMU e dichiarazioni)

### 3.4 Patrimonio e logistica

- Immobili, sedi operative, magazzino e articoli
- Gestione eventi

### 3.5 Supporto al commercialista

Un'area dedicata consente al professionista esterno di accedere in modo sicuro e controllato ai dati di ciascun ente che segue, senza interferire con la gestione ordinaria. Questa funzione è descritta in dettaglio alla sezione 8.

---

## 4. Caratteristiche funzionali

### 4.1 Moduli disponibili

| Area | Funzionalità principali |
|------|------------------------|
| **Soci** | Anagrafica, libro soci, tipologie, tessere (numerazione ANNO-NNN), scadenzario quote |
| **Cassa** | Quote, donazioni, incassi generici, ricevute, spese, rimborsi, fatture |
| **Documenti** | Archivio documentale, verbali, template email e ricevute |
| **Organi e votazioni** | Organi, cariche, incarichi, elezioni con voto e scrutinio |
| **Atti fondativi** | Statuto, Atto Costitutivo |
| **Patrimonio** | Immobili, sedi, magazzino, articoli, cespiti con ammortamento |
| **IVA e fatturazione** | Fatture attive/passive, Fattura Elettronica XML, registri, LIPE, liquidazioni |
| **Adempimenti fiscali** | Ritenute, CU, F24, compensi a terzi |
| **Banca** | RI.BA/CBI, riconciliazione bancaria |
| **Bilancio ETS** | Bilancio CEE (XBRL), Relazione di Missione, Erogazioni Liberali |
| **Cooperativa** | Capitale Sociale, Prestito Sociale, Ristorni, report specifici |
| **Area Consulente** | Accesso cross-tenant, richieste documenti, note, dashboard consulente |
| **Anagrafica ente** | Dati ente, ricerca aziende (API), RUNTS |
| **Amministrazione** | Utenti, ruoli, impostazioni tenant, audit trail |

### 4.2 Dashboard e analytics

La homepage di ogni tenant offre una dashboard con:
- Grafici andamento incassi/uscite (12 mesi)
- Composizione soci per tipologia
- Andamento iscrizioni e cessazioni
- Distribuzione delle donazioni (solo ETS)
- KPI specifici per cooperative (capitale versato, prestiti, ristorni)

### 4.3 Export e interoperabilità

- **PDF** — ricevute, CU, F24, Relazione di Missione, Bilancio CEE (DomPDF)
- **Excel (XLSX)** — soci, prima nota, registri IVA, cespiti, capitale sociale, compensi terzi
- **XML** — Fattura Elettronica (SDI FatturaPA 1.3.2), LIPE, XBRL per CCIAA
- **CSV** — export generico di qualsiasi modulo

### 4.4 Ruoli e permessi

Il sistema prevede ruoli differenziati per ciascun tenant:

| Ruolo | Accesso |
|-------|---------|
| `admin` | Accesso completo all'ente |
| `segreteria` | Soci, documenti, organi, cassa |
| `contabile` | Cassa, fatturazione, IVA, bilancio |
| `socio` | La propria scheda, i propri rimborsi |
| `consultant` | Area consulente (cross-tenant, accesso controllato) |

### 4.5 Feature flag per modulo

Ogni funzionalità è governata da un flag attivabile/disattivabile per ambiente tramite variabili `.env`. Questo permette di:
- Abilitare solo i moduli necessari per ogni tipo di ente
- Disattivare funzionalità avanzate nei piani base
- Rilasciare nuove funzionalità in modo controllato

---

## 5. Architettura e tecnologia

### Stack tecnologico

| Livello | Tecnologie |
|---------|-----------|
| **Backend** | PHP 8.4+, Laravel 12, Jetstream, Fortify, Sanctum |
| **Frontend** | Vue 3 (Composition API), Inertia.js 2, Vite 7, Tailwind CSS 3 |
| **Database** | MySQL 8.0 / MariaDB / SQLite |
| **Export** | DomPDF (PDF), maatwebsite/excel (XLSX), SimpleXML (XML/XBRL) |
| **Code/Build** | Docker Compose, GitHub Actions |
| **Test** | Pest 4, PHPUnit 12 — **562 test, 0 falliti** |

### Architettura applicativa

Tessera è una **Single Page Application (SPA)** Laravel + Vue3 connessa tramite Inertia.js. Non esiste un'API separata: il backend e il frontend condividono lo stesso ciclo request/response Laravel, semplificando autenticazione, autorizzazione e gestione dati.

Il multi-tenant è implementato a livello di **row-level security** tramite il trait `BelongsToTenant`: ogni query è automaticamente filtrata per `tenant_id`, rendendo impossibile la contaminazione tra dati di enti diversi.

---

## 6. Sicurezza e conformità

- **Autenticazione** — Laravel Jetstream + Fortify, con supporto opzionale 2FA (TOTP)
- **Autorizzazione** — Model Policies granulari per ogni modulo; `Gate::before` per bypass admin
- **Isolamento dati** — Row-level security multi-tenant, nessun dato di un ente accessibile da un altro
- **Audit trail** — log immutabile di ogni operazione (chi, cosa, quando, da quale IP)
- **CSRF/CORS** — protezione nativa Laravel
- **Crittografia** — chiavi applicazione, password hashing Bcrypt, dati sensibili crittografati a riposo
- **GDPR** — gestione consensi, export dati socio, diritto all'oblio implementabile

---

## 7. Il modello SaaS multi-tenant

Tessera è progettato per essere **ospitato centralmente** e servire più organizzazioni simultaneamente (multi-tenant), oppure installato in modalità **single-tenant** on-premise per una singola realtà.

### Isolamento tra tenant

Ogni organizzazione (tenant) è completamente separata dalle altre:
- Database condiviso con filtro automatico per `tenant_id`
- Storage file separato per percorso
- Configurazioni e feature flag indipendenti
- Utenti possono appartenere a più tenant con ruoli diversi in ciascuno

### Gestione accessi

Un utente può essere:
- **Admin di un ETS** (ruolo pieno nel proprio ente)
- **Membro/socio** (accesso alla propria scheda)
- **Consulente esterno** (accesso in sola lettura o controllato a più enti)
- **Operatore SaaS** (accesso amministrativo alla piattaforma, via pannello admin separato)

---

## 8. L'area consulente — collaborazione con il commercialista

### La filosofia

Tessera **non sostituisce** il software contabile del commercialista (TeamSystem, Zucchetti, Profis, OmniaWeb, ecc.). Lo **alimenta con i dati corretti**.

L'ente gestisce la propria operatività quotidiana sulla piattaforma. Il commercialista — già modellato come "consulente esterno" con accesso cross-tenant — preleva i dati nei formati che gli servono per chiudere i suoi adempimenti.

### Cosa è già implementato

L'infrastruttura cross-tenant per il consulente è **già operativa**:

- **`ConsultantAssignment`** — assegnazione consulente ↔ ente (N:M, senza isolamento tenant)
- **`ConsultantRequest`** — richieste documenti/informazioni dal commercialista all'ente
- **`ConsultantNote`** — note del commercialista su un ente, visibili solo a lui
- **Dashboard consulente** (`/consultant/dashboard`) — lista degli enti seguiti, stato richieste
- **Accesso cross-tenant** — il consulente entra nell'ente e vede i dati con i ruoli appropriati
- **Banner visivo** — quando il consulente è nell'area di un ente, vede un banner "Modalità consulente"

### Il flusso operativo attuale

```
Commercialista
  └─ Accede alla propria dashboard consulente
  └─ Seleziona un ente tra quelli che segue
  └─ Entra nell'area dell'ente (con accesso controllato)
  └─ Consulta i dati operativi (fatture, cassa, soci, adempimenti)
  └─ Crea richieste di documenti verso l'ente
  └─ Lascia note per uso interno
  └─ Torna alla propria dashboard
```

---

## 9. Cosa Tessera NON è

Per chiarire il posizionamento del prodotto:

| Non è | Perché |
|-------|--------|
| Un software di **contabilità in partita doppia** | La contabilità ufficiale resta nel gestionale del commercialista. Il codice esiste ma è disabilitato di default. |
| Un gestionale **paghe e stipendi** | Payroll e cedolini sono fuori scope |
| Un sostituto del **software dell'Agenzia delle Entrate** | I dichiarativi (Modello EAS, 770, Unico ETS) si compilano con i software specifici, alimentati dai dati di Tessera |
| Un portale di **e-commerce o vendite** | Non gestisce cataloghi prodotti, carrelli o pagamenti online |
| Un **CRM** generico | Le anagrafiche sono verticali per il Terzo Settore, non per uso commerciale |

Le funzionalità di contabilità (prima nota, piano dei conti, esercizi, ratei) sono **presenti nel codice e attivabili** tramite feature flag, ma non sono esposte di default nell'interfaccia. Questo permette a realtà che vogliono gestire la propria contabilità internamente di attivarle, senza impattare la semplicità d'uso per la maggior parte degli utenti.

---

## 10. Sviluppi futuri

### Fase 2 — Cruscotto dati per il commercialista `(~3 giorni sviluppo)`

Obiettivo: dare al consulente una vista d'insieme dell'attività operativa di ciascun ente per periodo selezionabile (mese/trimestre/anno).

**Contenuto previsto:**
- KPI aggregati: fatture emesse/ricevute, totale IVA, donazioni, spese, movimenti bancari riconciliati
- Grafici trend mensili con drill-down per area
- Stato Bilancio (bozza/approvato) e stato Capitale Sociale (per coop)
- Quick link a ogni area per navigazione diretta

**Tecnologia:** Service `ConsultantStatsService` con caching Redis, chart.js già integrato nello stack.

---

### Fase 3 — Export strutturati per software contabili `(~4 giorni sviluppo)`

Obiettivo: consentire al commercialista di generare, con un click, un bundle dati completo per il proprio software contabile.

**Formati previsti:**
- CSV generico (tutte le tabelle, una per file, in archivio ZIP)
- XML Agenzia delle Entrate (registri IVA, LIPE) — già parzialmente presente
- TeamSystem CSV (formato specifico per import)
- Zucchetti CSV
- Profis XML

**Dati esportabili:** fatture attive/passive, ricevute, donazioni, spese, compensi a terzi, F24, movimenti bancari, anagrafiche fornitori/clienti, cespiti.

**Architettura:** `ExportBundleService` con pattern Strategy per formato, `GenerateExportBundleJob` in coda con notifica al consulente al completamento. Storage privato con URL firmati e scadenza a 30 giorni.

---

### Fase 4 — Scambio bidirezionale e checklist adempimenti `(~5 giorni sviluppo)`

Obiettivo: chiudere il loop comunicativo tra ente e commercialista, eliminare email e fogli Excel con le scadenze.

**4a. Scambio file:**
- L'ente risponde alle richieste del consulente allegando documenti direttamente sulla piattaforma
- Il consulente carica per l'ente documenti prodotti (es. F24 da pagare, bilancio firmato, CU elaborate)
- Notifiche email e in-app a entrambi i lati

**4b. Checklist adempimenti per periodo:**
- Template predefiniti per le scadenze ricorrenti: IVA trimestrale, LIPE, CU, 770, Bilancio annuale, Modello EAS, ecc.
- Stato per ciascun item: da fare / in lavorazione / consegnato / completato
- Vista commercialista: compliance overview di tutti gli enti per periodo
- Vista ente: checklist propria con azioni collegate

**4c. Calendario consegne:**
- Vista mensile delle scadenze derivate automaticamente da tipologia ente e regime fiscale
- Notifiche anticipate (T-7, T-1, scaduto) via email e in-app

---

### Versione 1.2.0 — Q3/Q4 2026

- **Portale Soci** — self-service per soci: visualizzazione quota, tessera, rimborsi, bilancio pubblicato
- **API Pubblica** — REST API documentata (OpenAPI) per integrazioni con altri sistemi
- **Business Intelligence avanzata** — KPI comparativi multi-periodo, benchmark per tipologia ente
- **Magazzino avanzato** — distinte base, lotti, listini, sconti
- **RI.BA avanzato** — gestione effetti, scadenzario ricevute bancarie

### Versione 2.0.0 — Futuro

- **App Mobile** — iOS/Android per soci (gestione tessera, eventi, comunicazioni)
- **Open Banking** — collegamento diretto con i conti correnti per riconciliazione automatica
- **Firma digitale** — E-signing di documenti e verbali all'interno della piattaforma
- **Marketplace** — template, report personalizzati, estensioni per verticali specifici

---

## 11. Informazioni tecniche per l'installazione

### Requisiti minimi

| Componente | Versione |
|-----------|---------|
| PHP | 8.4+ |
| MySQL | 8.0+ (o MariaDB 10.6+) |
| Node.js | 18+ |
| Composer | 2.x |
| Web server | Apache / Nginx / Sail / Docker |

### Installazione rapida (Docker)

```bash
git clone https://github.com/mariomicrotel/tessera.git
cd tessera
docker compose up -d
docker compose exec app php artisan migrate --seed
docker compose exec app npm run build
```

Disponibile su: `http://localhost:8090`

### Credenziali demo

| Email | Password | Ruolo | Tipo ente |
|-------|----------|-------|-----------|
| `test@example.com` | `password` | admin | ETS (Associazione) |
| `coop-admin@example.com` | `password` | admin | Cooperativa |

### Repository

[github.com/mariomicrotel/tessera](https://github.com/mariomicrotel/tessera)

**Licenza:** GNU GPL v3

---

## Riepilogo

| Aspetto | Dettaglio |
|---------|-----------|
| **Tipo** | SaaS multi-tenant, installabile anche on-premise |
| **Target primario** | ETS italiani e Cooperative |
| **Target secondario** | Commercialisti e consulenti del lavoro |
| **Versione attuale** | 1.1.0 (Maggio 2026) |
| **Test** | 562 test, 0 falliti |
| **Licenza** | GNU GPL v3 — open source |
| **Stack** | PHP 8.4 / Laravel 12 / Vue 3 / MySQL 8 |
| **Maturità** | Production-ready |

---

*Documento redatto con Claude · 2026-05-25*
