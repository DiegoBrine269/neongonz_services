<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class StoreVehicleProjectRequest extends FormRequest
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
            'eco' => $this->usar_placa ? 'required|max:10' : 'required|numeric|digits:5',
            'type' => 'required|exists:vehicles_types,id',
            'commentary' => 'nullable|string|max:255',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:2048',
        ];
    }

    public function prepareForValidation(): void
    {
        foreach ($this->files->get('images') ?? [] as $index => $file) {
            if ($file->getError() === UPLOAD_ERR_INI_SIZE) {
                // Lanzar el error en el formato de Laravel
                throw ValidationException::withMessages([
                    "images.{$index}" => 'La imagen excede el tamaño máximo permitido.'
                ]);
            }
        }
    }

    public function messages(): array
    {
        return [
            'eco.max' => 'El económico o placa no puede tener más de 10 caracteres.',
            'eco.required' => 'El económico o placa es obligatorio.',
            'eco.numeric' => 'El económico debe ser un número.',
            'eco.digits' => 'El económico debe tener 5 dígitos.',
            'type.required' => 'El campo de tipo de vehículo es obligatorio.',
            'type.exists' => 'El tipo de vehículo especificado no existe.',
            'commentary.string' => 'El comentario debe ser una cadena de texto.',
            'commentary.max' => 'El comentario no puede tener más de 255 caracteres.',
            'images.*.image' => 'Cada archivo debe ser una imagen.',
            'images.*.mimes' => 'Cada imagen debe ser un archivo de tipo: jpeg, png, jpg o webp.',
            'images.*.max' => 'Cada imagen no puede superar los 2MB de tamaño.',
        ];
    }
}
