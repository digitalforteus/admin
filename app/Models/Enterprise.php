<?php

namespace App\Models;

use App\Sources\Db\App\Enterprises;
use Database\Factories\EnterpriseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Organization> $organizations
 * @property-read Collection<int, Connection> $connections
 *
 * @mixin IdeHelperEnterprise
 */
class Enterprise extends Model
{
    /** @use HasFactory<EnterpriseFactory> */
    use HasFactory;
    use HasUlids;

    /** @var list<string> */
    protected $fillable = [
        Enterprises::name->value,
        Enterprises::slug->value,
    ];

    /** @return HasMany<Organization, $this> */
    public function organizations(): HasMany
    {
        return $this->hasMany(Organization::class);
    }

    /** @return HasMany<Connection, $this> */
    public function connections(): HasMany
    {
        return $this->hasMany(Connection::class);
    }
}
