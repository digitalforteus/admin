<?php

namespace App\Models;

use App\Sources\Db\App\Organizations;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property string|null $icon
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
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
        Organizations::name->value,
        Organizations::icon->value,
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        Organizations::icon->value => null,
    ];
}
