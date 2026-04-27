<?php

namespace App\Services;

use App\Models\FatturaAttiva;
use App\Models\Member;
use App\Models\Tenant;
use DOMDocument;
use DOMElement;

/**
 * Generatore di Fattura Elettronica in formato FatturaPA 1.3.2 (SDI).
 *
 * Crea un file XML valido per il Sistema di Interscambio (AdE).
 * Supporta: TD01 (fattura), TD04 (nota di credito), TD07 (fattura semplificata).
 *
 * Riferimento: https://www.fatturapa.gov.it/it/norme-e-regole/documentazione-sk/
 *
 * Il file viene salvato in storage/app/private/fatture-xml/{tenant}/{filename}.xml
 * e il path relativo viene aggiornato su FatturaAttiva::xml_sdi_path.
 */
class FatturaXmlService
{
    private const NS_FATTURA = 'http://ivaservizi.agenziaentrate.gov.it/docs/xsd/fatture/v1.2';
    private const NS_DS      = 'http://www.w3.org/2000/09/xmldsig#';
    private const NS_XSD     = 'http://www.w3.org/2001/XMLSchema';
    private const NS_XSI     = 'http://www.w3.org/2001/XMLSchema-instance';
    private const VERSIONE    = 'FPR12'; // Fattura Privati / Autonomi

    // ─────────────────────────────────────────────────────────────────────
    // Punto di ingresso pubblico
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Genera l'XML FatturaPA per una FatturaAttiva e salva il file.
     *
     * @return string  Path relativo al file (storage/app/private/...)
     */
    public function genera(FatturaAttiva $fattura): string
    {
        $tenant = Tenant::findOrFail($fattura->tenant_id);
        $cliente = $fattura->cliente_id ? Member::find($fattura->cliente_id) : null;

        $dom = $this->buildDocument($fattura, $tenant, $cliente);

        $xml      = $dom->saveXML();
        $filename = $this->nomeFile($tenant, $fattura);
        $path     = "fatture-xml/{$tenant->slug}/{$filename}";

        \Illuminate\Support\Facades\Storage::disk('private')->put($path, $xml);

        $fattura->update(['xml_sdi_path' => $path]);

        return $path;
    }

    /**
     * Restituisce l'XML come stringa senza salvare su disco.
     * Utile per preview e test.
     */
    public function generaStringa(FatturaAttiva $fattura): string
    {
        $tenant  = Tenant::findOrFail($fattura->tenant_id);
        $cliente = $fattura->cliente_id ? Member::find($fattura->cliente_id) : null;

        return $this->buildDocument($fattura, $tenant, $cliente)->saveXML();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Build DOM
    // ─────────────────────────────────────────────────────────────────────

    private function buildDocument(FatturaAttiva $fattura, Tenant $tenant, ?Member $cliente): DOMDocument
    {
        $fattura->loadMissing('righe.codiceIva');

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        /** @var DOMElement $root */
        $root = $dom->createElementNS(self::NS_FATTURA, 'p:FatturaElettronica');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:p',   self::NS_FATTURA);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ds',  self::NS_DS);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsd', self::NS_XSD);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', self::NS_XSI);
        $root->setAttribute('versione', self::VERSIONE);
        $dom->appendChild($root);

        // 1. Intestazione
        $root->appendChild($this->buildIntestazione($dom, $fattura, $tenant));

        // 2. Corpo (può essere multiplo per fatture multiple — qui 1 sola)
        $root->appendChild($this->buildCorpoFattura($dom, $fattura, $tenant, $cliente));

        return $dom;
    }

    // ─────────────────────────────────────────────────────────────────────
    // FatturaElettronicaHeader
    // ─────────────────────────────────────────────────────────────────────

    private function buildIntestazione(DOMDocument $dom, FatturaAttiva $fattura, Tenant $tenant): DOMElement
    {
        $header = $dom->createElement('FatturaElettronicaHeader');

        // DatiTrasmissione
        $dt = $dom->createElement('DatiTrasmissione');
        $id = $dom->createElement('IdTrasmittente');
        $this->addEl($dom, $id, 'IdPaese', 'IT');
        $this->addEl($dom, $id, 'IdCodice', $this->normalizzaCf($tenant->codice_fiscale ?? $tenant->partita_iva ?? '00000000000'));
        $dt->appendChild($id);
        $this->addEl($dom, $dt, 'ProgressivoInvio', str_pad((string) $fattura->progressivo, 5, '0', STR_PAD_LEFT));
        $this->addEl($dom, $dt, 'FormatoTrasmissione', self::VERSIONE);
        $this->addEl($dom, $dt, 'CodiceDestinatario', '0000000'); // Codice SDI generico (B2B/B2C)
        $header->appendChild($dt);

        // CedentePrestatore (il tenant — emittente)
        $cp = $dom->createElement('CedentePrestatore');
        $dd = $dom->createElement('DatiAnagrafici');
        $idFiscale = $dom->createElement('IdFiscaleIVA');
        $this->addEl($dom, $idFiscale, 'IdPaese', 'IT');
        $this->addEl($dom, $idFiscale, 'IdCodice', $this->pIva($tenant));
        $dd->appendChild($idFiscale);
        if ($tenant->codice_fiscale) {
            $this->addEl($dom, $dd, 'CodiceFiscale', $this->normalizzaCf($tenant->codice_fiscale));
        }
        $anagrafica = $dom->createElement('Anagrafica');
        $this->addEl($dom, $anagrafica, 'Denominazione', $this->xml($tenant->name));
        $dd->appendChild($anagrafica);
        $this->addEl($dom, $dd, 'RegimeFiscale', 'RF19'); // Altro (ETS/Coop non profit)
        $cp->appendChild($dd);

        $sede = $dom->createElement('Sede');
        $tenantSettings = $tenant->settings ?? [];
        $this->addEl($dom, $sede, 'Indirizzo',   $this->xml($tenantSettings['indirizzo'] ?? 'Via Sede 1'));
        $this->addEl($dom, $sede, 'CAP',         $tenantSettings['cap'] ?? '00000');
        $this->addEl($dom, $sede, 'Comune',      $this->xml($tenantSettings['comune'] ?? 'Roma'));
        $this->addEl($dom, $sede, 'Nazione',     'IT');
        $cp->appendChild($sede);
        $header->appendChild($cp);

        // CessionarioCommittente (cliente)
        $cc = $dom->createElement('CessionarioCommittente');
        $dda = $dom->createElement('DatiAnagrafici');
        $anag = $dom->createElement('Anagrafica');

        if ($fattura->cliente_id && ($cliente = Member::find($fattura->cliente_id))) {
            if ($cliente->tipo_persona === 'giuridica' && $cliente->ragione_sociale) {
                $this->addEl($dom, $anag, 'Denominazione', $this->xml($cliente->ragione_sociale));
            } else {
                $this->addEl($dom, $anag, 'Nome',    $this->xml($cliente->nome    ?? ''));
                $this->addEl($dom, $anag, 'Cognome', $this->xml($cliente->cognome ?? ''));
            }
            if ($cliente->codice_fiscale) {
                $this->addEl($dom, $dda, 'CodiceFiscale', $this->normalizzaCf($cliente->codice_fiscale));
            }
            if ($cliente->partita_iva) {
                $idFisc = $dom->createElement('IdFiscaleIVA');
                $this->addEl($dom, $idFisc, 'IdPaese', 'IT');
                $this->addEl($dom, $idFisc, 'IdCodice', $cliente->partita_iva);
                $dda->insertBefore($idFisc, $dda->firstChild);
            }
        } else {
            $this->addEl($dom, $anag, 'Denominazione', 'CLIENTE VARIO');
        }
        $dda->appendChild($anag);
        $cc->appendChild($dda);

        $sedec = $dom->createElement('Sede');
        $this->addEl($dom, $sedec, 'Indirizzo', 'Via Generica 1');
        $this->addEl($dom, $sedec, 'CAP',       '00000');
        $this->addEl($dom, $sedec, 'Comune',    'Roma');
        $this->addEl($dom, $sedec, 'Nazione',   'IT');
        $cc->appendChild($sedec);
        $header->appendChild($cc);

        return $header;
    }

    // ─────────────────────────────────────────────────────────────────────
    // FatturaElettronicaBody
    // ─────────────────────────────────────────────────────────────────────

    private function buildCorpoFattura(
        DOMDocument $dom,
        FatturaAttiva $fattura,
        Tenant $tenant,
        ?Member $cliente
    ): DOMElement {
        $body = $dom->createElement('FatturaElettronicaBody');

        // DatiGenerali
        $dg  = $dom->createElement('DatiGenerali');
        $dgd = $dom->createElement('DatiGeneraliDocumento');
        $this->addEl($dom, $dgd, 'TipoDocumento',   $fattura->tipo_documento ?? 'TD01');
        $this->addEl($dom, $dgd, 'Divisa',          'EUR');
        $this->addEl($dom, $dgd, 'Data',            $fattura->data_fattura->format('Y-m-d'));
        $this->addEl($dom, $dgd, 'Numero',          $this->xml($fattura->numero_fattura));
        $this->addEl($dom, $dgd, 'ImportoTotaleDocumento', number_format((float) $fattura->totale_documento, 2, '.', ''));

        // Causale opzionale (nota di credito)
        if ($fattura->tipo_documento === 'TD04' && $fattura->motivo_nota_credito) {
            $this->addEl($dom, $dgd, 'Causale', $this->xml(substr($fattura->motivo_nota_credito, 0, 200)));
        }

        // Dati riferimento NC → fattura originale
        if ($fattura->tipo_documento === 'TD04' && $fattura->fattura_collegata_id) {
            $collegata = FatturaAttiva::find($fattura->fattura_collegata_id);
            if ($collegata) {
                $dda = $dom->createElement('DatiDocumentoDiRiferimento');
                $this->addEl($dom, $dda, 'Data',   $collegata->data_fattura->format('Y-m-d'));
                $this->addEl($dom, $dda, 'Numero', $this->xml($collegata->numero_fattura));
                $dgd->appendChild($dda);
            }
        }

        $dg->appendChild($dgd);
        $body->appendChild($dg);

        // DatiBeniServizi
        $dbs = $dom->createElement('DatiBeniServizi');

        $numRiga = 1;
        foreach ($fattura->righe as $riga) {
            $dl = $dom->createElement('DettaglioLinee');
            $this->addEl($dom, $dl, 'NumeroLinea', (string) $numRiga++);
            $this->addEl($dom, $dl, 'Descrizione', $this->xml(substr($riga->descrizione, 0, 1000)));
            $this->addEl($dom, $dl, 'Quantita',    number_format(abs((float) $riga->quantita), 2, '.', ''));
            $this->addEl($dom, $dl, 'PrezzoUnitario', number_format(abs((float) $riga->prezzo_unitario), 4, '.', ''));
            if ((float) $riga->sconto_percentuale > 0) {
                $sc = $dom->createElement('ScontoMaggiorazione');
                $this->addEl($dom, $sc, 'Tipo',       'SC');
                $this->addEl($dom, $sc, 'Percentuale', number_format((float) $riga->sconto_percentuale, 2, '.', ''));
                $dl->appendChild($sc);
            }
            $this->addEl($dom, $dl, 'PrezzoTotale', number_format(abs((float) $riga->imponibile), 2, '.', ''));
            $this->addEl($dom, $dl, 'AliquotaIVA',  number_format($riga->codiceIva ? (float) $riga->codiceIva->percentuale : 0, 2, '.', ''));

            // Natura IVA (solo se aliquota = 0)
            if ($riga->codiceIva && (float) $riga->codiceIva->percentuale === 0.0) {
                $natura = match ($riga->codiceIva->tipo ?? 'esente') {
                    'esente'       => 'N4',
                    'fuori_campo'  => 'N1',
                    'non_imponib'  => 'N3.1',
                    default        => 'N4', // esente
                };
                $this->addEl($dom, $dl, 'Natura', $natura);
            }

            $dbs->appendChild($dl);
        }

        // Riepilogo aliquote IVA
        $aliquoteGroups = $fattura->righe
            ->groupBy(fn ($r) => $r->codiceIva ? (float) $r->codiceIva->percentuale : 0.0);

        foreach ($aliquoteGroups as $aliquota => $righeGruppo) {
            $dr = $dom->createElement('DatiRiepilogo');
            $this->addEl($dom, $dr, 'AliquotaIVA',   number_format((float) $aliquota, 2, '.', ''));

            // Natura (solo aliquota = 0)
            if ((float) $aliquota === 0.0 && $righeGruppo->first()?->codiceIva) {
                $iva = $righeGruppo->first()->codiceIva;
                $natura = match ($iva->tipo ?? 'esente') {
                    'esente'      => 'N4',
                    'fuori_campo' => 'N1',
                    'non_imponib' => 'N3.1',
                    default       => 'N4',
                };
                $this->addEl($dom, $dr, 'Natura', $natura);
            }

            $imponibile = $righeGruppo->sum(fn ($r) => abs((float) $r->imponibile));
            $imposta    = $righeGruppo->sum(fn ($r) => abs((float) $r->iva));

            $this->addEl($dom, $dr, 'ImponibileImporto', number_format($imponibile, 2, '.', ''));
            $this->addEl($dom, $dr, 'Imposta',           number_format($imposta, 2, '.', ''));

            // Esigibilità IVA
            $esig = match ($fattura->esigibilita) {
                'immediata'    => 'I',
                'differita'    => 'D',
                'split_payment' => 'S',
                default        => 'I',
            };
            $this->addEl($dom, $dr, 'EsigibilitaIVA', $esig);

            $dbs->appendChild($dr);
        }

        $body->appendChild($dbs);

        // DatiPagamento (solo per fatture, non NC)
        if ($fattura->tipo_documento !== 'TD04') {
            $dp  = $dom->createElement('DatiPagamento');
            $this->addEl($dom, $dp, 'CondizioniPagamento', 'TP02'); // Pagamento completo
            $dd2 = $dom->createElement('DettaglioPagamento');
            $this->addEl($dom, $dd2, 'ModalitaPagamento', 'MP05'); // Bonifico
            $this->addEl($dom, $dd2, 'ImportoPagamento', number_format(abs((float) $fattura->totale_documento), 2, '.', ''));
            if ($fattura->data_scadenza) {
                $this->addEl($dom, $dd2, 'DataScadenzaPagamento', $fattura->data_scadenza->format('Y-m-d'));
            }
            $dp->appendChild($dd2);
            $body->appendChild($dp);
        }

        return $body;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Utilities
    // ─────────────────────────────────────────────────────────────────────

    /** Aggiunge un elemento figlio con contenuto testuale. */
    private function addEl(DOMDocument $dom, DOMElement $parent, string $tag, string $value): DOMElement
    {
        $el = $dom->createElement($tag, $value);
        $parent->appendChild($el);
        return $el;
    }

    /** Sanitizza testo per XML (rimuove caratteri non validi). */
    private function xml(string $value): string
    {
        // Rimuove caratteri di controllo eccetto tab/newline/CR
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
        // Rimuove caratteri non ASCII non-UTF8 e caratteri speciali XML
        return htmlspecialchars($value, ENT_XML1, 'UTF-8', false);
    }

    /** Normalizza codice fiscale / P.IVA (uppercase, no spazi). */
    private function normalizzaCf(string $cf): string
    {
        return strtoupper(preg_replace('/\s+/', '', $cf));
    }

    /** Restituisce P.IVA o codice fiscale del tenant come identificativo SDI. */
    private function pIva(Tenant $tenant): string
    {
        $piva = $tenant->partita_iva ?? $tenant->codice_fiscale ?? '00000000000';
        return $this->normalizzaCf($piva);
    }

    /**
     * Nome file FatturaPA: IT<CF_EMITTENTE>_<PROGRESSIVO>.xml
     * Esempio: IT91000001234_00001.xml
     */
    private function nomeFile(Tenant $tenant, FatturaAttiva $fattura): string
    {
        $cf   = $this->normalizzaCf($tenant->codice_fiscale ?? $tenant->partita_iva ?? '00000000000');
        $prog = str_pad((string) $fattura->progressivo, 5, '0', STR_PAD_LEFT);
        return "IT{$cf}_{$prog}.xml";
    }
}
