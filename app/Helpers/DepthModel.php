<?php

namespace App\Helpers;

use Attribute;
use Illuminate\Database\Eloquent\Model;

#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
readonly class DepthModel
{
    /** @param  class-string<Model>  $model */
    public function __construct(public string $model) {}
}
