<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class Money
{
    public static function minor(string|int $value): int
    {
        if (! preg_match('/^\d{1,10}(\.\d{1,2})?$/', (string) $value)) {
            throw new InvalidArgumentException('Invalid monetary amount.');
        }

        return (int) bcmul((string) $value, '100', 0);
    }

    public static function decimal(int $value): string
    {
        if (abs($value) > 99999999999999) {
            throw ValidationException::withMessages(['amount' => 'The transaction exceeds the supported monetary limit. Split it into smaller transactions.']);
        }

        return bcdiv((string) $value, '100', 2);
    }

    public static function tax(int $net, int $basisPoints): int
    {
        return intdiv($net * $basisPoints + 5000, 10000);
    }
}
