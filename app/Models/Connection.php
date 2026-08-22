<?php

namespace App\Models;

use App\Sources\Db\App\Connections;
use App\Sources\Db\App\OrganizationConnection;
use Database\Factories\ConnectionFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $enterprise_id
 * @property string $provider
 * @property string $name
 * @property string $slug
 * @property array<string, mixed> $credentials
 * @property array<string, mixed>|null $config
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Enterprise $enterprise
 * @property-read Collection<int, Organization> $organizations
 *
 * @mixin IdeHelperConnection
 */
class Connection extends Model
{
    /** @use HasFactory<ConnectionFactory> */
    use HasFactory;
    use HasUlids;

    /** @var list<string> */
    protected $fillable = [
        Connections::enterprise_id->value,
        Connections::provider->value,
        Connections::name->value,
        Connections::slug->value,
        Connections::credentials->value,
        Connections::config->value,
    ];

    /** @var list<string> */
    protected $hidden = [
        Connections::credentials->value,
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        Connections::config->value => null,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            Connections::credentials->value => 'encrypted:array',
            Connections::config->value => 'array',
        ];
    }

    /** @return BelongsTo<Enterprise, $this> */
    public function enterprise(): BelongsTo
    {
        return $this->belongsTo(Enterprise::class);
    }

    /** @return BelongsToMany<Organization, $this> */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, OrganizationConnection::table())
            ->withPivot(OrganizationConnection::enabled_at->value)
            ->withTimestamps();
    }
}
