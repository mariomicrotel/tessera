<?php

namespace App\Http\Controllers;

use App\Models\ReceiptTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReceiptTemplateController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin');
    }

    public function index()
    {
        $types = config('receipt_templates.types', []);
        $items = [];

        foreach ($types as $tipo => $cfg) {
            $items[] = [
                'tipo' => $tipo,
                'label' => $cfg['label'] ?? $tipo,
                'body_text' => ReceiptTemplate::getBodyForTipo($tipo),
            ];
        }

        return Inertia::render('ReceiptTemplates/Index', [
            'receiptTemplates' => $items,
        ]);
    }

    public function edit(string $tipo)
    {
        $types = config('receipt_templates.types', []);
        if (! array_key_exists($tipo, $types)) {
            abort(404, 'Tipo template ricevuta non valido.');
        }

        $config = $types[$tipo];
        $template = ReceiptTemplate::firstOrNew(['tipo' => $tipo], [
            'body_text' => $config['default_text'] ?? '',
        ]);

        $placeholders = [];
        foreach ($config['placeholders'] ?? [] as $key => $desc) {
            $placeholders[] = ['key' => $key, 'description' => $desc];
        }

        return Inertia::render('ReceiptTemplates/Edit', [
            'template' => [
                'tipo' => $template->tipo,
                'body_text' => $template->body_text ?? '',
            ],
            'typeLabel' => $config['label'] ?? $tipo,
            'placeholders' => $placeholders,
            'preview_samples' => $this->previewSamplesForTipo($tipo),
        ]);
    }

    public function update(Request $request, string $tipo)
    {
        $types = config('receipt_templates.types', []);
        if (! array_key_exists($tipo, $types)) {
            abort(404, 'Tipo template ricevuta non valido.');
        }

        $request->validate([
            'body_text' => 'nullable|string|max:50000',
        ]);

        ReceiptTemplate::updateOrCreate(
            ['tipo' => $tipo],
            ['body_text' => (string) $request->input('body_text', '')]
        );

        return redirect()->route('receipt-templates.index')
            ->with('flash', ['type' => 'success', 'message' => 'Template ricevuta aggiornato.']);
    }

    private function previewSamplesForTipo(string $tipo): array
    {
        return [
            'nome-associazione' => 'Nome Associazione',
            'receipt_number' => '2026/0001',
            'data' => now()->format('d/m/Y'),
            'amount' => '50,00',
            'causale' => $tipo === 'incasso_quota' ? 'Quota associativa 2026' : ($tipo === 'incasso_donazione' ? 'Erogazione liberale' : 'Incasso attività istituzionale'),
            'recipient_name' => 'Mario Rossi',
            'recipient_cf' => 'RSSMRA80A01H501U',
            'iban' => 'IT60X0542811101000000123456',
            'anno' => (string) now()->year,
            'presidente' => 'Mario Bianchi',
            'sede' => 'Via Esempio 1, 70100 Bari',
            'sede-legale' => 'Via Legale 2, 70100 Bari',
            'sede-operativa' => 'Via Operativa 3, 70100 Bari',
        ];
    }
}
