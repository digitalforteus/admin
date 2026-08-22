<?php

namespace Database\Factories;

use App\Models\Enterprise;
use App\Models\Organization;
use App\Sources\Db\App\Organizations;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Organization> */
class OrganizationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            Organizations::enterprise_id->value => Enterprise::factory(),
            Organizations::name->value => $name,
            Organizations::slug->value => Str::slug($name).'-'.Str::lower(Str::random(6)),
        ];
    }
}
