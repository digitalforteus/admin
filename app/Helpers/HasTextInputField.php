<?php

namespace App\Helpers;

interface HasTextInputField
{
    /** @return array<string, mixed> */
    public static function textInput(string $property): array;
}
