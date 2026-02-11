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
        ];
    }
}
