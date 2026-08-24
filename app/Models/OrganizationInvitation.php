<?php

namespace App\Models;

use App\Helpers\MemberRole;
use App\Sources\Db\App\OrganizationInvitations;
use Database\Factories\OrganizationInvitationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $email
 * @property MemberRole $role
 * @property string $token
 * @property Carbon $expires_at
 * @property string|null $invited_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read User|null $inviter
 *
 * @mixin IdeHelperOrganizationInvitation
 */
class OrganizationInvitation extends Model
{
    /** @use HasFactory<OrganizationInvitationFactory> */
    use HasFactory;
    use HasUlids;

    /** @var list<string> */
    protected $fillable = [
        OrganizationInvitations::organization_id->value,
        OrganizationInvitations::email->value,
        OrganizationInvitations::role->value,
        OrganizationInvitations::token->value,
        OrganizationInvitations::expires_at->value,
        OrganizationInvitations::invited_by->value,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            OrganizationInvitations::role->value => MemberRole::class,
            OrganizationInvitations::expires_at->value => 'datetime',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<User, $this> */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, OrganizationInvitations::invited_by->value);
    }

    public function expired(): bool
    {
        return $this->expires_at->isPast();
    }
}
