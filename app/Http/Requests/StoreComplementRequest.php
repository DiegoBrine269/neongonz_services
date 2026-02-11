<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreComplementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'invoice_ids' => 'required|array|min:1',
            'invoice_ids.*' => 'exists:invoices,id',
            'payment_date' => 'required|date|before_or_equal:today',
            'payment_amount' => 'numeric|min:1',
            'payment_form' => 'required|string|in:01,02,03,04,28,29,30,31',
        ];
    }

    public function messages(): array
    {
        return [
            'invoice_ids.required' => 'Debes seleccionar al menos una factura para generar el CFDI.',
            'invoice_ids.array' => 'El formato de los IDs de las facturas no es válido.',
            'invoice_ids.min' => 'Debes seleccionar al menos una factura para generar el CFDI.',
            'invoice_ids.*.exists' => 'Una o más facturas seleccionadas no existen.',
            'payment_date.required' => 'La fecha de pago es obligatoria.',
            'payment_date.date' => 'La fecha de pago no es una fecha válida.',
            'payment_date.before_or_equal' => 'La fecha de pago no puede ser futura.',
            'payment_amount.numeric' => 'El monto de pago debe ser un número.',
            'payment_amount.min' => 'El monto de pago debe ser al menos 1.',
            'payment_form.required' => 'La forma de pago es obligatoria.',
            'payment_form.in' => 'La forma de pago no es válida.',
        ];
    }
}
