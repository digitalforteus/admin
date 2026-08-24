<?php

namespace App\Models;

use App\Helpers\Disk;
use App\Sources\Db\App\Organizations;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $enterprise_id
 * @property string $name
 * @property string $slug
 * @property string|null $icon
 * @property string|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Enterprise                              $enterprise
 * @property-read User|null                               $creator
 * @property-read Collection<int, Project>                $projects
 * @property-read Collection<int, OrganizationInvitation> $invitations
 *
 * @mixin IdeHelperOrganization
 */
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;
    use HasUlids;

    /** @var list<string> */
    protected $fillable = [
        Organizations::enterprise_id->value,
        Organizations::name->value,
        Organizations::slug->value,
        Organizations::icon->value,
        Organizations::created_by->value,
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        Organizations::icon->value => null,
        Organizations::created_by->value => null,
    ];

    public function iconUrl(): ?string
    {
        return $this->icon !== null && $this->icon !== '' ? Disk::public->url($this->icon) : null;
    }

    /** @return BelongsTo<Enterprise, $this> */
    public function enterprise(): BelongsTo
    {
        return $this->belongsTo(Enterprise::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, Organizations::created_by->value);
    }

    /** @return HasMany<Project, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /** @return HasMany<OrganizationInvitation, $this> */
    public function invitations(): HasMany
    {
        return $this->hasMany(OrganizationInvitation::class);
    }
}
