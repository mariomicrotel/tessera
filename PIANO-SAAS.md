# Piano di Trasformazione: ETS-OK → Infotel Sistemi SaaS

## Panoramica

Trasformazione dell'applicativo ETS-OK (gestionale single-tenant per ETS) in una piattaforma SaaS multi-tenant brandizzata **Infotel Sistemi**.

---

## FASE 0 — Rebranding (Stimato: 1-2 giorni)

### 0.1 Identità
- [ ] Nuovo nome: **Infotel Sistemi** (o nome prodotto specifico, es. "Infotel ETS", "Infotel Gestionale")
- [ ] Nuovo logo SVG (ApplicationLogo.vue, ApplicationMark.vue)
- [ ] Nuovo favicon.ico
- [ ] Palette colori Infotel (attualmente viola #6875F5)

### 0.2 File da modificare

| File | Cosa cambiare |
|------|--------------|
| `.env.example` | `APP_NAME=Infotel Sistemi` |
| `composer.json` | description, name, keywords |
| `package.json` | name |
| `README.md` | Titolo, descrizione, URL repo |
| `agent.md` | Titolo, descrizione |
| `LICENSE` | Copyright Infotel Sistemi |
| `resources/views/install/layout.blade.php` | Titolo "Installazione Infotel Sistemi" |
| `app/Http/Controllers/InstallController.php` | Email test subject/body |
| `resources/js/Components/ApplicationLogo.vue` | SVG logo |
| `resources/js/Components/ApplicationMark.vue` | SVG mark |
| `public/favicon.ico` | Favicon |
| `database/seeders/InstallSeeder.php` | Default nome_associazione |
| `database/seeders/DatabaseSeeder.php` | Default nome_associazione |
| `database/migrations/*_add_extended_settings_keys.php` | Default |
| `app/Providers/AppServiceProvider.php` | Commenti |
| `app/Http/Middleware/HandleInertiaRequests.php` | Commenti |

---

## FASE 1 — Infrastruttura Tenant (Stimato: 3-5 giorni)

### 1.1 Modello Tenant (Organization)

```
tenants
├── id (UUID)
├── name (nome organizzazione)
├── slug (per URL: infotel.app/org/{slug})
├── domain (custom domain opzionale)
├── plan (free|basic|pro|enterprise)
├── plan_expires_at
├── settings (JSON — sovrascrive defaults)
├── is_active (boolean)
├── created_at
├── updated_at
└── deleted_at (soft delete)
```

### 1.2 Relazione User ↔ Tenant

```
tenant_user (pivot)
├── tenant_id
├── user_id
├── role (admin|contabile|segreteria|socio)
└── created_at
```

Un utente può appartenere a più tenant (es. commercialista che gestisce più ETS).

### 1.3 Strategia di Routing

**Scelta consigliata: Path-based con subdomain opzionale**

```
# Piattaforma SaaS
/                           → Landing page marketing
/register                   → Registrazione nuovo tenant
/login                      → Login (selezione tenant se multipli)
/pricing                    → Piani e prezzi

# App tenant (path-based)
/app/{tenant:slug}/dashboard
/app/{tenant:slug}/members
/app/{tenant:slug}/settings
...

# Sito pubblico tenant
/org/{tenant:slug}          → Homepage pubblica dell'ETS
/org/{tenant:slug}/admission → Form ammissione soci

# Super Admin
/admin/tenants              → Gestione tenant
/admin/billing              → Fatturazione
/admin/stats                → Statistiche piattaforma
```

### 1.4 Middleware Stack

```
TenantMiddleware
├── Risolve tenant da URL ({tenant:slug})
├── Verifica che l'utente appartenga al tenant
├── Verifica che il tenant sia attivo
├── Imposta tenant corrente in app container
└── Imposta ruolo utente nel contesto tenant
```

---

## FASE 2 — Migrazione Database (Stimato: 3-4 giorni)

### 2.1 Nuove tabelle

```sql
-- Tenant
CREATE TABLE tenants (...);

-- Relazione utente-tenant con ruolo
CREATE TABLE tenant_user (...);

-- Piani SaaS (opzionale, può essere enum)
CREATE TABLE plans (...);

-- Billing (Stripe/Paddle integration)
CREATE TABLE tenant_subscriptions (...);
CREATE TABLE tenant_invoices (...);
```

### 2.2 Aggiunta tenant_id a 36 modelli

Una singola migration aggiunge `tenant_id` (foreign key, indexed) a:

**Finanziari:** incassi, spese, prima_nota_entries, receipts, expense_refunds, refund_items, conti, receipt_templates

**Soci:** members, member_invites, member_types, subscriptions

**Documenti:** documents, verbali, events, event_registrations, email_templates, templates, attachments

**Organi:** organi, cariche_sociali, incarichi, elezioni, candidature, partecipazioni_voto, voti

**Patrimonio:** properties, assets, items, warehouses, warehouse_stocks, locations

**Configurazione:** settings (aggiunge tenant_id alla primary key)

### 2.3 Strategia Migrazione Dati Esistenti

```
1. Crea tenant "default" per dati esistenti
2. UPDATE ogni tabella SET tenant_id = {default_tenant_id}
3. ALTER ADD NOT NULL constraint
4. ADD INDEX su tenant_id
```

---

## FASE 3 — Scoping Modelli (Stimato: 4-5 giorni)

### 3.1 Trait BelongsToTenant

```php
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        // Auto-scope tutte le query al tenant corrente
        static::addGlobalScope('tenant', function ($query) {
            $query->where('tenant_id', app('current_tenant')->id);
        });

        // Auto-set tenant_id alla creazione
        static::creating(function ($model) {
            $model->tenant_id = app('current_tenant')->id;
        });
    }
}
```

### 3.2 Applicare il trait a tutti i 36 modelli

### 3.3 Aggiornare Settings

```php
// Da:
Settings::get('nome_associazione')

// A:
Settings::forTenant($tenant)->get('nome_associazione')
// oppure con global scope automatico
```

### 3.4 Aggiornare Services

- `AttachmentService` → path: `media/{tenant_id}/allegati/...`
- `PlaceholderResolver` → contesto tenant
- `ReceiptService` → settings del tenant
- `RendicontoCassaService` → dati del tenant

---

## FASE 4 — Controller & Frontend (Stimato: 5-7 giorni)

### 4.1 Controller

Ogni controller già funziona con Eloquent → con il global scope i dati sono automaticamente filtrati. Serve però:

- Aggiornare route parameter binding per includere tenant slug
- Aggiornare Inertia props per passare tenant corrente
- Aggiornare autorizzazioni (Policy) per verificare tenant ownership
- Aggiornare form validation per unicità scoped (es. receipt number unico per tenant)

### 4.2 Frontend (Vue/Inertia)

- Aggiornare AppLayout.vue per mostrare tenant corrente
- Aggiornare navigazione con tenant slug nelle route
- Aggiungere tenant switcher (se utente multi-tenant)
- Aggiornare Ziggy route helper per includere tenant parameter
- Aggiornare tutti i `route('...', params)` nei componenti Vue

### 4.3 Nuove pagine

- Landing page marketing (/)
- Pagina registrazione tenant (/register)
- Tenant switcher
- Dashboard admin piattaforma (/admin/*)

---

## FASE 5 — SaaS Features (Stimato: 5-7 giorni)

### 5.1 Registrazione Self-Service

```
1. Utente compila form (nome, email, password, nome ETS)
2. Sistema crea: User + Tenant + Member (admin)
3. Esegue TenantSeeder (ruoli, organi, conti default, email templates)
4. Redirect a onboarding wizard
```

### 5.2 Onboarding Wizard

```
Step 1: Dati associazione (nome, CF, P.IVA, indirizzo)
Step 2: Upload logo
Step 3: Configurazione quote
Step 4: Invita primi collaboratori
```

### 5.3 Piani e Limiti

| Feature | Free | Basic (€19/mese) | Pro (€49/mese) | Enterprise |
|---------|------|-------------------|-----------------|------------|
| Soci | 25 | 100 | 500 | Illimitati |
| Utenti staff | 1 | 3 | 10 | Illimitati |
| Storage | 100MB | 1GB | 10GB | 50GB |
| Sito pubblico | No | Sì | Sì | Custom domain |
| Export PDF | Base | Completo | Completo | Completo |
| Supporto | Community | Email | Prioritario | Dedicato |

### 5.4 Billing Integration

- **Stripe** (consigliato) o **Paddle** per pagamenti
- Laravel Cashier per gestione subscription
- Webhook per aggiornamento stato
- Invoice automatiche
- Trial period (14 giorni)

### 5.5 Super Admin Dashboard

```
/admin
├── /tenants          → Lista tenant, stato, piano
├── /tenants/{id}     → Dettaglio tenant, impersonate
├── /billing          → Revenue, MRR, churn
├── /stats            → Utenti attivi, registrazioni
└── /system           → Health check, job queue, logs
```

---

## FASE 6 — Sicurezza & Performance (Stimato: 2-3 giorni)

### 6.1 Sicurezza

- [ ] Test isolamento dati: nessun tenant può vedere dati altrui
- [ ] Rate limiting per tenant
- [ ] Audit log per operazioni sensibili
- [ ] Backup per tenant
- [ ] GDPR: export e cancellazione dati per tenant

### 6.2 Performance

- [ ] Index compositi (tenant_id + colonne frequenti)
- [ ] Cache per tenant (prefisso cache key con tenant_id)
- [ ] Queue jobs con tenant context
- [ ] Database connection pooling

### 6.3 Testing

- [ ] Test factory con tenant context
- [ ] Test di isolamento cross-tenant
- [ ] Test piani e limiti
- [ ] Test billing webhook
- [ ] Load testing multi-tenant

---

## FASE 7 — Deploy & Infrastruttura (Stimato: 2-3 giorni)

### 7.1 Infrastruttura Consigliata

```
┌─────────────────────────────────────────┐
│           Load Balancer (Nginx)         │
│         *.infotel-ets.com               │
├──────────┬──────────┬───────────────────┤
│  App #1  │  App #2  │   App #N          │
│  PHP-FPM │  PHP-FPM │   PHP-FPM         │
├──────────┴──────────┴───────────────────┤
│         MySQL / PostgreSQL              │
│         (RDS o managed)                 │
├─────────────────────────────────────────┤
│         Redis (cache + queue)           │
├─────────────────────────────────────────┤
│         S3 / MinIO (file storage)       │
└─────────────────────────────────────────┘
```

### 7.2 Docker Production

- PHP-FPM + Nginx (non `artisan serve`)
- Redis per cache e queue
- MySQL 8.0 gestito (RDS)
- S3 per file storage
- CI/CD pipeline (GitHub Actions)

---

## Riepilogo Timeline

| Fase | Descrizione | Giorni stimati |
|------|-------------|----------------|
| 0 | Rebranding | 1-2 |
| 1 | Infrastruttura Tenant | 3-5 |
| 2 | Migrazione Database | 3-4 |
| 3 | Scoping Modelli | 4-5 |
| 4 | Controller & Frontend | 5-7 |
| 5 | SaaS Features | 5-7 |
| 6 | Sicurezza & Performance | 2-3 |
| 7 | Deploy & Infrastruttura | 2-3 |
| **Totale** | | **25-36 giorni** |

---

## Decisioni da prendere PRIMA di iniziare

1. **Nome prodotto esatto**: "Infotel Sistemi", "Infotel ETS", "Infotel Gestionale"?
2. **Logo e colori**: Hai già il logo Infotel Sistemi in SVG?
3. **Routing**: Path-based (`/app/{slug}/...`) o subdomain (`{slug}.infotel.app`)?
4. **Database**: Single DB con tenant_id (consigliato) o DB separati per tenant?
5. **Billing**: Stripe, Paddle, o altro?
6. **Piani e pricing**: Confermi la struttura proposta o hai altri tier?
7. **Dominio**: Quale dominio userai? (es. `gestionale.infotelsistemi.it`)
8. **Hosting**: VPS, AWS, DigitalOcean, Hetzner?
