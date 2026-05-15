<?php

namespace App\Services;

use App\Models\CodiceIva;
use App\Models\FatturaPassiva;
use App\Models\RigaFatturaPassiva;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use SimpleXMLElement;

/**
 * Importa fatture passive in formato FatturaPA 1.3.2 (XML elettronico AdE).
 * Supporta sia file singoli che multipli (es. lotti SdI con più body).
 */
class FatturaXmlImportService
{
    /**
     * Analizza l'XML e restituisce un array di dati anteprima senza salvare.
     */
    public function preview(string $xmlContent): array
    {
        $xml = $this->parseXml($xmlContent);
        $results = [];

        foreach ($this->getBodies($xml) as $body) {
            $results[] = $this->extractFatturaData($xml, $body);
        }

        return $results;
    }

    /**
     * Importa l'XML creando le fatture passive. Restituisce il numero di fatture create.
     * Salta quelle già esistenti (stesso fornitore + numero_fattura + data_fattura).
     */
    public function import(string $xmlContent, bool $skipDuplicates = true): array
    {
        $xml = $this->parseXml($xmlContent);
        $created = [];
        $skipped = [];

        DB::transaction(function () use ($xml, $skipDuplicates, &$created, &$skipped) {
            foreach ($this->getBodies($xml) as $body) {
                $data = $this->extractFatturaData($xml, $body);

                // Cerca o crea il fornitore
                $supplier = $this->resolveSupplier($data['cedente']);

                // Controlla duplicati
                if ($skipDuplicates) {
                    $exists = FatturaPassiva::where('numero_fattura', $data['numero_fattura'])
                        ->where('data_fattura', $data['data_fattura'])
                        ->when($supplier, fn ($q) => $q->where('supplier_id', $supplier->id))
                        ->exists();

                    if ($exists) {
                        $skipped[] = $data['numero_fattura'];
                        continue;
                    }
                }

                $fattura = FatturaPassiva::create([
                    'supplier_id'        => $supplier?->id,
                    'numero_fattura'     => $data['numero_fattura'],
                    'data_fattura'       => $data['data_fattura'],
                    'data_ricezione'     => now()->toDateString(),
                    'data_registrazione' => now()->toDateString(),
                    'data_scadenza'      => $data['data_scadenza'],
                    'tipo_documento'     => $data['tipo_documento'],
                    'imponibile_totale'  => $data['imponibile_totale'],
                    'iva_totale'         => $data['iva_totale'],
                    'totale_documento'   => $data['totale_documento'],
                    'esigibilita'        => $data['esigibilita'],
                    'stato_pagamento'    => FatturaPassiva::STATO_DA_PAGARE,
                    'note'               => "Importata da XML AdE",
                ]);

                foreach ($data['righe'] as $riga) {
                    $codiceIva = $this->resolveCodiceIva($riga['aliquota_iva'], $riga['natura'] ?? null);
                    $imp = (float) $riga['imponibile'];
                    $aliq = $codiceIva ? (float) $codiceIva->percentuale : 0.0;
                    $iva = round($imp * $aliq / 100, 2);

                    RigaFatturaPassiva::create([
                        'fattura_passiva_id'      => $fattura->id,
                        'codice_iva_id'           => $codiceIva?->id,
                        'descrizione'             => $riga['descrizione'],
                        'quantita'                => $riga['quantita'],
                        'prezzo_unitario'         => $riga['prezzo_unitario'],
                        'imponibile'              => $imp,
                        'iva'                     => $iva,
                        'totale'                  => round($imp + $iva, 2),
                        'indetraibile_percentuale' => 0,
                        'iva_indetraibile'        => 0,
                    ]);
                }

                $created[] = $data['numero_fattura'];
            }
        });

        return ['created' => $created, 'skipped' => $skipped];
    }

    // ──────────────────────────────────────────────────────────────────────

    private function parseXml(string $content): SimpleXMLElement
    {
        libxml_use_internal_errors(true);
        // Rimuove namespace p: per semplificare l'accesso
        $content = preg_replace('/(<\/?)(\w+):/', '$1', $content);
        $xml = simplexml_load_string($content);
        if ($xml === false) {
            $errors = array_map(fn ($e) => $e->message, libxml_get_errors());
            throw new \InvalidArgumentException('XML non valido: ' . implode('; ', $errors));
        }
        return $xml;
    }

    private function getBodies(SimpleXMLElement $xml): array
    {
        // FatturaPA può avere più body (es. lotti)
        $bodies = $xml->FatturaElettronicaBody ?? [];
        if (count($bodies) === 0) {
            $bodies = [$xml->FatturaElettronicaBody];
        }
        return iterator_to_array($bodies, false);
    }

    private function extractFatturaData(SimpleXMLElement $xml, SimpleXMLElement $body): array
    {
        $header = $xml->FatturaElettronicaHeader ?? $xml;
        $cedente = $header->CedentePrestatore ?? null;
        $datiDoc = $body->DatiGenerali->DatiGeneraliDocumento ?? null;

        $tipoDoc  = (string) ($datiDoc->TipoDocumento ?? 'TD01');
        $numero   = (string) ($datiDoc->Numero ?? '');
        $data     = (string) ($datiDoc->Data ?? '');
        $totale   = (float)  ($datiDoc->ImportoTotaleDocumento ?? 0);

        // Scadenza da DatiPagamento
        $scadenza = null;
        $esigibilita = FatturaPassiva::ESIGIBILITA_IMMEDIATA;
        if (isset($body->DatiPagamento)) {
            foreach ($body->DatiPagamento as $pag) {
                $det = $pag->DettaglioPagamento ?? null;
                if ($det && isset($det->DataScadenzaPagamento)) {
                    $scadenza = (string) $det->DataScadenzaPagamento;
                }
                if ($det && isset($det->ModalitaPagamento)) {
                    // MP12 = Riba
                }
            }
        }

        // Esigibilità IVA da DatiRiepilogo
        if (isset($body->DatiBeniServizi->DatiRiepilogo)) {
            $riepilogo = $body->DatiBeniServizi->DatiRiepilogo;
            $esIva = (string) ($riepilogo->EsigibilitaIVA ?? '');
            if ($esIva === 'D') $esigibilita = FatturaPassiva::ESIGIBILITA_DIFFERITA;
            if ($esIva === 'S') $esigibilita = FatturaPassiva::ESIGIBILITA_SPLIT_PAYMENT;
        }

        // Righe (linee)
        $righe = [];
        $impTot = 0.0;
        $ivaTot = 0.0;

        if (isset($body->DatiBeniServizi->DettaglioLinee)) {
            foreach ($body->DatiBeniServizi->DettaglioLinee as $linea) {
                $imp = (float) ($linea->PrezzoTotale ?? 0);
                $aliq = (float) ($linea->AliquotaIVA ?? 0);
                $natura = (string) ($linea->Natura ?? '');
                $iva = round($imp * $aliq / 100, 2);
                $impTot += $imp;
                $ivaTot += $iva;
                $righe[] = [
                    'descrizione'    => (string) ($linea->Descrizione ?? ''),
                    'quantita'       => (float)  ($linea->Quantita ?? 1),
                    'prezzo_unitario' => (float)  ($linea->PrezzoUnitario ?? 0),
                    'imponibile'     => round($imp, 2),
                    'aliquota_iva'   => $aliq,
                    'natura'         => $natura ?: null,
                ];
            }
        }

        // Se non ci sono righe, crea una riga riassuntiva da DatiRiepilogo
        if (empty($righe) && isset($body->DatiBeniServizi->DatiRiepilogo)) {
            foreach ($body->DatiBeniServizi->DatiRiepilogo as $riepilogo) {
                $imp  = (float) ($riepilogo->ImponibileImporto ?? 0);
                $iva  = (float) ($riepilogo->Imposta ?? 0);
                $aliq = (float) ($riepilogo->AliquotaIVA ?? 0);
                $natura = (string) ($riepilogo->Natura ?? '');
                $impTot += $imp;
                $ivaTot += $iva;
                $righe[] = [
                    'descrizione'    => 'Importato da XML AdE',
                    'quantita'       => 1,
                    'prezzo_unitario' => $imp,
                    'imponibile'     => round($imp, 2),
                    'aliquota_iva'   => $aliq,
                    'natura'         => $natura ?: null,
                ];
            }
        }

        // Cedente anagrafici
        $datiAnag = $cedente->DatiAnagrafici ?? null;
        $anag     = $datiAnag->Anagrafica    ?? null;
        $piva     = (string) ($datiAnag->IdFiscaleIVA->IdCodice ?? '');
        $cf       = (string) ($datiAnag->CodiceFiscale ?? '');
        $nome     = (string) ($anag->Denominazione ?? '') ?: trim(
            ((string) ($anag->Nome ?? '')) . ' ' . ((string) ($anag->Cognome ?? ''))
        );
        $sede     = $cedente->Sede ?? null;
        $indirizzo = trim(
            ((string) ($sede->Indirizzo ?? '')) . ' ' .
            ((string) ($sede->NumeroCivico ?? ''))
        );
        $cap     = (string) ($sede->CAP          ?? '');
        $comune  = (string) ($sede->Comune       ?? '');
        $nazione = (string) ($sede->Nazione      ?? 'IT');

        return [
            'numero_fattura'    => $numero,
            'data_fattura'      => $data,
            'data_scadenza'     => $scadenza,
            'tipo_documento'    => $tipoDoc,
            'imponibile_totale' => round($impTot, 2),
            'iva_totale'        => round($ivaTot, 2),
            'totale_documento'  => $totale ?: round($impTot + $ivaTot, 2),
            'esigibilita'       => $esigibilita,
            'righe'             => $righe,
            'cedente'           => [
                'nome'       => $nome,
                'piva'       => $piva,
                'cf'         => $cf,
                'indirizzo'  => $indirizzo,
                'cap'        => $cap,
                'comune'     => $comune,
                'nazione'    => $nazione,
            ],
        ];
    }

    private function resolveSupplier(array $cedente): ?Supplier
    {
        // Cerca per P.IVA
        if ($cedente['piva']) {
            $s = Supplier::where('partita_iva', $cedente['piva'])->first();
            if ($s) return $s;
        }
        // Cerca per CF
        if ($cedente['cf']) {
            $s = Supplier::where('codice_fiscale', $cedente['cf'])->first();
            if ($s) return $s;
        }
        // Crea nuovo fornitore
        if ($cedente['nome']) {
            return Supplier::create([
                'name'            => $cedente['nome'],
                'ragione_sociale' => $cedente['nome'],
                'partita_iva'     => $cedente['piva'] ?: null,
                'codice_fiscale'  => $cedente['cf']   ?: null,
                'indirizzo'       => $cedente['indirizzo'] ?: null,
                'cap'             => $cedente['cap']       ?: null,
                'citta'           => $cedente['comune']    ?: null,
                'nazione'         => $cedente['nazione']   ?: 'IT',
                'attivo'          => true,
            ]);
        }
        return null;
    }

    private function resolveCodiceIva(float $aliquota, ?string $natura): ?CodiceIva
    {
        // Cerca per percentuale esatta
        $codice = CodiceIva::where('percentuale', $aliquota)->first();
        if ($codice) return $codice;

        // Se natura presente (N1-N7) cerca per natura SDI
        if ($natura) {
            $codice = CodiceIva::where('natura_sdi', $natura)->first();
            if ($codice) return $codice;
        }

        return null;
    }
}
