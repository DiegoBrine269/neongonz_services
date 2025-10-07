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
    public function index(Request $request)
    {

        $centres = Centre::with('responsibles')->orderBy('name', 'asc')->get();
        
        $centres->makeHidden(['created_at', 'updated_at']);

        

        return $centres;
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $fields = $request->validate([
            'name' =>'required|max:255|unique:centres',
            'responsible_id' =>'required|exists:responsibles,id',
            'location' => 'nullable|max:255',
        ],[
            'name.required' => 'El nombre del centro de ventas es obligatorio.',
            'name.max' => 'El nombre debe ser menor a 255 caracteres.',
            'name.unique' => "El centro de ventas $request->name ya existe.", 
            'responsible_id.required' => 'El responsable es obligatorio.',
            'responsible_id.exists' => 'El responsable seleccionado no es válido.',
            'location.max' => 'La ubicación debe ser menor a 255 caracteres.',
        ]);

        $centre = Centre::create([
            'name' => $fields['name'],
            'location' => $request->location,
        ]);

        $centre->responsibles()->attach($fields['responsible_id']);
        

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
        ->where('is_open', 1)
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
        $centre = Centre::with('responsibles')->find($id);

        if (!$centre) {
            return response()->json(['message' => 'Centro de ventas no encontrado.'], 404);
        }


        return $centre;
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $centre = Centre::find($id);

        if (!$centre) {
            return response()->json(['message' => 'Centro de ventas no encontrado.'], 404);
        }

        $fields = $request->validate([
            'name' =>'required|max:255|unique:centres,name,'.$id,
            'location' => 'nullable|max:255',
            'responsibles' =>'required|array|exists:responsibles,id',
        ],[

            'name.required' => 'El nombre del centro de ventas es obligatorio.',
            'name.max' => 'El nombre debe ser menor a 255 caracteres.',
            'name.unique' => "El centro de ventas $request->name ya existe.", 
            'location.max' => 'La ubicación debe ser menor a 255 caracteres.',
            'responsibles.required' => 'Debe seleccionar al menos un responsable.',
            'responsibles.array' => 'El formato de los responsables no es válido.',
        ]);

        $centre->name = $fields['name'];
        $centre->location = $request->location;
        $centre->responsibles()->sync($fields['responsibles']);
        $centre->save();

        return response()->json([
            'message' => 'Centro de ventas actualizado correctamente.',
            'centre' => $centre,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
