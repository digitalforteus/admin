<?php

namespace App\Plugins\AdminLink;

use Attribute;

/**
 * Tags a route the administrative index of links displays.
 *
 * The tag is the whole of the declaration: it carries the order and nothing else,
 * and the plugin that reads it does the collecting. The order is optional — tagged
 * cases sort by it across every index at once, and the ones giving none follow, in
 * the order their own enum declares them.
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
readonly class AdminLink
{
    public function __construct(public ?int $order = null) {}
}
