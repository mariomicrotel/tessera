# Guida per agenti AI – ETS OK

Documentazione per orientarsi rapidamente nella codebase. **ETS OK** è il gestionale per Enti del Terzo Settore. Lingua del progetto: **italiano** (backend, frontend, commenti, `lang/it`).

---

## 1. Panoramica e scopo

Gestionale per **Ente del Terzo Settore (ETS)**. Gestisce:

- **Soci e volontari**: anagrafica, tipi socio, stati (domanda, ammissione, cessazione, decesso, morosità, dimissioni, esclusione), libro soci; approvazione domande singola (scheda socio) e bulk (lista soci con checkbox), con opzione invio email di notifica approvazione
- **Cassa**: incassi (quote e donazioni), ricevute, rimborsi spese (approvazione → contabilizzazione automatica: una voce prima nota per riga; descrizione = descrizione riga + riferimento rimborso, es. «Rimborso spese #5»)
- **Contabilità**: voci del rendiconto (Modello D, schema fisso in config), prima nota, report contabili, rendiconto di cassa (PDF)
- **Organi e votazioni**: **organi e cariche hardcoded** da `config/organi.php` (slug, nome, cariche); il seeder `OrganiHardcodedSeeder` sincronizza il DB. Identificazione per **slug** (es. `consiglio_direttivo`). Incarichi, elezioni, candidature, voti. Per aggiungere un organo: modificare config e eseguire il seeder.
- **Patrimonio**: immobili e beni, magazzini, ubicazioni, articoli
- **Documenti**: verbali, documenti, allegati
- **Eventi**: eventi e iscrizioni
- **Impostazioni**: nome associazione, logo, intestazione/carta intestata (letterhead), quote, P.IVA, causali, email di test, sito pubblico (sezioni e sfondi)

---

## 2. Stack tecnologico

| Livello | Tecnologie |
|--------|------------|
| **Backend** | PHP 8.4+, Laravel 12, Laravel Jetstream (auth), Laravel Fortify, Sanctum, barryvdh/laravel-dompdf |
| **Frontend** | Vue 3 (Composition API, `<script setup>`), Inertia.js 2, Vite 7, Tailwind CSS 3 (@tailwindcss/vite), Heroicons, Ziggy (route), TipTap (editor) |
| **Database** | Configurabile (MySQL ecc.); migrations in `database/migrations/` |
| **Test** | Pest 4, pest-plugin-laravel, Laravel Pail |

---

## 3. Struttura delle cartelle principali

- **Backend**: `app/`  
  - `Http/Controllers/` – controller Inertia e logica richiesta/risposta  
  - `Models/` – Eloquent  
  - `Http/Middleware/` – HandleInertiaRequests, EnsureUserHasRole  
  - `Http/Requests/` – Form Request (es. StoreMemberRequest, UpdateMemberRequest)  
  - `Services/` – AttachmentService, ReceiptService, RendicontoCassaService, RendicontoCassaSchema  
  - `Actions/` – Fortify/Jetstream (auth, profilo)  
  - `Policies/` – autorizzazioni (se usate)

- **Frontend**: `resources/js/`  
  - `Pages/` – una cartella per risorsa (es. `Organi/Show.vue`, `Members/Index.vue`)  
  - `Components/` – componenti riutilizzabili (pulsanti, input, modali, FlashToast…)  
  - `Layouts/AppLayout.vue` – layout principale con menu laterale  
  - `Composables/` – es. `useTheme.js`

- **Route**: `routes/web.php` – route nominate; area autenticata con `auth:sanctum` + `verified`. Route pubbliche: `/` (sito pubblico, `PublicSiteController`), `/install` (wizard installazione), domanda ammissione socio con token (`members/admission-request/{token}`), download firmati (signed): logo, statuto, rendiconto, sfondo sezioni sito (`PublicDownloadController`).

- **Viste Blade**:  
  - `resources/views/app.blade.php` – root Inertia  
  - `resources/views/emails/`, `resources/views/receipts/`, `resources/views/rendiconto_cassa/` – email e template PDF  
  - `resources/views/pdf/` – letterhead (carta intestata), letterhead-preview  
  - `resources/views/install/` – wizard installazione  
  - `resources/views/documents/pdf.blade.php`, `resources/views/verbali/pdf.blade.php` – PDF documenti e verbali

---

## 4. Modelli e dominio

- **User** (auth) ↔ **Member** (relazione 1:1 opzionale): un socio può avere un account per area self-service; `User` ha `member()` e `Member` ha `user()`. **Gestione utenti** (solo admin): pagina Utenti (`Users/Index.vue`) con elenco, filtri, creazione utente (modale: nome, email, password opzionale, ruoli), modifica ruoli, collegamento/scollegamento socio, invio link reset password, eliminazione; `UserController`, route `users.index`, `users.store`, `users.roles.update`, `users.member.update`, ecc.

- **Ruoli**: `admin`, `contabile`, `segreteria`, `socio` (tabelle `roles` e `role_user`). Middleware `role:admin,segreteria` ecc. registrato in `bootstrap/app.php`; usato nelle route o nei controller.

- **Modelli principali**:  
  Member, MemberType, Organo, CaricaSociale, Incarico, Elezione, Candidatura, Voto, Incasso, Receipt, ExpenseRefund, RefundItem, PrimaNotaEntry, Conto, Property, Asset, Warehouse, WarehouseStock, Item, Location, Event, EventRegistration, Verbale, Document, Template, Attachment, Settings, Supplier.

- **Contabilità**: le voci del rendiconto sono **fisse** (Modello D, GU 18-04-2020), definite in `config/rendiconto_cassa.php` e usate tramite `App\Services\RendicontoCassaSchema`. La prima nota (`PrimaNotaEntry`) ha `conto_id` (conto tesoreria) e `rendiconto_code` (nessun piano dei conti né mapping). Gli **incassi** hanno **conto di destinazione** (`conto_id`) obbligatorio: l’utente sceglie il conto su cui registrare l’entrata e il movimento in prima nota usa quel conto. Codici fissi per prima nota automatica: quota → INC_A_1, donazione → INC_A_4, rimborsi → EXP_A_5.

- **Relazioni chiave**:  
  - Organo → caricheSociali → incarichi → member  
  - Member → incassi, expenseRefunds, candidature, voti, registrazioni eventi  
  - **Conto** (tesoreria) → `movimenti()` (PrimaNotaEntry), `incassi()` (Incasso). Un conto si può eliminare solo se non ha movimenti né incassi collegati (`ContoController::destroy`). Route resource `conti`: parametro `{conti}` → nel controller usare `Conto $conti` per il model binding.  
  - Incassi e ricevute collegati a prima nota (rendiconto_code). **ExpenseRefund** → `primaNotaEntries()` (morphMany): le voci sono create all’approvazione, una per ogni RefundItem; descrizione movimento = descrizione riga + « – Rimborso spese #id ».  
  - Attachment polimorfico (Settings logo, documenti, ecc.)

---

## 5. Flusso richiesta/risposta (Inertia)

- I controller restituiscono `Inertia::render('Cartella/Page', [ ... props ])`.  
  Il path della pagina Vue deve corrispondere: `resources/js/Pages/Cartella/Page.vue` (es. `Organi/Show.vue`).

- **Dati condivisi** (ogni richiesta): definiti in `app/Http/Middleware/HandleInertiaRequests.php` → `share()`:  
  `user` (da parent/Jetstream), `ziggy`, `csrf_token`, `userRoles`, `authMember`, `logo_url`, `flash`, `apiVersions`, `appVersion`, `nome_associazione`.  
  In frontend: `usePage().props` (es. per ruoli, flash, titolo browser).

- **Route nominate**: Ziggy espone `route('nome.route', parametri)` nel frontend. Usare sempre `route()` per link e submit (es. `route('organi.show', organo)`).

---

## 6. Convenzioni di codice

- **Controller**:  
  - Validazione spesso inline: `$request->validate([...])`; dove serve, usare Form Request in `Http/Requests/`.  
  - Redirect con messaggio: `redirect()->route(...)->with('flash', ['type' => 'success', 'message' => '...'])` (tipi: `success`, `error`, `info`).

- **Pagine Vue**:  
  - `<script setup>`, `defineProps` per i dati dalla pagina.  
  - Form: `useForm` di Inertia; invii con `form.post(route(...), { preserveScroll: true, onSuccess: ... })`.  
  - Navigazione: `router.get/post/put/delete` e `route()`.  
  - Layout comune: `AppLayout`; titolo con `Head` e `title`.

- **Alias**: in `jsconfig.json` `@/*` → `resources/js/*` (es. `@/Layouts/AppLayout.vue`, `@/Components/PrimaryButton.vue`).

- **Permessi**:  
  - Menu e sezioni in `AppLayout.vue` basate su `userRoles` e `route().current()`.  
  - Backend: middleware `role:admin,segreteria` (o simile) e, se necessario, `$user->hasRole(...)` / `$user->isAdmin()`.

---

## 7. Servizi e funzionalità speciali

- **Services**:  
  - `app/Services/AttachmentService.php` – salvataggio allegati (path `media/allegati/{context}/...`, record `Attachment`)  
  - `app/Services/PlaceholderResolver.php` – sostituzione placeholder `{{key}}` e `{{ora+30m}}` nel contenuto di verbali/documenti al salvataggio. Placeholder: `nome-associazione`, `data`, `ora`, `ora+30m`/`ora+1h20m` (offset), `n-soci`, `anno`/`anno+1` (offset anni), `presidente`, `segretario`, `tesoriere`, `sede`, `sede-legale`, `sede-operativa` (da Location per tipo), `direttivo`, `richieste-soci`. I template non vengono risolti.  
  - `app/Services/ReceiptService.php` – gestione ricevute  
  - `app/Services/RendicontoCassaService.php` – rendiconto di cassa (legge prima nota per `rendiconto_code`, struttura da schema MOD_D)  
  - `app/Services/RendicontoCassaSchema.php` – schema hardcoded Modello D, voci selezionabili, code→label
- **Config**: `config/rendiconto_cassa.php` – voci fisse del rendiconto (macro_areas e children)

- **Settings**: modello `Settings` (chiave-valore) per nome associazione, quote, P.IVA, causali, letterhead, sezioni sito pubblico, ecc. Logo: `Attachment::forSetting('logo')`. Sfondi sezioni sito: attachment per section.

- **PDF**: DomPDF per ricevute, rendiconto cassa, documenti, verbali e carta intestata; template in `resources/views/receipts/`, `resources/views/rendiconto_cassa/`, `resources/views/pdf/letterhead.blade.php`, `resources/views/documents/pdf.blade.php`, `resources/views/verbali/pdf.blade.php`.

- **Modifica documenti/verbali/template**: in **update** si usa un controllo "stale" (conflitto di modifica): il frontend invia `_updated_at` (quando l’utente ha aperto il form); se in DB il record è stato modificato dopo (`updated_at` più recente), il backend risponde con errore `stale` e redirect; il frontend mostra un modale "Annulla" / "Sovrascrivi"; in caso di "Sovrascrivi" si rinvia la richiesta con `force_overwrite=1`. Controllo in `DocumentController`, `VerbaleController`, `TemplateController` (metodo `update`); modale e `_updated_at` nelle pagine Edit (Documents/Edit.vue, Verbali/Edit.vue, Templates/Edit.vue).

- **Template email**: tipi definiti in `config/email_templates.php` (label, default subject/body, placeholders); modello `EmailTemplate`, `EmailTemplate::render(tipo, replacements)` per soggetto e corpo HTML; seeder `EmailTemplatesSeeder` crea i record dai tipi in config. Pagine **Documenti → Template email** (`EmailTemplates/Index.vue`, `EmailTemplates/Edit.vue`): modifica oggetto e corpo con editor WYSIWYG o **modalità HTML raw** (toggle Editor / HTML raw). Anteprima con valori campione per placeholder. Tipo `notifica_approvazione_socio`: inviato (opzionale) quando un aspirante viene approvato; placeholders `appName`, `member_name`, `quota_importo`. Invio gestito in `MemberController::approveOneMember()` (parametro `$sendEmail`); l’utente sceglie tramite checkbox "Invia email di notifica" in scheda socio (approvazione singola) e in lista soci (approvazione bulk).

- **Ammissione soci (approvazione)**: approvazione singola da scheda socio: `MemberController::acceptAdmission`, route `members.accept-admission`; approvazione bulk da lista soci (`Members/Index.vue`): checkbox su ogni riga, barra "Approva selezionati", route `members.accept-admission-bulk`. Backend carica solo soci con `stato === 'aspirante'` (gli altri id in request vengono ignorati). Logica condivisa in `approveOneMember($member, $sendEmail)` (update stato/date + eventuale invio email). In entrambi i flussi l’utente può attivare/disattivare l’invio email di notifica (checkbox; default: invia).

- **Tema**: `resources/js/Composables/useTheme.js` e componente `ThemeTriStateButton` per tema chiaro/scuro/system.

---

## 8. Database e seed

- **Migrations**: in `database/migrations/`, numerate e descrittive.

- **Seeders**:  
  - `RoleSeeder` – ruoli: admin, contabile, segreteria, socio  
  - `MemberTypeSeeder`  
  - `DatabaseSeeder` – chiama i seeders sopra, imposta valori default in Settings e crea utente di test con ruolo admin.  
  - `InstallSeeder` – usato dall’installer web: ruoli, tipi socio, conto Cassa contanti, Settings e un solo utente admin (dati da config).

- **Installazione iniziale**: route `/install` (solo se `APP_KEY` vuoto); wizard per configurazione DB (MySQL o SQLite) e creazione utente amministratore; controller `InstallController`, viste in `resources/views/install/`, middleware `EnsureNotInstalled`.

---

## 9. Dove cercare per modifiche comuni

| Obiettivo | Dove intervenire |
|-----------|------------------|
| **Installazione iniziale** | Route `/install`, `App\Http\Controllers\InstallController`, `resources/views/install/`, middleware `EnsureNotInstalled`; configurazione DB e utente admin. |
| **Nuova pagina Inertia** | Aggiungere route in `routes/web.php`, metodo in controller con `Inertia::render('Cartella/NomePage', [...])`, creare `resources/js/Pages/Cartella/NomePage.vue`. |
| **Nuovo modello / CRUD** | Migration, Model in `app/Models/`, controller (e eventuale policy), route in `web.php`, pagine Vue in `Pages/Cartella/`. |
| **Menu laterale** | `resources/js/Layouts/AppLayout.vue` – sezioni e link in base a `route().current()` e `userRoles`. |
| **Template documenti** | `app/Models/Template.php`, `App\Http\Controllers\TemplateController`, `resources/js/Pages/Templates/`, route `templates.*`. |
| **Sito pubblico** | `App\Http\Controllers\PublicSiteController`, `resources/js/Pages/Public/Home.vue`, route `home`. Download pubblici: `PublicDownloadController`, route signed `public.*`. |
| **Placeholder nei documenti/verbali** | `app/Services/PlaceholderResolver.php` (risoluzione al salvataggio); inserimento da toolbar in `resources/js/Components/RichTextEditor.vue` (dropdown «Placeholder»). |
| **Messaggi flash** | Backend: `with('flash', ['type' => 'success'|'error'|'info', 'message' => '...'])`. Frontend: componente `FlashToast` e `page.props.flash`. |
| **Ruoli e permessi** | Middleware `app/Http/Middleware/EnsureUserHasRole.php`, registrazione in `bootstrap/app.php`, seed in `database/seeders/RoleSeeder.php`. |
| **Gestione utenti (creazione, ruoli, socio)** | `App\Http\Controllers\UserController` (solo admin), route `users.index`, `users.store`, `users.roles.update`, `users.member.update`, `users.send-password-reset`, `users.destroy`; pagina `resources/js/Pages/Users/Index.vue` (modali: Crea utente, Modifica ruoli, Collega socio). |
| **Incassi / conto di destinazione** | `App\Http\Controllers\IncassoController`, modello `Incasso` (conto_id), pagine `resources/js/Pages/Incassi/` (Create, Index, Show). Conti tesoreria: `App\Http\Controllers\ContoController`, `resources/js/Pages/Conti/`. |
| **Template email** | `config/email_templates.php` (tipi, placeholders), `app/Models/EmailTemplate.php`, `App\Http\Controllers\EmailTemplateController`, `resources/js/Pages/EmailTemplates/` (Index, Edit con Editor/HTML raw). Seeder `EmailTemplatesSeeder`. |
| **Approvazione soci (singola e bulk)** | `MemberController::acceptAdmission` (route `members.accept-admission`), `acceptAdmissionBulk` (route `members.accept-admission-bulk`), metodo privato `approveOneMember($member, $sendEmail)`. Frontend: `Members/Show.vue` (checkbox invio email + Accogli domanda), `Members/Index.vue` (checkbox righe, barra bulk, checkbox invio email). |

---

## 10. File chiave

| Ruolo | Path |
|-------|------|
| Route web | `routes/web.php` |
| Installer | `app/Http/Controllers/InstallController.php`, `app/Http/Middleware/EnsureNotInstalled.php`, `resources/views/install/` |
| Sito pubblico | `app/Http/Controllers/PublicSiteController.php`, `app/Http/Controllers/PublicDownloadController.php` |
| Dati condivisi Inertia | `app/Http/Middleware/HandleInertiaRequests.php` |
| Middleware ruoli | `app/Http/Middleware/EnsureUserHasRole.php` |
| Seed ruoli | `database/seeders/RoleSeeder.php` |
| Layout e menu | `resources/js/Layouts/AppLayout.vue` |
| Entry frontend | `resources/js/app.js` |
| Build frontend | `vite.config.js` |
| Alias JS | `jsconfig.json` |
