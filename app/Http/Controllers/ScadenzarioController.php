<?php

namespace App\Http\Controllers;

use App\Models\EmailTemplate;
use App\Models\Member;
use App\Models\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Scadenzario quote: lista soci morosi + invio solleciti.
 */
class ScadenzarioController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,contabile');
    }

    public function index(Request $request)
    {
        $anno       = $request->filled('anno')        ? (int) $request->anno        : now()->year;
        $statoSocio = $request->filled('stato_socio') ? $request->stato_socio       : 'attivo';

        $query = Member::query()
            ->when($statoSocio !== 'tutti', fn ($q) => $q->where('stato', $statoSocio))
            ->whereDoesntHave('incassi', function ($q) use ($anno) {
                $q->where('type', 'quota')->whereYear('paid_at', $anno);
            })
            ->orderBy('cognome')
            ->orderBy('nome');

        if ($request->filled('search')) {
            $term = '%' . $request->search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('nome', 'like', $term)
                  ->orWhere('cognome', 'like', $term)
                  ->orWhere('email', 'like', $term);
            });
        }

        $morosi = $query->paginate(50)->withQueryString();

        $quotaAnnuale    = (float) Settings::get('quota_annuale', 0);
        $anniDisponibili = range(now()->year, max(now()->year - 4, 2020));

        // Flag template configurato
        $templatePresente = EmailTemplate::where('tipo', 'sollecito_quota')->exists();

        return Inertia::render('Scadenzario/Index', [
            'morosi'           => $morosi,
            'anno'             => $anno,
            'statoSocio'       => $statoSocio,
            'quotaAnnuale'     => $quotaAnnuale,
            'anniDisponibili'  => $anniDisponibili,
            'templatePresente' => $templatePresente,
            'filters'          => $request->only('anno', 'stato_socio', 'search'),
        ]);
    }

    public function sendSollecito(Member $member, Request $request)
    {
        $anno   = $request->filled('anno') ? (int) $request->anno : now()->year;
        $result = $this->inviaEmail($member, $anno);

        if ($result['sent']) {
            return redirect()->back()->with('flash', [
                'type'    => 'success',
                'message' => "Sollecito inviato a {$member->full_name}.",
            ]);
        }

        return redirect()->back()->with('flash', [
            'type'    => 'error',
            'message' => $result['error'] ?? 'Errore durante l\'invio.',
        ]);
    }

    public function sendSollecitoMassivo(Request $request)
    {
        $request->validate([
            'member_ids'   => 'required|array|min:1|max:200',
            'member_ids.*' => 'integer|exists:members,id',
        ]);

        $anno    = $request->filled('anno') ? (int) $request->anno : now()->year;
        $sent    = 0;
        $errors  = 0;
        $noEmail = 0;

        $members = Member::whereIn('id', $request->member_ids)->get();

        foreach ($members as $member) {
            if (empty(trim((string) $member->email))) {
                $noEmail++;
                continue;
            }

            $result = $this->inviaEmail($member, $anno);

            if ($result['sent']) {
                $sent++;
            } else {
                $errors++;
            }

            // Rate limiting leggero: 100 ms tra un invio e l'altro
            usleep(100_000);
        }

        $msg = "Solleciti inviati: {$sent}.";
        if ($noEmail > 0) {
            $msg .= " Senza indirizzo email: {$noEmail}.";
        }
        if ($errors > 0) {
            $msg .= " Errori: {$errors}.";
        }

        return redirect()->back()->with('flash', [
            'type'    => $errors > 0 ? 'warning' : 'success',
            'message' => $msg,
        ]);
    }

    public function exportMorosi(Request $request): StreamedResponse
    {
        $anno       = $request->filled('anno')        ? (int) $request->anno        : now()->year;
        $statoSocio = $request->filled('stato_socio') ? $request->stato_socio       : 'attivo';

        $morosi = Member::query()
            ->when($statoSocio !== 'tutti', fn ($q) => $q->where('stato', $statoSocio))
            ->whereDoesntHave('incassi', function ($q) use ($anno) {
                $q->where('type', 'quota')->whereYear('paid_at', $anno);
            })
            ->orderBy('cognome')
            ->orderBy('nome')
            ->get(['id', 'nome', 'cognome', 'email', 'data_iscrizione', 'stato']);

        $quotaAnnuale = (float) Settings::get('quota_annuale', 0);
        $filename     = 'morosi_' . $anno . '.csv';

        return response()->streamDownload(function () use ($morosi, $quotaAnnuale, $anno) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8
            fputcsv($out, ['Cognome', 'Nome', 'Email', 'Stato', 'Iscritto dal', "Quota dovuta {$anno}"]);
            foreach ($morosi as $m) {
                fputcsv($out, [
                    $m->cognome,
                    $m->nome,
                    $m->email ?? '',
                    $m->stato,
                    $m->data_iscrizione?->format('d/m/Y') ?? '',
                    number_format($quotaAnnuale, 2, '.', ''),
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Helpers privati
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Invia email sollecito quota a un socio.
     * Restituisce ['sent' => bool, 'error' => string|null].
     */
    private function inviaEmail(Member $member, int $anno): array
    {
        if (empty(trim((string) $member->email))) {
            return ['sent' => false, 'error' => 'Nessun indirizzo email per questo socio.'];
        }

        $quotaAnnuale     = (float) Settings::get('quota_annuale', 0);
        $nomeAssociazione = Settings::get('nome_associazione', config('app.name'));

        $replacements = [
            'nome_socio'        => $member->full_name,
            'anno'              => (string) $anno,
            'quota_annuale'     => number_format($quotaAnnuale, 2, ',', '.'),
            'nome_associazione' => $nomeAssociazione,
            'appName'           => $nomeAssociazione,
        ];

        $rendered = EmailTemplate::render('sollecito_quota', $replacements);

        if (! $rendered) {
            return ['sent' => false, 'error' => 'Template "sollecito_quota" non configurato. Configuralo in Impostazioni → Template email.'];
        }

        try {
            Mail::html($rendered['body'], function ($message) use ($member, $rendered) {
                $message->to($member->email)->subject($rendered['subject']);
            });

            return ['sent' => true, 'error' => null];
        } catch (\Throwable $e) {
            report($e);

            return ['sent' => false, 'error' => $e->getMessage()];
        }
    }
}
