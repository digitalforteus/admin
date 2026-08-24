<?php

namespace App\Models;

use App\Helpers\Disk;
use App\Sources\Db\App\ProjectConnection;
use App\Sources\Db\App\Projects;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $name
 * @property string $slug
 * @property string|null $icon
 * @property string|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read User|null    $creator
 * @property-read Collection<int, Connection> $connections
 *
 * @mixin IdeHelperProject
 */
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;
    use HasUlids;

    /** @var list<string> */
    protected $fillable = [
        Projects::organization_id->value,
        Projects::name->value,
        Projects::slug->value,
        Projects::icon->value,
        Projects::created_by->value,
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        Projects::icon->value => null,
        Projects::created_by->value => null,
    ];

    public function resolveChildRouteBinding($childType, $value, $field): ?Model
    {
        $Relation = $this->{Str::plural(Str::camel($childType))}();

        return $Relation->getModel()->resolveRouteBinding($value, $field);
    }

    /** @return BelongsToMany<Connection, $this> */
    public function connections(): BelongsToMany
    {
        return $this->belongsToMany(Connection::class, ProjectConnection::table())
            ->withPivot(ProjectConnection::enabled_at->value)
            ->withTimestamps();
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, Projects::created_by->value);
    }

    public function iconUrl(): ?string
    {
        return $this->icon !== null && $this->icon !== '' ? Disk::public->url($this->icon) : null;
    }
}
