<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomInvoiceRequest extends FormRequest
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
            'invoice_id' => 'nullable|exists:invoices,id',
            'centre_id' => 'required|exists:centres,id',
            'rows' => 'required|array|min:1',
            'rows.*.concept' => 'required|string',
            'rows.*.quantity' => 'required|numeric|min:1',
            'rows.*.price' => 'required|numeric|min:1',
            'comments' => 'nullable|string|max:255',
            'completed' => 'boolean',
            'internal_commentary' => 'nullable|string|max:255',
            'date' => 'required|date|before_or_equal:today',
            'is_budget' => 'boolean',
            'responsible_id' => 'required|exists:responsibles,id',
            'rows.*.sat_unit_key' => 'nullable|string|exists:sat_units,key',
            'rows.*.sat_key_prod_serv' => 'required|string|digits:8',
            'rows.*.price'=>'required|numeric|min:1',
        ];
    }

    public function messages(): array 
    {
        return [
            'invoice_id.exists' => 'La cotización que intentas imprimir no existe', 
            'centre_id.required' => 'El centro es obligatorio.',
            'centre_id.exists' => 'El centro seleccionado no existe.',
            'rows.required' => 'Debes agregar al menos una fila a la cotización.',
            'rows.array' => 'El formato de las filas no es válido.',
            'rows.min' => 'Debes agregar al menos una fila a la cotización.',
            'rows.*.concept.required' => 'El concepto es obligatorio en todas las filas.',
            'rows.*.quantity.required' => 'La cantidad es obligatoria en todas las filas.',
            'rows.*.price.required' => 'El precio es obligatorio en todas las filas.',
            'quantity.numeric' => 'La cantidad debe ser un número.',
            'price.numeric' => 'El precio debe ser un número.',
            'quantity.min' => 'La cantidad debe ser al menos 1.',
            'price.min' => 'El precio debe ser al menos 1.',
            'comments.max' => 'El comentario debe ser menor a 255 caracteres.',
            'completed.boolean' => 'El campo completado debe ser verdadero o falso.',
            'internal_commentary.max' => 'El comentario debe ser menor a 255 caracteres.',
            'date.required' => 'La fecha es obligatoria.',
            'date.date' => 'La fecha no es válida.',
            'date.before_or_equal' => 'La fecha no puede ser futura.',
            'is_budget.boolean' => 'El campo tipo debe ser verdadero o falso.',
            'responsible_id.exists' => 'El responsable seleccionado no existe.',
            'responsible_id.required' => 'El responsable es obligatorio.',
            'rows.*.sat_unit_key.exists' => 'Una o más unidades de medida no son válidas.',
            'rows.*.sat_key_prod_serv.required' => 'La clave de producto o servicio es obligatoria en todas las filas.',
            'rows.*.sat_key_prod_serv.digits' => 'La clave de producto o servicio debe tener 8 dígitos.',
            'rows.*.price.required' => 'El precio es obligatorio en todas las filas.',
            'rows.*.price.numeric' => 'El precio debe ser un número.',
            'rows.*.price.min' => 'El precio debe ser al menos 1.',
        ];
    }
}
