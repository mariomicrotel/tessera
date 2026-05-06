<?php

namespace App\Services\Onboarding;

/**
 * Catalogo dei 26 ambiti di interesse generale dell'art. 5 D.Lgs. 117/2017
 * (Codice del Terzo Settore).
 *
 * Ogni Ente del Terzo Settore deve esercitare in via esclusiva o principale
 * una o più attività di interesse generale tra quelle elencate in questo articolo.
 *
 * Le attività diverse (art. 6 CTS) sono ammesse a condizione che siano
 * secondarie e strumentali rispetto a quelle di interesse generale, secondo
 * criteri e limiti stabiliti dal D.M. 107/2021.
 */
final class AmbitiInteresseGenerale
{
    /**
     * Restituisce la mappa completa dei 26 ambiti (lettere a-z).
     * Riferimento: art. 5, c. 1 D.Lgs. 117/2017.
     *
     * @return array<string, array{titolo: string, descrizione: string, sezioni_compatibili: string[]}>
     */
    public static function tutti(): array
    {
        return [
            'a' => [
                'titolo'              => 'Interventi e servizi sociali',
                'descrizione'         => 'Interventi e servizi sociali ai sensi della L. 328/2000 e successive modificazioni.',
                'sezioni_compatibili' => ['a', 'b', 'c', 'd', 'g'],
            ],
            'b' => [
                'titolo'              => 'Interventi e prestazioni sanitarie',
                'descrizione'         => 'Interventi e prestazioni sanitarie.',
                'sezioni_compatibili' => ['a', 'b', 'd', 'g'],
            ],
            'c' => [
                'titolo'              => 'Prestazioni socio-sanitarie',
                'descrizione'         => 'Prestazioni socio-sanitarie ai sensi del D.P.C.M. 14/2/2001.',
                'sezioni_compatibili' => ['a', 'b', 'd', 'g'],
            ],
            'd' => [
                'titolo'              => 'Educazione, istruzione, formazione professionale',
                'descrizione'         => 'Educazione, istruzione e formazione professionale, ai sensi della L. 53/2003, nonché attività culturali di interesse sociale con finalità educativa.',
                'sezioni_compatibili' => ['a', 'b', 'd', 'g'],
            ],
            'e' => [
                'titolo'              => 'Tutela ambiente, ecosistemi',
                'descrizione'         => 'Interventi e servizi finalizzati alla salvaguardia e al miglioramento delle condizioni dell\'ambiente.',
                'sezioni_compatibili' => ['a', 'b', 'd', 'g'],
            ],
            'f' => [
                'titolo'              => 'Beni di interesse storico, artistico, paesaggistico',
                'descrizione'         => 'Interventi di tutela e valorizzazione del patrimonio culturale e del paesaggio.',
                'sezioni_compatibili' => ['a', 'b', 'd', 'g'],
            ],
            'g' => [
                'titolo'              => 'Formazione universitaria e post-universitaria',
                'descrizione'         => 'Formazione universitaria e post-universitaria.',
                'sezioni_compatibili' => ['a', 'b', 'd', 'g'],
            ],
            'h' => [
                'titolo'              => 'Ricerca scientifica',
                'descrizione'         => 'Ricerca scientifica di particolare interesse sociale.',
                'sezioni_compatibili' => ['a', 'b', 'c', 'd', 'g'],
            ],
            'i' => [
                'titolo'              => 'Attività culturali, artistiche o ricreative',
                'descrizione'         => 'Organizzazione e gestione di attività culturali, artistiche o ricreative di interesse sociale, incluse attività editoriali di promozione e diffusione della cultura e della pratica del volontariato.',
                'sezioni_compatibili' => ['a', 'b', 'd', 'g'],
            ],
            'j' => [
                'titolo'              => 'Radiodiffusione sonora a carattere comunitario',
                'descrizione'         => 'Radiodiffusione sonora a carattere comunitario, ai sensi dell\'art. 16, c. 5, L. 223/1990.',
                'sezioni_compatibili' => ['b', 'd', 'g'],
            ],
            'k' => [
                'titolo'              => 'Attività turistiche di interesse sociale',
                'descrizione'         => 'Organizzazione e gestione di attività turistiche di interesse sociale, culturale o religioso.',
                'sezioni_compatibili' => ['a', 'b', 'd', 'g'],
            ],
            'l' => [
                'titolo'              => 'Formazione extrascolastica',
                'descrizione'         => 'Formazione extra-scolastica, finalizzata alla prevenzione della dispersione scolastica e al successo scolastico e formativo.',
                'sezioni_compatibili' => ['a', 'b', 'd', 'g'],
            ],
            'm' => [
                'titolo'              => 'Servizi strumentali a ETS',
                'descrizione'         => 'Servizi strumentali ad enti del Terzo settore resi da enti composti in misura non inferiore al 70% da ETS.',
                'sezioni_compatibili' => ['e', 'g'],
            ],
            'n' => [
                'titolo'              => 'Cooperazione allo sviluppo',
                'descrizione'         => 'Cooperazione allo sviluppo, ai sensi della L. 125/2014.',
                'sezioni_compatibili' => ['a', 'b', 'd', 'g'],
            ],
            'o' => [
                'titolo'              => 'Commercio equo e solidale',
                'descrizione'         => 'Attività commerciali, produttive, di educazione e informazione, di promozione, rappresentanza, concessione in licenza di marchi di certificazione, svolte nell\'ambito o a favore di filiere del commercio equo e solidale.',
                'sezioni_compatibili' => ['b', 'd', 'g'],
            ],
            'p' => [
                'titolo'              => 'Inserimento lavorativo',
                'descrizione'         => 'Servizi finalizzati all\'inserimento o al reinserimento nel mercato del lavoro dei lavoratori e delle persone svantaggiate.',
                'sezioni_compatibili' => ['a', 'b', 'd', 'g'],
            ],
            'q' => [
                'titolo'              => 'Alloggio sociale',
                'descrizione'         => 'Alloggio sociale, ai sensi del D.M. 22/4/2008, nonché ogni altra attività di carattere residenziale temporaneo per soggetti in difficoltà.',
                'sezioni_compatibili' => ['a', 'b', 'd', 'g'],
            ],
            'r' => [
                'titolo'              => 'Accoglienza migranti',
                'descrizione'         => 'Accoglienza umanitaria ed integrazione sociale dei migranti.',
                'sezioni_compatibili' => ['a', 'b', 'd', 'g'],
            ],
            's' => [
                'titolo'              => 'Agricoltura sociale',
                'descrizione'         => 'Agricoltura sociale, ai sensi della L. 141/2015.',
                'sezioni_compatibili' => ['a', 'b', 'd', 'g'],
            ],
            't' => [
                'titolo'              => 'Sport dilettantistico',
                'descrizione'         => 'Organizzazione e gestione di attività sportive dilettantistiche.',
                'sezioni_compatibili' => ['a', 'b', 'd', 'g'],
            ],
            'u' => [
                'titolo'              => 'Beneficenza',
                'descrizione'         => 'Beneficenza, sostegno a distanza, cessione gratuita di alimenti o prodotti, erogazione di denaro o beni o servizi a sostegno di persone svantaggiate o di attività di interesse generale.',
                'sezioni_compatibili' => ['a', 'b', 'c', 'g'],
            ],
            'v' => [
                'titolo'              => 'Cultura della legalità e pace',
                'descrizione'         => 'Promozione della cultura della legalità, della pace tra i popoli, della nonviolenza e della difesa non armata.',
                'sezioni_compatibili' => ['a', 'b', 'd', 'g'],
            ],
            'w' => [
                'titolo'              => 'Diritti umani e civili',
                'descrizione'         => 'Promozione e tutela dei diritti umani, civili, sociali e politici, nonché dei diritti dei consumatori.',
                'sezioni_compatibili' => ['a', 'b', 'd', 'g'],
            ],
            'x' => [
                'titolo'              => 'Adozione internazionale',
                'descrizione'         => 'Cura di procedure di adozione internazionale ex L. 184/1983.',
                'sezioni_compatibili' => ['a', 'b', 'd', 'g'],
            ],
            'y' => [
                'titolo'              => 'Protezione civile',
                'descrizione'         => 'Protezione civile ai sensi della L. 225/1992 e successive modificazioni.',
                'sezioni_compatibili' => ['a', 'b', 'd', 'g'],
            ],
            'z' => [
                'titolo'              => 'Beni pubblici / confiscati',
                'descrizione'         => 'Riqualificazione di beni pubblici inutilizzati o di beni confiscati alla criminalità organizzata.',
                'sezioni_compatibili' => ['a', 'b', 'd', 'g'],
            ],
        ];
    }

    /**
     * Restituisce gli ambiti compatibili con una determinata sezione RUNTS.
     */
    public static function ambitiPerSezione(string $sezione): array
    {
        return collect(self::tutti())
            ->filter(fn ($amb) => in_array($sezione, $amb['sezioni_compatibili'], true))
            ->toArray();
    }

    /**
     * Restituisce le sezioni RUNTS valide.
     */
    public static function sezioniRunts(): array
    {
        return [
            'a' => 'OdV — Organizzazioni di Volontariato',
            'b' => 'APS — Associazioni di Promozione Sociale',
            'c' => 'Enti Filantropici',
            'd' => 'Imprese sociali (incluse cooperative sociali)',
            'e' => 'Reti associative',
            'f' => 'Società di mutuo soccorso',
            'g' => 'Altri enti del Terzo settore',
        ];
    }

    /**
     * Sezione RUNTS suggerita per una forma giuridica.
     */
    public static function sezioneSuggerita(string $formaGiuridica): ?string
    {
        return match ($formaGiuridica) {
            'ets_odv'        => 'a',
            'ets_aps'        => 'b',
            'ets_fondazione' => 'c',  // se filantropica, altrimenti 'g'
            'ets_generico'   => 'g',
            'coop_sociale_a',
            'coop_sociale_b' => 'd',
            default          => null,
        };
    }

    /**
     * Validazione: ogni ambito scelto deve essere compatibile con la sezione RUNTS.
     */
    public static function validaAmbiti(string $sezione, array $ambiti): array
    {
        $errors = [];
        $tutti = self::tutti();
        foreach ($ambiti as $a) {
            if (! isset($tutti[$a])) {
                $errors[] = "Ambito '{$a}' non riconosciuto.";
                continue;
            }
            if (! in_array($sezione, $tutti[$a]['sezioni_compatibili'], true)) {
                $errors[] = "L'ambito '{$tutti[$a]['titolo']}' (lett. {$a}) non è tipicamente svolto da enti della sezione RUNTS '{$sezione}'.";
            }
        }
        return $errors;
    }
}
