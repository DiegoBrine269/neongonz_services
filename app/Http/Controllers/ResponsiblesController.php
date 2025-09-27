<?php

namespace App\Http\Controllers;

use App\Models\Responsible;
use Illuminate\Http\Request;

class ResponsiblesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $responsibles = Responsible::orderBy('name', 'asc')->get();
        
        return $responsibles;
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $fields = $request->validate([
            'name' =>'required|max:255|unique:responsibles',
            'email' =>'email|required|unique:responsibles',
        ],[
            'name.required' => 'El nombre del responsable es obligatorio.',
            'name.max' => 'El nombre debe ser menor a 255 caracteres.',
            'name.unique' => "El responsable $request->name ya existe.", 
            'email.email' => 'El correo electrónico no es válido.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.unique' => "El correo electrónico $request->email ya existe.",
        ]);

        $responsible = Responsible::create([
            'name' => $fields['name'],
            'email' => $fields['email'],
        ]);

        return response()->json([
            'message' => 'Responsable creado correctamente.',
            'responsible' => $responsible,
        ], 201);
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
