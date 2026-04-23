<?php

namespace App\Http\Controllers;

use App\Models\EmailTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmailTemplateController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin');
    }

    public function index(Request $request)
    {
        $configTypes = config('email_templates.types', []);
        $query = EmailTemplate::query()->orderBy('tipo');

        if ($request->filled('search')) {
            $term = '%' . $request->search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('tipo', 'like', $term)->orWhere('subject', 'like', $term);
            });
        }

        $emailTemplates = $query->paginate(10)->withQueryString();

        $typeLabels = [];
        foreach ($configTypes as $tipo => $config) {
            $typeLabels[$tipo] = $config['label'] ?? $tipo;
        }

        return Inertia::render('EmailTemplates/Index', [
            'emailTemplates' => $emailTemplates,
            'typeLabels' => $typeLabels,
            'filters' => $request->only('search'),
        ]);
    }

    public function edit(string $tipo)
    {
        $types = config('email_templates.types', []);
        if (! array_key_exists($tipo, $types)) {
            abort(404, 'Tipo template email non valido.');
        }

        $config = $types[$tipo];
        $template = EmailTemplate::where('tipo', $tipo)->first();

        if (! $template) {
            $template = new EmailTemplate([
                'tipo' => $tipo,
                'subject' => $config['default_subject'] ?? '[{{appName}}]',
                'body_html' => $config['default_body'] ?? '',
            ]);
        }

        $placeholders = [];
        foreach ($config['placeholders'] ?? [] as $key => $desc) {
            $placeholders[] = ['key' => $key, 'description' => $desc];
        }

        return Inertia::render('EmailTemplates/Edit', [
            'template' => [
                'tipo' => $template->tipo,
                'subject' => $template->subject,
                'body_html' => $template->body_html ?? '',
            ],
            'typeLabel' => $config['label'] ?? $tipo,
            'placeholders' => $placeholders,
            'preview_samples' => $this->previewSamplesForTipo($tipo),
        ]);
    }

    public function update(Request $request, string $tipo)
    {
        $types = config('email_templates.types', []);
        if (! array_key_exists($tipo, $types)) {
            abort(404, 'Tipo template email non valido.');
        }

        $request->validate([
            'subject' => 'required|string|max:255',
            'body_html' => 'nullable|string',
        ]);

        EmailTemplate::updateOrCreate(
            ['tipo' => $tipo],
            [
                'subject' => $request->input('subject'),
                'body_html' => $request->input('body_html', ''),
            ]
        );

        return redirect()->route('email-templates.index')
            ->with('flash', ['type' => 'success', 'message' => 'Template email aggiornato.']);
    }

    /**
     * Valori di esempio per l'anteprima in tempo reale (placeholder => valore).
     */
    private function previewSamplesForTipo(string $tipo): array
    {
        $samples = [
            'invito_ammissione' => [
                'link' => 'https://esempio.it/members/admission-request/xxx',
                'expiry_days' => '7',
                'appName' => 'Nome Associazione',
                'year' => (string) now()->year,
            ],
            'ricevuta' => [
                'receipt_number' => '2024-001',
                'receipt_issued_at' => '18/02/2024',
                'appName' => 'Nome Associazione',
                'receipt_amount' => '50,00',
                'recipient_name' => 'Mario Rossi',
                'year' => (string) now()->year,
            ],
            'notifica_approvazione_socio' => [
                'appName' => 'Nome Associazione',
                'member_name' => 'Mario Rossi',
                'quota_importo' => '50,00',
                'iban' => 'IT60X0542811101000000123456',
                'year' => (string) now()->year,
            ],
        ];

        return $samples[$tipo] ?? [];
    }
}
