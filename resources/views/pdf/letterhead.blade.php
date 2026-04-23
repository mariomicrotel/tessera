<style>
@page { margin-top: 25px; }
.letterhead-fixed { position: fixed; top: 0; left: 0; right: 0; height: 68px; z-index: 1; padding-top: 0; }
</style>
<table class="letterhead" style="width: 100%; margin: 0 0 0 0; font-family: DejaVu Sans, sans-serif; font-size: 11px; border: none;">
    <tr>
        <td style="width: 1%; vertical-align: middle; border: none; padding: 0;">
            @if(!empty($logo_data_uri))
                <img src="{{ $logo_data_uri }}" alt="Logo" style="max-height: 48px; display: block;" />
            @endif
        </td>
        <td style="vertical-align: middle; text-align: right; border: none; padding: 0;">
            @if(!empty($nome_associazione))
                <div style="font-size: 14px; font-weight: bold; margin: 0;">{{ $nome_associazione }}</div>
            @endif
            @if(!empty($indirizzo_associazione))
                <div style="color: #333; margin: 0;">{{ $indirizzo_associazione }}</div>
            @endif
            @if(!empty($email_associazione) || !empty($pec_associazione))
                <div style="color: #333; margin: 0; font-size: 10px;">
                    @if(!empty($email_associazione))
                        Email: {{ $email_associazione }}
                    @endif
                    @if(!empty($email_associazione) && !empty($pec_associazione))
                        &nbsp;–&nbsp;
                    @endif
                    @if(!empty($pec_associazione))
                        PEC: {{ $pec_associazione }}
                    @endif
                </div>
            @endif
            @if(!empty($codice_fiscale_associazione) || !empty($partita_iva_associazione))
                <div style="font-size: 10px; color: #555; margin: 0;">
                    @if(!empty($codice_fiscale_associazione))
                        Codice fiscale: {{ $codice_fiscale_associazione }}
                    @endif
                    @if(!empty($codice_fiscale_associazione) && !empty($partita_iva_associazione))
                        &nbsp;–&nbsp;
                    @endif
                    @if(!empty($partita_iva_associazione))
                        P.IVA: {{ $partita_iva_associazione }}
                    @endif
                </div>
            @endif
        </td>
    </tr>
</table>
