<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Settings;
use App\Services\AttachmentService;
use Carbon\Carbon;
use App\Support\PdfLetterheadData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

/**
 * Pagina impostazioni (quota, dati associazione, causali). Solo admin.
 */
class SettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin');
    }

    public function index()
    {
        $logoAttachment = Attachment::forSetting('logo')->first();
        $logo = $logoAttachment ? [
            'id' => $logoAttachment->id,
            'url' => $logoAttachment->url(),
            'original_name' => $logoAttachment->original_name,
        ] : null;

        $smtp = config('mail.mailers.smtp', []);

        $siteSections = Settings::get('site_sections', []);
        $allowedIds = config('site_sections.allowed_ids', []);
        $sectionStyles = [];
        foreach ($allowedIds as $sectionId) {
            $bgAttachment = Attachment::forSetting('section_bg_' . $sectionId)->first();
            $sectionStyles[$sectionId] = [
                'bg_color' => Settings::get('site_section_' . $sectionId . '_bg_color', ''),
                'text_color' => Settings::get('site_section_' . $sectionId . '_text_color', ''),
                'background_image' => $bgAttachment ? [
                    'id' => $bgAttachment->id,
                    'url' => $bgAttachment->url(),
                    'original_name' => $bgAttachment->original_name,
                ] : null,
            ];
        }

        $tenant = app()->bound('current_tenant') ? app('current_tenant') : null;

        return Inertia::render('Settings/Index', [
            'quota_annuale'  => Settings::get('quota_annuale', 0),
            'quota_mensile'  => Settings::get('quota_mensile', 0),
            'quota_ingresso' => Settings::get('quota_ingresso', 0),
            // Impostazioni cooperative (passate sempre, il tab è nascosto se non cooperativa)
            'quota_valore_unitario_coop'      => Settings::get('quota_valore_unitario_coop', 50.00),
            'quota_minima_quote_coop'         => (int) Settings::get('quota_minima_quote_coop', 1),
            'ristorno_percentuale_max'        => Settings::get('ristorno_percentuale_max', 100),
            'riserva_legale_percentuale'      => Settings::get('riserva_legale_percentuale', 30),
            'riserva_indivisibile_percentuale' => Settings::get('riserva_indivisibile_percentuale', 3),
            'tasso_interesse_prestito'        => Settings::get('tasso_interesse_prestito', 0),
            'is_cooperativa'                  => $tenant?->isCooperativa() ?? false,
            'nome_associazione' => Settings::get('nome_associazione', ''),
            'site_sections' => $siteSections,
            'site_sections_list' => config('site_sections.sections', []),
            'section_styles' => $sectionStyles,
            'site_hero_title' => Settings::get('site_hero_title', ''),
            'site_hero_subtitle' => Settings::get('site_hero_subtitle', ''),
            'site_chi_siamo_text' => Settings::get('site_chi_siamo_text', ''),
            'site_footer_text' => Settings::get('site_footer_text', ''),
            'indirizzo_associazione' => Settings::get('indirizzo_associazione', ''),
            'email_associazione' => Settings::get('email_associazione', ''),
            'pec_associazione' => Settings::get('pec_associazione', ''),
            'codice_fiscale_associazione' => Settings::get('codice_fiscale_associazione', ''),
            'partita_iva_associazione' => Settings::get('partita_iva_associazione', ''),
            'legale_rappresentante_associazione' => Settings::get('legale_rappresentante_associazione', ''),
            'data_costituzione_associazione' => self::normalizeDateForInput(Settings::get('data_costituzione_associazione', '')),
            'data_iscrizione_runts' => self::normalizeDateForInput(Settings::get('data_iscrizione_runts', '')),
            'ets_è_odv' => (bool) Settings::get('ets_è_odv', false),
            'luogo_emissione_ricevute' => Settings::get('luogo_emissione_ricevute', ''),
            'causale_default_donazione' => Settings::get('causale_default_donazione', 'Erogazione liberale'),
            'causale_default_quota' => Settings::get('causale_default_quota', 'Quota associativa'),
            'causale_default_rimborso' => Settings::get('causale_default_rimborso', 'Rimborso spese'),
            'informativa_privacy_domanda_ammissione' => Settings::get('informativa_privacy_domanda_ammissione', ''),
            'logo' => $logo,
            'mailConfig' => [
                'mailer' => config('mail.default'),
                'host' => $smtp['host'] ?? null,
                'port' => $smtp['port'] ?? null,
                'username' => $smtp['username'] ?? null,
                'encryption' => $smtp['encryption'] ?? $smtp['scheme'] ?? null,
                'from_address' => config('mail.from.address'),
                'from_name' => config('mail.from.name'),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $tenant = app()->bound('current_tenant') ? app('current_tenant') : null;
        $allowedSections = config('site_sections.allowed_ids', []);
        $rules = [
            'quota_annuale'  => 'required|numeric|min:0',
            'quota_mensile'  => 'nullable|numeric|min:0',
            'quota_ingresso' => 'nullable|numeric|min:0',
            'nome_associazione' => 'nullable|string|max:255',
            'indirizzo_associazione' => 'nullable|string|max:255',
            'email_associazione' => 'nullable|email|max:255',
            'pec_associazione' => 'nullable|email|max:255',
            'codice_fiscale_associazione' => 'nullable|string|max:16',
            'partita_iva_associazione' => 'nullable|string|max:20',
            'legale_rappresentante_associazione' => 'nullable|string|max:255',
            'data_costituzione_associazione' => 'nullable|date',
            'data_iscrizione_runts' => 'nullable|string|max:20',
            'ets_è_odv' => 'nullable|boolean',
            'luogo_emissione_ricevute' => 'nullable|string|max:255',
            'causale_default_donazione' => 'nullable|string|max:255',
            'causale_default_quota' => 'nullable|string|max:255',
            'causale_default_rimborso' => 'nullable|string|max:255',
            'site_sections' => 'nullable|array',
            'site_sections.*' => 'string|in:' . implode(',', $allowedSections),
            'site_hero_title' => 'nullable|string|max:500',
            'site_hero_subtitle' => 'nullable|string|max:2000',
            'site_chi_siamo_text' => 'nullable|string|max:5000',
            'site_footer_text' => 'nullable|string|max:2000',
            'informativa_privacy_domanda_ammissione' => 'nullable|string|max:10000',
        ];
        if ($tenant?->isCooperativa()) {
            $rules['quota_valore_unitario_coop']       = 'nullable|numeric|min:0.01';
            $rules['quota_minima_quote_coop']          = 'nullable|integer|min:1';
            $rules['ristorno_percentuale_max']         = 'nullable|numeric|min:0|max:100';
            $rules['riserva_legale_percentuale']       = 'nullable|numeric|min:0|max:100';
            $rules['riserva_indivisibile_percentuale'] = 'nullable|numeric|min:0|max:100';
            $rules['tasso_interesse_prestito']         = 'nullable|numeric|min:0|max:100';
        }
        foreach ($allowedSections as $sectionId) {
            $rules['site_section_' . $sectionId . '_bg_color'] = 'nullable|string|max:20';
            $rules['site_section_' . $sectionId . '_text_color'] = 'nullable|string|max:20';
        }
        $request->validate($rules);

        Settings::set('quota_annuale',  $request->input('quota_annuale'));
        Settings::set('quota_mensile',  $request->input('quota_mensile',  0));
        Settings::set('quota_ingresso', $request->input('quota_ingresso', 0));
        Settings::set('nome_associazione', $request->input('nome_associazione', ''));
        Settings::set('indirizzo_associazione', $request->input('indirizzo_associazione', ''));
        Settings::set('email_associazione', $request->input('email_associazione', ''));
        Settings::set('pec_associazione', $request->input('pec_associazione', ''));
        Settings::set('codice_fiscale_associazione', $request->input('codice_fiscale_associazione', ''));
        Settings::set('partita_iva_associazione', $request->input('partita_iva_associazione', ''));
        Settings::set('legale_rappresentante_associazione', $request->input('legale_rappresentante_associazione', ''));
        Settings::set('data_costituzione_associazione', $request->input('data_costituzione_associazione', ''));
        Settings::set('data_iscrizione_runts', $request->input('data_iscrizione_runts', ''));
        Settings::set('ets_è_odv', $request->boolean('ets_è_odv') ? '1' : '0');
        Settings::set('luogo_emissione_ricevute', $request->input('luogo_emissione_ricevute', ''));
        Settings::set('causale_default_donazione', $request->input('causale_default_donazione', ''));
        Settings::set('causale_default_quota', $request->input('causale_default_quota', ''));
        Settings::set('causale_default_rimborso', $request->input('causale_default_rimborso', ''));
        $siteSections = $request->input('site_sections', []);
        Settings::set('site_sections', json_encode(array_values(array_unique($siteSections))));
        Settings::set('site_hero_title', $request->input('site_hero_title', ''));
        Settings::set('site_hero_subtitle', $request->input('site_hero_subtitle', ''));
        Settings::set('site_chi_siamo_text', $request->input('site_chi_siamo_text', ''));
        Settings::set('site_footer_text', $request->input('site_footer_text', ''));
        Settings::set('informativa_privacy_domanda_ammissione', $request->input('informativa_privacy_domanda_ammissione', ''));
        if ($tenant?->isCooperativa()) {
            Settings::set('quota_valore_unitario_coop',       $request->input('quota_valore_unitario_coop', 50));
            Settings::set('quota_minima_quote_coop',          $request->input('quota_minima_quote_coop', 1));
            Settings::set('ristorno_percentuale_max',         $request->input('ristorno_percentuale_max', 100));
            Settings::set('riserva_legale_percentuale',       $request->input('riserva_legale_percentuale', 30));
            Settings::set('riserva_indivisibile_percentuale', $request->input('riserva_indivisibile_percentuale', 3));
            Settings::set('tasso_interesse_prestito',         $request->input('tasso_interesse_prestito', 0));
        }
        foreach ($allowedSections as $sectionId) {
            Settings::set('site_section_' . $sectionId . '_bg_color', $request->input('site_section_' . $sectionId . '_bg_color', ''));
            Settings::set('site_section_' . $sectionId . '_text_color', $request->input('site_section_' . $sectionId . '_text_color', ''));
        }

        return redirect()->route('settings.index')->with('flash', ['type' => 'success', 'message' => 'Impostazioni aggiornate.']);
    }

    /**
     * Upload logo associazione (sostituisce il precedente se presente).
     */
    public function uploadLogo(Request $request, AttachmentService $attachmentService)
    {
        $request->validate([
            'logo' => 'required|file|image|max:2048',
        ]);

        Attachment::forSetting('logo')->each(fn (Attachment $a) => $a->delete());
        $attachmentService->storeForSetting($request->file('logo'), 'logo');
        Cache::forget('public.logo.signed_url');

        return redirect()->route('settings.index')->with('flash', ['type' => 'success', 'message' => 'Logo caricato.']);
    }

    /**
     * Rimuove il logo associazione.
     */
    public function deleteLogo()
    {
        Attachment::forSetting('logo')->each(fn (Attachment $a) => $a->delete());
        Cache::forget('public.logo.signed_url');
        return redirect()->route('settings.index')->with('flash', ['type' => 'success', 'message' => 'Logo rimosso.']);
    }

    /**
     * Upload immagine di sfondo per una sezione del sito pubblico (sostituisce la precedente se presente).
     */
    public function uploadSectionBackground(Request $request, string $sectionId, AttachmentService $attachmentService)
    {
        $allowedIds = config('site_sections.allowed_ids', []);
        if (! in_array($sectionId, $allowedIds, true)) {
            abort(404, 'Sezione non valida.');
        }
        $request->validate([
            'background' => 'required|file|image|max:5120',
        ]);

        Attachment::forSetting('section_bg_' . $sectionId)->each(fn (Attachment $a) => $a->delete());
        $attachmentService->storeForSetting($request->file('background'), 'section_bg_' . $sectionId);
        Cache::forget('public.section_bg.signed_url.' . $sectionId);

        return redirect()->route('settings.index')->with('flash', ['type' => 'success', 'message' => 'Immagine di sfondo caricata.']);
    }

    /**
     * Rimuove l'immagine di sfondo di una sezione del sito pubblico.
     */
    public function deleteSectionBackground(string $sectionId)
    {
        $allowedIds = config('site_sections.allowed_ids', []);
        if (! in_array($sectionId, $allowedIds, true)) {
            abort(404, 'Sezione non valida.');
        }
        Attachment::forSetting('section_bg_' . $sectionId)->each(fn (Attachment $a) => $a->delete());
        Cache::forget('public.section_bg.signed_url.' . $sectionId);

        return redirect()->route('settings.index')->with('flash', ['type' => 'success', 'message' => 'Immagine di sfondo rimossa.']);
    }

    /**
     * Invia un'email di test al destinatario indicato o all'utente loggato (per verificare la configurazione SMTP).
     */
    public function sendTestEmail(Request $request)
    {
        $request->validate([
            'email' => 'nullable|email',
        ]);

        $user = $request->user();
        $email = $request->filled('email') ? $request->input('email') : $user->email;

        if (empty($email)) {
            return redirect()->route('settings.index')
                ->with('flash', ['type' => 'error', 'message' => 'Indica un destinatario o assicurati che il tuo account abbia un indirizzo email.']);
        }

        $appName = Settings::get('nome_associazione', config('app.name'));

        try {
            Mail::raw(
                "Questa è un'email di test inviata da " . $appName . ".\n\nSe la ricevi, la configurazione dell'invio email è corretta.\n\nData e ora: " . now()->format('d/m/Y H:i:s'),
                function ($message) use ($email, $appName) {
                    $message->to($email)
                        ->subject('[' . $appName . '] Email di test');
                }
            );

            return redirect()->route('settings.index')
                ->with('flash', ['type' => 'success', 'message' => "Email di test inviata a {$email}. Controlla la casella (e lo spam)."]);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('settings.index')
                ->with('flash', ['type' => 'error', 'message' => 'Errore nell\'invio: ' . $e->getMessage()]);
        }
    }

    /**
     * Anteprima PDF della carta intestata (dati associazione).
     */
    public function letterheadPreview()
    {
        $letterheadData = PdfLetterheadData::data();
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.letterhead-preview', [
            'letterheadData' => $letterheadData,
        ]);
        return $pdf->stream('anteprima-carta-intestata.pdf');
    }

    /**
     * Normalizza una data per l'input HTML type="date" (YYYY-MM-DD).
     */
    private static function normalizeDateForInput(string $value): string
    {
        if (trim($value) === '') {
            return '';
        }
        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return $value;
        }
    }
}
