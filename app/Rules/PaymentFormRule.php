<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PaymentFormRule implements ValidationRule
{

    /** @var array<string> */
    private array $allowed = [
        '01','02','03','04','17','28','29','30','31','99',
    ];

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Normaliza por si llega como int o con espacios
        $value = is_null($value) ? null : trim((string) $value);

        if ($value === null || $value === '') {
            // Si quieres que sea required, eso va en las rules del request,
            // aquí solo valida "si viene, que sea válido".
            return;
        }

        // Debe ser exactamente 2 caracteres
        if (strlen($value) !== 2) {
            $fail("La forma de pago debe tener exactamente 2 caracteres.");
            return;
        }

        if (!in_array($value, $this->allowed, true)) {
            $fail("La forma de pago '{$value}' no es válida.");
        }
    }
}
