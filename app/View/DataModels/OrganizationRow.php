<?php

namespace App\View\DataModels;

use App\Helpers\DataModel;
use App\Helpers\Disk;
use App\Helpers\Initials;
use App\Routes\Auth;
use Illuminate\Support\Carbon;
use Zerotoprod\DataModel\Describe;

#[Describe([Describe::nullable => true])]
readonly class OrganizationRow
{
    use DataModel;

    public const string id = 'id';

    #[Describe([Describe::required => true])]
    public string $id;

    public const string name = 'name';

    #[Describe([Describe::required => true])]
    public string $name;

    public const string icon = 'icon';

    public ?string $icon;

    public const string created_at = 'created_at';

    public ?string $created_at;

    public const string owns = 'owns';

    #[Describe([Describe::default => false])]
    public bool $owns;

    public function url(): string
    {
        return Auth::settingsOrganization->url([Auth::organizationParameter => $this->id]);
    }

    public function iconUrl(): ?string
    {
        return $this->icon !== null && $this->icon !== '' ? Disk::public->url($this->icon) : null;
    }

    public function initials(): string
    {
        return Initials::from($this->name);
    }

    public function createdAt(): string
    {
        return $this->created_at !== null ? Carbon::parse($this->created_at)->toFormattedDateString() : '—';
    }
}
