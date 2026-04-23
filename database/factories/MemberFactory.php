<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\MemberType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Member>
 */
class MemberFactory extends Factory
{
    protected $model = Member::class;

    public function definition(): array
    {
        return [
            'member_type_id' => MemberType::query()->inRandomOrder()->first()?->id ?? 1,
            'nome' => fake()->firstName(),
            'cognome' => fake()->lastName(),
            'email' => fake()->optional()->safeEmail(),
            'codice_fiscale' => null,
            'data_iscrizione' => fake()->dateTimeBetween('-2 years'),
            'stato' => 'attivo',
            'indirizzo' => fake()->optional()->address(),
            'telefono' => fake()->optional()->phoneNumber(),
            'note' => null,
        ];
    }
}
