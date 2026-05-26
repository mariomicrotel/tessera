<?php

namespace App\Http\Controllers;

use App\Models\EmailTemplate;
use App\Models\Receipt;
use App\Models\Settings;
use App\Services\ProtocolloService;
use App\Services\ReceiptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/**
 * Elenco ricevute e download PDF.
 */
class ReceiptController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,segreteria,contabile');
    }

    public function index(Request $request)
    {
        $query = Receipt::with('member');

        if ($request->filled('member_id')) {
            $query->where('member_id', $request->member_id);
        }
        if ($request->filled('from')) {
            $query->whereDate('issued_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('issued_at', '<=', $request->to);
        }

        $receipts = $query->orderByDesc('issued_at')->paginate(20)->withQueryString();
        return Inertia::render('Receipts/Index', [
            'receipts' => $receipts,
            'filters' => $request->only('member_id', 'from', 'to'),
        ]);
    }

    public function show(Receipt $receipt)
    {
        $receipt->load('member');
        $suggestedEmail = $receipt->member?->email;

        return Inertia::render('Receipts/Show', [
            'receipt' => $receipt,
            'suggestedEmail' => $suggestedEmail,
        ]);
    }

    /**
     * Download del file PDF della ricevuta.
     * Con ?inline=1 restituisce Content-Disposition: inline per anteprima nel browser.
     */
    public function download(Request $request, Receipt $receipt)
    {
        if (! $receipt->file_path || ! Storage::disk('local')->exists($receipt->file_path)) {
            abort(404, 'File non trovato.');
        }
        $safeNumber = str_replace(['/', '\\'], '-', $receipt->number);
        $filename = 'ricevuta-' . $safeNumber . '.pdf';
        $path = Storage::disk('local')->path($receipt->file_path);

        if ($request->boolean('inline')) {
            return response()->file($path, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $filename . '"',
            ]);
        }

        return Storage::disk('local')->download($receipt->file_path, $filename, ['Content-Type' => 'application/pdf']);
    }

    /**
     * Invia la ricevuta per email al destinatario indicato (PDF in allegato).
     */
    public function sendEmail(Request $request, Receipt $receipt)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        if (! $receipt->file_path || ! Storage::disk('local')->exists($receipt->file_path)) {
            return redirect()->route('receipts.show', $receipt)
                ->with('flash', ['type' => 'error', 'message' => 'File PDF della ricevuta non trovato. Impossibile inviare.']);
        }

        $email = $request->input('email');
        $appName = Settings::get('nome_associazione', config('app.name'));
        $safeNumber = str_replace(['/', '\\'], '-', $receipt->number);
        $filename = 'ricevuta-' . $safeNumber . '.pdf';

        $rawAmount = null;
        if ($receipt->receivable) {
            $rawAmount = $receipt->receivable->amount ?? $receipt->receivable->total ?? null;
        }
        $replacements = [
            'receipt_number' => $receipt->number,
            'receipt_issued_at' => $receipt->issued_at?->format('d/m/Y') ?? '',
            'appName' => $appName,
            'receipt_amount' => $rawAmount !== null && $rawAmount !== '' ? number_format((float) $rawAmount, 2, ',', '.') : '',
            'recipient_name' => $receipt->member ? trim($receipt->member->cognome . ' ' . $receipt->member->nome) : '',
            'year' => (string) now()->year,
        ];
        $rendered = EmailTemplate::render('ricevuta', $replacements);

        try {
            if ($rendered) {
                Mail::html($rendered['body'], function ($message) use ($email, $rendered, $receipt, $filename) {
                    $message->to($email)->subject($rendered['subject']);
                    $message->attach(Storage::disk('local')->path($receipt->file_path), [
                        'as' => $filename,
                        'mime' => 'application/pdf',
                    ]);
                });
            } else {
                Mail::send('emails.receipt', [
                    'receipt' => $receipt,
                    'appName' => $appName,
                ], function ($message) use ($email, $appName, $receipt, $filename) {
                    $message->to($email)
                        ->subject('[' . $appName . '] Ricevuta n. ' . $receipt->number);
                    $message->attach(Storage::disk('local')->path($receipt->file_path), [
                        'as' => $filename,
                        'mime' => 'application/pdf',
                    ]);
                });
            }

            $receipt->update(['sent_at' => now()]);

            // Auto-registra nel protocollo uscita
            try {
                $receipt->load('member');
                app(ProtocolloService::class)->registraUscitaRicevuta($receipt, $email);
            } catch (\Throwable $ignored) {
                report($ignored);
            }

            return redirect()->route('receipts.show', $receipt)
                ->with('flash', ['type' => 'success', 'message' => 'Ricevuta inviata a ' . $email . '.']);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('receipts.show', $receipt)
                ->with('flash', ['type' => 'error', 'message' => 'Errore nell\'invio: ' . $e->getMessage()]);
        }
    }

    /**
     * Rigenera il PDF della ricevuta (stesso numero, dati aggiornati dal template/incasso).
     */
    public function regenerate(Receipt $receipt, ReceiptService $receiptService)
    {
        try {
            $receiptService->regenerate($receipt);

            return redirect()->route('receipts.show', $receipt)
                ->with('flash', ['type' => 'success', 'message' => 'Ricevuta rigenerata. Puoi scaricare il nuovo PDF.']);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('receipts.show', $receipt)
                ->with('flash', ['type' => 'error', 'message' => 'Errore nella rigenerazione: ' . $e->getMessage()]);
        }
    }
}
