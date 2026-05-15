<?php

namespace App\Services;

use App\Models\EstrattoContoModel;
use App\Models\MovimentoBancario;
use Illuminate\Support\Facades\DB;

/**
 * Importa estratti conto in formato CSV (generico) oppure MT940 (SWIFT).
 */
class EstrattoCsv940ImportService
{
    /**
     * Analizza il file e restituisce un'anteprima senza salvare.
     */
    public function preview(string $contenuto, string $formato): array
    {
        $movimenti = match ($formato) {
            'mt940' => $this->parseMt940($contenuto),
            'csv'   => $this->parseCsv($contenuto),
            default => throw new \InvalidArgumentException("Formato non supportato: {$formato}"),
        };

        return $movimenti;
    }

    /**
     * Importa creando EstrattoContoModel + MovimentoBancario.
     * Restituisce il numero di movimenti importati.
     */
    public function import(
        string $contenuto,
        string $formato,
        string $nomeFile,
        ?string $banca = null,
        ?string $iban = null
    ): EstrattoContoModel {
        $movimenti = $this->preview($contenuto, $formato);

        if (empty($movimenti)) {
            throw new \RuntimeException('Nessun movimento trovato nel file.');
        }

        $date = collect($movimenti)->pluck('data_valuta');

        return DB::transaction(function () use ($movimenti, $nomeFile, $banca, $iban, $formato, $date) {
            $estratto = EstrattoContoModel::create([
                'nome_file'   => $nomeFile,
                'banca'       => $banca,
                'iban'        => $iban,
                'periodo_dal' => $date->min(),
                'periodo_al'  => $date->max(),
                'formato'     => $formato,
            ]);

            foreach ($movimenti as $m) {
                MovimentoBancario::create([
                    'estratto_conto_id' => $estratto->id,
                    'data_valuta'       => $m['data_valuta'],
                    'data_contabile'    => $m['data_contabile'] ?? null,
                    'descrizione'       => $m['descrizione'],
                    'importo'           => abs($m['importo']),
                    'tipo'              => $m['importo'] >= 0 ? MovimentoBancario::TIPO_AVERE : MovimentoBancario::TIPO_DARE,
                    'riferimento'       => $m['riferimento'] ?? null,
                    'riconciliato'      => false,
                ]);
            }

            return $estratto->load('movimenti');
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Parser MT940
    // ─────────────────────────────────────────────────────────────────────────

    private function parseMt940(string $contenuto): array
    {
        $movimenti = [];
        // Cerca tutti i campi :61: (transaction) e :86: (narrative)
        preg_match_all('/:61:(\d{6})(\d{6})?([CD])(\d+),(\d{0,2})(.*?)\r?\n(?::86:(.*?))?(?=:61:|:62:|$)/s', $contenuto, $matches, PREG_SET_ORDER);

        foreach ($matches as $m) {
            $dataVal     = '20' . substr($m[1], 0, 2) . '-' . substr($m[1], 2, 2) . '-' . substr($m[1], 4, 2);
            $dataCont    = $m[2] ? '20' . substr($m[2], 0, 2) . '-' . substr($m[2], 2, 2) . '-' . substr($m[2], 4, 2) : null;
            $segno       = $m[3] === 'C' ? 1 : -1;
            $centesimi   = $m[5] ? $m[4] . '.' . str_pad($m[5], 2, '0') : $m[4] . '.00';
            $importo     = (float) $centesimi * $segno;
            $descrizione = trim(preg_replace('/\s+/', ' ', str_replace(["\r", "\n"], ' ', $m[7] ?? $m[6])));

            $movimenti[] = [
                'data_valuta'   => $dataVal,
                'data_contabile' => $dataCont,
                'descrizione'   => $descrizione ?: 'Movimento bancario',
                'importo'       => $importo,
                'riferimento'   => trim($m[6]),
            ];
        }

        return $movimenti;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Parser CSV generico (auto-detect separatore e colonne)
    // ─────────────────────────────────────────────────────────────────────────

    private function parseCsv(string $contenuto): array
    {
        $contenuto = str_replace("\r\n", "\n", $contenuto);
        $contenuto = ltrim($contenuto, "\xEF\xBB\xBF"); // rimuovi BOM UTF-8

        $lines = array_filter(explode("\n", $contenuto), fn ($l) => trim($l) !== '');
        if (empty($lines)) return [];

        // Rileva separatore
        $header    = reset($lines);
        $separator = str_contains($header, ';') ? ';' : ',';

        $headers = array_map('trim', str_getcsv($header, $separator));
        $headerLow = array_map('strtolower', $headers);

        // Mappa colonne (ricerca fuzzy)
        $colData       = $this->findCol($headerLow, ['data valuta', 'data', 'value date', 'date', 'data_valuta']);
        $colDataCont   = $this->findCol($headerLow, ['data contabile', 'data_contabile', 'booking date']);
        $colDescrizione = $this->findCol($headerLow, ['descrizione', 'causale', 'description', 'narrative', 'causale/descrizione']);
        $colImporto    = $this->findCol($headerLow, ['importo', 'amount', 'importo eur', 'importo €']);
        $colDare       = $this->findCol($headerLow, ['dare', 'addebito', 'uscita', 'debit']);
        $colAvere      = $this->findCol($headerLow, ['avere', 'accredito', 'entrata', 'credit']);
        $colRif        = $this->findCol($headerLow, ['riferimento', 'reference', 'rif', 'numero']);

        $movimenti = [];
        $rows = array_slice(array_values($lines), 1);

        foreach ($rows as $line) {
            $cols = array_map('trim', str_getcsv($line, $separator));
            if (count($cols) < 2) continue;

            $dataVal = $colData !== null ? ($cols[$colData] ?? '') : '';
            if (! $dataVal) continue;

            $dataVal = $this->normalizeDate($dataVal);
            if (! $dataVal) continue;

            $descrizione = $colDescrizione !== null ? ($cols[$colDescrizione] ?? 'Movimento') : 'Movimento';

            // Importo: colonna singola o dare/avere separati
            $importo = 0.0;
            if ($colImporto !== null) {
                $raw = str_replace(['.', ' '], ['', ''], $cols[$colImporto] ?? '0');
                $raw = str_replace(',', '.', $raw);
                $importo = (float) $raw;
            } elseif ($colDare !== null || $colAvere !== null) {
                $dare  = $colDare  !== null ? $this->parseAmount($cols[$colDare]  ?? '0') : 0.0;
                $avere = $colAvere !== null ? $this->parseAmount($cols[$colAvere] ?? '0') : 0.0;
                $importo = $avere - $dare;
            }

            $movimenti[] = [
                'data_valuta'    => $dataVal,
                'data_contabile' => $colDataCont !== null ? $this->normalizeDate($cols[$colDataCont] ?? '') : null,
                'descrizione'    => $descrizione,
                'importo'        => $importo,
                'riferimento'    => $colRif !== null ? ($cols[$colRif] ?? null) : null,
            ];
        }

        return $movimenti;
    }

    private function findCol(array $headerLow, array $possibili): ?int
    {
        foreach ($possibili as $p) {
            $k = array_search(strtolower($p), $headerLow);
            if ($k !== false) return $k;
        }
        // Ricerca parziale
        foreach ($headerLow as $i => $h) {
            foreach ($possibili as $p) {
                if (str_contains($h, strtolower($p))) return $i;
            }
        }
        return null;
    }

    private function normalizeDate(string $raw): ?string
    {
        $raw = trim($raw);
        if (! $raw) return null;
        // Prova vari formati
        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd.m.Y', 'Ymd', 'd/m/y'] as $fmt) {
            $d = \DateTime::createFromFormat($fmt, $raw);
            if ($d) return $d->format('Y-m-d');
        }
        return null;
    }

    private function parseAmount(string $raw): float
    {
        $raw = str_replace([' ', "\xc2\xa0"], '', $raw); // rimuovi spazi e nbsp
        $raw = preg_replace('/[^\d,.\-]/', '', $raw);
        if (str_contains($raw, ',') && str_contains($raw, '.')) {
            // Decide quale è il decimale in base all'ordine
            if (strrpos($raw, ',') > strrpos($raw, '.')) {
                $raw = str_replace('.', '', $raw);
                $raw = str_replace(',', '.', $raw);
            } else {
                $raw = str_replace(',', '', $raw);
            }
        } elseif (str_contains($raw, ',')) {
            $raw = str_replace(',', '.', $raw);
        }
        return (float) $raw;
    }
}
