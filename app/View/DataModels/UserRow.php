<?php

namespace App\View\DataModels;

use App\Helpers\DataModel;
use App\Helpers\Gravatar;
use App\Helpers\SvgName;
use App\Routes\Admin;
use App\Sources\Db\App\Users;
use Illuminate\Support\Carbon;
use Zerotoprod\DataModel\Describe;

readonly class UserRow
{
    use DataModel;
    use FormatsTableCell;

    public const string id = 'id';

    #[Describe([Describe::required => true])]
    public string $id;

    public const string name = 'name';

    #[Describe([Describe::required => true])]
    public string $name;

    public const string picture = 'picture';

    #[Describe([Describe::default => null])]
    public ?string $picture;

    public const string email = 'email';

    #[Describe([Describe::required => true])]
    public string $email;

    public const string email_verified_at = 'email_verified_at';

    #[Describe([Describe::default => null])]
    public ?string $email_verified_at;

    public const string created_at = 'created_at';

    #[Describe([Describe::default => null])]
    public ?string $created_at;

    public const string last_session_at = 'last_session_at';

    #[Describe([Describe::default => null])]
    public ?int $last_session_at;

    public function editUrl(): string
    {
        return Admin::user->url([Admin::userParameter => $this->id]);
    }

    public function picture(): string
    {
        return $this->picture ?? Gravatar::url($this->email);
    }

    /** @return array<string, mixed> */
    public function avatar(): array
    {
        return [
            Avatar::name => $this->name,
            Avatar::picture => $this->picture(),
            Avatar::size => 'w-8',
            Avatar::fallback => SvgName::user,
        ];
    }

    public function emailVerified(): bool
    {
        return $this->collect()->get(self::email_verified_at) !== null;
    }

    /** @return list<string> */
    public function cells(): array
    {
        return [
            ...array_map(fn (Users $Column): string => $this->cell($Column), UsersTable::columns()),
            $this->lastSession(),
        ];
    }

    public function lastSession(): string
    {
        $timestamp = $this->collect()->get(self::last_session_at);

        return is_numeric($timestamp)
            ? Carbon::createFromTimestamp((int) $timestamp)->diffForHumans()
            : '—';
    }

    public function cell(Users $Users): string
    {
        return $this->formattedCell($this->collect()->get($Users->value), $Users->type());
    }
}
