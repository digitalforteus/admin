<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Project;
use App\Sources\Db\App\Projects;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Project> */
class ProjectFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->catchPhrase();

        return [
            Projects::organization_id->value => Organization::factory(),
            Projects::name->value => $name,
            Projects::slug->value => Str::slug($name).'-'.Str::lower(Str::random(6)),
        ];
    }
}
