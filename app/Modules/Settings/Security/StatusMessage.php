<?php

namespace App\Modules\Settings\Security;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
readonly class StatusMessage
{
    public function __construct(public string $message) {}
}
