<?php

namespace App\Services;

use App\Models\Riba;
use Illuminate\Support\Collection;

/**
 * Genera il file CBI (.rtr) per la presentazione di RI.BA in banca.
 * Formato CBI RID/RI.BA — Record 14/50/51/70 standard bancario italiano.
 */
class RibaCbiExportService
{
    /**
     * Genera la stringa del file CBI per una collezione di RI.BA.
     *
     * @param Collection<Riba> $ribaList
     * @param string           $codiceSia   Codice SIA del mittente (5 car. alfanumerici)
     * @param string           $codiceCab   CAB banca presentatrice (5 cifre)
     * @param string           $codiceCc    Numero c/c presentatrice (12 car.)
     * @param string           $ragioneSociale Ragione sociale mittente (<=24 car.)
     */
    public function generate(
        Collection $ribaList,
        string $codiceSia,
        string $codiceCab,
        string $codiceCc,
        string $ragioneSociale
    ): string {
        $lines = [];
        $dataCreazione = now()->format('dmy'); // GGMMAA
        $progressivo = 0;
        $totaleImporti = 0.0;
        $numDisposizioni = $ribaList->count();

        // ── Record di Testa (tipo 14) ───────────────────────────────────────
        $progressivo++;
        $lines[] = $this->record14(
            $codiceSia,
            $codiceCab,
            $dataCreazione,
            $ragioneSociale,
            $progressivo
        );

        $numRecord = 1;
        foreach ($ribaList as $riba) {
            $numRecord++;
            $progressivo++;
            $importoCentesimi = (int) round($riba->importo * 100);
            $totaleImporti += $riba->importo;

            // ── Record Dettaglio Debitore (tipo 50) ──────────────────────────
            $lines[] = $this->record50(
                $codiceSia,
                $codiceCab,
                $dataCreazione,
                $riba,
                $importoCentesimi,
                $progressivo
            );

            // ── Record Dettaglio Debitore 2 (tipo 51) ───────────────────────
            $numRecord++;
            $progressivo++;
            $lines[] = $this->record51(
                $codiceSia,
                $codiceCab,
                $dataCreazione,
                $riba,
                $progressivo
            );
        }

        // ── Record di Coda (tipo 70) ────────────────────────────────────────
        $progressivo++;
        $numRecord++;
        $lines[] = $this->record70(
            $codiceSia,
            $codiceCab,
            $dataCreazione,
            $numDisposizioni,
            (int) round($totaleImporti * 100),
            $numRecord,
            $progressivo
        );

        return implode("\r\n", $lines) . "\r\n";
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Costruttori record
    // ─────────────────────────────────────────────────────────────────────────

    private function record14(
        string $sia,
        string $cab,
        string $data,
        string $ragioneSociale,
        int $progressivo
    ): string {
        return $this->pad('14', 2)
            . $this->pad($progressivo, 7, '0', STR_PAD_LEFT)
            . $this->pad($sia, 5)
            . $this->pad($cab, 5)
            . $this->pad($data, 6)
            . $this->pad('', 6)             // Data valuta (vuoto)
            . $this->pad('40', 2)            // Qualificatore flusso: 40=RI.BA
            . $this->pad('', 1)             // Immediato/differito
            . $this->pad('', 3)             // Filler
            . $this->pad(strtoupper($ragioneSociale), 24)
            . $this->pad('', 24)            // Indirizzo mittente
            . $this->pad('', 24)            // Localita mittente
            . $this->pad('', 12)            // Filler
            . $this->pad('', 1)             // Filler
            . str_repeat(' ', 10);          // Filler finale (tot 120 car.)
    }

    private function record50(
        string $sia,
        string $cab,
        string $data,
        Riba $riba,
        int $importoCentesimi,
        int $progressivo
    ): string {
        $scadenza = $riba->data_scadenza->format('dmy');
        $cfPiva   = $this->pad($riba->cf_piva_debitore ?? '', 16);
        $iban     = $this->pad($riba->iban_debitore ?? '', 27);
        $nome     = $this->pad(strtoupper($riba->nome_debitore), 24);
        $numero   = $this->pad($riba->numero_riba ?? $riba->id, 10);

        return $this->pad('50', 2)
            . $this->pad($progressivo, 7, '0', STR_PAD_LEFT)
            . $this->pad($sia, 5)
            . $this->pad($cab, 5)
            . $this->pad($data, 6)          // Data creazione
            . $this->pad($scadenza, 6)      // Data scadenza
            . $this->pad($importoCentesimi, 13, '0', STR_PAD_LEFT)
            . $this->pad($cfPiva, 16)        // CF/P.IVA debitore
            . $this->pad('', 1)             // Filler
            . $this->pad($iban, 27)          // IBAN c/c debitore
            . $this->pad($nome, 24)          // Nome debitore
            . $this->pad('', 24)            // Indirizzo debitore
            . $this->pad('', 24)            // Luogo debitore
            . $this->pad('', 6);            // Filler (tot 120 car. per tipo 50)
    }

    private function record51(
        string $sia,
        string $cab,
        string $data,
        Riba $riba,
        int $progressivo
    ): string {
        $numero = $this->pad($riba->numero_riba ?? '', 10);
        $note   = $this->pad($riba->note ?? '', 40);

        return $this->pad('51', 2)
            . $this->pad($progressivo, 7, '0', STR_PAD_LEFT)
            . $this->pad($sia, 5)
            . $this->pad($cab, 5)
            . $this->pad($data, 6)
            . $this->pad($numero, 10)       // Numero effetto
            . $this->pad('', 10)            // Ns. riferimento
            . $this->pad($note, 40)         // Descrizione
            . str_repeat(' ', 35);          // Filler (tot 120 car. per tipo 51)
    }

    private function record70(
        string $sia,
        string $cab,
        string $data,
        int $numDisposizioni,
        int $totaleImportiCentesimi,
        int $numRecord,
        int $progressivo
    ): string {
        return $this->pad('70', 2)
            . $this->pad($progressivo, 7, '0', STR_PAD_LEFT)
            . $this->pad($sia, 5)
            . $this->pad($cab, 5)
            . $this->pad($data, 6)
            . $this->pad($numDisposizioni, 7, '0', STR_PAD_LEFT)
            . $this->pad($totaleImportiCentesimi, 15, '0', STR_PAD_LEFT)
            . $this->pad($numRecord, 7, '0', STR_PAD_LEFT)
            . str_repeat(' ', 66);          // Filler (tot 120 car.)
    }

    private function pad(mixed $val, int $len, string $padChar = ' ', int $padType = STR_PAD_RIGHT): string
    {
        $s = (string) $val;
        if (mb_strlen($s) > $len) {
            $s = mb_substr($s, 0, $len);
        }
        return str_pad($s, $len, $padChar, $padType);
    }
}
