<?php

namespace App\Services;

use App\Models\ErogazioneLiberale;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Business logic per le Erogazioni Liberali ETS.
 *
 * Art. 83 D.Lgs. 117/2017 — Le erogazioni liberali in favore degli ETS
 * danno diritto a detrazioni/deduzioni in capo al donante, a condizione
 * che il pagamento avvenga tramite strumenti tracciabili.
 *
 * Funzioni principali:
 *  1. crea()          — registra una nuova erogazione liberale
 *  2. aggiorna()      — modifica dati di una erogazione
 *  3. riepilogoAnno() — statistiche per anno (per la comunicazione AdE)
 *  4. generaCsv()     — esporta in formato CSV compatibile AdE
 *  5. generaXml()     — esporta in formato XML per comunicazione telematica
 */
class ErogazioneLiberaleService
{
    // ─────────────────────────────────────────────────────────────────────
    // CRUD
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Registra una nuova erogazione liberale.
     *
     * Calcola automaticamente is_detraibile e aliquota_detrazione
     * in base a tipo donante e modalità di pagamento.
     */
    public function crea(Tenant $tenant, array $data): ErogazioneLiberale
    {
        $this->validaImporto($data['importo'] ?? 0);

        $modalita = $data['modalita_pagamento'] ?? 'bonifico';
        $tipo     = $data['donante_tipo'] ?? ErogazioneLiberale::TIPO_PERSONA_FISICA;

        $isDetraibile = in_array($modalita, ErogazioneLiberale::MODALITA_TRACCIABILI);
        $aliquota     = ErogazioneLiberale::calcolaAliquota($tipo, $modalita);

        return ErogazioneLiberale::create([
            ...$data,
            'tenant_id'          => $tenant->id,
            'is_detraibile'      => $isDetraibile,
            'aliquota_detrazione' => $aliquota,
        ]);
    }

    /**
     * Aggiorna una erogazione liberale.
     * Ricalcola detraibilità se cambia modalità o tipo donante.
     */
    public function aggiorna(ErogazioneLiberale $erogazione, array $data): ErogazioneLiberale
    {
        if (isset($data['importo'])) {
            $this->validaImporto($data['importo']);
        }

        $modalita = $data['modalita_pagamento'] ?? $erogazione->modalita_pagamento;
        $tipo     = $data['donante_tipo']       ?? $erogazione->donante_tipo;

        $data['is_detraibile']       = in_array($modalita, ErogazioneLiberale::MODALITA_TRACCIABILI);
        $data['aliquota_detrazione'] = ErogazioneLiberale::calcolaAliquota($tipo, $modalita);

        $erogazione->update($data);

        return $erogazione->fresh();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Riepilogo annuale
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Restituisce statistiche aggregate per anno.
     *
     * @return array{
     *   totale_importo: float,
     *   totale_detraibili: float,
     *   totale_non_detraibili: float,
     *   count: int,
     *   count_detraibili: int,
     *   per_tipo: array,
     *   per_modalita: array,
     *   per_donante: Collection,
     * }
     */
    public function riepilogoAnno(Tenant $tenant, int $anno): array
    {
        $base = ErogazioneLiberale::where('tenant_id', $tenant->id)
            ->where('anno', $anno);

        $totale          = (float) (clone $base)->sum('importo');
        $totDetraibili   = (float) (clone $base)->where('is_detraibile', true)->sum('importo');
        $totNoDetraibili = round($totale - $totDetraibili, 2);

        $count           = (clone $base)->count();
        $countDetraibili = (clone $base)->where('is_detraibile', true)->count();

        // Breakdown per tipo donante
        $perTipo = (clone $base)
            ->select('donante_tipo', DB::raw('COUNT(*) as n'), DB::raw('SUM(importo) as tot'))
            ->groupBy('donante_tipo')
            ->get()
            ->keyBy('donante_tipo')
            ->map(fn ($r) => ['n' => $r->n, 'tot' => (float) $r->tot])
            ->all();

        // Breakdown per modalità
        $perModalita = (clone $base)
            ->select('modalita_pagamento', DB::raw('COUNT(*) as n'), DB::raw('SUM(importo) as tot'))
            ->groupBy('modalita_pagamento')
            ->get()
            ->keyBy('modalita_pagamento')
            ->map(fn ($r) => ['n' => $r->n, 'tot' => (float) $r->tot])
            ->all();

        // Per donante (riepilogo CU-style)
        $perDonante = (clone $base)
            ->select(
                'donante_cf',
                'donante_tipo',
                'donante_cognome',
                'donante_nome',
                'donante_ragione_sociale',
                DB::raw('COUNT(*) as n_versamenti'),
                DB::raw('SUM(importo) as totale_versato'),
                DB::raw('SUM(CASE WHEN is_detraibile THEN importo ELSE 0 END) as totale_detraibile')
            )
            ->groupBy('donante_cf', 'donante_tipo', 'donante_cognome', 'donante_nome', 'donante_ragione_sociale')
            ->orderByDesc('totale_versato')
            ->get();

        return [
            'anno'                  => $anno,
            'totale_importo'        => round($totale, 2),
            'totale_detraibili'     => round($totDetraibili, 2),
            'totale_non_detraibili' => round($totNoDetraibili, 2),
            'count'                 => $count,
            'count_detraibili'      => $countDetraibili,
            'per_tipo'              => $perTipo,
            'per_modalita'          => $perModalita,
            'per_donante'           => $perDonante,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Export CSV
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Genera CSV per la comunicazione all'Agenzia delle Entrate.
     *
     * Formato conforme alle specifiche AdE per le erogazioni liberali ETS:
     * CF_ENTE; CF_DONANTE; COGNOME; NOME/RAGIONE_SOCIALE; IMPORTO; DATA; MODALITA; TIPO_DONANTE
     *
     * Sono incluse SOLO le erogazioni detraibili (strumento tracciabile).
     */
    public function generaCsv(Tenant $tenant, int $anno): string
    {
        $righe = ErogazioneLiberale::where('tenant_id', $tenant->id)
            ->where('anno', $anno)
            ->where('is_detraibile', true)
            ->orderBy('donante_cf')
            ->orderBy('data_erogazione')
            ->get();

        $cfEnte = $tenant->codice_fiscale ?? '';

        $lines   = [];
        $lines[] = implode(';', [
            'CF_ENTE_BENEFICIARIO',
            'CF_DONANTE',
            'COGNOME_DONANTE',
            'NOME_DONANTE',
            'RAGIONE_SOCIALE',
            'TIPO_DONANTE',
            'IMPORTO',
            'DATA_EROGAZIONE',
            'MODALITA_PAGAMENTO',
            'CODICE_MODALITA',
            'ALIQUOTA_DETRAZIONE',
        ]);

        foreach ($righe as $e) {
            $lines[] = implode(';', [
                $cfEnte,
                strtoupper($e->donante_cf),
                $e->donante_cognome ?? '',
                $e->donante_nome ?? '',
                $e->donante_ragione_sociale ?? '',
                $e->donante_tipo === ErogazioneLiberale::TIPO_PERSONA_FISICA ? 'PF' : 'PG',
                number_format((float) $e->importo, 2, '.', ''),
                $e->data_erogazione->format('d/m/Y'),
                ErogazioneLiberale::MODALITA[$e->modalita_pagamento] ?? $e->modalita_pagamento,
                $e->codiceModalitaAde(),
                ($e->aliquota_detrazione ?? 0) . '%',
            ]);
        }

        return implode("\r\n", $lines) . "\r\n";
    }

    // ─────────────────────────────────────────────────────────────────────
    // Export XML
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Genera XML per la comunicazione telematica all'AdE.
     *
     * Struttura conforme al tracciato per comunicazione erogazioni liberali
     * verso gli ETS (format semplificato, include solo righe detraibili).
     */
    public function generaXml(Tenant $tenant, int $anno): string
    {
        $righe = ErogazioneLiberale::where('tenant_id', $tenant->id)
            ->where('anno', $anno)
            ->where('is_detraibile', true)
            ->orderBy('donante_cf')
            ->get();

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElementNS(
            'urn:it:agenziaentrate:erogazioniliberali:1.0',
            'ErogazioniLiberali'
        );
        $dom->appendChild($root);

        // ── Testata ──
        $testata = $dom->createElement('Testata');
        $root->appendChild($testata);
        $testata->appendChild($dom->createElement('CfEnte',    $tenant->codice_fiscale ?? ''));
        $testata->appendChild($dom->createElement('AnnoRif',   (string) $anno));
        $testata->appendChild($dom->createElement('DataGen',   now()->format('d/m/Y')));
        $testata->appendChild($dom->createElement('TotErog',   (string) $righe->count()));
        $testata->appendChild($dom->createElement('TotImporto',
            number_format($righe->sum('importo'), 2, '.', '')));

        // ── Erogazioni ──
        $erogazioni = $dom->createElement('Erogazioni');
        $root->appendChild($erogazioni);

        foreach ($righe as $e) {
            $er = $dom->createElement('Erogazione');
            $erogazioni->appendChild($er);

            // Donante
            $donante = $dom->createElement('Donante');
            $er->appendChild($donante);
            $donante->appendChild($dom->createElement('CodiceFiscale', strtoupper($e->donante_cf)));
            $donante->appendChild($dom->createElement('Tipo',
                $e->donante_tipo === ErogazioneLiberale::TIPO_PERSONA_FISICA ? 'PF' : 'PG'));

            if ($e->donante_tipo === ErogazioneLiberale::TIPO_PERSONA_FISICA) {
                $donante->appendChild($dom->createElement('Cognome', htmlspecialchars($e->donante_cognome ?? '')));
                $donante->appendChild($dom->createElement('Nome',    htmlspecialchars($e->donante_nome ?? '')));
            } else {
                $donante->appendChild($dom->createElement('RagioneSociale',
                    htmlspecialchars($e->donante_ragione_sociale ?? '')));
                if ($e->donante_piva) {
                    $donante->appendChild($dom->createElement('PartitaIva', $e->donante_piva));
                }
            }

            if ($e->donante_comune) {
                $indirizzo = $dom->createElement('Indirizzo');
                $donante->appendChild($indirizzo);
                if ($e->donante_indirizzo) {
                    $indirizzo->appendChild($dom->createElement('Via', htmlspecialchars($e->donante_indirizzo)));
                }
                if ($e->donante_cap)      $indirizzo->appendChild($dom->createElement('Cap',      $e->donante_cap));
                if ($e->donante_comune)   $indirizzo->appendChild($dom->createElement('Comune',   htmlspecialchars($e->donante_comune)));
                if ($e->donante_provincia) $indirizzo->appendChild($dom->createElement('Provincia', strtoupper($e->donante_provincia)));
            }

            // Versamento
            $versamento = $dom->createElement('Versamento');
            $er->appendChild($versamento);
            $versamento->appendChild($dom->createElement('Importo',
                number_format((float) $e->importo, 2, '.', '')));
            $versamento->appendChild($dom->createElement('DataErogazione',
                $e->data_erogazione->format('d/m/Y')));
            $versamento->appendChild($dom->createElement('ModalitaPagamento',
                $e->codiceModalitaAde()));
            $versamento->appendChild($dom->createElement('AliquotaDetrazione',
                (string) ($e->aliquota_detrazione ?? 0)));
        }

        return $dom->saveXML();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Import da Incassi esistenti
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Importa le donazioni già presenti in `incassi` per un anno, creando
     * le corrispondenti ErogazioniLiberali (solo se non già importate).
     *
     * @return int numero di righe importate
     */
    public function importaDaIncassi(Tenant $tenant, int $anno): int
    {
        $incassi = \App\Models\Incasso::where('tenant_id', $tenant->id)
            ->where('type', \App\Models\Incasso::TYPE_DONAZIONE)
            ->whereYear('paid_at', $anno)
            ->whereNull('id') // overridden below
            ->orWhere(function ($q) use ($tenant, $anno) {
                $q->where('tenant_id', $tenant->id)
                  ->where('type', \App\Models\Incasso::TYPE_DONAZIONE)
                  ->whereYear('paid_at', $anno)
                  ->whereNotIn('id',
                      ErogazioneLiberale::where('tenant_id', $tenant->id)
                          ->where('anno', $anno)
                          ->whereNotNull('incasso_id')
                          ->pluck('incasso_id')
                  );
            })
            ->get();

        $importate = 0;

        foreach ($incassi as $incasso) {
            // Evita duplicati
            if (ErogazioneLiberale::where('incasso_id', $incasso->id)->exists()) {
                continue;
            }

            ErogazioneLiberale::create([
                'tenant_id'          => $tenant->id,
                'anno'               => $anno,
                'donante_tipo'       => ErogazioneLiberale::TIPO_PERSONA_FISICA,
                'donante_cf'         => '',   // Da completare manualmente
                'donante_nome'       => $incasso->donor_name ?? 'Da completare',
                'importo'            => $incasso->amount,
                'data_erogazione'    => $incasso->paid_at->format('Y-m-d'),
                'modalita_pagamento' => 'altro_tracciabile',
                'is_detraibile'      => true,
                'aliquota_detrazione' => ErogazioneLiberale::ALIQUOTA_PF,
                'incasso_id'         => $incasso->id,
                'note'               => 'Importato da incassi. Completare i dati fiscali.',
            ]);

            $importate++;
        }

        return $importate;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Private
    // ─────────────────────────────────────────────────────────────────────

    private function validaImporto(mixed $importo): void
    {
        if ((float) $importo <= 0) {
            throw new InvalidArgumentException('L\'importo deve essere maggiore di zero.');
        }
    }
}
