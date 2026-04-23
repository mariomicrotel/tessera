# 📊 Tessera - SaaS per Enti del Terzo Settore e Cooperative

**Tessera** è una piattaforma SaaS **multi-tenant** per la gestione completa di **Enti del Terzo Settore (ETS)** italiani e **Cooperative**: anagrafica soci e volontari, cassa, contabilità, organi e votazioni, patrimonio, documenti ed eventi.

Progettata per semplificare l'amministrazione di organizzazioni non profit, con strumenti specifici per cooperative (capitale sociale, prestiti sociali, ristorni).

**Repository:** [github.com/mariomicrotel/tessera](https://github.com/mariomicrotel/tessera)

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)
[![PHP 8.4+](https://img.shields.io/badge/PHP-8.4+-777BB4?logo=php)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel)](https://laravel.com)
[![Vue 3](https://img.shields.io/badge/Vue-3-4FC08D?logo=vue.js)](https://vuejs.org)
[![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?logo=docker)](https://www.docker.com)
[![Status](https://img.shields.io/badge/Status-Production-green?logo=checkmark)]()

---

## Funzionalità

### Per Enti del Terzo Settore (ETS)

- **Soci e volontari** — Anagrafica, tipologie socio, stati (domanda, ammissione, cessazione, morosità, dimissioni, ecc.), libro soci, approvazione domande singola e bulk con opzione invio email di notifica
- **Cassa** — Incassi (quote e donazioni), ricevute, rimborsi spese con contabilizzazione automatica, fatture e invio SDI
- **Contabilità** — Prima nota, voci del rendiconto (Modello D), report contabili, rendiconto di cassa (PDF)
- **Organi e votazioni** — Organi e cariche definiti in config (hardcoded), incarichi, elezioni, candidature e voti
- **Patrimonio** — Immobili e beni, magazzini, sedi, articoli
- **Documenti** — Verbali, documenti, allegati, template
- **Eventi** — Gestione eventi e iscrizioni
- **Impostazioni** — Nome associazione, logo, quote, P.IVA, causali, email di test, template email (documenti → Template email)

### Per Cooperative

- **Capitale Sociale** — Gestione quote di capitale, versamenti, riscatti, situazione capitale con report dettagliati
- **Prestito Sociale** — Libretti prestito, depositi, prelievi, calcolo automatico interessi, storico movimenti
- **Ristorni** — Distribuzioni utili ai soci, calcolo ritenute d'acconto, rendicontazione per socio
- **Dashboard Cooperativa** — KPI specifici: capitale versato, quote da versare, prestito sociale totale, ristorni anno precedente, soci lavoratori attivi
- **Soci Lavoratori** — Gestione dedicata per cooperative di lavoro e sociali con identificazione soci lavoratori
- **Governance** — Stessi strumenti ETS (organi, cariche, elezioni) adattati per cooperative

### Funzionalità Comuni

Ruoli predefiniti: **admin**, **contabile**, **segreteria**, **socio** (con area self-service per i soci).

---

## Organi e cariche

Gli **organi** (es. Consiglio direttivo) e le **cariche** (Presidente, Vicepresidente, Tesoriere, Segretario, Consigliere) sono definiti in modo fisso in **`config/organi.php`**: l’applicazione non permette di creare, modificare o eliminare organi o cariche dall’interfaccia; è possibile solo **assegnare i soci alle cariche** (incarichi).

- **Struttura**: in config ogni organo ha uno **slug** (es. `consiglio_direttivo`), un nome e l’elenco delle cariche (nome + ordine). Il seeder `OrganiHardcodedSeeder` sincronizza il database con la config (crea organi e cariche se non esistono).
- **URL e codice**: le pagine degli organi usano lo slug nell’URL (es. `/organi/consiglio-direttivo`); in codice l’organo si risolve con lo slug (es. `Organo::where('slug', 'consiglio_direttivo')->first()`).
- **Aggiungere un nuovo organo**: 1) in `config/organi.php` aggiungere un elemento all’array con **slug** (univoco), **nome** e **cariche** (nome + ordine); 2) eseguire `php artisan db:seed --class=OrganiHardcodedSeeder`. Il nuovo organo comparirà nell’elenco Organi, nelle Elezioni (select/filtro) e nel select “Assegna carica” nella scheda socio.

---

## Route Cooperative

Le seguenti rotte sono disponibili **solo** per tenant di tipo "cooperative":

### Capitale Sociale
- `GET /app/{tenant}/capitale-sociale` — Lista quote
- `POST /app/{tenant}/capitale-sociale` — Nuova quota
- `GET /app/{tenant}/capitale-sociale/create` — Form nuova quota
- `GET /app/{tenant}/capitale-sociale/{share}` — Dettagli quota
- `POST /app/{tenant}/capitale-sociale/{share}/versa` — Versa quota
- `POST /app/{tenant}/capitale-sociale/{share}/riscatta` — Riscatta quota
- `GET /app/{tenant}/capitale-sociale/export` — Esporta (CSV/PDF)

### Prestito Sociale
- `GET /app/{tenant}/prestito-sociale` — Lista libretti
- `POST /app/{tenant}/prestito-sociale` — Nuovo libretto
- `GET /app/{tenant}/prestito-sociale/create` — Form nuovo libretto
- `GET /app/{tenant}/prestito-sociale/{libretto}` — Dettagli libretto
- `POST /app/{tenant}/prestito-sociale/{libretto}/deposita` — Deposito
- `POST /app/{tenant}/prestito-sociale/{libretto}/preleva` — Prelievo
- `POST /app/{tenant}/prestito-sociale/{libretto}/chiudi` — Chiudi libretto
- `GET /app/{tenant}/prestito-sociale/{libretto}/export` — Esporta
- `POST /app/{tenant}/prestito-sociale/calcola-interessi` — Calcola interessi

### Ristorni
- `GET /app/{tenant}/ristorni/{ristorno}` — Dettagli ristorno

---

## Dashboard Multi-Tenant

La dashboard si adatta automaticamente al tipo di organizzazione:

**Per ETS:** mostra saldi conti, incassi mese, soci attivi, rimborsi in sospeso

**Per Cooperative:** mostra KPI aggiuntivi:
- 💰 Capitale versato totale
- 💳 Quote da versare
- 🏦 Prestito sociale totale
- 💵 Ristorni anno precedente
- 👥 Soci lavoratori attivi

La navigazione si adatta mostrando/nascondendo link specifici per tipo organizzazione (es. "Quote sociali" per ETS, "Capitale Sociale" per cooperative).

---

## Requisiti

- PHP 8.4+
- Composer
- Node.js (LTS) e npm
- Database MySQL/MariaDB o SQLite
- Estensioni PHP: mbstring, xml, ctype, json, bcmath, pdo, dom, fileinfo

---

## Installazione

### Da release (hosting)

1. Scarica l’ultima [release](https://github.com/pfumarola/ETS-OK/releases) (file `.zip`).
2. Estrai l’archivio nella root del sito; il **document root** del server deve puntare alla cartella `public`.
3. Apri nel browser l’URL di installazione: **`/install`**.
4. Completa il wizard: configurazione database (MySQL o SQLite) e creazione dell’utente amministratore.
5. Accedi con le credenziali inserite.

### Da sorgente (sviluppo)

```bash
git clone https://github.com/pfumarola/ETS-OK.git
cd ETS-OK
composer install
cp .env.example .env
php artisan key:generate
# Configura .env (DB, APP_URL, ecc.)
php artisan migrate
npm install
npm run build
# Oppure, per avviare tutto in un colpo: composer run dev
```

Per il primo utente admin puoi usare l’installer (`/install` se `APP_KEY` è vuoto) oppure:

```bash
# Seeder base con dati ETS demo
php artisan db:seed
# Crea utente test@example.com / password (vedi DatabaseSeeder)

# Seeder cooperativa con dati completi demo
php artisan db:seed --class=CooperativaSeeder
# Crea tenant cooperativa-demo con 5 soci, 50 quote, 3 libretti prestito, ristorni
# Accedi con: coop-admin@example.com / password
```

---

## Stack tecnologico

| Livello      | Tecnologie |
|-------------|------------|
| Backend     | PHP 8.4+, Laravel 12, Jetstream, Fortify, Sanctum, DomPDF |
| Frontend    | Vue 3 (Composition API), Inertia.js 2, Vite 7, Tailwind CSS 3, Heroicons, Ziggy |
| Database    | MySQL 8.0/MariaDB o SQLite (configurabile) |
| Test        | Pest 4, Laravel Pail |
| Container   | Docker Compose con PHP + MySQL |

---

## Struttura Database - Tabelle Cooperative

### Tabelle Principali

**Capitale Sociale**
- `cooperative_shares` — Quote di capitale (sottoscritto, versato, riscattato)
- `share_payment_requests` — Richieste di versamento quote

**Prestito Sociale**
- `prestito_sociale_libretti` — Libretti prestito (numero, saldo, tasso interesse)
- `prestito_sociale_movimenti` — Movimenti su libretti (depositi, prelievi, interessi)

**Ristorni**
- `ristorni` — Deliberazioni ristorno per anno
- `ristorno_entries` — Voci singole ristorno per socio (lordo, ritenuta, netto)

### Relazioni Multi-Tenant

Tutte le tabelle cooperative includono `tenant_id` per isolamento dati tra tenant (BelongsToTenant trait).

---

## Docker Compose

Per sviluppo con Docker:

```bash
# Avvia i container (app + MySQL)
docker compose up -d

# Esegui i comandi normali nel container
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
docker compose exec app npm run build

# Visualizza i log
docker compose logs -f app
docker compose logs -f db

# Ferma i container
docker compose down
```

L'applicazione è disponibile a: **http://localhost:8090**

---

## Sviluppo

- **Setup completo**: `composer run setup` (composer, .env, key, migrate, npm, build)
- **Ambiente dev** (server, queue, log, Vite): `composer run dev`
- **Test**: `composer run test` oppure `php artisan test`
- **Lingua**: italiano (backend, frontend, commenti)

### Seeder Demo

```bash
# Seeder standard ETS
php artisan db:seed

# Seeder cooperativa con dati completi
php artisan db:seed --class=CooperativaSeeder

# Ricrea il database e popola con seeders
php artisan migrate:fresh --seed
```

### File Principali Modificati/Creati

```
database/seeders/
├── CooperativaSeeder.php              [NUOVO] Seeder cooperativa completo
└── DatabaseSeeder.php                 [MODIFICATO] Chiama CooperativaSeeder (solo local/testing)

app/Http/Controllers/
├── DashboardController.php            [MODIFICATO] KPI cooperativi
└── Controller.php                     [MODIFICATO] Fix parameter mapping

resources/js/Pages/
└── Dashboard.vue                      [MODIFICATO] KPI card cooperativi

resources/js/Layouts/
└── AppLayout.vue                      [MODIFICATO] Navigazione condizionale
```

---

## CooperativaSeeder - Dati Demo

Il `CooperativaSeeder` crea un tenant completo di cooperativa di lavoro con:

### 1️⃣ Tenant Cooperativa
- Slug: `cooperativa-demo`
- Type: `cooperative` / `lavoro`
- Codice Fiscale demo: `12345678901234`

### 2️⃣ Utente Admin
- Email: `coop-admin@example.com`
- Password: `password`
- Role: admin

### 3️⃣ Cassa Cooperativa
- Conto di tesoreria attivo

### 4️⃣ Soci Lavoratori (5)
```
✓ Mario Rossi
✓ Anna Bianchi
✓ Carlo Verdi
✓ Lucia Neri
✓ Paolo Gialli
```
Stato: attivo | Socio lavoratore: sì

### 5️⃣ Capitale Sociale
- 10 quote @ €50 per socio
- Totale: €2,500 sottoscritto
- Valore unitario: €50.00
- Quote minime: 10

### 6️⃣ Prestito Sociale
- 3 Libretti (PS-001, PS-002, PS-003)
- Saldo iniziale: €1,000 per libretto
- Tasso interesse: 2% annuo
- Totale: €3,000

### 7️⃣ Ristorni
- Anno: 2025
- Importo totale: €500 lordi
- Aliquota ritenuta: 26%
- Distribuito ai 5 soci (€100 cadauno netto)

### 8️⃣ Impostazioni
```
quota_valore_unitario_coop = 50.00
quota_minima_quote_coop = 10
riserva_legale_percentuale = 30%
```

### Esecuzione Idempotente
Il seeder usa `firstOrCreate()` ovunque: è sicuro eseguirlo più volte senza duplicare dati.

Documentazione per sviluppatori e agenti AI: vedi [agent.md](agent.md).

---

## Release e deploy

Le release pronte per l’hosting vengono generate automaticamente al **merge sul branch `release`** tramite [GitHub Actions](.github/workflows/release.yml): build di produzione (Composer senza dev, build frontend), creazione dello zip e pubblicazione come [GitHub Release](https://docs.github.com/en/repositories/releasing-projects-on-github) con allegato.

La versione mostrata in app e nel tag di release è definita da **`APP_VERSION`** in `.env.example` (es. `1.0.0`).

---

## Contribuire

Contributi sono benvenuti: issue, pull request, miglioramenti alla documentazione. Per modifiche rilevanti è utile aprire prima una discussione in issue.

- Codice e commenti in **italiano**.
- Rispettare le convenzioni del progetto (vedi [agent.md](agent.md)).

---

## Sicurezza

Per segnalare vulnerabilità di sicurezza, apri una **security advisory** privata nel repository GitHub invece di una issue pubblica.

---

## Licenza

Questo progetto è open source sotto licenza [GNU GPL v3](https://www.gnu.org/licenses/gpl-3.0). Vedi il file [LICENSE](LICENSE) per il testo completo.

---

## Changelog

### v1.0.0 (2026-04-17) ✅ **RELEASE - Supporto Cooperative**
- ✅ **Nuovo:** Supporto completo Cooperative (capitale sociale, prestiti sociali, ristorni)
- ✅ **Nuovo:** Dashboard multi-tenant con KPI specifici per cooperativa
- ✅ **Nuovo:** CooperativaSeeder con dati demo completi e idempotenti
- ✅ **Nuovo:** Navigazione condizionale per tipo organizzazione (ETS vs Cooperative)
- ✅ **Fix:** Parameter mapping in Controller base class (callAction override)
- ✅ **Miglioramento:** Frontend build e assets ottimizzati
- ✅ **Docs:** README aggiornato con guide cooperative

### v0.9.0 (2024)
- Prima versione stabile per Enti del Terzo Settore (ETS)
- Gestione soci, contabilità, governance, patrimonio

---

## Contatti e Supporto

- **GitHub:** [pfumarola/ETS-OK](https://github.com/pfumarola/ETS-OK)
- **Segnala Bug:** [Issues](https://github.com/pfumarola/ETS-OK/issues)
- **Discussioni:** [GitHub Discussions](https://github.com/pfumarola/ETS-OK/discussions)
- **Email:** [support@etsok.local](mailto:support@etsok.local)

---

**Versione:** 1.0.0  
**Ultima aggiornamento:** 17 Aprile 2026  
**Ambiente di test:** Docker Compose con PHP 8.4 + MySQL 8.0 + Node.js v18+
