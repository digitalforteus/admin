<?php

namespace App\Models;

use App\Helpers\Theme;
use App\Sources\Db\App\OrganizationUser;
use App\Sources\Db\App\Users;
use Database\Factories\UserFactory;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\PasskeyAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property string $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $picture
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property Theme $theme
 * @property string|null $remember_token
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, OauthProvider> $oauthProviders
 * @property-read Collection<int, Organization> $organizations
 * @property-read Collection<int, Passkey> $passkeys
 *
 * @mixin IdeHelperUser
 */
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasApiTokens<PersonalAccessToken> */
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;
    use HasRoles;
    use HasUlids;
    use Notifiable;
    use PasskeyAuthenticatable;
    use TwoFactorAuthenticatable;

    /** @var list<string> */
    protected $fillable = [
        Users::name->value,
        Users::email->value,
        Users::phone->value,
        Users::picture->value,
        Users::password->value,
        Users::theme->value,
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        Users::theme->value => Theme::auto->value,
        Users::picture->value => null,
        Users::two_factor_secret->value => null,
        Users::two_factor_recovery_codes->value => null,
        Users::two_factor_confirmed_at->value => null,
    ];

    /** @var list<string> */
    protected $hidden = [
        Users::password->value,
        Users::remember_token->value,
        Users::two_factor_secret->value,
        Users::two_factor_recovery_codes->value,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            Users::email_verified_at->value => 'datetime',
            Users::password->value => 'hashed',
            Users::theme->value => Theme::class,
            Users::two_factor_confirmed_at->value => 'datetime',
        ];
    }

    public static function authenticated(Request $Request): self
    {
        $User = $Request->user();

        if (! $User instanceof self) {
            throw new AuthenticationException('Unauthenticated');
        }

        return $User;
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $Authenticated = Auth::user();

        if ($Authenticated instanceof self
            && ($field === null || $field === $Authenticated->getRouteKeyName())
            && $Authenticated->id === $value) {
            return $Authenticated;
        }

        return parent::resolveRouteBinding($value, $field);
    }

    /** @return HasMany<OauthProvider, $this> */
    public function oauthProviders(): HasMany
    {
        return $this->hasMany(OauthProvider::class);
    }

    /** @return BelongsToMany<Organization, $this> */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, OrganizationUser::table())
            ->withPivot(OrganizationUser::role->value)
            ->withTimestamps();
    }
}
