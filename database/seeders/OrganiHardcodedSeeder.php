<?php

namespace Database\Seeders;

use App\Models\CaricaSociale;
use App\Models\Organo;
use Illuminate\Database\Seeder;

class OrganiHardcodedSeeder extends Seeder
{
    public function run(): void
    {
        $organi = config('organi.organi', []);

        foreach ($organi as $item) {
            $slug = $item['slug'] ?? null;
            $nome = $item['nome'] ?? '';
            $durataMesi = isset($item['durata_mesi']) ? (int) $item['durata_mesi'] : null;
            $richiediElezione = (bool) ($item['richiedi_elezioni_fine_mandato'] ?? false);
            $cariche = $item['cariche'] ?? [];

            if ($slug === null || $nome === '') {
                continue;
            }

            $organo = Organo::where('slug', $slug)->first();
            if (! $organo) {
                $organo = Organo::where('nome', $nome)->first();
                if ($organo) {
                    $organo->update([
                        'slug' => $slug,
                        'nome' => $nome,
                        'durata_mesi' => $durataMesi,
                        'richiedi_elezioni_fine_mandato' => $richiediElezione,
                    ]);
                } else {
                    $organo = Organo::create([
                        'slug' => $slug,
                        'nome' => $nome,
                        'durata_mesi' => $durataMesi,
                        'richiedi_elezioni_fine_mandato' => $richiediElezione,
                    ]);
                }
            } else {
                $organo->update([
                    'nome' => $nome,
                    'durata_mesi' => $durataMesi,
                    'richiedi_elezioni_fine_mandato' => $richiediElezione,
                ]);
            }

            foreach ($cariche as $c) {
                $nomeCarica = $c['nome'] ?? '';
                $ordine = (int) ($c['ordine'] ?? 0);
                $multiplo = (bool) ($c['multiplo'] ?? false);
                if ($nomeCarica === '') {
                    continue;
                }
                $carica = CaricaSociale::firstOrCreate(
                    [
                        'organo_id' => $organo->id,
                        'nome' => $nomeCarica,
                    ],
                    ['ordine' => $ordine, 'multiplo' => $multiplo]
                );
                if (! $carica->wasRecentlyCreated) {
                    $carica->update(['ordine' => $ordine, 'multiplo' => $multiplo]);
                }
            }
        }
    }
}
