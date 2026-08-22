<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Sources\Db\App\Organizations;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Organization> */
class OrganizationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            Organizations::name->value => fake()->company(),
        ];
    }
}
