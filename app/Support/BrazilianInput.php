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
        $document = strtoupper(
            preg_replace(
                '/[^A-Za-z0-9]+/',
                '',
                (string) ($value ?? '')
            )
        );

        if ($document === '') {
            return null;
        }

        return substr(
            $document,
            0,
            14
        );
    }

    public static function isValidDocument(
        null|string|int $value
    ): bool {
        $document =
            self::document($value);

        if ($document === null) {
            return false;
        }

        if (
            preg_match(
                '/^\\d{11}$/',
                $document
            ) === 1
        ) {
            return self::isValidCpf(
                $document
            );
        }

        if (
            preg_match(
                '/^[A-Z0-9]{12}\\d{2}$/',
                $document
            ) === 1
        ) {
            return self::isValidCnpj(
                $document
            );
        }

        return false;
    }

    public static function formatDocument(
        null|string|int $value
    ): ?string {
        $document =
            self::document($value);

        if ($document === null) {
            return null;
        }

        if (
            preg_match(
                '/^\\d{11}$/',
                $document
            ) === 1
        ) {
            return sprintf(
                '%s.%s.%s-%s',
                substr($document, 0, 3),
                substr($document, 3, 3),
                substr($document, 6, 3),
                substr($document, 9, 2)
            );
        }

        if (
            preg_match(
                '/^[A-Z0-9]{14}$/',
                $document
            ) === 1
        ) {
            return sprintf(
                '%s.%s.%s/%s-%s',
                substr($document, 0, 2),
                substr($document, 2, 3),
                substr($document, 5, 3),
                substr($document, 8, 4),
                substr($document, 12, 2)
            );
        }

        return $document;
    }

    public static function formatPhone(
        null|string|int $value
    ): ?string {
        $phone =
            self::phone($value);

        if ($phone === null) {
            return null;
        }

        if (strlen($phone) === 11) {
            return sprintf(
                '(%s) %s-%s',
                substr($phone, 0, 2),
                substr($phone, 2, 5),
                substr($phone, 7, 4)
            );
        }

        if (strlen($phone) === 10) {
            return sprintf(
                '(%s) %s-%s',
                substr($phone, 0, 2),
                substr($phone, 2, 4),
                substr($phone, 6, 4)
            );
        }

        return $phone;
    }

    private static function isValidCpf(
        string $cpf
    ): bool {
        if (
            strlen($cpf) !== 11
            || preg_match(
                '/^(\\d)\\1{10}$/',
                $cpf
            ) === 1
        ) {
            return false;
        }

        for (
            $position = 9;
            $position <= 10;
            $position++
        ) {
            $sum = 0;

            for (
                $index = 0;
                $index < $position;
                $index++
            ) {
                $sum +=
                    (int) $cpf[$index]
                    * (
                        $position
                        + 1
                        - $index
                    );
            }

            $digit =
                ($sum * 10) % 11;

            if ($digit === 10) {
                $digit = 0;
            }

            if (
                $digit
                !== (int) $cpf[$position]
            ) {
                return false;
            }
        }

        return true;
    }

    private static function isValidCnpj(
        string $cnpj
    ): bool {
        if (
            preg_match(
                '/^[A-Z0-9]{12}\\d{2}$/',
                $cnpj
            ) !== 1
        ) {
            return false;
        }

        if (
            preg_match(
                '/^(\\d)\\1{13}$/',
                $cnpj
            ) === 1
        ) {
            return false;
        }

        $base =
            substr(
                $cnpj,
                0,
                12
            );

        $firstDigit =
            self::calculateCnpjDigit(
                $base,
                [
                    5, 4, 3, 2,
                    9, 8, 7, 6,
                    5, 4, 3, 2,
                ]
            );

        $secondDigit =
            self::calculateCnpjDigit(
                $base
                . $firstDigit,
                [
                    6, 5, 4, 3, 2,
                    9, 8, 7, 6,
                    5, 4, 3, 2,
                ]
            );

        return
            substr($cnpj, 12, 2)
            === (
                (string) $firstDigit
                . (string) $secondDigit
            );
    }

    private static function calculateCnpjDigit(
        string $value,
        array $weights
    ): int {
        $sum = 0;

        foreach (
            str_split($value)
            as $index => $character
        ) {
            $numericValue =
                ord($character) - 48;

            $sum +=
                $numericValue
                * $weights[$index];
        }

        $remainder =
            $sum % 11;

        return $remainder < 2
            ? 0
            : 11 - $remainder;
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
