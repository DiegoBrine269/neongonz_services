<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceRequest extends FormRequest
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
            'vehicles' => 'required_unless:completed,false|array|min:1',
            'date' => 'required|date|before_or_equal:today',
            'responsible_id' => 'required|exists:responsibles,id',
            'quantity' => 'sometimes|numeric|min:1',
            'price' => 'sometimes|numeric|min:1',
            'concept' => 'sometimes|string',
            'comments' => 'max:255',
            'oc'              => 'required_with:f_receipt,validation_date|nullable|string|max:255',
            'f_receipt'       => 'required_with:oc,validation_date|nullable|string|max:255',
            'validation_date' => 'required_with:f_receipt,oc|nullable|date|before_or_equal:today',
            'status' => 'required|string|in:envio,oc,factura,f,complemento,finalizada',
        ];
    }

    public function messages(): array
    {
        return [
            'vehicles.required' => 'Debes seleccionar al menos un vehículo para la cotización.',
            'vehicles.array' => 'El formato de los vehículos no es válido.',
            'vehicles.min' => 'Debes seleccionar al menos un vehículo para la cotización.',
            'date.required' => 'La fecha es obligatoria.',
            'date.date' => 'La fecha no es válida.',
            'date.before_or_equal' => 'La fecha no puede ser futura.',
            'responsible_id.required' => 'El responsable es obligatorio.',
            'responsible_id.exists' => 'El responsable seleccionado no existe.',
            'comments.max' => 'El comentario debe ser menor a 255 caracteres.',
            'oc.max' => 'El número de OC debe ser menor a 255 caracteres.',
            'f_receipt.max' => 'El número de F debe ser menor a 255 caracteres.',
            'validation_date.date' => 'La fecha de validación no es válida.',
            'validation_date.before_or_equal' => 'La fecha de validación no puede ser futura.',
            'oc.required_with' => 'El número de OC es obligatorio cuando se proporciona el número de F o la fecha de validación.',
            'f_receipt.required_with' => 'El número de F es obligatorio cuando se proporciona el número de OC o la fecha de validación.',
            'validation_date.required_with' => 'La fecha de validación es obligatoria cuando se proporciona el número de OC o el número de F.',
            'status.required' => 'El estado es obligatorio.',
            'status.string' => 'El estado debe ser una cadena de texto.',
            'status.in' => 'El estado seleccionado no es válido.',
        ];
    }
}
