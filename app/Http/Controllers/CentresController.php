<?php

namespace App\Http\Controllers;

use App\Models\Centre;
use App\Models\Project;
use App\Models\ProjectVehicle;
use Illuminate\Http\Request;

class CentresController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Centre::orderBy('name', 'asc')->get();
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $fields = $request->validate([
            'name' =>'required|max:255|unique:centres',
            'responsible' =>'required',
        ],[
            'name.required' => 'El nombre del centro de ventas es obligatorio.',
            'name.max' => 'El nombre debe ser menor a 255 caracteres.',
            'name.unique' => "El centro de ventas $request->name ya existe.", 
            'responsible.required' => 'El responsable es obligatorio.',
        ]);

        $centre = Centre::create([
            'name' => $fields['name'],
            'location' => $request->location,
            'responsible' => $fields['responsible'],
        ]);

        return response()->json([
            'message' => 'Centro de ventas creado correctamente.',
            'centre' => $centre,
        ], 201);
    }

    public function showOpenProjects(int $id)
    {
        $projects = Project::with(['service' => function ($query) {
            $query->select('id', 'name'); // Selecciona solo las columnas necesarias del servicio
        }])
        ->where('centre_id', $id)
        ->get()
        ->map(function ($project) {
            return [
                'id' => $project->id, // Solo el ID del proyecto
                'service' => $project->service->name, // Solo el nombre del servicio

            ];
        });
    
        return response()->json($projects);
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
