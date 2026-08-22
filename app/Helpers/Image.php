<?php

namespace App\Helpers;

use Attribute;

/**
 * Marks a file extension an image may be sent as.
 *
 * The tag is the whole of the declaration: what validates an upload and what a file
 * picker filters by both read the tagged cases, so neither restates a list the other
 * could disagree with. An untagged case is accepted nowhere, and tagging one widens
 * every rule that reads them at once.
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
final class Image {}
