<?php

namespace Database\Factories;

use App\Models\Asset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    protected $model = Asset::class;

    public function definition(): array
    {
        return [
            'name'                     => fake()->words(3, true),
            'costo_storico'            => fake()->randomFloat(2, 500, 50000),
            'data_inizio_ammortamento' => fake()->dateTimeBetween('-5 years', 'now')->format('Y-01-01'),
            'metodo_ammortamento'      => Asset::METODO_ORDINARIO,
            'stato'                    => Asset::STATO_IN_USO,
            'primo_anno_ridotto'       => true,
            'percentuale_deducibilita' => 100.0,
        ];
    }
}
