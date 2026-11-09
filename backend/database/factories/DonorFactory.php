<?php

namespace Database\Factories;

use App\Models\Donor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Donor> */
class DonorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Fictional '.fake()->name(),
            'blood_type' => fake()->randomElement(['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-']),
            'municipality' => 'Sample Municipality',
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
            'creation_key' => (string) Str::uuid(),
            'creation_hash' => hash('sha256', 'fixture'),
        ];
    }
}
