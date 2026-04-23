<?php

namespace App\Http\Controllers;

use App\Models\Template;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TemplateController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,segreteria');
    }

    public function index(Request $request)
    {
        $query = Template::orderBy('categoria')->orderBy('nome');

        if ($request->filled('search')) {
            $query->where('nome', 'like', '%' . $request->search . '%');
        }
        if ($request->filled('categoria')) {
            $query->where('categoria', $request->categoria);
        }

        $templates = $query->paginate(15)->withQueryString();

        return Inertia::render('Templates/Index', [
            'templates' => $templates,
            'filters' => $request->only('search', 'categoria'),
        ]);
    }

    public function create()
    {
        return Inertia::render('Templates/Create', [
            'tipoOptions' => [
                ['value' => '', 'label' => 'Per tutti i tipi'],
                ['value' => 'consiglio_direttivo', 'label' => 'Consiglio direttivo'],
                ['value' => 'assemblea', 'label' => 'Assemblea'],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'categoria' => 'required|in:documento,verbale',
            'tipo_verbale' => 'nullable|in:consiglio_direttivo,assemblea',
            'contenuto' => 'nullable|string',
        ]);
        if ($validated['categoria'] === 'documento') {
            $validated['tipo_verbale'] = null;
        }
        if (isset($validated['tipo_verbale']) && $validated['tipo_verbale'] === '') {
            $validated['tipo_verbale'] = null;
        }
        Template::create($validated);

        return redirect()->route('templates.index')
            ->with('flash', ['type' => 'success', 'message' => 'Template creato.']);
    }

    public function edit(Template $template)
    {
        return Inertia::render('Templates/Edit', [
            'template' => $template,
            'tipoOptions' => [
                ['value' => '', 'label' => 'Per tutti i tipi'],
                ['value' => 'consiglio_direttivo', 'label' => 'Consiglio direttivo'],
                ['value' => 'assemblea', 'label' => 'Assemblea'],
            ],
        ]);
    }

    public function update(Request $request, Template $template)
    {
        if (! $request->boolean('force_overwrite')) {
            $submittedAt = $request->input('_updated_at');
            if ($submittedAt !== null && $submittedAt !== '') {
                $template->refresh();
                if (\Carbon\Carbon::parse($template->updated_at)->gt(\Carbon\Carbon::parse($submittedAt))) {
                    return redirect()->back()
                        ->withErrors(['stale' => 'Questo template è stato modificato da un altro utente. Vuoi sovrascrivere comunque?'])
                        ->withInput();
                }
            }
        }

        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'categoria' => 'required|in:documento,verbale',
            'tipo_verbale' => 'nullable|in:consiglio_direttivo,assemblea',
            'contenuto' => 'nullable|string',
        ]);
        if ($validated['categoria'] === 'documento') {
            $validated['tipo_verbale'] = null;
        }
        if (isset($validated['tipo_verbale']) && $validated['tipo_verbale'] === '') {
            $validated['tipo_verbale'] = null;
        }
        $template->update($validated);

        return redirect()->route('templates.index')
            ->with('flash', ['type' => 'success', 'message' => 'Template aggiornato.']);
    }

    public function destroy(Template $template)
    {
        $template->delete();

        return redirect()->route('templates.index')
            ->with('flash', ['type' => 'success', 'message' => 'Template eliminato.']);
    }
}
