<?php

namespace App\Helpers;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
readonly class DepthBreadcrumbForm
{
    /** @param  class-string<HasTextInputField>|null  $form The form a new subject of this depth is named on, or null where it cannot be created inside the trail. */
    public function __construct(public ?string $form = null) {}
}
