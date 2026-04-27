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
[![Tests](https://img.shields.io/badge/Tests-562%20passed-brightgreen)]()

---

## 📑 Indice

1. [Moduli Implementati](#-moduli-implementati)
2. [Funzionalità Per Tipo Organizzazione](#-funzionalità-per-tipo-organizzazione)
3. [Stack Tecnologico](#-stack-tecnologico)
4. [Installazione](#-installazione)
5. [Sviluppo e Testing](#-sviluppo-e-testing)
6. [Roadmap](#-roadmap)
7. [Struttura Database](#-struttura-database)
8. [Docker Compose](#-docker-compose)
9. [Documentazione](#-documentazione)

---

## ✅ Moduli Implementati

### **FASE 1: Contabilità Core** ✅ COMPLETO

| Modulo | Descrizione | Status | Tests |
|--------|-------------|--------|-------|
| **Piano dei Conti** | 4 livelli gerarchici, natura, tipo SP/CE | ✅ Completo | 12 |
| **Movimenti Contabili** | Prima nota, righe DARE/AVERE, conferma | ✅ Completo | 18 |
| **Liquidazione IVA** | Mensile/trimestrale, calcolo automatico, F24 | ✅ Completo | 14 |
| **Cespiti (Beni)** | Acquisizione, ammortamento piano, dismissione | ✅ Completo | 25 |
| **Scadenzario** | Clienti, fornitori, generiche, ripresa anno | ✅ Completo | 10 |
| **Ratei e Risconti** | Rettifiche infrannuali per competenza | ✅ Completo | 8 |

### **FASE 2: Ciclo Attivo (Fatturazione)** ✅ COMPLETO

| Modulo | Descrizione | Status | Tests |
|--------|-------------|--------|-------|
| **F-ATT: Fatture Attive CRUD** | Create/Edit/Delete, righe fattura, importi | ✅ Completo | 12 |
| **F-XML: Fattura Elettronica** | Genera FatturaPA 1.3.2, integrazione SDI | ✅ Completo | 8 |
| **F3: Nota di Credito Attiva (TD04)** | Storno totale/parziale, movimenti inverse | ✅ Completo | 5 |
| **F7: Fattura Semplificata (TD07)** | Importi < €400, dati ridotti | ✅ Completo | 3 |

### **FASE 3: Ciclo Passivo** ✅ COMPLETO

| Modulo | Descrizione | Status | Tests |
|--------|-------------|--------|-------|
| **Fatture Passive** | CRUD, registrazione automatica movimenti | ✅ Completo | 16 |
| **Fornitori** | Anagrafica, contatti, conti banca | ✅ Completo | 8 |
| **Registri IVA** | Registro acquisti, libro vendite | ✅ Completo | 10 |

### **FASE 4: Governance e Organi** ✅ COMPLETO

| Modulo | Descrizione | Status | Tests |
|--------|-------------|--------|-------|
| **Organi e Cariche** | Hardcoded in config, assegnazione soci | ✅ Completo | 12 |
| **Incarichi** | Gestione incarichi, durata, motivi cessazione | ✅ Completo | 8 |
| **Elezioni** | Candidature, voti, scrutinio, risultati | ✅ Completo | 15 |

### **FASE 5: Cooperativa** ✅ COMPLETO

| Modulo | Descrizione | Status | Tests |
|--------|-------------|--------|-------|
| **Capitale Sociale** | Quote, versamenti, riscatti, report | ✅ Completo | 14 |
| **Prestito Sociale** | Libretti, depositi, prelievi, interessi | ✅ Completo | 12 |
| **Ristorni** | Deliberazioni, distribuzioni, ritenute | ✅ Completo | 10 |

### **FASE 6: Adempimenti Fiscali ETS** ✅ COMPLETO

| Modulo | Descrizione | Status | Tests |
|--------|-------------|--------|-------|
| **Ritenute d'Acconto** | Compensi terzi, CU, versamenti | ✅ Completo | 12 |
| **Modello F24** | Codici tributo, export PDF/XML | ✅ Completo | 8 |
| **Relazione di Missione** | Art. 13 D.Lgs. 117/2017, template PDF | ✅ Completo | 6 |
| **Erogazioni Liberali** | 5x1000, donazioni, dichiarazioni | ✅ Completo | 5 |

### **FASE 7: Bilancio e Report** ✅ COMPLETO

| Modulo | Descrizione | Status | Tests |
|--------|-------------|--------|-------|
| **Bilancio CEE** | Stato Patrimoniale, Conto Economico, XBRL | ✅ Completo | 16 |
| **Rendiconto Gestionale ETS** | Per area/progetto, entrate/uscite | ✅ Completo | 8 |
| **Centri di Costo** | Contabilità per area/progetto | ✅ Completo | 7 |

### **FASE 8: Anagrafi e Membership** ✅ COMPLETO

| Modulo | Descrizione | Status | Tests |
|--------|-------------|--------|-------|
| **Soci e Volontari** | Anagrafica, tipologie, stati, libro soci | ✅ Completo | 22 |
| **Cassa e Incassi** | Incassi quote/donazioni, ricevute, rimborsi | ✅ Completo | 14 |
| **Assistenza e Supporto** | Ticket, richieste, chat | ✅ Completo | 6 |

### **FASE 9: Security e Compliance** ✅ COMPLETO

| Modulo | Descrizione | Status | Tests |
|--------|-------------|--------|-------|
| **Model Policies** | Authorization per tutti i moduli | ✅ Completo | 18 |
| **Audit Trail** | Log modifiche, chi ha fatto cosa | ✅ Completo | 8 |
| **Multi-Tenant Isolation** | Row-level security, BelongsToTenant | ✅ Completo | 12 |

---

## 🎯 Funzionalità Per Tipo Organizzazione

### **Per Enti del Terzo Settore (ETS)**

#### 👥 Anagrafi e Membership
- ✅ Soci e volontari — Anagrafica, tipologie socio, stati (domanda, ammissione, cessazione, morosità, dimissioni)
- ✅ Libro soci — Elenco approvato, filtri, export PDF
- ✅ Approvazione domande — Singola e bulk con notifica email

#### 💰 Cassa e Incassi
- ✅ Incassi — Quote, donazioni, rimborsi spese
- ✅ Ricevute — Quietanze, template, export
- ✅ Contabilizzazione automatica — Movimenti contabili da incassi

#### 📚 Contabilità
- ✅ Prima nota — Movimenti DARE/AVERE, conferma, storno
- ✅ Piano dei conti — 4 livelli, natura, tipo bilancio
- ✅ Liquidazione IVA — Mensile/trimestrale, calcoli automatici
- ✅ Fatture attive/passive — CRUD, XML SDI, note di credito
- ✅ Scadenzario — Incassi/pagamenti attesi, ripresa anno precedente
- ✅ Ratei e risconti — Rettifiche infrannuali

#### 📊 Bilancio e Report
- ✅ Stato Patrimoniale — Struttura IV Direttiva CEE
- ✅ Conto Economico — Sezioni A-E, risultato esercizio
- ✅ Rendiconto Gestionale — Per area di attività
- ✅ Bilancio XBRL — Export per CCIAA

#### ⚖️ Adempimenti Fiscali
- ✅ Relazione di Missione — Art. 13 D.Lgs. 117/2017
- ✅ Erogazioni Liberali — 5x1000, detrazioni
- ✅ Modello F24 — Codici tributo, tributi IVA/IRPEF
- ✅ Ritenute d'Acconto — Compensi terzi, CU

#### 🏛️ Governance
- ✅ Organi e cariche — Consiglio, assemblea, organo controllo (configurabili)
- ✅ Incarichi — Assegnazione soci a cariche, durata
- ✅ Elezioni — Candidature, voti, risultati

#### 📁 Patrimonio e Documenti
- ✅ Cespiti — Acquisizione, ammortamento, dismissione
- ✅ Magazzino — Articoli, sedi, beni
- ✅ Documenti — Verbali, allegati, template email

### **Per Cooperative**

#### **Tutte le funzionalità ETS +**

#### 💳 Capitale Sociale
- ✅ Gestione quote — Sottoscritto, versato, riscattato
- ✅ Versamenti — Richieste, pagamenti, scadenze
- ✅ Report capitale — Situazione per socio, totali

#### 🏦 Prestito Sociale
- ✅ Libretti — Numero, saldo, tasso interesse
- ✅ Depositi/Prelievi — Movimenti, interessi automatici
- ✅ Calcolo interessi — Automatico, configurabile
- ✅ Storico — Completo per audit

#### 💵 Ristorni
- ✅ Deliberazioni — Anno, importo, metodo distribuzione
- ✅ Distribuzioni — Per socio, lordo, ritenuta, netto
- ✅ Certificazione — Per adempimenti fiscali

#### 👥 Soci Lavoratori
- ✅ Identificazione — Flag soci lavoratori
- ✅ Gestione dedicata — Per cooperative di lavoro
- ✅ Report — KPI soci attivi

#### 📊 Dashboard Cooperativa
- 💰 Capitale versato totale
- 💳 Quote da versare
- 🏦 Prestito sociale totale
- 💵 Ristorni anno precedente
- 👥 Soci lavoratori attivi

---

## 🛠️ Stack Tecnologico

| Livello | Tecnologie |
|---------|-----------|
| **Backend** | PHP 8.4+, Laravel 12, Jetstream, Fortify, Sanctum |
| **Frontend** | Vue 3 (Composition API), Inertia.js 2, Vite 7, Tailwind CSS 3 |
| **Database** | MySQL 8.0 / MariaDB / SQLite |
| **Export** | DomPDF (PDF), CSV, XLSX, XML |
| **API** | REST + SDI (Sistema di Interscambio) |
| **Test** | Pest 4, PHPUnit 12, RefreshDatabase |
| **Container** | Docker Compose (PHP + MySQL) |
| **CI/CD** | GitHub Actions, Auto-release |

---

## 📥 Installazione

### Opzione 1: Release Pronta (Hosting)

1. Scarica l'ultima [release](https://github.com/mariomicrotel/tessera/releases)
2. Estrai nella root del sito (document root → `public/`)
3. Apri nel browser: **`/install`**
4. Completa il wizard (database, utente admin)
5. Accedi con le credenziali inserite

### Opzione 2: Sorgenti (Sviluppo)

```bash
git clone https://github.com/mariomicrotel/tessera.git
cd tessera
composer install
npm install

# Configura
cp .env.example .env
php artisan key:generate
# Edita .env: DB, APP_URL, MAIL_MAILER, ecc.

# Setup completo
composer run setup

# Oppure step-by-step
php artisan migrate
php artisan db:seed                    # ETS demo
php artisan db:seed --class=CooperativaSeeder  # Cooperative demo
npm run build
```

### Opzione 3: Docker Compose

```bash
docker compose up -d
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
docker compose exec app npm run build
```

Disponibile a: **http://localhost:8090**

---

## 🧪 Sviluppo e Testing

### Comandi Rapidi

```bash
# Setup completo (Composer, .env, key, migrate, npm, build)
composer run setup

# Dev: server + Vite + queue + log
composer run dev

# Test completo
composer run test
php artisan test

# Test specifico
php artisan test tests/Feature/Iva/FatturaAttivaTest.php

# Build frontend
npm run build

# Dev frontend (hot reload)
npm run dev
```

### Seeder Demo

```bash
# ETS standard
php artisan db:seed

# Cooperativa con dati completi
php artisan db:seed --class=CooperativaSeeder

# Fresh + seed
php artisan migrate:fresh --seed
```

### Account Test

| Email | Password | Role | Tipo |
|-------|----------|------|------|
| `test@example.com` | `password` | admin | ETS |
| `coop-admin@example.com` | `password` | admin | Cooperativa |

### Test Status

```
✅ 562 test passed
⏭️  13 test skipped (Jetstream features disabled)
✅ 0 failed
✅ 0 deprecated
📊 2058 assertions
⏱️  ~170 secondi per run completo
```

---

## 🗺️ Roadmap

### COMPLETATO (v1.0.0)

- ✅ **Fase 1**: Contabilità core (piano conti, movimenti, liquidazione IVA)
- ✅ **Fase 2**: Ciclo attivo (fatture, XML SDI, note credito, TD07)
- ✅ **Fase 3**: Ciclo passivo (fatture passive, fornitori, registri)
- ✅ **Fase 4**: Governance (organi, incarichi, elezioni)
- ✅ **Fase 5**: Cooperative (capitale, prestiti, ristorni)
- ✅ **Fase 6**: Adempimenti ETS (ritenute, F24, relazione missione, 5x1000)
- ✅ **Fase 7**: Bilancio e report (SP, CE, rendiconto, XBRL)
- ✅ **Fase 8**: Anagrafi (soci, volontari, cassa)
- ✅ **Fase 9**: Security (policies, audit, multi-tenant)

### PIANIFICATO (v1.1.0 - Q3/Q4 2026)

- 🟡 **Portale Soci** — Self-service per soci (bilancio, quote, ristorni)
- 🟡 **API Pubblica** — REST API documentata per integrazioni
- 🟡 **Business Intelligence** — Dashboard KPI avanzati, analisi comparativa
- 🟡 **Magazzino Avanzato** — BOM, lotti, listini, sconti
- 🟡 **RI.BA** — Ricevute bancarie, gestione effetti

### FUTURO (v2.0.0+)

- 🔵 **Mobile App** — iOS/Android per soci
- 🔵 **Integrazione Banche** — Open Banking, riconciliazione
- 🔵 **E-signing** — Firma digitale, documenti certificati
- 🔵 **Marketplace** — Template, plugin, estensioni

---

## 📊 Struttura Database

### Tabelle Core Contabilità

```
conti_contabili                 Piano dei conti (4 livelli)
movimenti_contabili             Movimenti (DARE/AVERE)
righe_movimento_contabile       Righe dettaglio movimenti
causali_contabili               Causali/giustificativi
liquidazioni_iva                Calcoli IVA (mensile/trimestrale)
```

### Tabelle Fatturazione

```
fatture_attive                  Fatture di vendita
fatture_attive_righe            Righe fatture attive
fatture_passive                 Fatture di acquisto
fatture_passive_righe           Righe fatture passive
scadenze                        Incassi/pagamenti attesi
```

### Tabelle Cespiti

```
assets                          Immobili, beni, macchinari
asset_depreciation_schedules    Piani ammortamento
asset_depreciation_entries      Rate ammortamento registrate
```

### Tabelle Cooperative

```
cooperative_shares              Quote di capitale
share_payment_requests          Richieste versamento
prestito_sociale_libretti       Libretti prestito
prestito_sociale_movimenti      Movimenti prestito
ristorni                        Deliberazioni ristorno
ristorno_entries                Voci ristorno per socio
```

### Tabelle Governance

```
organi                          Organi (Consiglio, Assemblea, ecc.)
cariche                         Cariche (Presidente, Tesoriere, ecc.)
incarichi                       Assegnazione soci a cariche
elezioni                        Processi elettorali
candidature                     Candidati alle elezioni
voti                            Schede votazione
```

### Tabelle Anagrafi

```
members                         Soci e volontari
member_types                    Tipologie socio
incassi                         Incassi (quote, donazioni)
ricevute                        Ricevute/quietanze
```

**Tutte le tabelle includono `tenant_id` per isolamento multi-tenant.**

---

## 🐳 Docker Compose

### Struttura

```yaml
services:
  app:
    image: php:8.4-fpm-alpine
    volumes:
      - .:/var/www/html           # Bind mount (auto-sync su Windows)
    ports:
      - "8090:80"
    depends_on:
      - db
  
  db:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: tessera
      MYSQL_ROOT_PASSWORD: secret
    ports:
      - "3306:3306"
    volumes:
      - db_data:/var/lib/mysql
```

### Comandi Utili

```bash
# Avvia tutto
docker compose up -d

# Esegui artisan nel container
docker compose exec app php artisan tinker
docker compose exec app php artisan optimize:clear

# Visualizza log
docker compose logs -f app
docker compose logs -f db

# Ferma tutto
docker compose down

# Ferma e rimuovi volumi (attenzione!)
docker compose down -v
```

---

## 📖 Documentazione

- **[agent.md](agent.md)** — Guide per sviluppatori e AI agents
- **[CHANGELOG.md](CHANGELOG.md)** — Storico versioni e modifiche
- **[LICENSE](LICENSE)** — GNU GPL v3

### Per Operator

- Email: [support@etsok.local](mailto:support@etsok.local)
- Issues: [GitHub Issues](https://github.com/mariomicrotel/tessera/issues)
- Discussioni: [GitHub Discussions](https://github.com/mariomicrotel/tessera/discussions)

### Configurazione

**Organi e Cariche** sono definiti in `config/organi.php` — hardcoded per semplicità:

```php
'organi' => [
    'consiglio_direttivo' => [
        'nome' => 'Consiglio Direttivo',
        'cariche' => [
            ['nome' => 'Presidente', 'ordine' => 1],
            ['nome' => 'Vicepresidente', 'ordine' => 2],
            ['nome' => 'Tesoriere', 'ordine' => 3],
            ['nome' => 'Segretario', 'ordine' => 4],
        ]
    ],
    // Aggiungere altri organi qui
]
```

**Per aggiungere un nuovo organo:**

1. Edita `config/organi.php` con nuovo slug + nome + cariche
2. Esegui: `php artisan db:seed --class=OrganiHardcodedSeeder`
3. Il nuovo organo comparea in Organi, Elezioni, Incarichi

---

## 🔒 Sicurezza

- **Autenticazione**: Jetstream + Fortify (2FA opzionale)
- **Authorization**: Model Policies per tutti i moduli
- **Encryption**: Dati sensibili criptati a riposo
- **Audit**: Audit trail completo (who, what, when)
- **Multi-tenant**: Row-level security con BelongsToTenant trait
- **CSRF/CORS**: Protezione CSRF, CORS configurato

**Per segnalare vulnerabilità:** apri una security advisory privata, non una issue pubblica.

---

## 📝 Changelog

### v1.0.0 (17 Aprile 2026) ✅ RELEASE

**Completamento Fase 1-9:**

- ✅ **Contabilità**: Piano conti, movimenti, liquidazione IVA, scadenzario, ratei, cespiti
- ✅ **Fatturazione**: Fatture attive/passive CRUD, XML SDI, note credito, TD07
- ✅ **Cooperative**: Capitale, prestiti, ristorni, soci lavoratori
- ✅ **Governance**: Organi, incarichi, elezioni
- ✅ **Bilancio**: SP, CE, rendiconto gestionale, XBRL
- ✅ **Adempimenti ETS**: Relazione missione, erogazioni liberali, F24, ritenute
- ✅ **Security**: Policies, audit trail, multi-tenant isolation
- ✅ **Test**: 562 test passed, 0 failed, 0 deprecated
- ✅ **Docs**: README completo, agent.md, inline docs

---

## ©️ Licenza

GNU GPL v3 — Vedi [LICENSE](LICENSE)

---

**Versione:** 1.0.0  
**Ultimo aggiornamento:** 27 Aprile 2026  
**Ambiente:** Laravel 12 + Vue 3 + MySQL 8.0 + Docker Compose  
**Mantainer:** [Network GTC](https://github.com/mariomicrotel)
