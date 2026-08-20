<?php

namespace App\Modules\Login;

use App\Helpers\DataModel;
use Illuminate\Support\Str;
use Zerotoprod\DataModel\Describe;

class GitHubUser
{
    use DataModel;

    public const string id = 'id';

    #[Describe([Describe::required => true])]
    public string $id;

    public const string login = 'login';

    #[Describe([Describe::required => true])]
    public string $login;

    public const string name = 'name';

    #[Describe([Describe::default => null])]
    public ?string $name = null;

    public const string email = 'email';

    #[Describe([
        Describe::default => null,
        Describe::cast => [self::class, 'castEmail'],
    ])]
    public ?string $email = null;

    public static function castEmail(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return Str::lower(trim($value));
    }

    public const string avatar_url = 'avatar_url';

    #[Describe([Describe::default => null])]
    public ?string $avatar_url = null;

    public const string bio = 'bio';

    #[Describe([Describe::default => null])]
    public ?string $bio = null;

    public const string blog = 'blog';

    #[Describe([Describe::default => null])]
    public ?string $blog = null;

    public const string company = 'company';

    #[Describe([Describe::default => null])]
    public ?string $company = null;

    public const string location = 'location';

    #[Describe([Describe::default => null])]
    public ?string $location = null;

    public function hasVerifiedEmail(): bool
    {
        return $this->email !== null
            && filter_var($this->email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function getDisplayName(): string
    {
        return $this->name ?: $this->login;
    }
}
