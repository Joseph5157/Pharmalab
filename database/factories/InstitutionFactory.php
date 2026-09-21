<?php

namespace Database\Factories;

use App\Models\Institution;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Institution> */
class InstitutionFactory extends Factory
{
    protected $model = Institution::class;

    public function definition(): array
    {
        $name = fake()->unique()->company().' College of Pharmacy';

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'status' => 'active',
            'timezone' => 'Asia/Kolkata',
        ];
    }
}
