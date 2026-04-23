<?php

namespace App\Support;

use App\Models\Attachment;
use App\Models\Settings;

/**
 * Dati condivisi per la carta intestata negli header dei PDF.
 */
class PdfLetterheadData
{
    /**
     * Restituisce l'array di variabili per il partial pdf.letterhead.
     *
     * @return array{logo_data_uri: ?string, nome_associazione: string, indirizzo_associazione: string, email_associazione: string, pec_associazione: string, codice_fiscale_associazione: string, partita_iva_associazione: string}
     */
    public static function data(): array
    {
        return [
            'logo_data_uri' => Attachment::logoDataUriForPdf(),
            'nome_associazione' => Settings::get('nome_associazione', ''),
            'indirizzo_associazione' => Settings::get('indirizzo_associazione', ''),
            'email_associazione' => Settings::get('email_associazione', ''),
            'pec_associazione' => Settings::get('pec_associazione', ''),
            'codice_fiscale_associazione' => Settings::get('codice_fiscale_associazione', ''),
            'partita_iva_associazione' => Settings::get('partita_iva_associazione', ''),
        ];
    }
}
