/**
 * Menu placeholder per editor template ricevuta / override incasso.
 * Esclusi i token tipici dei verbali (direttivo, richieste-soci, offset ora riunioni).
 */
export const receiptEditorPlaceholders = [
    { value: '{{nome-associazione}}', label: 'Nome associazione' },
    { value: '{{receipt_number}}', label: 'Numero ricevuta' },
    { value: '{{data}}', label: 'Data emissione' },
    { value: '{{amount}}', label: 'Importo formattato' },
    { value: '{{causale}}', label: 'Causale pagamento' },
    { value: '{{recipient_name}}', label: 'Nome destinatario' },
    { value: '{{recipient_cf}}', label: 'Codice fiscale destinatario' },
    { value: '{{iban}}', label: 'IBAN conto incasso' },
    { value: '{{anno}}', label: 'Anno corrente' },
    { value: '{{presidente}}', label: 'Presidente' },
    { value: '{{sede}}', label: 'Sede (indirizzo)' },
    { value: '{{sede-legale}}', label: 'Sede legale' },
    { value: '{{sede-operativa}}', label: 'Sede operativa' },
];
