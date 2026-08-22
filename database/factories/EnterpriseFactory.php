<?php

namespace Database\Factories;

use App\Models\Enterprise;
use App\Sources\Db\App\Enterprises;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Enterprise> */
class EnterpriseFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            Enterprises::name->value => $name,
            Enterprises::slug->value => Str::slug($name).'-'.Str::lower(Str::random(6)),
        ];
    }
}
