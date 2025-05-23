<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectVehicle;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProjectsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Get all projects

        $user = auth()->user();

        // Limitando los proyectos a los que el usuario tiene acceso

        $query = Project::with([
            'centre:id,name', // Incluye solo 'id' y 'name' de la tabla centres
            'service:id,name' // Incluye solo 'id' y 'name' de la tabla services
        ])
        ->select('id', 'is_open', 'date', 'centre_id', 'service_id') // Selecciona solo los campos necesarios de la tabla projects
        ->orderBy('updated_at', 'desc');
        $show_closed_param = request()->query('show_closed');

        if($user->role == 'user'){
            $query->where('is_open', 1); 
        }
        else if ($user->role == 'admin' && $show_closed_param == 0) {
            $query->where('is_open', 1); 
            // dump("El usuario solo quiere ver proyectos abiertos");
        }
        
        $projects = $query->get();

        // $projects = Project::with(['centre', 'service'])->orderBy('id', 'desc')->get();

        $projects = $projects->map(function ($project) {
            $project->total_vehicles = $project->vehicles()->count();
            return $project;
        });

        return response()->json($projects);
    }



    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        $fields = $request->validate([
            'centre_id' => 'required|exists:centres,id',
            'service_id' => 'required|exists:services,id',
            'date' => "required|date",
        ],[
            'centre_id.required' => 'El campo centre_id es obligatorio.',
            'centre_id.exists' => 'El centro especificado no existe.',
            'service_id.required' => 'El campo service_id es obligatorio.',
            'service_id.exists' => 'El servicio especificado no existe.',
            'date.required' => 'La fecha es obligatoria.',
            'date.date' => 'La fecha no es válida.',
            // 'date.before' => 'La fecha debe ser anterior a hoy.',
        ]);

        $project = Project::create([
            'centre_id' => $fields['centre_id'],
            'service_id' => $fields['service_id'],
            'date' => $fields['date'],
        ]);

        return response()->json([
            'message' => 'Proyecto creado correctamente',
            'project' => $project,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $project = Project::with([
                'centre:id,name', 
                'service:id,name',
                'vehicles' => function ($query) {
                    $query->select('vehicles.id', 'vehicles.eco', 'vehicles.vehicle_type_id') // Especifica la tabla para evitar ambigüedad
                        ->with('type:id,type', 'projectUser:name,last_name')
                        ->orderBy('project_vehicles.id', 'desc');
                }
            ])
            ->where('id', $id)
            ->first();


        if (!$project) {
            return response()->json([
                'message' => 'Servicio no encontrado',
            ], 404);
        }

        // $project->centre->makeHidden(['id', 'location', 'responsible', 'created_at', 'updated_at']);
        // $project->vehicles->makeHidden(['pivot', 'created_at', 'updated_at']);
        
        $project->makeHidden(['created_at', 'updated_at']);
        // unset($project['centre_id']);
        // unset($project['service_id']);



        //Formatear los vehículos para incluir el campo commentary al mismo nivel
        $project->vehicles->transform(function ($vehicle) {
            return [
                'id' => $vehicle->id,
                // 'centre_id' => $vehicle->centre_id,
                // 'service_id' => $vehicle->service_id,
                'eco' => $vehicle->eco,
                'type' => $vehicle->type->type,
                'commentary' => $vehicle->pivot->commentary, // Extrae commentary de la tabla pivote
                'user' => $vehicle->projectUser,
                'created_at' => $vehicle->pivot->created_at, // Formatear la fecha
            ];
        });

        return response()->json($project);
    }

    public function addVehicle(Request $request, string $id)
    {

        $project = Project::find($id);
        
        if (!$project) {
            return response()->json([
                'message' => 'Proyecto no encontrado',
            ], 404);
        }

        $fields = $request->validate([
            'eco' => 'required|numeric|digits:5',
            'type' => 'required|exists:vehicles_types,id',
            'commentary' => 'nullable|string|max:255',
        ],[
            'eco.required' => 'El económico es obligatorio.',
            'eco.numeric' => 'El económico debe ser un número.',
            'eco.digits' => 'El económico debe tener 5 dígitos.',
            'type.required' => 'El campo de tipo de vehículo es obligatorio.',
            'type.exists' => 'El tipo de vehículo especificado no existe.',
            'commentary.string' => 'El comentario debe ser una cadena de texto.',
            'commentary.max' => 'El comentario no puede tener más de 255 caracteres.',
        ]);

        $vehicle = Vehicle::where('eco', $fields['eco'])->first();


        if (!$vehicle) {
            $vehicle = Vehicle::create([
                'eco' => $fields['eco'],
                'centre_id' => $project->centre_id,
                'vehicle_type_id' => $fields['type'],
            ]);
        } else {


            //Si ya existe, revisar que sea del mismo centro, y que no esté ya asociado al proyecto

            $request->validate([
                'eco' => function ($attribute, $value, $fail) use ($vehicle, $project) {
                    if ($vehicle->centre_id != $project->centre_id) {
                        $fail('El vehículo pertenece a otro centro de ventas.');
                    }


                    if ($project->vehicles()->where('vehicle_id', $vehicle->id)->exists()) {
                        $fail('El vehículo ya está asociado a este proyecto.');
                    }
                },
            ]);
            
        }

        $project->vehicles()->attach($vehicle->id, [
            'commentary' => $fields['commentary'] ?? null,
            'user_id' => auth()->user()->id,
            'created_at' => now(),
        ]);

        $project->updated_at = now();
        $project->save();

        // En caso de que hayan proyectos extras
        if($request->extra_projects && is_array($request->extra_projects)){
            foreach($request->extra_projects as $id_extra_project){
                $extra_project = Project::find($id_extra_project); 

                $extra_project->vehicles()->attach($vehicle->id, [
                    'commentary' => null,
                    'user_id' => auth()->user()->id,
                    'created_at' => now(),
                ]);
        
                $extra_project->updated_at = now();
                $extra_project->save();
            }
        }

        return response()->json([
            'message' => 'Vehículo añadido correctamente al proyecto',
        ], 201);
    }

    public function removeVehicle(Request $request, string $id) {
        $project = Project::find($id);

        
        if (!$project) {
            return response()->json([
                'message' => 'Proyecto no encontrado',
            ], 404);
        }

        $fields = $request->validate([
            'id' => 'numeric|required|exists:vehicles,id',
        ],[
            'id.required' => 'El vehículo es obligatorio.',
            'id.numeric' => 'El id del vehículo debe ser un número.',
            'id.exists' => 'El vehículo especificado no existe.',
        ]);

        $vehicle = Vehicle::find($fields['id']);

        $project->vehicles()->detach($vehicle->id);

        //Si el vehículo no está asociado a ningún otro proyecto, eliminarlo
        if ($vehicle->projects()->count() == 0) {
            $vehicle->delete();
        }
        return response()->json([
            'message' => 'Vehículo eliminado correctamente del proyecto',
        ], 200);

    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $project = Project::find($id);

        if (!$project) {
            return response()->json([
                'message' => 'Proyecto no encontrado',
            ], 404);
        }

        $fields = $request->validate([
            'centre_id' => 'required|exists:centres,id',
            'service_id' => 'required|exists:services,id',
            'date' => "required|date",
            'extra_projects' => 'nullable|array',
            'extra_projects.*' => 'exists:projects,id',
        ],[
            'centre_id.required' => 'El campo centre_id es obligatorio.',
            'centre_id.exists' => 'El centro especificado no existe.',
            'service_id.required' => 'El campo service_id es obligatorio.',
            'service_id.exists' => 'El servicio especificado no existe.',
            'date.required' => 'La fecha es obligatoria.',
            'date.date' => 'La fecha no es válida.',
        ]);

        // dump(json_encode($fields['extra_projects']));
        $related_projects = $fields['extra_projects'] ?? [];

        $project->update([
            'centre_id' => $fields['centre_id'],
            'service_id' => $fields['service_id'],
            'date' => $fields['date'],
            'related_projects' => json_encode($related_projects),
        ]);

        // Si hay proyectos extras, también asignarles el campo related_projects

        dump($related_projects);
        if (!empty($related_projects)) {
            foreach ($related_projects as $id_extra_project) {
                $extra_project = Project::find($id_extra_project);
        
                // Copiamos los proyectos relacionados
                $_related_projects = $related_projects;
        
                // Quitamos el ID del proyecto actual de la lista
                $_related_projects = array_filter($_related_projects, function ($id) use ($id_extra_project) {
                    return $id !== $id_extra_project;
                });
        
                // Agregamos el proyecto principal
                $_related_projects[] = $project->id;

                // dump($_related_projects);
        
                // Actualizamos el proyecto relacionado
                $extra_project->update([
                    'related_projects' => json_encode(array_values($_related_projects)), // Si usas cast a array
                ]);
            }
        }
        
        

        foreach ($project->vehicles as $vehicle) {
            $vehicle->centre_id = $fields['centre_id'];
            $vehicle->save();
        }

        return response()->json([
            'message' => 'Proyecto actualizado correctamente',
            // 'project' => $project,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $project = Project::find($id);
        $project->vehicles()->detach();

        $project->delete();
    }

    public function close(Request $request, string $id) {
        $project = Project::find($id);

        if (!$project) {
            return response()->json([
                'message' => 'Proyecto no encontrado',
            ], 404);
        }

        $project->is_open = 0;
        $project->save();

        return response()->json([
            'message' => 'Proyecto cerrado correctamente',
        ], 200);
    }
}
