<?php

namespace App\Rules;

use App\Support\BrazilianInput;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class ValidBrazilianDocument
    implements ValidationRule
{
    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail
    ): void {
        if (
            $value === null
            || trim((string) $value) === ''
        ) {
            return;
        }

        if (
            ! BrazilianInput::isValidDocument(
                $value
            )
        ) {
            $fail(
                'Informe um CPF ou CNPJ válido.'
            );
        }
    }
}
