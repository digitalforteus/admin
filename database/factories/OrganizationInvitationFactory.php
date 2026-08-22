<?php

namespace Database\Factories;

use App\Helpers\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Sources\Db\App\OrganizationInvitations;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<OrganizationInvitation> */
class OrganizationInvitationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            OrganizationInvitations::organization_id->value => Organization::factory(),
            OrganizationInvitations::email->value => fake()->unique()->safeEmail(),
            OrganizationInvitations::role->value => OrganizationRole::member,
            OrganizationInvitations::token->value => Str::random(48),
            OrganizationInvitations::expires_at->value => now()->addWeek(),
        ];
    }
}
