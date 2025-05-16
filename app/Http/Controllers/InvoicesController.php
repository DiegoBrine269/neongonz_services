<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class InvoicesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $fields = $request->validate([
            'vehicles' => 'required|array|min:1', // Asegúrate de que sea un array con al menos un elemento
            'vehicles.*' => 'integer', // Valida que cada elemento del array sea un número entero
        ], [
            'vehicles.required' => 'Debes seleccionar al menos un vehículo.',
            'vehicles.array' => 'El campo vehicles debe ser un array.',
            'vehicles.min' => 'Debes seleccionar al menos un vehículo.',
            'vehicles.*.integer' => 'Todos los elementos del campo vehicles deben ser números enteros.',
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
