<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomBillingRequest extends FormRequest
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
            'customer_id' => 'required|exists:customers,id',
            'payment_date' => 'required_if:payment_method,PUE|nullable|date',
            'payment_form' => 'required|string|in:01,02,03,04,28,29,30,31,99',
            'payment_method' => 'required|string|in:PUE,PPD',
            'email' => 'nullable|email',
            'rows' => 'required|array|min:1',
            'rows.*.concept' => 'required|string',
            'rows.*.quantity' => 'required|numeric|min:1',
            'rows.*.price' => 'required|numeric|min:1',
            'rows.*.sat_unit_key' => 'required|string|exists:sat_units,key',
            'rows.*.sat_key_prod_serv' => 'required|string|digits:8',
        ];
    }

    public function messages(): array 
    {
        return [
            'customer_id.required' => 'El cliente es obligatorio.',
            'customer_id.exists' => 'El cliente seleccionado no existe.',
            'payment_date.date' => 'La fecha de pago no es válida.',
            'payment_date.required_if' => 'La fecha de pago es obligatoria cuando el método de pago es PUE.',
            'payment_form.required' => 'La forma de pago es obligatoria.',
            'payment_form.in' => 'La forma de pago seleccionada no es válida.',
            'payment_method.required' => 'El método de pago es obligatorio.',
            'payment_method.in' => 'El método de pago seleccionado no es válido.',
            'email.email' => 'El correo electrónico no es válido.',
            'rows.required' => 'Debes agregar al menos una fila a la facturación.',
            'rows.array' => 'El formato de las filas no es válido.',
            'rows.min' => 'Debes agregar al menos una fila a la facturación.',
            'rows.*.concept.required' => 'El producto es obligatorio.',
            'rows.*.quantity.required' => 'La cantidad es obligatoria.',
            'rows.*.quantity.numeric' => 'La cantidad debe ser un número.',
            'rows.*.price.required' => 'El precio es obligatorio.',
            'rows.*.price.numeric' => 'El precio debe ser un número.',
            'rows.*.sat_unit_key.required' => 'La clave de unidad SAT es obligatoria.',
            'rows.*.sat_unit_key.exists' => 'La clave de unidad SAT seleccionada no existe.',
            'rows.*.sat_key_prod_serv.required' => 'La clave de producto/servicio SAT es obligatoria.',
            'rows.*.sat_key_prod_serv.digits' => 'La clave de producto/servicio SAT debe tener 8 dígitos.',
        ];

    }
}
