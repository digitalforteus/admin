<?php

namespace App\Models;

use App\Sources\Db\App\Memberships;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $subject_type
 * @property string $subject_id
 * @property string $user_id
 * @property string $role
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin IdeHelperMembership
 */
class Membership extends Model
{
    /** @var string */
    protected $table = 'memberships';

    /** @var bool */
    public $incrementing = false;

    /** @var list<string> */
    protected $fillable = [
        Memberships::subject_type->value,
        Memberships::subject_id->value,
        Memberships::user_id->value,
        Memberships::role->value,
    ];
}
