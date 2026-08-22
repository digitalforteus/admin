<?php

namespace App\Modules\Connections;

use App\Helpers\SvgName;
use App\Models\Connection;
use App\Models\Organization;
use App\View\DataModels\NavItem;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Contracts\View\View;

interface ConnectionPlugin
{
    public function label(): string;

    public function icon(): SvgName;

    /** @return list<array<string, mixed>> */
    public function form(): array;

    /** @return list<string> */
    public function secrets(): array;

    /** @param  array<string, mixed>  $fields */
    public function validate(array $fields): Validator;

    public function verify(Connection $Connection): bool;

    /** @return list<NavItem> */
    public function navItems(Organization $Organization, Connection $Connection): array;

    public function page(Organization $Organization, Connection $Connection): View;
}
