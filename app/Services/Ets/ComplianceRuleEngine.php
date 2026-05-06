<?php

namespace App\Services\Ets;

use App\Models\EtsAttoCostituivo;
use App\Models\EtsComplianceCheck;
use App\Models\EtsComplianceCheckResult;
use App\Models\EtsComplianceRule;
use App\Models\EtsStatuto;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;

/**
 * Motore di compliance parametrico.
 *
 * Le regole sono caricate da DB (ets_compliance_rules); la logica di
 * valutazione dipende dal campo `check_type`:
 *   - presenza  → verifica che un campo su Tenant sia compilato
 *   - scadenza  → verifica che una data non sia scaduta (con preavviso)
 *   - documento → verifica che esista un EtsStatuto o EtsAttoCostituivo nel dato stato
 *   - manuale   → restituisce sempre 'non_applicabile' (da verificare a mano)
 */
final class ComplianceRuleEngine
{
    public function esegui(Tenant $tenant, ?User $user = null): EtsComplianceCheck
    {
        $forma  = $tenant->forma_giuridica ?? '';
        $rules  = EtsComplianceRule::attive()->get();

        $check = EtsComplianceCheck::create([
            'tenant_id'        => $tenant->id,
            'run_by_user_id'   => $user?->id,
            'stato'            => self::STATO_COMPLETATO,
            'totale'           => $rules->count(),
        ]);

        $counts = ['ok' => 0, 'avviso' => 0, 'errore' => 0, 'na' => 0];

        foreach ($rules as $rule) {
            [$esito, $valore, $messaggio, $suggerimento] = $this->valutaRegola($rule, $tenant, $forma);

            EtsComplianceCheckResult::create([
                'compliance_check_id' => $check->id,
                'rule_id'             => $rule->id,
                'esito'               => $esito,
                'valore_rilevato'     => $valore,
                'messaggio'           => $messaggio,
                'suggerimento'        => $suggerimento,
            ]);

            $counts[$esito === 'non_applicabile' ? 'na' : $esito]++;
        }

        $check->update([
            'n_ok'     => $counts['ok'],
            'n_avviso' => $counts['avviso'],
            'n_errore' => $counts['errore'],
            'n_na'     => $counts['na'],
            'stato'    => $counts['errore'] > 0 ? EtsComplianceCheck::STATO_CON_ERRORI : EtsComplianceCheck::STATO_COMPLETATO,
        ]);

        return $check->load('results.rule');
    }

    // ──────────────────────────────────────────────────────────────────────────

    private function valutaRegola(EtsComplianceRule $rule, Tenant $tenant, string $forma): array
    {
        // Verifica se la regola si applica alla forma giuridica corrente
        if ($rule->forme_applicabili !== null && !in_array($forma, $rule->forme_applicabili, true)) {
            return ['non_applicabile', null, 'Non applicabile a questa forma giuridica.', null];
        }

        $config = $rule->check_config ?? [];

        return match ($rule->check_type) {
            'presenza'   => $this->checkPresenza($rule, $tenant, $config),
            'scadenza'   => $this->checkScadenza($rule, $tenant, $config),
            'documento'  => $this->checkDocumento($rule, $tenant, $config),
            'manuale'    => ['non_applicabile', null, 'Controllo manuale — verificare a mano.', $config['suggerimento'] ?? null],
            default      => ['non_applicabile', null, 'Tipo controllo non riconosciuto: '.$rule->check_type, null],
        };
    }

    private function checkPresenza(EtsComplianceRule $rule, Tenant $tenant, array $config): array
    {
        $campo = $config['campo'] ?? null;
        if (!$campo) {
            return ['non_applicabile', null, 'Configurazione regola incompleta (campo mancante).', null];
        }

        $valore = $tenant->{$campo};

        // Array vuoto = mancante
        if (is_array($valore) && empty($valore)) {
            $valore = null;
        }

        $mancante = $valore === null || $valore === '';

        if ($mancante) {
            return [
                $rule->severita === 'errore' ? 'errore' : 'avviso',
                null,
                "Campo '{$campo}' non compilato.",
                $config['suggerimento'] ?? 'Compilare il campo nel wizard di configurazione.',
            ];
        }

        $display = is_array($valore)
            ? count($valore).' voci selezionate'
            : (string) $valore;

        return ['ok', $display, null, null];
    }

    private function checkScadenza(EtsComplianceRule $rule, Tenant $tenant, array $config): array
    {
        $campo = $config['campo'] ?? null;
        if (!$campo) {
            return ['non_applicabile', null, 'Configurazione regola incompleta (campo mancante).', null];
        }

        $rawData = $tenant->{$campo};

        if (!$rawData) {
            return [
                $rule->severita === 'errore' ? 'errore' : 'avviso',
                null,
                "Data scadenza '{$campo}' non compilata.",
                $config['suggerimento'] ?? 'Inserire la data di scadenza.',
            ];
        }

        $oggi        = Carbon::now()->startOfDay();
        $scadenza    = Carbon::parse($rawData);
        $preavviso   = (int) ($config['giorni_preavviso'] ?? 60);

        if ($scadenza->lt($oggi)) {
            return [
                'errore',
                $scadenza->format('d/m/Y'),
                "Scadenza superata il {$scadenza->format('d/m/Y')}.",
                $config['suggerimento_scaduto'] ?? 'Rinnovare immediatamente.',
            ];
        }

        if ($scadenza->lt($oggi->copy()->addDays($preavviso))) {
            return [
                'avviso',
                $scadenza->format('d/m/Y'),
                "Scade il {$scadenza->format('d/m/Y')} (entro {$preavviso} giorni).",
                $config['suggerimento_in_scadenza'] ?? 'Procedere al rinnovo.',
            ];
        }

        return ['ok', $scadenza->format('d/m/Y'), null, null];
    }

    private function checkDocumento(EtsComplianceRule $rule, Tenant $tenant, array $config): array
    {
        $modello = $config['modello'] ?? null;
        $stato   = $config['stato'] ?? null;

        $esiste = match ($modello) {
            'EtsStatuto' => EtsStatuto::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenant->id)
                ->when($stato, fn ($q) => $q->where('stato', $stato))
                ->exists(),

            'EtsAttoCostituivo' => EtsAttoCostituivo::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenant->id)
                ->when($stato, fn ($q) => $q->where('stato', $stato))
                ->exists(),

            default => false,
        };

        if (!$esiste) {
            $label = $modello . ($stato ? " ({$stato})" : '');
            return [
                $rule->severita === 'errore' ? 'errore' : 'avviso',
                'Assente',
                "Documento '{$label}' non trovato.",
                $config['suggerimento'] ?? 'Caricare e approvare il documento richiesto.',
            ];
        }

        return ['ok', 'Presente', null, null];
    }

    private const STATO_COMPLETATO = EtsComplianceCheck::STATO_COMPLETATO;
}
