<?php

use App\Helpers\DataModelCast;

test('a cast squishes, lowercases, and reads an empty value as nothing or as null', function (): void {
    expect(DataModelCast::sanitize("  a   b \n"))->toBe('a b')
        ->and(DataModelCast::sanitize(null))->toBeEmpty()
        ->and(DataModelCast::sanitizeNullable('  a   b  '))->toBe('a b')
        ->and(DataModelCast::sanitizeNullable('   '))->toBeNull()
        ->and(DataModelCast::sanitizeNullable(null))->toBeNull()
        ->and(DataModelCast::sanitizeEmail('  JOHN@Example.COM '))->toBe('john@example.com')
        ->and(DataModelCast::sanitizeEmail(null))->toBeEmpty()
        ->and(DataModelCast::toIntNullable('5'))->toBe(5)
        ->and(DataModelCast::toIntNullable(null))->toBeNull()
        ->and(DataModelCast::toIntNullable(''))->toBeNull();
});
