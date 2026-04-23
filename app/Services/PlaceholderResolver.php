<?php

namespace App\Services;

use App\Models\CaricaSociale;
use App\Models\Location;
use App\Models\Member;
use App\Models\Organo;
use App\Models\Settings;
use Carbon\Carbon;

/**
 * Risolve i placeholder {{key}} e {{key±offset}} (es. {{ora+30m}}) nel contenuto HTML
 * di verbali e documenti. Usato al salvataggio (store/update); i template non vengono risolti.
 */
class PlaceholderResolver
{
    /** Pattern: {{key}} o {{key+30m}} / {{ora-1h}} / {{anno-1}}. Key solo lettere e trattini (no cifre) così offset -1/-1h non viene inglobato. */
    private const PLACEHOLDER_PATTERN = '/\{\{([a-z]+(?:-[a-z]+)*)([+-](?:\d+h)?(?:\d+m)?|[+-]\d+)?\}\}/';

    public static function resolve(string $html, array $context = []): string
    {
        $baseDate = isset($context['data']) && $context['data'] instanceof Carbon
            ? $context['data']
            : now();

        return preg_replace_callback(self::PLACEHOLDER_PATTERN, function (array $m) use ($baseDate) {
            $key = $m[1];
            $offsetRaw = $m[2] ?? null;
            $value = self::resolvePlaceholder($key, $offsetRaw, $baseDate);
            $str = (string) $value;
            // direttivo e richieste-soci restituiscono HTML con <br>: non escapare per permettere gli a capo
            if ($key === 'direttivo' || $key === 'richieste-soci') {
                return $str;
            }
            return e($str);
        }, $html);
    }

    private static function resolvePlaceholder(string $key, ?string $offsetRaw, Carbon $baseDate): string
    {
        return match ($key) {
            'nome-associazione' => (string) Settings::get('nome_associazione', ''),
            'data' => $baseDate->format('d/m/Y'),
            'ora' => self::resolveOra(now(), $offsetRaw ?? ''),
            'sede' => (string) Settings::get('indirizzo_associazione', ''),
            'sede-legale' => self::resolveSedeLegale(),
            'sede-operativa' => self::resolveSedeOperativa(),
            'n-soci' => (string) Member::where('stato', 'attivo')->count(),
            'anno' => self::resolveAnno($baseDate, $offsetRaw ?? ''),
            'presidente' => self::resolvePersonaByCarica('Presidente'),
            'segretario' => self::resolvePersonaByCarica('Segretario'),
            'tesoriere' => self::resolvePersonaByCarica('Tesoriere'),
            'direttivo' => self::resolveDirettivo(),
            'richieste-soci' => self::resolveRichiesteSoci(),
            default => '{{' . $key . ($offsetRaw !== null && $offsetRaw !== '' ? $offsetRaw : '') . '}}',
        };
    }

    private static function resolveOra(Carbon $baseDate, string $offsetRaw): string
    {
        if ($offsetRaw === '') {
            return $baseDate->format('H:i');
        }
        $sign = str_starts_with($offsetRaw, '-') ? -1 : 1;
        $date = $baseDate->copy();
        if (preg_match('/(\d+)h/', $offsetRaw, $hm)) {
            $date->addHours($sign * (int) $hm[1]);
        }
        if (preg_match('/(\d+)m/', $offsetRaw, $mm)) {
            $date->addMinutes($sign * (int) $mm[1]);
        }

        return $date->format('H:i');
    }

    private static function resolveAnno(Carbon $baseDate, string $offsetRaw): string
    {
        $year = $baseDate->year;
        if ($offsetRaw !== '' && preg_match('/^([+-])(\d+)$/', $offsetRaw, $mm)) {
            $sign = $mm[1] === '+' ? 1 : -1;
            $year += $sign * (int) $mm[2];
        }

        return (string) $year;
    }

    private static function resolvePersonaByCarica(string $nomeCarica): string
    {
        $organo = self::getOrganoConsiglioDirettivo();
        if ($organo === null) {
            return '—';
        }
        $carica = CaricaSociale::where('organo_id', $organo->id)
            ->whereRaw('LOWER(nome) = ?', [strtolower($nomeCarica)])
            ->first();
        if ($carica === null) {
            return '—';
        }
        $incarico = $carica->incarichi()->with('member')->orderByDesc('id')->first();
        if ($incarico === null || $incarico->member === null) {
            return '—';
        }

        return $incarico->member->full_name;
    }

    private static function getOrganoConsiglioDirettivo(): ?Organo
    {
        return Organo::where('slug', 'consiglio_direttivo')->first();
    }

    private static function resolveSedeLegale(): string
    {
        $location = Location::where('tipo', Location::TIPO_LEGALE)->first();
        if ($location && trim((string) $location->address) !== '') {
            return (string) $location->address;
        }

        return (string) Settings::get('indirizzo_associazione', '');
    }

    private static function resolveSedeOperativa(): string
    {
        $location = Location::where('tipo', Location::TIPO_OPERATIVA)->orderBy('id')->first();
        if ($location && trim((string) $location->address) !== '') {
            return (string) $location->address;
        }

        return '—';
    }

    private static function resolveDirettivo(): string
    {
        $organo = self::getOrganoConsiglioDirettivo();
        if ($organo === null) {
            return '—';
        }

        $caricheSociali = $organo->caricheSociali;
        $memberCariche = [];
        foreach ($caricheSociali as $carica) {
            $incarico = $carica->incarichi()->with('member')->orderByDesc('id')->first();
            if ($incarico && $incarico->member) {
                $memberId = $incarico->member_id;
                if (! isset($memberCariche[$memberId])) {
                    $memberCariche[$memberId] = ['name' => $incarico->member->full_name, 'cariche' => []];
                }
                $memberCariche[$memberId]['cariche'][] = $carica->nome;
            }
        }
        if (empty($memberCariche)) {
            return '—';
        }
        $lines = [];
        foreach ($memberCariche as $row) {
            $caricheStr = implode(', ', array_map('e', $row['cariche']));
            $lines[] = e($row['name']) . ' – ' . $caricheStr;
        }

        return implode('<br>', $lines);
    }

    private static function resolveRichiesteSoci(): string
    {
        $aspiranti = Member::where('stato', 'aspirante')
            ->orderBy('domanda_presentata_at')
            ->get(['nome', 'cognome', 'domanda_presentata_at']);
        $lines = [];
        foreach ($aspiranti as $m) {
            $nome = trim($m->nome . ' ' . $m->cognome);
            $data = $m->domanda_presentata_at
                ? $m->domanda_presentata_at->format('d/m/Y')
                : '—';
            $lines[] = e($nome) . ' il ' . e($data);
        }

        return implode('<br>', $lines);
    }
}
