<?php

namespace App\Support;

final class BrazilianInput
{
    public static function digits(
        null|string|int $value,
        ?int $max = null
    ): ?string {
        $digits = preg_replace(
            '/\D+/',
            '',
            (string) ($value ?? '')
        );

        if ($digits === '') {
            return null;
        }

        if ($max !== null) {
            $digits = substr(
                $digits,
                0,
                $max
            );
        }

        return $digits;
    }

    public static function document(
        null|string|int $value
    ): ?string {
        return self::digits(
            $value,
            14
        );
    }

    public static function phone(
        null|string|int $value
    ): ?string {
        $digits = self::digits($value);

        if ($digits === null) {
            return null;
        }

        if (
            strlen($digits) > 11
            && str_starts_with($digits, '55')
        ) {
            $digits = substr(
                $digits,
                2
            );
        }

        return substr(
            $digits,
            0,
            11
        );
    }

    public static function cep(
        null|string|int $value
    ): ?string {
        return self::digits(
            $value,
            8
        );
    }

    public static function state(
        ?string $value
    ): ?string {
        $state = strtoupper(
            preg_replace(
                '/[^A-Za-z]/',
                '',
                (string) ($value ?? '')
            )
        );

        $state = substr(
            $state,
            0,
            2
        );

        return $state !== ''
            ? $state
            : null;
    }
}
