<?php

namespace App\View\DataModels;

use App\Helpers\Depth;

readonly class BreadcrumbFields
{
    /**
     * The boxes a depth can be created from inside the trail, or none where it cannot.
     *
     * @return list<array<string, mixed>>
     */
    public static function of(Depth $Depth): array
    {
        $form = $Depth->breadcrumbForm();

        if ($form === null) {
            return [];
        }

        return [BreadcrumbField::of($form::textInput('name'))];
    }
}
