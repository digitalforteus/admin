<?php

namespace Database\Factories;

use App\Models\Connection;
use App\Models\Enterprise;
use App\Modules\Connections\ConnectionProvider;
use App\Sources\Db\App\Connections;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Connection> */
class ConnectionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            Connections::enterprise_id->value => Enterprise::factory(),
            Connections::provider->value => ConnectionProvider::github->name,
            Connections::name->value => $name,
            Connections::slug->value => Str::slug((string) $name).'-'.Str::lower(Str::random(6)),
            Connections::credentials->value => [],
            Connections::config->value => [],
        ];
    }
}
