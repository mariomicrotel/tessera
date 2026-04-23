<?php

namespace App\Http\Controllers;

use App\Models\PrimaNotaEntry;
use App\Services\RendicontoCassaSchemaResolver;
use App\Services\RendicontoCassaService;
use App\Support\PdfLetterheadData;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rendiconto economico per cassa (Modello D). Visualizzazione e export PDF.
 */
class RendicontoCassaController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,contabile');
    }

    public function index(Request $request, RendicontoCassaService $service)
    {
        $anno = (int) $request->get('anno', now()->year);
        $anno = $anno >= 2000 && $anno <= 2100 ? $anno : (int) now()->year;

        $data = $service->buildRendiconto($anno);
        $annoPrecedente = $anno - 1;
        $annoPrecedenteHaMovimenti = false;
        $mapPrecedente = [];
        if ($annoPrecedente >= 2000 && $annoPrecedente <= 2100) {
            $annoPrecedenteHaMovimenti = PrimaNotaEntry::whereYear('date', $annoPrecedente)->exists();
            $dataPrecedente = $service->buildRendiconto($annoPrecedente);
            $mapPrecedente = $this->buildMapCodiceToImporto($dataPrecedente['sezioni'] ?? []);
        }
        $data['anno_precedente'] = $annoPrecedente;
        foreach ($data['sezioni'] ?? [] as $i => $sezione) {
            foreach ($sezione['voci'] ?? [] as $j => $voce) {
                $code = $voce['codice_voce'] ?? '';
                $data['sezioni'][$i]['voci'][$j]['importo_anno_precedente'] = round((float) ($mapPrecedente[$code] ?? 0), 2);
            }
        }

        return Inertia::render('Reports/RendicontoCassa', [
            'rendiconto' => $data,
            'anno' => $anno,
            'anno_precedente_ha_movimenti' => $annoPrecedenteHaMovimenti,
        ]);
    }

    /**
     * Export PDF del rendiconto per cassa (Modello D) – GET: dati da prima nota, senza modifica.
     */
    public function exportPdf(Request $request, RendicontoCassaService $service): Response
    {
        $anno = (int) $request->get('anno', now()->year);
        $anno = $anno >= 2000 && $anno <= 2100 ? $anno : (int) now()->year;

        $data = $service->buildRendiconto($anno);
        $data['data_generazione'] = now()->format('d/m/Y H:i');

        $annoPrecedente = $anno - 1;
        $mapPrecedente = [];
        if ($annoPrecedente >= 2000 && $annoPrecedente <= 2100) {
            $dataPrecedente = $service->buildRendiconto($annoPrecedente);
            $mapPrecedente = $this->buildMapCodiceToImporto($dataPrecedente['sezioni'] ?? []);
        }
        $data['anno_precedente'] = $annoPrecedente;

        foreach ($data['sezioni'] ?? [] as $i => $sezione) {
            foreach ($sezione['voci'] ?? [] as $j => $voce) {
                $code = $voce['codice_voce'] ?? '';
                $data['sezioni'][$i]['voci'][$j]['importo_anno_precedente'] = round((float) ($mapPrecedente[$code] ?? 0), 2);
            }
        }

        // Stesse sezioni e voci della pagina Vue (nessun filtro su importi zero)
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('rendiconto_cassa.template', [
            'rendiconto' => $data,
            'letterhead' => PdfLetterheadData::data(),
        ])->setOption('enable_php', true);

        $filename = 'rendiconto_cassa_' . $anno . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Export PDF del rendiconto da payload modificato (POST): sezioni con importi e data_generazione.
     */
    public function exportPdfFromPayload(Request $request, RendicontoCassaService $service): Response
    {
        $schema = RendicontoCassaSchemaResolver::class();
        $validCodes = $schema::getValidCodes();

        $rules = [
            'anno' => 'required|integer|min:2000|max:2100',
            'data_generazione' => 'nullable|string|max:20',
            'sezioni' => 'required|array',
            'sezioni.*.sezione' => 'required|string|max:255',
            'sezioni.*.voci' => 'required|array',
            'sezioni.*.voci.*.codice_voce' => 'required|string|in:' . implode(',', $validCodes),
            'sezioni.*.voci.*.tipo' => 'required|string|in:entrata,uscita',
            'sezioni.*.voci.*.importo' => 'required|numeric|min:0',
            'sezioni.*.voci.*.importo_anno_precedente' => 'nullable|numeric|min:0',
        ];
        $validated = $request->validate($rules);

        $anno = (int) $validated['anno'];
        $sezioniInput = $validated['sezioni'];

        $sezioni = [];
        $totaleEntrate = 0.0;
        $totaleUscite = 0.0;

        foreach ($sezioniInput as $s) {
            $macroName = $s['sezione'];
            $vociSezione = [];
            $totaleEntrateSez = 0.0;
            $totaleUsciteSez = 0.0;

            foreach ($s['voci'] as $v) {
                $code = $v['codice_voce'];
                $tipo = $v['tipo'];
                $importo = round((float) $v['importo'], 2);
                $info = $schema::getInfoByCode($code);
                $descrizione = $info ? ($info['name'] ?? $code) : $schema::getLabelForCode($code);
                $ministerialCode = $info ? ($info['ministerial_code'] ?? $code) : $code;
                if ($tipo === 'entrata') {
                    $totaleEntrateSez += $importo;
                    $totaleEntrate += $importo;
                } else {
                    $totaleUsciteSez += $importo;
                    $totaleUscite += $importo;
                }
                $importoAnnoPrec = array_key_exists('importo_anno_precedente', $v) && $v['importo_anno_precedente'] !== null && $v['importo_anno_precedente'] !== ''
                    ? round((float) $v['importo_anno_precedente'], 2)
                    : null;
                $vociSezione[] = [
                    'codice_voce' => $code,
                    'ministerial_code' => $ministerialCode,
                    'descrizione' => $descrizione,
                    'tipo' => $tipo,
                    'importo' => $importo,
                    'importo_anno_precedente' => $importoAnnoPrec,
                ];
            }

            $tipi = array_unique(array_column($vociSezione, 'tipo'));
            $tipoSezione = count($tipi) > 1 ? 'misto' : ($vociSezione[0]['tipo'] ?? 'entrata');
            $area = null;
            $macro = $schema::getMacroByName($macroName);
            if ($macro !== null) {
                $area = $macro['area'] ?? null;
                $section = $macro['section'] ?? '';
                $tipoSezione = $section === 'EXPENSES' ? 'uscita' : ($section === 'INCOME' ? 'entrata' : 'misto');
            }
            $sezioni[] = [
                'sezione' => $macroName,
                'voci' => $vociSezione,
                'totale_entrate' => round($totaleEntrateSez, 2),
                'totale_uscite' => round($totaleUsciteSez, 2),
                'tipo_sezione' => $tipoSezione,
                'area' => $area,
            ];
        }

        $risultatoPerCassa = round($totaleEntrate - $totaleUscite, 2);

        $dataGenerazione = now()->format('d/m/Y H:i');
        if (! empty($validated['data_generazione'])) {
            try {
                $parsed = Carbon::parse($validated['data_generazione']);
                $dataGenerazione = $parsed->format('d/m/Y H:i');
            } catch (\Throwable $e) {
                // mantieni default
            }
        }

        $annoPrecedente = $anno - 1;
        $mapPrecedente = [];
        if ($annoPrecedente >= 2000 && $annoPrecedente <= 2100) {
            $dataPrecedente = $service->buildRendiconto($annoPrecedente);
            $mapPrecedente = $this->buildMapCodiceToImporto($dataPrecedente['sezioni'] ?? []);
        }

        foreach ($sezioni as $i => $s) {
            foreach ($s['voci'] ?? [] as $j => $v) {
                $code = $v['codice_voce'] ?? '';
                $fromPayload = $v['importo_anno_precedente'] ?? null;
                $sezioni[$i]['voci'][$j]['importo_anno_precedente'] = $fromPayload !== null
                    ? $fromPayload
                    : round((float) ($mapPrecedente[$code] ?? 0), 2);
            }
        }

        // Stesse voci della modale Vue (nessun filtro su importi zero); totali già calcolati su tutte le voci
        $rendiconto = [
            'anno' => $anno,
            'anno_precedente' => $annoPrecedente,
            'sezioni' => $sezioni,
            'totale_entrate' => round($totaleEntrate, 2),
            'totale_uscite' => round($totaleUscite, 2),
            'risultato_per_cassa' => $risultatoPerCassa,
            'data_generazione' => $dataGenerazione,
            'conti_saldi' => $service->buildContiSaldi($anno),
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('rendiconto_cassa.template', [
            'rendiconto' => $rendiconto,
            'letterhead' => PdfLetterheadData::data(),
        ])->setOption('enable_php', true);

        $filename = 'rendiconto_cassa_' . $anno . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Mappa codice_voce => importo a partire dalle sezioni di un rendiconto.
     */
    private function buildMapCodiceToImporto(array $sezioni): array
    {
        $map = [];
        foreach ($sezioni as $sezione) {
            foreach ($sezione['voci'] ?? [] as $voce) {
                $code = $voce['codice_voce'] ?? '';
                if ($code !== '') {
                    $map[$code] = (float) ($voce['importo'] ?? 0);
                }
            }
        }
        return $map;
    }

    /**
     * Filtra voci con importo zero, rimuove sezioni vuote, ricalcola totali.
     * Restituisce: sezioni, totale_entrate, totale_uscite, risultato_per_cassa.
     */
    private function filterSezioniAndRecalcTotals(array $sezioni): array
    {
        $totaleEntrate = 0.0;
        $totaleUscite = 0.0;
        $sezioniFiltered = [];

        foreach ($sezioni as $sezione) {
            $vociFiltered = [];
            $totaleEntrateSez = 0.0;
            $totaleUsciteSez = 0.0;

            foreach ($sezione['voci'] ?? [] as $voce) {
                $importo = (float) ($voce['importo'] ?? 0);
                if (abs($importo) < 0.005) {
                    continue;
                }
                $voce['importo'] = round($importo, 2);
                $vociFiltered[] = $voce;
                if (($voce['tipo'] ?? '') === 'entrata') {
                    $totaleEntrateSez += $importo;
                    $totaleEntrate += $importo;
                } else {
                    $totaleUsciteSez += $importo;
                    $totaleUscite += $importo;
                }
            }

            if (count($vociFiltered) > 0) {
                $out = [
                    'sezione' => $sezione['sezione'] ?? '',
                    'voci' => $vociFiltered,
                    'totale_entrate' => round($totaleEntrateSez, 2),
                    'totale_uscite' => round($totaleUsciteSez, 2),
                ];
                if (array_key_exists('tipo_sezione', $sezione)) {
                    $out['tipo_sezione'] = $sezione['tipo_sezione'];
                }
                if (array_key_exists('area', $sezione)) {
                    $out['area'] = $sezione['area'];
                }
                $sezioniFiltered[] = $out;
            }
        }

        return [
            'sezioni' => $sezioniFiltered,
            'totale_entrate' => round($totaleEntrate, 2),
            'totale_uscite' => round($totaleUscite, 2),
            'risultato_per_cassa' => round($totaleEntrate - $totaleUscite, 2),
        ];
    }
}
