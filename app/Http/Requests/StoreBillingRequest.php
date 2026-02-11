<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBillingRequest extends FormRequest
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
            'joined' => 'boolean|required',
            'payment_form' => 'required|string|in:01,02,03,04,28,29,30,31,99',
            'payment_method' => 'required|string|in:PUE,PPD',
        ];
    }

    public function messages(): array
    {
        return [
            'invoice_ids.required' => 'Debes seleccionar al menos una factura para generar el CFDI.',
            'invoice_ids.array' => 'El formato de los IDs de las facturas no es válido.',
            'invoice_ids.min' => 'Debes seleccionar al menos una factura para generar el CFDI.',
            'invoice_ids.*.exists' => 'Una o más facturas seleccionadas no existen.',
            'joined.required' => 'El campo de facturación conjunta es obligatorio.',
            'joined.boolean' => 'El campo de facturación conjunta debe ser verdadero o falso.',
            'payment_form.required' => 'La forma de pago es obligatoria.',
            'payment_form.in' => 'La forma de pago no es válida.',
            'payment_method.required' => 'El método de pago es obligatorio.',
            'payment_method.in' => 'El método de pago no es válido.',
        ];
    }
}
