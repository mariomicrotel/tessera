<?php

namespace App\Services;

use App\Models\LiquidazioneIva;
use App\Models\ModelloF24;
use App\Models\RigaF24;
use App\Models\Tenant;
use App\Models\VersamentoRitenuta;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Business logic per il Modello F24.
 *
 * Flusso principale:
 *  1. crea()                     — nuovo F24 manuale da array di righe
 *  2. generaDaLiquidazioneIva()  — auto-compila sezione Erario da LiquidazioneIva
 *  3. generaDaRitenute()         — auto-compila sezione Erario da VersamentoRitenuta
 *  4. aggiorna()                 — modifica righe (solo se bozza/compilato)
 *  5. segnaVersato()             — marca come versato
 *  6. generaXml()                — XML per home banking (TracciatiCBI)
 */
class ModelloF24Service
{
    // ─────────────────────────────────────────────────────────────────────
    // Creazione manuale
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Crea un Modello F24 con righe specificate manualmente.
     *
     * @param  array  $testata  [anno, mese?, data_compilazione, stato?, note?]
     * @param  array  $righe    Array di ['sezione', 'codice_tributo', 'importo_debito', 'importo_credito', ...]
     */
    public function crea(Tenant $tenant, array $testata, array $righe = []): ModelloF24
    {
        $this->validaTestata($testata);

        return DB::transaction(function () use ($tenant, $testata, $righe) {
            $modello = ModelloF24::create([
                'tenant_id'        => $tenant->id,
                'anno'             => $testata['anno'],
                'mese'             => $testata['mese'] ?? null,
                'data_compilazione'=> $testata['data_compilazione'],
                'data_versamento'  => $testata['data_versamento'] ?? null,
                'stato'            => $testata['stato'] ?? ModelloF24::STATO_BOZZA,
                'note'             => $testata['note'] ?? null,
            ]);

            foreach ($righe as $i => $riga) {
                $this->aggiungiRiga($modello, $riga, $i);
            }

            $modello->ricalcolaTotali();

            return $modello->fresh('righe');
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // Generazione automatica da LiquidazioneIva
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Genera un F24 pre-compilato dalla liquidazione IVA.
     *
     * Inserisce automaticamente il codice tributo corretto (6001-6012 mensile
     * o 6031-6099 trimestrale) nella sezione Erario con il saldo a debito.
     *
     * @throws InvalidArgumentException
     */
    public function generaDaLiquidazioneIva(Tenant $tenant, LiquidazioneIva $liquidazione, array $extra = []): ModelloF24
    {
        if ((float) $liquidazione->saldo_finale <= 0) {
            throw new InvalidArgumentException(
                'La liquidazione non ha IVA a debito: nessun F24 da generare.'
            );
        }

        if ($liquidazione->status === LiquidazioneIva::STATUS_VERSATA) {
            throw new InvalidArgumentException(
                'La liquidazione è già stata versata.'
            );
        }

        $codiceTributo = $this->codiceTributoIva($liquidazione);
        $periodo       = $this->periodoRateazione($liquidazione);

        return DB::transaction(function () use ($tenant, $liquidazione, $codiceTributo, $periodo, $extra) {
            $modello = ModelloF24::create([
                'tenant_id'            => $tenant->id,
                'anno'                 => $liquidazione->anno,
                'mese'                 => $liquidazione->tipo_periodo === LiquidazioneIva::TIPO_MENSILE
                                             ? $liquidazione->periodo
                                             : null,
                'data_compilazione'    => $extra['data_compilazione'] ?? now()->toDateString(),
                'data_versamento'      => $extra['data_versamento']   ?? null,
                'stato'                => ModelloF24::STATO_BOZZA,
                'liquidazione_iva_id'  => $liquidazione->id,
                'note'                 => $extra['note'] ?? "Generato da liquidazione IVA {$liquidazione->periodo_label}",
            ]);

            RigaF24::create([
                'modello_f24_id'  => $modello->id,
                'sezione'         => ModelloF24::SEZIONE_ERARIO,
                'codice_tributo'  => $codiceTributo,
                'descrizione'     => "IVA {$liquidazione->periodo_label}",
                'rateazione'      => $periodo,
                'anno_riferimento'=> $liquidazione->anno,
                'importo_debito'  => round((float) $liquidazione->saldo_finale, 2),
                'importo_credito' => 0,
                'ordinamento'     => 10,
            ]);

            // Eventuali interessi trimestrali (es. Q1/Q2/Q3)
            if ((float) $liquidazione->interessi_trimestrali > 0) {
                RigaF24::create([
                    'modello_f24_id'  => $modello->id,
                    'sezione'         => ModelloF24::SEZIONE_ERARIO,
                    'codice_tributo'  => '6494', // interessi ritardato versamento IVA
                    'descrizione'     => "Interessi IVA trimestrale {$liquidazione->periodo_label}",
                    'anno_riferimento'=> $liquidazione->anno,
                    'importo_debito'  => round((float) $liquidazione->interessi_trimestrali, 2),
                    'importo_credito' => 0,
                    'ordinamento'     => 20,
                ]);
            }

            $modello->ricalcolaTotali();

            return $modello->fresh('righe');
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // Generazione automatica da VersamentoRitenuta
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Genera un F24 pre-compilato da un versamento ritenute (codice 1040 / 1038).
     */
    public function generaDaRitenute(Tenant $tenant, VersamentoRitenuta $versamento, array $extra = []): ModelloF24
    {
        if ((float) $versamento->importo_totale <= 0) {
            throw new InvalidArgumentException('Il versamento non ha importo da versare.');
        }

        return DB::transaction(function () use ($tenant, $versamento, $extra) {
            $modello = ModelloF24::create([
                'tenant_id'               => $tenant->id,
                'anno'                    => $versamento->anno_riferimento,
                'mese'                    => $versamento->mese_riferimento,
                'data_compilazione'       => $extra['data_compilazione'] ?? now()->toDateString(),
                'data_versamento'         => $extra['data_versamento']   ?? null,
                'stato'                   => ModelloF24::STATO_BOZZA,
                'versamento_ritenuta_id'  => $versamento->id,
                'note'                    => $extra['note'] ?? "Generato da versamento ritenute {$versamento->mese_riferimento}/{$versamento->anno_riferimento}",
            ]);

            // Raggruppa per codice tributo
            $versamento->load('compensi');
            $perCodice = $versamento->compensi->groupBy('codice_causale');

            $ord = 10;
            foreach ($perCodice as $causale => $compensi) {
                $codiceTributo = in_array($causale, ['Q','R','V'])
                    ? ModelloF24::CODICE_RITENUTA_PROVVIGIONI
                    : ModelloF24::CODICE_RITENUTA_LAV_AUTONOMO;

                $totale = $compensi->sum('ritenuta');

                RigaF24::create([
                    'modello_f24_id'  => $modello->id,
                    'sezione'         => ModelloF24::SEZIONE_ERARIO,
                    'codice_tributo'  => $codiceTributo,
                    'descrizione'     => "Ritenute causale {$causale} — {$versamento->mese_riferimento}/{$versamento->anno_riferimento}",
                    'rateazione'      => str_pad($versamento->mese_riferimento, 2, '0', STR_PAD_LEFT)
                                         . substr((string)$versamento->anno_riferimento, -2),
                    'anno_riferimento'=> $versamento->anno_riferimento,
                    'importo_debito'  => round($totale, 2),
                    'importo_credito' => 0,
                    'ordinamento'     => $ord,
                ]);

                $ord += 10;
            }

            $modello->ricalcolaTotali();

            return $modello->fresh('righe');
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // Aggiornamento
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Sostituisce tutte le righe e ricalcola i totali.
     *
     * @param  array  $righe  Stesso formato di crea()
     * @throws InvalidArgumentException se già versato
     */
    public function aggiorna(ModelloF24 $modello, array $testata, array $righe): ModelloF24
    {
        if ($modello->isVersionato()) {
            throw new InvalidArgumentException('Impossibile modificare un F24 già versato.');
        }

        return DB::transaction(function () use ($modello, $testata, $righe) {
            $modello->update([
                'anno'              => $testata['anno']              ?? $modello->anno,
                'mese'              => $testata['mese']              ?? $modello->mese,
                'data_compilazione' => $testata['data_compilazione'] ?? $modello->data_compilazione,
                'data_versamento'   => $testata['data_versamento']   ?? $modello->data_versamento,
                'stato'             => $testata['stato']             ?? $modello->stato,
                'note'              => $testata['note']              ?? $modello->note,
            ]);

            // Sostituisci righe
            $modello->righe()->delete();
            foreach ($righe as $i => $riga) {
                $this->aggiungiRiga($modello, $riga, $i);
            }

            $modello->ricalcolaTotali();

            return $modello->fresh('righe');
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // Segna come versato
    // ─────────────────────────────────────────────────────────────────────

    public function segnaVersato(ModelloF24 $modello, string $dataVersamento): ModelloF24
    {
        if ($modello->isVersionato()) {
            throw new InvalidArgumentException('Il modello è già versato.');
        }

        $modello->update([
            'stato'           => ModelloF24::STATO_VERSATO,
            'data_versamento' => $dataVersamento,
        ]);

        return $modello->fresh('righe');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Export XML (formato TracciatiCBI per home banking)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Genera XML semplificato del Modello F24 compatibile con il tracciato
     * usato dalle banche per il pagamento digitale.
     *
     * Nota: non è il formato telematico ufficiale AdE (richiede credenziali
     * Entratel), ma un XML strutturato per la riconciliazione interna e
     * l'invio a portali bancari che accettano upload XML.
     */
    public function generaXml(ModelloF24 $modello): string
    {
        $modello->load('righe');
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElement('ModelF24');
        $root->setAttribute('xmlns', 'urn:it:agenziaentrate:f24:1.0');
        $dom->appendChild($root);

        // Testata
        $testata = $dom->createElement('Testata');
        $testata->appendChild($dom->createElement('Anno',     (string) $modello->anno));
        $testata->appendChild($dom->createElement('Mese',     (string) ($modello->mese ?? '')));
        $testata->appendChild($dom->createElement('DataComp', $modello->data_compilazione->toDateString()));
        $testata->appendChild($dom->createElement('Saldo',    number_format($modello->saldo, 2, '.', '')));
        $root->appendChild($testata);

        // Sezioni
        $sezioni = $dom->createElement('Sezioni');

        foreach (ModelloF24::SEZIONI as $codSezione => $labelSezione) {
            $righe = $modello->righePerSezione($codSezione);
            if ($righe->isEmpty()) {
                continue;
            }

            $sezioneEl = $dom->createElement('Sezione');
            $sezioneEl->setAttribute('codice', $codSezione);
            $sezioneEl->setAttribute('descrizione', $labelSezione);

            foreach ($righe as $riga) {
                $rigaEl = $dom->createElement('Riga');
                $rigaEl->appendChild($dom->createElement('CodiceTributo',   htmlspecialchars($riga->codice_tributo)));
                $rigaEl->appendChild($dom->createElement('Descrizione',     htmlspecialchars($riga->descrizione ?? '')));
                $rigaEl->appendChild($dom->createElement('Rateazione',      $riga->rateazione ?? ''));
                $rigaEl->appendChild($dom->createElement('AnnoRiferimento', (string) ($riga->anno_riferimento ?? '')));
                $rigaEl->appendChild($dom->createElement('ImportoDebito',   number_format($riga->importo_debito, 2, '.', '')));
                $rigaEl->appendChild($dom->createElement('ImportoCredito',  number_format($riga->importo_credito, 2, '.', '')));
                $sezioneEl->appendChild($rigaEl);
            }

            $sezioni->appendChild($sezioneEl);
        }

        $root->appendChild($sezioni);

        return $dom->saveXML();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers privati
    // ─────────────────────────────────────────────────────────────────────

    private function aggiungiRiga(ModelloF24 $modello, array $riga, int $idx): RigaF24
    {
        return RigaF24::create([
            'modello_f24_id'  => $modello->id,
            'sezione'         => $riga['sezione']          ?? ModelloF24::SEZIONE_ERARIO,
            'codice_tributo'  => $riga['codice_tributo'],
            'descrizione'     => $riga['descrizione']      ?? null,
            'rateazione'      => $riga['rateazione']       ?? null,
            'anno_riferimento'=> $riga['anno_riferimento'] ?? null,
            'regione_codice'  => $riga['regione_codice']   ?? null,
            'ente_codice'     => $riga['ente_codice']      ?? null,
            'importo_debito'  => (float) ($riga['importo_debito']  ?? 0),
            'importo_credito' => (float) ($riga['importo_credito'] ?? 0),
            'ordinamento'     => $riga['ordinamento'] ?? ($idx * 10),
        ]);
    }

    private function validaTestata(array $data): void
    {
        if (empty($data['anno']) || $data['anno'] < 2000 || $data['anno'] > 2100) {
            throw new InvalidArgumentException('Anno non valido.');
        }
        if (empty($data['data_compilazione'])) {
            throw new InvalidArgumentException('Data compilazione obbligatoria.');
        }
    }

    /** Determina il codice tributo IVA corretto in base al tipo e periodo. */
    private function codiceTributoIva(LiquidazioneIva $liq): string
    {
        if ($liq->tipo_periodo === LiquidazioneIva::TIPO_MENSILE) {
            return ModelloF24::CODICI_IVA_MENSILE[$liq->periodo] ?? '6001';
        }

        return ModelloF24::CODICI_IVA_TRIMESTRALE[$liq->periodo] ?? '6031';
    }

    /** Rateazione nel formato MMAA (es. "0126" per gen 2026). */
    private function periodoRateazione(LiquidazioneIva $liq): string
    {
        if ($liq->tipo_periodo === LiquidazioneIva::TIPO_MENSILE) {
            return str_pad($liq->periodo, 2, '0', STR_PAD_LEFT)
                . substr((string)$liq->anno, -2);
        }
        // Trimestrale: il periodo è Q1..Q4 → mese di scadenza: 16/5, 16/8, 16/11, 16/1
        $meseScadenza = [1 => '05', 2 => '08', 3 => '11', 4 => '01'];
        return ($meseScadenza[$liq->periodo] ?? '01') . substr((string)$liq->anno, -2);
    }
}
