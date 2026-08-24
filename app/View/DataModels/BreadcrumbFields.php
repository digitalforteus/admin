<?php

namespace App\View\DataModels;

use App\Helpers\Depth;
use App\Modules\Enterprises\EnterpriseForm;
use App\Modules\Organizations\Organizations\OrganizationForm;
use App\Modules\Projects\ProjectForm;

readonly class BreadcrumbFields
{
    /**
     * The boxes a depth can be created from inside the trail, or none where it cannot.
     *
     * @return list<array<string, mixed>>
     */
    public static function of(Depth $Depth): array
    {
        return match ($Depth) {
            Depth::enterprise => [BreadcrumbField::of(EnterpriseForm::textInput(EnterpriseForm::name))],
            Depth::organization => [BreadcrumbField::of(OrganizationForm::textInput(OrganizationForm::name))],
            Depth::project => [BreadcrumbField::of(ProjectForm::textInput(ProjectForm::name))],
            Depth::connection => [],
        };
    }
}
