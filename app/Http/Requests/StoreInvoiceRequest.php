<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
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
            'vehicles' => 'required|array|min:1',
            'comments' => 'max:255',
            'responsible_id' => 'required|exists:responsibles,id',
        ];
    }

    public function messages(): array
    {
        return [
            'vehicles.required' => 'Debes seleccionar al menos un vehículo para la cotización.',
            'vehicles.array' => 'El formato de los vehículos no es válido.',
            'vehicles.min' => 'Debes seleccionar al menos un vehículo para la cotización.',
            'comments.max' => 'El comentario debe ser menor a 255 caracteres.',
            'responsible_id.required' => 'El responsable es obligatorio.',
            'responsible_id.exists' => 'El responsable seleccionado no existe.',
        ];
    }
}
