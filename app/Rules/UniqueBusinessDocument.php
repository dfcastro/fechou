<?php

namespace App\Rules;

use App\Models\Business;
use App\Support\BrazilianInput;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class UniqueBusinessDocument
    implements ValidationRule
{
    public function __construct(
        private ?int $ignoreBusinessId = null
    ) {
    }

    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail
    ): void {
        $document =
            BrazilianInput::document(
                $value
            );

        if ($document === null) {
            return;
        }

        $query = Business::query()
            ->where(
                'document',
                $document
            );

        if (
            $this->ignoreBusinessId
            !== null
        ) {
            $query->where(
                'id',
                '!=',
                $this->ignoreBusinessId
            );
        }

        if ($query->exists()) {
            $fail(
                'Este CPF/CNPJ já está cadastrado em outra conta.'
            );
        }
    }
}
