<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Project;
use App\Models\Vehicle;
use App\Models\ProjectType;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\ProjectVehicle;
use Illuminate\Support\Facades\Log;

class ProjectsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
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
        }

        if ($request->has('filter')) {
            foreach ($request->filter as $filter) {
                if (isset($filter['field'], $filter['type'], $filter['value'])) {
    
                    $field = $filter['field'];
                    $type = $filter['type'];
                    $value = $filter['value'];
    
                    if($field === 'id'){
                        $query->where('id', '=', $value);
                    }
                    elseif ($field === 'date') {
                        $query->where('date', '=', $value);
                    }

                    elseif ($field === 'service.name') {
                        $query->whereHas('service', function ($q) use ($type, $value) {
                            if ($type === 'like') {
                                $q->where('name', 'ilike', '%' . $value . '%');
                            } elseif ($type === '=') {
                                $q->where('name', '=', $value);
                            }
                        });
                    }
                    
    
                    elseif ($field === 'centre.name') {
                        $query->whereHas('centre', function ($q) use ($type, $value) {
                            if ($type === 'like') {
                                $q->where('name', 'ilike', '%' . $value . '%');
                            } elseif ($type === '=') {
                                $q->where('name', '=', $value);
                            }
                        });
                    } elseif (in_array($field, ['date', 'invoice_number'])) {
                        if ($type === 'like') {
                            $query->where($field, 'ilike', '%' . $value . '%');
                        } elseif ($type === '=') {
                            $query->where($field, '=', $value);
                        }
                        
                    } 

                }
            }
        }
        
        $projects = $query
            ->withCount(['vehicles as total_vehicles'])
            ->paginate(20);

        return $projects;
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
            'commentary' => 'nullable|string|max:255',
        ],[
            'centre_id.required' => 'El campo centre_id es obligatorio.',
            'centre_id.exists' => 'El centro especificado no existe.',
            'service_id.required' => 'El campo service_id es obligatorio.',
            'service_id.exists' => 'El servicio especificado no existe.',
            'date.required' => 'La fecha es obligatoria.',
            'date.date' => 'La fecha no es válida.',
            'project_type_id.exists' => 'El tipo de proyecto especificado no existe.',
            'commentary.string' => 'El comentario debe ser una cadena de texto.',
            'commentary.max' => 'El comentario no puede tener más de 255 caracteres.',
            
        ]);


        $project = Project::create([
            'centre_id' => $fields['centre_id'],
            'service_id' => $fields['service_id'],
            'date' => $fields['date'],
            'commentary' => $fields['commentary'] ?? null,
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
            // 'type',
            'vehicles' => function ($query) {
                $query->select('vehicles.id', 'vehicles.eco', 'vehicles.vehicle_type_id')
                    ->with([
                        // 'type:id,type',
                        'projectVehicle.user:id,name,last_name',
                    ])
                    ->orderBy('project_vehicles.id', 'desc');
            }

        ])->findOrFail($id);
        


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


        $vehicles = $project->vehicles;
        // Colección de usuarios
        //obtener todos los user_id únicos desde el pivote
        $userIds = $vehicles->pluck('pivot.user_id')->filter()->unique();
        //obtener todos los usuarios relacionados en una sola consulta
        $users = User::whereIn('id', $userIds)->get()->keyBy('id');

        $formatted = [
            'id' => $project->id,
            // 'type' => $project->type,
            'commentary' => $project->commentary,
            'centre_id' => $project->centre_id,
            'service_id' => $project->service_id,
            'is_open' => $project->is_open,
            'date' => $project->date,
            'related_projects' => $project->related_projects,
            'centre' => $project->centre ? [
                'id' => $project->centre->id,
                'name' => $project->centre->name,
            ] : null,
            'service' => $project->service ? [
                'id' => $project->service->id,
                'name' => $project->service->name,
            ] : null,
            'vehicles' => $vehicles->map(function ($vehicle) use ($users) {
                $pivot = $vehicle->pivot;

                // Consultando en colección en memoria
                $user = $pivot?->user_id ? $users->get($pivot->user_id) : null;

                return [
                    'id' => $vehicle->id,
                    'eco' => $vehicle->eco,
                    'type' => optional($vehicle->type)->type,
                    'user' => $user ? [
                        'id' => $user->id,
                        'name' => $user->name . ' ' . $user->last_name,
                    ] : null,
                    'created_at' => $pivot->created_at,
                    'commentary' => $pivot->commentary,
                ];
            })->toArray()
        ];


        return response()->json($formatted);
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
            'eco' => $request->usar_placa ? 'required|max:10' : 'required|numeric|digits:5',
            'type' => 'required|exists:vehicles_types,id',
            'commentary' => 'nullable|string|max:255',
        ],[
            'eco.max' => 'El económico o placa no puede tener más de 10 caracteres.',
            'eco.required' => 'El económico o placa es obligatorio.',
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
                'eco' => Str::upper($fields['eco']),
                'centre_id' => $project->centre_id,
                'vehicle_type_id' => $fields['type'],
            ]);
        } else {


            //Si ya existe, revisar que sea del mismo centro, y que no esté ya asociado al proyecto

            $request->validate([
                'eco' => function ($attribute, $value, $fail) use ($vehicle, $project) {
                    // Se elimina a petición de Carlos, en su lugar, se actualiza el vehículo
                    // if ($vehicle->centre_id != $project->centre_id) {
                    //     $fail('El vehículo pertenece a otro centro de ventas.');
                    // }
                    $vehicle->centre_id = $project->centre_id;


                    if ($project->vehicles()->where('vehicle_id', $vehicle->id)->exists()) {
                        $fail('El vehículo ya está asociado a este proyecto.');
                    }
                },
            ]);
            $vehicle->vehicle_type_id = $fields['type'];
            $vehicle->save();
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

                if($extra_project->vehicles()->where('vehicle_id', $vehicle->id)->exists()){
                    continue; // Si el vehículo ya está asociado a este proyecto, saltar
                }

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

    public function duplicate(Request $request, string $id) {
        $project = Project::find($id);

        $vehicles = $project->vehicles;

        $new_project = $project->replicate();
        $new_project->save();

        // Adjunta los mismos vehículos al nuevo proyecto, incluyendo los campos del pivot
        foreach ($vehicles as $vehicle) {
            $new_project->vehicles()->attach($vehicle->id, [
                'commentary' => $vehicle->pivot->commentary,
                'user_id' => $vehicle->pivot->user_id,
            ]);
        }

       return $project; 
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
            'extra_projects.*' => 'projects,id',
            'commentary' => 'nullable|string|max:255',
        ],[
            'centre_id.required' => 'El campo centre_id es obligatorio.',
            'centre_id.exists' => 'El centro especificado no existe.',
            'service_id.required' => 'El campo service_id es obligatorio.',
            'service_id.exists' => 'El servicio especificado no existe.',
            'date.required' => 'La fecha es obligatoria.',
            'date.date' => 'La fecha no es válida.',
            'extra_projects.array' => 'Los proyectos extras deben ser un array.',
            // 'extra_projects.*.exists' => 'Uno o más proyectos extras especificados no existen.',
            'commentary.string' => 'El comentario debe ser una cadena de texto.',
            'commentary.max' => 'El comentario no puede tener más de 255 caracteres.',
        ]);

        // dump(json_encode($fields['extra_projects']));
        $ids_related_projects = $fields['extra_projects'] ?? [];



        $project->update([
            'centre_id' => $fields['centre_id'],
            'service_id' => $fields['service_id'],
            'date' => $fields['date'],
            'related_projects' => json_encode($ids_related_projects),
            'commentary' => $fields['commentary'], // Si usas cast a array
        ]);

        // Si hay proyectos extras, también asignarles el campo related_projects
        // dump($related_projects);
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

    public function toggleStatus(Request $request, string $id) {
        $project = Project::find($id);

        if (!$project) {
            return response()->json([
                'message' => 'Proyecto no encontrado',
            ], 404);
        }

        $project->is_open = !$project->is_open;
        $project->save();

        return response()->json([
            'message' => 'Proyecto cerrado/abierto correctamente',
        ], 200);
    }


}
