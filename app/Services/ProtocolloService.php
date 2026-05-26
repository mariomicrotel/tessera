<?php

namespace App\Services;

use App\Models\Protocollo;
use App\Models\Receipt;
use Illuminate\Support\Facades\Auth;

class ProtocolloService
{
    /**
     * Crea un record Protocollo con numero progressivo automatico.
     */
    public function crea(array $data): Protocollo
    {
        $tenantId = $data['tenant_id'] ?? null;
        if (!$tenantId) {
            throw new \InvalidArgumentException('tenant_id obbligatorio');
        }
        $anno   = $data['anno'] ?? now()->year;
        $numero = Protocollo::nextNumero($tenantId, $anno);

        return Protocollo::create(array_merge($data, [
            'anno'       => $anno,
            'numero'     => $numero,
            'created_by' => $data['created_by'] ?? Auth::id(),
        ]));
    }

    /**
     * Auto-registra in uscita l'invio di una ricevuta per email.
     * Chiamato da IncassoController::sendReceiptEmail e ReceiptController::sendEmail.
     * Se un'entrata protocollo per questa ricevuta esiste già, non ne crea una duplicata.
     */
    public function registraUscitaRicevuta(Receipt $receipt, string $emailDestinatario): Protocollo
    {
        $existing = Protocollo::where('linked_type', Receipt::class)
            ->where('linked_id', $receipt->id)
            ->where('tipo', Protocollo::TIPO_USCITA)
            ->first();

        if ($existing) {
            return $existing;
        }

        $tenantId = $receipt->tenant_id;
        $anno     = now()->year;

        $recipientName = $receipt->member
            ? trim($receipt->member->cognome . ' ' . $receipt->member->nome)
            : ($receipt->recipient_name ?? $emailDestinatario);

        $oggetto = 'Invio ricevuta n° ' . $receipt->number . ' a ' . $recipientName;

        return $this->crea([
            'tenant_id'          => $tenantId,
            'anno'               => $anno,
            'tipo'               => Protocollo::TIPO_USCITA,
            'data_registrazione' => now()->toDateString(),
            'oggetto'            => $oggetto,
            'destinatario'       => $recipientName . ' <' . $emailDestinatario . '>',
            'linked_type'        => Receipt::class,
            'linked_id'          => $receipt->id,
        ]);
    }
}
