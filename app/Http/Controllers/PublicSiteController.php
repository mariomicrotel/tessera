<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Event;
use App\Models\PrimaNotaEntry;
use App\Models\Settings;
use App\Services\RendicontoCassaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;

/**
 * Sito pubblico: single-page dell'associazione.
 * Mostra sezioni configurabili da Impostazioni > Sito pubblico.
 * Gli URL firmati (logo, statuto, rendiconti) sono in cache per ridurre il carico.
 */
class PublicSiteController extends Controller
{
    /** Durata cache URL firmati (secondi). Gli URL sono validi 60 min, la cache 50 min. */
    private const SIGNED_URL_CACHE_TTL = 3000;

    /**
     * Sostituisce {{chiave}} nel template con il valore (escapato) da $variables.
     */
    private static function replaceTemplateVariables(string $template, array $variables): string
    {
        $result = $template;
        foreach ($variables as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $result = str_replace($placeholder, e((string) $value), $result);
        }
        return $result;
    }

    public function __invoke(Request $request)
    {
        $sections = Settings::get('site_sections', []);
        $sections = is_array($sections) ? $sections : [];

        $logoAttachment = Attachment::forSetting('logo')->first();
        $logoUrl = null;
        if ($logoAttachment) {
            $logoUrl = Cache::remember('public.logo.signed_url', self::SIGNED_URL_CACHE_TTL, function () use ($logoAttachment) {
                return URL::temporarySignedRoute(
                    'public.logo.show',
                    now()->addMinutes(60),
                    ['attachment' => $logoAttachment->id]
                );
            });
        }
        $nomeAssociazione = Settings::get('nome_associazione', config('app.name'));
        $indirizzoAssociazione = Settings::get('indirizzo_associazione', '');
        $codiceFiscale = Settings::get('codice_fiscale_associazione', '');
        $templateVariables = [
            'nome_associazione' => $nomeAssociazione,
            'indirizzo_associazione' => $indirizzoAssociazione,
            'codice_fiscale_associazione' => $codiceFiscale,
            'anno' => now()->format('Y'),
            'data_oggi' => now()->format('d/m/Y'),
        ];

        $siteHeroTitle = trim((string) Settings::get('site_hero_title', ''));
        $siteHeroSubtitle = trim((string) Settings::get('site_hero_subtitle', ''));
        $siteChiSiamoText = trim((string) Settings::get('site_chi_siamo_text', ''));
        $siteFooterText = trim((string) Settings::get('site_footer_text', ''));

        $allowedIds = config('site_sections.allowed_ids', []);
        $sectionStyles = [];
        foreach ($allowedIds as $sectionId) {
            $bgColor = trim((string) Settings::get('site_section_' . $sectionId . '_bg_color', ''));
            $textColor = trim((string) Settings::get('site_section_' . $sectionId . '_text_color', ''));
            $bgAttachment = Attachment::forSetting('section_bg_' . $sectionId)->first();
            $backgroundImageUrl = null;
            if ($bgAttachment) {
                $backgroundImageUrl = Cache::remember(
                    'public.section_bg.signed_url.' . $sectionId,
                    self::SIGNED_URL_CACHE_TTL,
                    function () use ($bgAttachment, $sectionId) {
                        return URL::temporarySignedRoute(
                            'public.section-background.show',
                            now()->addMinutes(60),
                            ['sectionId' => $sectionId, 'attachment' => $bgAttachment->id]
                        );
                    }
                );
            }
            $sectionStyles[$sectionId] = [
                'background_image_url' => $backgroundImageUrl,
                'bg_color' => $bgColor !== '' ? $bgColor : null,
                'text_color' => $textColor !== '' ? $textColor : null,
            ];
        }

        $site = [
            'nome_associazione' => $nomeAssociazione,
            'indirizzo_associazione' => $indirizzoAssociazione,
            'codice_fiscale_associazione' => $codiceFiscale,
            'logo_url' => $logoUrl,
            'hero_title' => $siteHeroTitle !== '' ? self::replaceTemplateVariables($siteHeroTitle, $templateVariables) : $nomeAssociazione,
            'hero_subtitle' => $siteHeroSubtitle !== '' ? self::replaceTemplateVariables($siteHeroSubtitle, $templateVariables) : '',
            'chi_siamo_text' => $siteChiSiamoText !== '' ? self::replaceTemplateVariables($siteChiSiamoText, $templateVariables) : '',
            'footer_text' => $siteFooterText !== '' ? self::replaceTemplateVariables($siteFooterText, $templateVariables) : '',
            'section_styles' => $sectionStyles,
        ];

        $events = [];
        if (in_array('eventi', $sections, true)) {
            $events = Event::query()
                ->where('start_at', '>=', now())
                ->orderBy('start_at')
                ->limit(10)
                ->get()
                ->map(fn (Event $e) => [
                    'id' => $e->id,
                    'title' => $e->title,
                    'start_at' => $e->start_at->toIso8601String(),
                    'end_at' => $e->end_at?->toIso8601String(),
                    'description' => $e->description,
                    'solo_soci' => $e->solo_soci,
                ]);
        }

        $rendicontiYears = [];
        $rendicontiUrls = [];
        if (in_array('rendiconti', $sections, true)) {
            $yearExpression = DB::connection()->getDriverName() === 'sqlite'
                ? "cast(strftime('%Y', date) as integer)"
                : 'YEAR(date)';
            $rendicontiYears = PrimaNotaEntry::query()
                ->selectRaw("{$yearExpression} as year")
                ->distinct()
                ->orderByDesc('year')
                ->pluck('year')
                ->values()
                ->toArray();
            foreach ($rendicontiYears as $year) {
                $rendicontiUrls[$year] = Cache::remember(
                    'public.rendiconto.signed_url.' . $year,
                    self::SIGNED_URL_CACHE_TTL,
                    function () use ($year) {
                        return URL::temporarySignedRoute(
                            'public.rendiconto.download',
                            now()->addMinutes(60),
                            ['anno' => $year]
                        );
                    }
                );
            }
        }

        $statutoUrl = null;
        if (in_array('statuto', $sections, true)) {
            $statutoAttachment = Attachment::forSetting('statuto')->first();
            if ($statutoAttachment) {
                $statutoUrl = Cache::remember(
                    'public.statuto.signed_url',
                    self::SIGNED_URL_CACHE_TTL,
                    function () use ($statutoAttachment) {
                        return URL::temporarySignedRoute(
                            'public.statuto.download',
                            now()->addMinutes(60),
                            ['attachment' => $statutoAttachment->id]
                        );
                    }
                );
            }
        }

        return Inertia::render('Public/Home', [
            'canLogin' => \Illuminate\Support\Facades\Route::has('login'),
            'canRegister' => \Illuminate\Support\Facades\Route::has('register'),
            'sections' => $sections,
            'site' => $site,
            'events' => $events,
            'rendiconti_years' => $rendicontiYears,
            'rendiconti_urls' => $rendicontiUrls,
            'statuto_url' => $statutoUrl,
        ]);
    }
}
