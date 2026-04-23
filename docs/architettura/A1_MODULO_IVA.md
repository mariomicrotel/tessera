# Architettura — Modulo IVA (A1)

> **Versione:** 1.0 · **Modello:** claude-opus-4-7 · **Data:** 2026-04-22
> Documento di riferimento per le fasi A2 (migration/modelli), A3 (service) e A4 (UI).

---

## 1. Premessa strategica

### 1.1 Contesto attuale del progetto

ETS-OK v1.0.0 è nato come sistema di **rendicontazione per cassa** per ETS
(Modello D – D.M. 5/3/2020). La prima nota esistente è mono-conto e mono-riga:

```
PrimaNotaEntry {
  conto_id, rendiconto_code, date, amount (signed),
  entryable_type, entryable_id   // morphTo: Incasso | Spesa | ExpenseRefund
}
```

Non è una partita doppia. Per gli ETS (spesso esenti IVA ex art. 10 DPR 633/72
e per attività non commerciali) questo è **sufficiente**.

Per le **cooperative** invece l'IVA è la regola: registri acquisti/vendite,
liquidazione periodica, dichiarazione annuale sono obbligatori.

### 1.2 Principi progettuali

| Principio | Decisione |
|-----------|-----------|
| **Coesistenza ETS / Cooperative** | Il modulo IVA è **opzionale** e disattivato per default negli ETS. Un flag `iva_enabled` sul tenant lo abilita. Le cooperative l'hanno `true` per default. |
| **Non-invasività sul sistema esistente** | Nessuna modifica a `prima_nota_entries`, `incassi`, `spese`. Il modulo IVA vive in tabelle proprie e si aggancia via relazioni polymorphic dove serve. |
| **Resolver pattern** | Seguendo il pattern `RendicontoCassaSchemaResolver`, introduciamo `RegimeIvaResolver` che espone l'interfaccia corretta in base al tenant (ordinario / forfettario / esente). |
| **Multi-tenant first** | Tutte le tabelle IVA hanno `tenant_id` FK + indice + global scope tramite `BelongsToTenant`. |
| **Periodo IVA immutabile dopo chiusura** | Una volta chiuso un periodo di liquidazione (`status=definitiva`), le fatture con data in quel periodo diventano **read-only**. |
| **Esigibilità IVA** | Supporto a *immediata* (default) e *differita* (IVA per cassa, art. 32-bis DL 83/2012) con un campo dedicato. |

### 1.3 Decisioni fuori scope (per ora)

- ❌ Liquidazione IVA di gruppo (gruppo IVA ex art. 70-bis)
- ❌ Plafond esportatori abituali
- ❌ Pro-rata di detraibilità (rimandato a v2)
- ❌ Ventilazione corrispettivi (commercio al dettaglio — rimandato)
- ✅ Split payment (art. 17-ter DPR 633/72) — **gestito**
- ✅ Reverse charge (art. 17 comma 6 / 74 comma 7-8) — **gestito**
- ✅ IVA per cassa — **gestito**

---

## 2. Schema ER

### 2.1 Diagramma relazionale

```
tenants
   │
   │  (1 : N)
   ├─────────────► codici_iva
   │                  │
   │                  │  (1 : N)
   │                  └──► righe_fattura_passiva
   │                  └──► righe_fattura_attiva
   │
   │  (1 : N)
   ├─────────────► fatture_passive ──► righe_fattura_passiva (1:N)
   │                  │                         │
   │                  │                         └──► cost_center_id (nullable, FK E1)
   │                  │
   │                  ├──► supplier_id (FK → suppliers, Area C1)
   │                  └──► prima_nota_entries (morphMany as entryable, solo parte "cassa")
   │
   │  (1 : N)
   ├─────────────► fatture_attive ──► righe_fattura_attiva (1:N)
   │                  │                         │
   │                  │                         └──► cost_center_id (nullable)
   │                  │
   │                  ├──► member_id nullable (FK → members, per soci/clienti soci)
   │                  ├──► cliente_* (denormalizzato se non socio)
   │                  └──► prima_nota_entries (morphMany as entryable)
   │
   │  (1 : N)
   ├─────────────► liquidazioni_iva
   │                  │
   │                  └──► lock su fatture del periodo quando status=definitiva
   │
   └──► settings (chiavi iva_*: regime, periodicita, ecc.)
```

### 2.2 Tabelle

#### `codici_iva`

Codici aliquota configurabili. Precaricati via seeder, personalizzabili per tenant.

| Colonna | Tipo | Note |
|---|---|---|
| `id` | `bigIncrements` | PK |
| `tenant_id` | `foreignId` | FK `tenants.id`, `onDelete('cascade')` |
| `codice` | `string(10)` | Es. `22`, `10`, `4`, `0`, `N2.2`, `N6.1` |
| `descrizione` | `string(100)` | Es. `IVA ordinaria 22%` |
| `percentuale` | `decimal(5,2)` | `22.00`, `10.00`, `0.00` |
| `tipo` | `enum` | `normale`, `esente`, `fuori_campo`, `non_imponibile`, `reverse_charge`, `split_payment` |
| `natura_sdi` | `string(5)` nullable | Codice Natura FatturaPA (N1, N2.1, N3.5, ecc.) — serve per A2 + H2 |
| `indetraibile_percentuale` | `decimal(5,2)` default `0.00` | Quota non detraibile (0=tutto detraibile, 100=indetraibile) |
| `attivo` | `boolean` default `true` | |
| `di_sistema` | `boolean` default `false` | Se `true`, non eliminabile via UI |
| `created_at`, `updated_at` | timestamps | |

**Indici:**
- `UNIQUE(tenant_id, codice)` – un tenant non può avere due codici uguali
- `INDEX(tenant_id, attivo)` – per filtri veloci in select

**Seeder precaricato (dati di sistema per tenant cooperativa):**
```
22  | IVA ordinaria 22%    | normale       | percentuale=22
10  | IVA ridotta 10%      | normale       | percentuale=10
4   | IVA agevolata 4%     | normale       | percentuale=4
0   | IVA 0%               | normale       | percentuale=0
ESE | Esente art.10        | esente        | natura_sdi=N4
FC  | Fuori campo IVA      | fuori_campo   | natura_sdi=N2.2
NI  | Non imponibile       | non_imponibile| natura_sdi=N3.5
RC  | Reverse charge       | reverse_charge| natura_sdi=N6.1
SP  | Split payment        | split_payment | natura_sdi=N6.9
```

#### `fatture_passive`

Testata fattura fornitore ricevuta.

| Colonna | Tipo | Note |
|---|---|---|
| `id` | `bigIncrements` | |
| `tenant_id` | `foreignId` | FK `tenants.id` |
| `supplier_id` | `foreignId` | FK `suppliers.id` (Area C1) |
| `numero_fattura` | `string(50)` | Numero assegnato dal fornitore |
| `data_fattura` | `date` | Data di emissione da parte del fornitore |
| `data_ricezione` | `date` | Data di ricezione (quando sono state consegnate) |
| `data_registrazione` | `date` | **Data di competenza IVA** (guida la liquidazione) |
| `data_scadenza` | `date` nullable | Calcolata da `condizioni_pagamento` fornitore |
| `imponibile_totale` | `decimal(12,2)` | Somma imponibili righe |
| `iva_totale` | `decimal(12,2)` | Somma IVA righe |
| `totale_documento` | `decimal(12,2)` | `imponibile_totale + iva_totale` |
| `esigibilita` | `enum` | `immediata` (default), `differita`, `split_payment` |
| `tipo_documento` | `enum` | `fattura`, `nota_credito`, `nota_debito`, `autofattura`, `parcella` |
| `stato_pagamento` | `enum` | `da_pagare`, `parziale`, `pagata`, `stornata` |
| `xml_sdi_path` | `string` nullable | Path XML SDI se ricevuta elettronicamente (preparazione per H1) |
| `note` | `text` nullable | |
| `liquidazione_iva_id` | `foreignId` nullable | FK → `liquidazioni_iva` quando il periodo è chiuso (lock) |
| `created_at`, `updated_at` | timestamps | |
| `deleted_at` | softDeletes | Solo bozze eliminabili; post-liquidazione bloccato |

**Indici:**
- `UNIQUE(tenant_id, supplier_id, numero_fattura, data_fattura)` – evita duplicati inserimento
- `INDEX(tenant_id, data_registrazione)` – per liquidazione periodica
- `INDEX(tenant_id, stato_pagamento)` – per scadenziario

#### `righe_fattura_passiva`

Righe di dettaglio (una per aliquota IVA / conto di costo).

| Colonna | Tipo | Note |
|---|---|---|
| `id` | `bigIncrements` | |
| `tenant_id` | `foreignId` | FK (denormalizzato per query dirette) |
| `fattura_passiva_id` | `foreignId` | FK cascade |
| `descrizione` | `string(200)` | |
| `quantita` | `decimal(10,4)` default `1.0000` | |
| `prezzo_unitario` | `decimal(12,4)` | |
| `imponibile` | `decimal(12,2)` | Calcolato: qty × prezzo |
| `codice_iva_id` | `foreignId` | FK `codici_iva.id` |
| `iva` | `decimal(12,2)` | `imponibile × percentuale / 100` |
| `totale_riga` | `decimal(12,2)` | `imponibile + iva` |
| `conto_costo_id` | `foreignId` nullable | FK `chart_of_accounts.id` (Area B2) — per partita doppia futura |
| `cost_center_id` | `foreignId` nullable | FK `cost_centers.id` (Area E1) — per analitica |
| `created_at`, `updated_at` | timestamps | |

**Indici:**
- `INDEX(fattura_passiva_id)` – per caricare righe di una fattura
- `INDEX(tenant_id, codice_iva_id)` – per riepilogo IVA per aliquota

#### `fatture_attive`

Testata fattura emessa verso clienti (soci cooperativa o soggetti esterni).

| Colonna | Tipo | Note |
|---|---|---|
| `id` | `bigIncrements` | |
| `tenant_id` | `foreignId` | FK `tenants.id` |
| `member_id` | `foreignId` nullable | FK `members.id` (se cliente è socio) |
| `cliente_ragione_sociale` | `string(200)` nullable | Se `member_id` null, denormalizzato |
| `cliente_partita_iva` | `string(11)` nullable | |
| `cliente_codice_fiscale` | `string(16)` nullable | |
| `cliente_indirizzo` | `string(200)` nullable | |
| `cliente_codice_sdi` | `string(7)` nullable | Per SDI futuro |
| `cliente_pec` | `string(100)` nullable | |
| `numero_fattura` | `string(20)` | Assegnato dal sistema (sezionale + progressivo) |
| `sezionale` | `string(10)` default `'A'` | Permette sezionali multipli se serve |
| `data_emissione` | `date` | |
| `data_registrazione` | `date` | **Competenza IVA** (uguale a emissione per default) |
| `data_scadenza` | `date` nullable | |
| `imponibile_totale` | `decimal(12,2)` | |
| `iva_totale` | `decimal(12,2)` | |
| `ritenuta_acconto` | `decimal(12,2)` nullable default `0` | Se prestazione professionale (integrazione con F1) |
| `totale_documento` | `decimal(12,2)` | `imponibile + iva − ritenuta` |
| `esigibilita` | `enum` | `immediata`, `differita`, `split_payment` |
| `tipo_documento` | `enum` | `fattura`, `nota_credito`, `nota_debito`, `acconto`, `parcella` |
| `tipo_documento_sdi` | `string(4)` | `TD01`, `TD04`, `TD05`, `TD06`, `TD24`… (per H2) |
| `stato` | `enum` | `bozza`, `emessa`, `inviata_sdi`, `consegnata_sdi`, `scartata_sdi`, `stornata` |
| `stato_incasso` | `enum` | `da_incassare`, `parziale`, `incassata` |
| `xml_sdi_path` | `string` nullable | |
| `note` | `text` nullable | |
| `liquidazione_iva_id` | `foreignId` nullable | Lock post-chiusura |
| `created_at`, `updated_at` | timestamps | |
| `deleted_at` | softDeletes | |

**Indici:**
- `UNIQUE(tenant_id, sezionale, numero_fattura)` – numerazione univoca per sezionale/tenant
- `INDEX(tenant_id, data_registrazione)` – liquidazione
- `INDEX(tenant_id, stato_incasso)` – scadenziario crediti
- `INDEX(tenant_id, member_id)` – fatture verso un socio

#### `righe_fattura_attiva`

Stessa struttura di `righe_fattura_passiva` ma con:
- `fattura_attiva_id` al posto di `fattura_passiva_id`
- `conto_ricavo_id` al posto di `conto_costo_id`

#### `liquidazioni_iva`

Chiusura periodica del registro IVA.

| Colonna | Tipo | Note |
|---|---|---|
| `id` | `bigIncrements` | |
| `tenant_id` | `foreignId` | |
| `anno` | `smallInt` | |
| `periodo` | `tinyInt` | 1-12 se mensile, 1-4 se trimestrale |
| `tipo_periodo` | `enum` | `mensile`, `trimestrale` |
| `data_inizio` | `date` | Primo giorno del periodo |
| `data_fine` | `date` | Ultimo giorno |
| `iva_debito` | `decimal(12,2)` | Somma IVA fatture attive del periodo |
| `iva_credito` | `decimal(12,2)` | Somma IVA fatture passive detraibile |
| `credito_periodo_precedente` | `decimal(12,2)` default `0` | Credito riportato |
| `saldo_periodo` | `decimal(12,2)` | `iva_debito − iva_credito` |
| `saldo_finale` | `decimal(12,2)` | `saldo_periodo − credito_periodo_precedente` |
| `acconto_versato` | `decimal(12,2)` nullable | Per acconto IVA dicembre |
| `interessi_trimestrali` | `decimal(12,2)` nullable | 1% per chi liquida trimestralmente |
| `status` | `enum` | `bozza`, `definitiva`, `versata` |
| `data_chiusura` | `date` nullable | Quando è diventata definitiva |
| `data_versamento` | `date` nullable | Quando è stato versato F24 |
| `numero_f24` | `string(50)` nullable | Riferimento al versamento |
| `note` | `text` nullable | |
| `created_at`, `updated_at` | timestamps | |

**Indici:**
- `UNIQUE(tenant_id, anno, periodo, tipo_periodo)` – una sola liquidazione per periodo
- `INDEX(tenant_id, status)` – per dashboard

### 2.3 Settings per tenant (no nuova tabella)

Aggiunti alla tabella `settings` esistente (key/value per tenant):

| Chiave | Valore esempio | Note |
|---|---|---|
| `iva_enabled` | `"true"` / `"false"` | Abilita modulo IVA (default `true` per cooperative, `false` per ETS) |
| `iva_regime` | `"ordinario"` / `"forfettario"` / `"agricolo"` / `"margine"` | Regime IVA del tenant |
| `iva_periodicita` | `"mensile"` / `"trimestrale"` | |
| `iva_numerazione_sezionale_default` | `"A"` | |
| `iva_ultimo_numero_fattura_attiva` | `"0"` | Progressivo corrente (per sezionale `A`) |
| `iva_aliquota_default` | `"22"` | Codice IVA precompilato nei form |
| `iva_esigibilita_default` | `"immediata"` | |

---

## 3. Diagramma servizi

```
┌────────────────────────────────────────────────────────────────────┐
│                     RegimeIvaResolver (statico)                     │
│  - currentRegime(): enum RegimeIva                                  │
│  - isEnabled(): bool                                                │
│  - periodicita(): enum Periodicita                                  │
│  - defaultCodiceIva(): CodiceIva                                    │
└────────────────────────────────────────────────────────────────────┘
                 │ determina il comportamento di ↓
                 ▼
┌────────────────────────────────────────────────────────────────────┐
│                          IvaService                                 │
│  ─────── Query / Calcolo ───────                                    │
│  - calcolaLiquidazione(anno, periodo, tipo): array                  │
│  - getRegistroAcquisti(anno, periodo): Collection<RigaRegistro>     │
│  - getRegistroVendite(anno, periodo): Collection<RigaRegistro>      │
│  - getRiepilogoIvaPerAliquota(fatture): array                       │
│                                                                     │
│  ─────── State transition ───────                                    │
│  - chiudiLiquidazione(anno, periodo, tipo): LiquidazioneIva         │
│      ↳ genera record definitivo, lock fatture del periodo           │
│      ↳ genera PrimaNotaEntry di riepilogo IVA (se config richiede)  │
│      ↳ emette evento LiquidazioneIvaChiusa                          │
│  - riaperaLiquidazione(id): void  [solo admin con motivazione]      │
│  - versaLiquidazione(id, f24): void                                 │
└────────────────────────────────────────────────────────────────────┘
                 ▲                                │
                 │                                │ usa
                 │                                ▼
┌────────────────────────────────────┐   ┌──────────────────────────┐
│      FatturaAttivaService          │   │    FatturaPassivaService │
│  - crea(dati): FatturaAttiva       │   │  - registra(dati)        │
│  - numeraProgressivo(sezionale)    │   │  - duplica(fattura)      │
│  - emetti(fattura): void           │   │  - paga(fattura, data)   │
│      ↳ status bozza→emessa         │   │  - storna(fattura)       │
│      ↳ blocca modifiche totali     │   │  - verificaDuplicati     │
│  - storna(fattura): FatturaAttiva  │   │                          │
│      ↳ genera nota di credito      │   │                          │
│  - incassa(fattura, importo, data) │   │                          │
└────────────────────────────────────┘   └──────────────────────────┘
                 │                                │
                 └──────────┬─────────────────────┘
                            ▼
          ┌─────────────────────────────────────┐
          │     CodiceIvaService                │
          │  - calcolaIva(imp, codice): float   │
          │  - validaNaturaSdi(codice): bool    │
          │  - calcolaIndetraibilita(...)       │
          └─────────────────────────────────────┘

Event listeners:
  ┌─ FatturaAttivaEmessa       → genera PrimaNotaEntry (entrata a credito cliente)
  ├─ FatturaPassivaRegistrata  → genera PrimaNotaEntry (uscita a debito fornitore)
  ├─ FatturaAttivaIncassata    → genera PrimaNotaEntry (entrata su conto)
  ├─ FatturaPassivaPagata      → genera PrimaNotaEntry (uscita su conto)
  └─ LiquidazioneIvaChiusa     → lock sui record, notifica admin
```

### 3.1 Responsabilità

| Servizio | Responsabilità | Non-responsabilità |
|---|---|---|
| `RegimeIvaResolver` | Dire che regime usa il tenant corrente | Calcoli |
| `IvaService` | Liquidazione, registri, riepiloghi | Emissione fatture singole |
| `FatturaAttivaService` | Ciclo di vita fattura attiva | XML SDI (→ H2), PDF (→ ReceiptService existing) |
| `FatturaPassivaService` | Ciclo di vita fattura passiva | Import XML SDI (→ H1) |
| `CodiceIvaService` | Matematica IVA e validazioni | Persistenza |

---

## 4. Multi-tenant

Segue esattamente il pattern esistente:

1. **Ogni nuova tabella** ha `tenant_id` come prima FK dopo `id`, con `onDelete('cascade')`.
2. **Ogni nuovo modello** usa il trait `BelongsToTenant` — global scope + auto-set `tenant_id` in `creating`.
3. **Le righe di dettaglio** (`righe_fattura_*`) denormalizzano `tenant_id` per permettere query dirette veloci senza join sulla testata (pattern già usato in `ristorno_entries` e `prestito_sociale_movimenti`).
4. **Route model binding:** override di `resolveRouteBinding` via trait (bypassa global scope al binding, ResolveTenant middleware autorizza dopo).
5. **Constraint uniqueness:** sempre scoped per `tenant_id` (numerazione fatture, codici IVA).

### 4.1 Validazione cross-tenant

Ogni `Request` del modulo IVA deve validare che le FK puntino a record dello stesso tenant:

```php
// Esempio: StoreFatturaPassivaRequest
'supplier_id' => [
    'required',
    Rule::exists('suppliers', 'id')->where('tenant_id', $this->tenant()->id),
],
'righe.*.codice_iva_id' => [
    'required',
    Rule::exists('codici_iva', 'id')->where('tenant_id', $this->tenant()->id),
],
```

---

## 5. Periodo di competenza IVA

### 5.1 Regola generale

La **data di competenza IVA** è:
- **Fatture attive:** `data_registrazione` (per default = `data_emissione`)
- **Fatture passive:** `data_registrazione` (può coincidere o seguire `data_ricezione`)

Il **periodo** si calcola da `data_registrazione`:

```
mensile:      periodo = MONTH(data_registrazione)
trimestrale:  periodo = CEIL(MONTH(data_registrazione) / 3)
```

### 5.2 Limiti per nuove fatture

Quando si crea o modifica una fattura, il sistema verifica:

```
se esiste liquidazione_iva(tenant, anno=YEAR(data_reg), periodo=PERIODO(data_reg),
                            status=definitiva):
  → blocca l'operazione con errore "Periodo IVA chiuso"
```

### 5.3 Esigibilità differita ("IVA per cassa")

Fatture con `esigibilita = differita`:
- **entrano nel registro** alla `data_registrazione` (documentale)
- **concorrono alla liquidazione** solo quando vengono incassate/pagate

Il servizio `IvaService::calcolaLiquidazione` implementa questa logica:

```php
iva_debito = fatture_attive.where(data_reg ∈ periodo).where(esigibilita=immediata)
           + fatture_attive.where(esigibilita=differita).where(incassata_in ∈ periodo)
           - note_credito_attive(...)

iva_credito = analogo per fatture passive (detraibile su pagamenti effettuati)
```

### 5.4 Acconto IVA di dicembre

Gestito come campo dedicato `acconto_versato` nella liquidazione di dicembre / IV trimestre.
Il calcolo (storico 88% vs previsionale 88%) è demandato all'utente: il sistema accetta il valore e lo scomputa.

---

## 6. Integrazione con il sistema esistente

### 6.1 Relazione con `prima_nota_entries`

Il modulo IVA **non modifica** `prima_nota_entries`. Si aggancia come entryable polymorphic:

```php
// FatturaAttiva.php
public function primaNotaEntries(): MorphMany {
    return $this->morphMany(PrimaNotaEntry::class, 'entryable');
}

// FatturaPassiva.php — idem
```

**Regola:** solo gli eventi *monetari* generano `PrimaNotaEntry`:
- `FatturaAttivaIncassata` → una entry positiva (su conto `conto_incasso_id`)
- `FatturaPassivaPagata` → una entry negativa (su conto `conto_pagamento_id`)

L'evento di *emissione/registrazione* della fattura **non** genera prima nota per cassa
(perché per cassa la competenza è sul momento dell'incasso/pagamento, non dell'emissione).

Per il modulo di **partita doppia** (Area B) invece la registrazione avviene
all'emissione: coesistono quindi due viste —
- Prima Nota semplificata (cassa, per tutti)
- Journal partita doppia (competenza, solo cooperative con contabilità ordinaria)

### 6.2 Relazione con `incassi`

Per **non duplicare** incassi:
- Le quote/donazioni/capitale/prestito sociale → continuano in `incassi`, **non generano fattura IVA**.
- Le cessioni di beni/prestazioni di servizi delle cooperative → generano `fatture_attive`, **non entrano in `incassi`**.
- Quando una fattura attiva viene incassata, il sistema crea una `PrimaNotaEntry` di tipo "incasso fattura" **ma non un record `Incasso`** (mantenendo `incassi` dedicato a quote/donazioni).

Questa separazione rispetta la semantica attuale: `incassi` = entrate non commerciali
del socio/donatore; `fatture_attive` = entrate commerciali.

### 6.3 Relazione con `spese`

Stessa logica: `spese` continua a esistere per uscite **non documentate da fattura** (piccole spese, rimborsi minori). Le fatture passive sono un layer separato.

Transizione dolce: chi oggi inserisce una "spesa" con fattura associata, domani potrà
"promuovere" la spesa a fattura passiva (via bottone UI, implementazione futura).

### 6.4 Relazione con `conti`

`conti` (cassa/banca) resta invariato. Le fatture IVA usano:
- `conto_incasso_id` / `conto_pagamento_id` → FK a `conti` (liquidità fisica)
- `conto_costo_id` / `conto_ricavo_id` → FK a `chart_of_accounts` (piano dei conti, Area B)

Per la v1 del modulo IVA possiamo **rimandare** l'aggancio a `chart_of_accounts`:
se il tenant non ha ancora la partita doppia attiva, i campi restano nullable e la
liquidazione funziona comunque solo sui dati IVA. L'integrazione contabile completa
arriva con l'Area B.

---

## 7. Configurazione e bootstrap

### 7.1 Quando creare i codici IVA di default

Option A (scelta): **seeder idempotente al momento della creazione tenant cooperativa**.

```
TenantCreated event → CreaCodiciIvaDefaultListener
  se tenant->isCooperativa():
    popola codici_iva con i 9 record di sistema (di_sistema=true)
```

### 7.2 Attivazione per tenant ETS

Un ETS che vuole abilitare l'IVA (es. ETS con attività commerciale marginale):
1. Admin imposta `settings.iva_enabled = true`
2. Listener manuale popola codici IVA di default
3. La navigazione mostra le voci "Fatture", "Registri IVA", "Liquidazione"

### 7.3 Feature flag in UI

Il layout principale legge il setting dal props Inertia:

```js
// $page.props.iva_enabled (iniettato da HandleInertiaRequests)
```

E nasconde/mostra l'intera sezione IVA.

---

## 8. Edge case gestiti

| Caso | Gestione |
|---|---|
| Nota di credito | Stesso modello `FatturaAttiva/Passiva`, `tipo_documento=nota_credito`, importi positivi con segno finale negativo nei riepiloghi (gestito in IvaService). |
| Split payment (PA) | Codice IVA dedicato `SP`; l'IVA non va a debito del tenant (la versa la PA). Liquidazione sottrae l'IVA dei documenti split. |
| Reverse charge (subappalto, rottami) | Codice IVA `RC`; genera doppia registrazione IVA (stessa aliquota sia debito che credito): effetto fiscale neutro ma obbligo di iscrizione in entrambi i registri. |
| IVA indetraibile al 100% | `codici_iva.indetraibile_percentuale=100`: l'IVA non entra in liquidazione credito; tutto l'importo diventa costo. |
| IVA indetraibile parziale (es. auto 40%) | `indetraibile_percentuale=60`: 40% detraibile, 60% costo. Calcolato in fase di registrazione riga. |
| Cambio regime in corso d'anno | Non supportato in v1. Richiede migrazione manuale e chiusura ultimo periodo regime precedente. |
| Fattura a cavallo (emissione 31/12, competenza 31/12) | Segue `data_registrazione`: se = 31/12 → periodo precedente; se = 1/1 → nuovo anno. |
| Numerazione sezionali | Un sezionale per tipologia (es. `A` = attive standard, `C` = note credito, `R` = reverse). Progressivo annuale o continuo è configurabile in settings. |

---

## 9. Modello di dominio — classi PHP (signature)

```php
// app/Models/CodiceIva.php
class CodiceIva extends Model {
    use BelongsToTenant;
    // casts: percentuale, indetraibile_percentuale → decimal:2
    public function isEsente(): bool;
    public function isFuoriCampo(): bool;
    public function richiedeNaturaSdi(): bool;
    public function scopeAttivi($q);
}

// app/Models/FatturaPassiva.php
class FatturaPassiva extends Model {
    use BelongsToTenant, SoftDeletes;
    public function supplier(): BelongsTo;
    public function righe(): HasMany;               // RigaFatturaPassiva
    public function liquidazioneIva(): BelongsTo;   // nullable
    public function primaNotaEntries(): MorphMany;
    public function attachments(): MorphMany;
    public function isReadOnly(): bool;             // true se periodo chiuso
    public function isPagata(): bool;
    public function saldoDaPagare(): float;
    public function scopeDaPagare($q);
    public function scopeInScadenza($q, int $giorni = 7);
}

// app/Models/RigaFatturaPassiva.php
class RigaFatturaPassiva extends Model {
    use BelongsToTenant;
    public function fattura(): BelongsTo;
    public function codiceIva(): BelongsTo;
    public function costCenter(): BelongsTo;
    public function contoCosto(): BelongsTo;         // ChartOfAccount (nullable finché B non implementato)
}

// app/Models/FatturaAttiva.php
class FatturaAttiva extends Model {
    use BelongsToTenant, SoftDeletes;
    public function member(): BelongsTo;
    public function righe(): HasMany;
    public function liquidazioneIva(): BelongsTo;
    public function primaNotaEntries(): MorphMany;
    public function attachments(): MorphMany;
    public function receipt(): MorphOne;             // riusa Receipt esistente per PDF
    public function isReadOnly(): bool;
    public function isIncassata(): bool;
    public function saldoDaIncassare(): float;
    public function clienteCompleto(): string;       // member ? member->full_name : cliente_ragione_sociale
}

// app/Models/LiquidazioneIva.php
class LiquidazioneIva extends Model {
    use BelongsToTenant;
    public function fatturePassive(): HasMany;
    public function fattureAttive(): HasMany;
    public function isDefinitiva(): bool;
    public function isVersata(): bool;
    public function etichettaPeriodo(): string;      // es. "Q2/2026", "Maggio 2026"
}
```

---

## 10. Roadmap esecutiva

| Step | Prompt | Modello | Dipende da |
|---|---|---|---|
| A1 | Architettura (questo doc) | opus | — |
| A2 | Migration + modelli + seeder codici IVA | sonnet | A1 |
| A3 | `IvaService`, `FatturaAttivaService`, `FatturaPassivaService`, `CodiceIvaService` | sonnet | A2 |
| A4 | Controller + pagine Vue (Fatture attive/passive, Registri, Liquidazione) | sonnet | A3 |

### 10.1 Test di accettazione per l'intero modulo

- [ ] Un tenant cooperativa appena creato ha i 9 codici IVA di default
- [ ] Inserendo una fattura passiva si aggiorna il registro acquisti
- [ ] Inserendo una fattura attiva si aggiorna il registro vendite
- [ ] La liquidazione mensile di un periodo con sole fatture 22% calcola saldo = debito − credito
- [ ] La chiusura definitiva di una liquidazione blocca la modifica delle fatture del periodo
- [ ] Una fattura con `esigibilita=differita` non pagata non entra nella liquidazione del suo periodo
- [ ] Una nota di credito sottrae correttamente dal debito IVA
- [ ] Tentare di inserire una fattura con data in periodo chiuso → errore 422
- [ ] Un tenant ETS con `iva_enabled=false` non vede la voce "IVA" in navigazione
- [ ] Le query sui registri sono filtrate per `tenant_id` automaticamente (test multi-tenant)

### 10.2 Decisioni da confermare prima di A2

Tre decisioni minori che richiedono input dell'utente prima di scrivere la migration:

1. **Numerazione progressivo fatture attive:** annuale (reset a 1 ogni anno) o continuo? **Proposta:** annuale per default, configurabile via settings.

2. **Storage XML SDI:** dove salviamo i file XML quando arriveranno in H1/H2? **Proposta:** `storage/app/tenants/{tenant_id}/sdi/{anno}/{mese}/`. Anticipiamo la colonna `xml_sdi_path` ora per non fare migration successive.

3. **Tabelle italiane vs inglesi:** il progetto mescola (`spese`, `incassi`, `conti` in italiano; `members`, `events`, `suppliers` in inglese). **Proposta per IVA:** italiano per i concetti fiscali italiani (`fatture_passive`, `righe_fattura_attiva`, `codici_iva`, `liquidazioni_iva`) perché non hanno equivalente inglese esatto e seguono la normativa. Coerente con `ristorni` e `prestito_sociale_*`.

---

## 11. Prompt per la fase A2 (prossimo step)

> Pronto da copiare-incollare in una sessione con modello `sonnet`.

```
Leggi docs/architettura/A1_MODULO_IVA.md per il contesto architetturale completo.

Implementa la fase A2 del piano:

1. MIGRATIONS nell'ordine:
   a. create_codici_iva_table (include campi: natura_sdi, indetraibile_percentuale, di_sistema)
   b. create_fatture_passive_table (con tutti i campi elencati in doc A1 §2.2)
   c. create_righe_fattura_passiva_table
   d. create_fatture_attive_table
   e. create_righe_fattura_attiva_table
   f. create_liquidazioni_iva_table
   g. add_liquidazione_iva_id_to_fatture (su entrambe le tabelle fatture)
   h. add_iva_settings_keys (seeder delle chiavi iva_* in settings)

2. MODELLI Eloquent con le signature elencate in A1 §9:
   - App\Models\CodiceIva
   - App\Models\FatturaPassiva + RigaFatturaPassiva
   - App\Models\FatturaAttiva + RigaFatturaAttiva
   - App\Models\LiquidazioneIva
   Tutti con BelongsToTenant, casts, scope, relazioni.

3. SEEDER App\Seeders\CodiciIvaDiSistemaSeeder
   - Popola i 9 codici di sistema (§2.2) per ogni tenant cooperativa esistente
   - Idempotente (firstOrCreate)

4. LISTENER App\Listeners\CreaCodiciIvaDefault
   - Ascolta TenantCreated
   - Se tenant->isCooperativa(), chiama il seeder per il singolo tenant

5. TEST Pest di base:
   - CodiceIva si crea con tenant_id automatico
   - FatturaPassiva calcola correttamente totale = imponibile + iva
   - Numerazione fatture attive è unique per (tenant, sezionale, numero)
   - LiquidazioneIva è unique per (tenant, anno, periodo, tipo_periodo)

Rispetta tutte le convenzioni del progetto: trait BelongsToTenant, naming italiano
per le tabelle, soft deletes dove indicato in doc, indici come specificati.

Non implementare ancora controller o UI — quello è A4.
Non implementare ancora services di calcolo — quello è A3.
Fermati dopo aver eseguito `php artisan migrate` con successo e dopo aver
eseguito i test di base.
```

---

## 12. Riepilogo decisioni architetturali

| ADR | Decisione | Motivazione |
|---|---|---|
| ADR-01 | Modulo IVA in tabelle separate, non in prima_nota | Non rompere il sistema ETS esistente, mantenere coesistenza. |
| ADR-02 | Resolver `RegimeIvaResolver` seguendo pattern rendiconto | Uniformità con il codebase, testabilità, feature flag naturale. |
| ADR-03 | Esigibilità immediata / differita / split payment come enum | Copre i 3 casi normativi italiani principali. |
| ADR-04 | Lock fatture via FK `liquidazione_iva_id` | Integrità referenziale a livello DB, non solo applicativa. |
| ADR-05 | Codici IVA con `natura_sdi` precaricato | Preparazione naturale per H1/H2 (FatturaPA) senza future migration. |
| ADR-06 | `tenant_id` denormalizzato su righe fatture | Performance query registri (pattern già in ristorno_entries). |
| ADR-07 | Numerazione progressiva configurabile per sezionale | Flessibilità per tenant con più sezionali (standard italiano). |
| ADR-08 | Settings per config tenant, non colonne su tenants | Evita bloat della tabella tenants, pattern già usato. |
| ADR-09 | `chart_of_accounts` FK nullable in fase A | Permette deploy IVA senza dipendere da Area B (partita doppia). |
| ADR-10 | PrimaNotaEntry solo su eventi monetari (incasso/pagamento) | Mantiene semantica cassa della prima nota ETS. |
