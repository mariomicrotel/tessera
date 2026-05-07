<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Settings;
use App\Services\AttachmentService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Anagrafica tenant: 3 sezioni (Civilistico, Fiscale, Amministrativo) + allegati.
 */
class AnagraficaController extends Controller
{
    private const ALLEGATO_TAGS = [
        'statuto',
        'visura_camerale',
        'durc',
        'certificato_ateco',
        'bilancio_approvato',
        'polizza_volontari',
    ];

    public function __construct()
    {
        $this->middleware('role:admin');
    }

    public function index()
    {
        $tenant = app('current_tenant');

        $allegati = [];
        foreach (self::ALLEGATO_TAGS as $tag) {
            $att = Attachment::forSetting('anagrafica_' . $tag)->first();
            $allegati[$tag] = $att ? [
                'id'            => $att->id,
                'url'           => $att->url(),
                'original_name' => $att->original_name,
                'size'          => $att->size,
                'mime_type'     => $att->mime_type,
            ] : null;
        }

        return Inertia::render('Anagrafica/Index', [
            'tenant' => $tenant->only([
                'name', 'slug', 'forma_giuridica', 'organization_type',
                'dimensione_bilancio', 'regime_contabile', 'regime_iva',
                'codice_fiscale', 'partita_iva', 'attivita_ateco',
                'rea_numero', 'rea_citta',
                'indirizzo', 'cap', 'citta', 'provincia', 'nazione',
                'pec', 'telefono', 'sito_web',
                'personalita_giuridica', 'patrimonio_destinato',
                'numero_iscrizione_albo_coop',
                'runts_numero', 'runts_sezione', 'runts_data_iscrizione',
                'fascia_entrate',
                'assicurazione_volontari_polizza',
                'assicurazione_volontari_compagnia',
                'assicurazione_volontari_scadenza',
                'bilancio_url_pubblicazione',
            ]),
            'settings' => [
                'nome_associazione'                  => Settings::get('nome_associazione', ''),
                'email_associazione'                 => Settings::get('email_associazione', ''),
                'legale_rappresentante_associazione' => Settings::get('legale_rappresentante_associazione', ''),
                'data_costituzione_associazione'     => self::normDate(Settings::get('data_costituzione_associazione', '')),
                'ets_è_odv'                          => (bool) Settings::get('ets_è_odv', false),
                'luogo_emissione_ricevute'           => Settings::get('luogo_emissione_ricevute', ''),
                'periodicita_liquidazione_iva'       => Settings::get('periodicita_liquidazione_iva', 'mensile'),
            ],
            'labels' => [
                'forma_giuridica'    => $tenant->formaGiuridicaLabel(),
                'dimensione_bilancio'=> $tenant->dimensioneBilancioLabel(),
                'regime_contabile'   => $tenant->regimeContabileLabel(),
                'regime_iva'         => $tenant->regimeIvaLabel(),
            ],
            'allegati' => $allegati,
        ]);
    }

    public function update(Request $request)
    {
        $tenant = app('current_tenant');

        $validated = $request->validate([
            // Settings
            'nome_associazione'                  => 'nullable|string|max:255',
            'email_associazione'                 => 'nullable|email|max:255',
            'legale_rappresentante_associazione' => 'nullable|string|max:255',
            'data_costituzione_associazione'     => 'nullable|date',
            'ets_è_odv'                          => 'nullable|boolean',
            'luogo_emissione_ricevute'           => 'nullable|string|max:255',
            'periodicita_liquidazione_iva'       => 'nullable|string|in:mensile,trimestrale',
            // Tenant — Civilistico
            'codice_fiscale'                     => 'nullable|string|max:16',
            'partita_iva'                        => 'nullable|string|max:20',
            'rea_numero'                         => 'nullable|string|max:20',
            'rea_citta'                          => 'nullable|string|max:100',
            'indirizzo'                          => 'nullable|string|max:255',
            'cap'                                => 'nullable|string|max:10',
            'citta'                              => 'nullable|string|max:100',
            'provincia'                          => 'nullable|string|max:4',
            'nazione'                            => 'nullable|string|max:100',
            'personalita_giuridica'              => 'nullable|boolean',
            'patrimonio_destinato'               => 'nullable|numeric|min:0',
            'numero_iscrizione_albo_coop'        => 'nullable|string|max:50',
            // Tenant — Fiscale
            'attivita_ateco'                     => 'nullable|string|max:10',
            'runts_numero'                       => 'nullable|string|max:50',
            'runts_sezione'                      => 'nullable|string|max:10',
            'runts_data_iscrizione'              => 'nullable|date',
            'fascia_entrate'                     => 'nullable|string|in:sotto_60k,sotto_220k,sotto_1m,sopra_1m',
            // Tenant — Amministrativo
            'pec'                                => 'nullable|email|max:255',
            'telefono'                           => 'nullable|string|max:30',
            'sito_web'                           => 'nullable|url|max:255',
            'assicurazione_volontari_polizza'    => 'nullable|string|max:100',
            'assicurazione_volontari_compagnia'  => 'nullable|string|max:255',
            'assicurazione_volontari_scadenza'   => 'nullable|date',
            'bilancio_url_pubblicazione'         => 'nullable|url|max:500',
        ]);

        // --- Aggiorna Tenant model ---
        $tenantFields = [
            'codice_fiscale', 'partita_iva', 'rea_numero', 'rea_citta',
            'indirizzo', 'cap', 'citta', 'provincia', 'nazione',
            'personalita_giuridica', 'patrimonio_destinato', 'numero_iscrizione_albo_coop',
            'attivita_ateco', 'runts_numero', 'runts_sezione', 'runts_data_iscrizione',
            'fascia_entrate',
            'pec', 'telefono', 'sito_web',
            'assicurazione_volontari_polizza', 'assicurazione_volontari_compagnia',
            'assicurazione_volontari_scadenza', 'bilancio_url_pubblicazione',
        ];
        $tenant->update(array_intersect_key($validated, array_flip($tenantFields)));

        // --- Aggiorna Settings key-value ---
        Settings::set('nome_associazione', $validated['nome_associazione'] ?? '');
        Settings::set('email_associazione', $validated['email_associazione'] ?? '');
        Settings::set('legale_rappresentante_associazione', $validated['legale_rappresentante_associazione'] ?? '');
        Settings::set('data_costituzione_associazione', $validated['data_costituzione_associazione'] ?? '');
        Settings::set('ets_è_odv', ($validated['ets_è_odv'] ?? false) ? '1' : '0');
        Settings::set('luogo_emissione_ricevute', $validated['luogo_emissione_ricevute'] ?? '');
        Settings::set('periodicita_liquidazione_iva', $validated['periodicita_liquidazione_iva'] ?? 'mensile');
        // Mantiene sync con campi duplicati nelle impostazioni PDF
        Settings::set('codice_fiscale_associazione', $validated['codice_fiscale'] ?? '');
        Settings::set('partita_iva_associazione', $validated['partita_iva'] ?? '');
        Settings::set('pec_associazione', $validated['pec'] ?? '');
        Settings::set('indirizzo_associazione', $validated['indirizzo'] ?? '');

        return redirect()->route('anagrafica.index')
            ->with('flash', ['type' => 'success', 'message' => 'Anagrafica aggiornata.']);
    }

    public function uploadAllegato(Request $request, string $tipo, AttachmentService $attachmentService)
    {
        if (! in_array($tipo, self::ALLEGATO_TAGS, true)) {
            abort(404);
        }
        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,docx,doc|max:10240',
        ]);

        $tag = 'anagrafica_' . $tipo;
        Attachment::forSetting($tag)->each(fn (Attachment $a) => $a->delete());
        $attachmentService->storeForSetting($request->file('file'), $tag);

        return redirect()->route('anagrafica.index')
            ->with('flash', ['type' => 'success', 'message' => 'Documento caricato.']);
    }

    public function deleteAllegato(string $tipo)
    {
        if (! in_array($tipo, self::ALLEGATO_TAGS, true)) {
            abort(404);
        }
        Attachment::forSetting('anagrafica_' . $tipo)->each(fn (Attachment $a) => $a->delete());

        return redirect()->route('anagrafica.index')
            ->with('flash', ['type' => 'success', 'message' => 'Documento rimosso.']);
    }

    private static function normDate(string $value): string
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
