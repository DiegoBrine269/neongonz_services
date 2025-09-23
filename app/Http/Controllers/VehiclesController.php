<?php

namespace App\Http\Controllers;

use App\Models\ProjectVehicle;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class VehiclesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $vehicles = [];

        // dump($request->editing_invoice);

        if ($request->invoice == 'pending') {
            $vehicles = ProjectVehicle::with([
                'vehicle:id,eco,vehicle_type_id', 
                'vehicle.type:id,type',
                'project.centre:id,name',
                'project.service'=> function ($query) {
                    return $query->with('vehicleTypes')->get();
                }
            ])
            ->select('id', 'vehicle_id', 'project_id') 
            ->when(
                $request->editing_invoice,
                fn($q) => $q->where(function ($sub) use ($request) {
                    $sub->whereNull('invoice_id')
                        ->orWhere('invoice_id', $request->editing_invoice);
                }),
                fn($q) => $q->whereNull('invoice_id')
            )
            ->when($request->has('centre_id'), function ($query) use ($request) {
                $query->whereHas('project', function ($q) use ($request) {
                    $q->where('centre_id', $request->centre_id);
                });
            })
            ->where('created_at', '>=', '2025-09-01') // Limitar a los últimos 6 meses
            ->get()
            ->map(function ($projectVehicle) {
                $service = $projectVehicle->project->service;
                $vehicleTypeId = $projectVehicle->vehicle->vehicle_type_id;

                // Filtrar solo el tipo de vehículo asociado al vehículo actual
                $filteredType = $service->vehicleTypes
                    ->firstWhere('id', $vehicleTypeId);

                return [
                    'id' => $projectVehicle->id,
                    'vehicle_id' => $projectVehicle->vehicle->id,
                    'eco' => $projectVehicle->vehicle->eco ?? null,
                    'centre_id' => $projectVehicle->project->centre->id ?? null,
                    'vehicle_type_id' => $vehicleTypeId,
                    'type' => $projectVehicle->vehicle->type->type,

                    'project_id' => $projectVehicle->project->id,
                    'project' => [
                        'id' => $projectVehicle->project->id,
                        'service' => $service->name,
                        'service_id' => $service->id,
                        'centre_id' => $projectVehicle->project->centre_id,
                        'date' => $projectVehicle->project->date,
                    ],

                    // Precio obtenido desde la tabla pivot service_vehicle_type
                    'price' => $filteredType?->pivot?->price ?? null,
                ];
            });
        }
        else{
            $query = Vehicle::with([
                'centre:id,name',
                'type',
                'projects' => function ($query) {
                    $query->orderBy('project_vehicles.created_at', 'desc')
                        ->limit(5)
                        ->with([
                            'centre:id,name',
                            'service:id,name'
                        ])
                        ->select('projects.id', 'centre_id', 'service_id', 'date'); // especifica tabla en id
                }
            ])->select('id', 'eco', 'centre_id', 'vehicle_type_id');

            
            // Apply filters from the request
            if ($request->has('filter')) {
                foreach ($request->filter as $filter) {
                    if (isset($filter['field'], $filter['type'], $filter['value'])) {
            
                        $field = $filter['field'];
                        $type = $filter['type'];
                        $value = $filter['value'];
            
                        // Si el filtro es para un campo relacionado
                        if ($field === 'centre.name') {
                            $query->whereHas('centre', function ($q) use ($type, $value) {
                                if ($type === 'like') {
                                    $q->where('name', 'ilike', '%' . $value . '%');
                                } elseif ($type === '=') {
                                    $q->where('name', '=', $value);
                                }
                            });
                        } elseif ($field === 'type') {
                            $query->whereHas('type', function ($q) use ($type, $value) {
                                if ($type === 'like') {
                                    $q->where('type', 'ilike', '%' . $value . '%');
                                } elseif ($type === '=') {
                                    $q->where('type', '=', $value);
                                }
                            });
                        } else {
                            // Campo directo del modelo Vehicle
                            if ($type === 'like') {
                                $query->where($field, 'ilike', '%' . $value . '%');
                            } elseif ($type === '=') {
                                $query->where($field, '=', $value);
                            }
                        }
                    }
                }
            }
        

            $vehicles = $query->orderBy('eco', 'asc')->paginate(20);

            $vehicles->map(function ($vehicle) {
                $name = $vehicle->centre->name;
                $type = $vehicle->type->type;

                $vehicle->centre = $vehicle->centre;
                // unset($vehicle->centre);
                unset($vehicle->type);

                $vehicle->type = $type;
                $vehicle->projects = $vehicle->projects->map(function ($project) {
                    $project->service = $project->service->name;
                    $project->centre = $project->centre->name;
                    // $project->date = $project->created_at;
                    // unset($project->centre);
                    unset($project->service_id);
                    unset($project->centre_id);
                    unset($project->pivot);
                    return $project;
                });
            });
        }



        return $vehicles;
    }
    
    public function show(string $eco)
    {
        $vehicle = Vehicle::where('eco', $eco)->first();
        return $vehicle;
    }



    public function store(Request $request)
    {
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $fields = $request->validate([
            'centre_id' => 'nullable|exists:centres,id' 
        ],[
            'centre_id.exists' => 'El centro seleccionado no es válido'
        ]);

        $vehicle = Vehicle::find($id);

        $vehicle->update($fields);

        return $vehicle;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {

    }

    public function types()
    {
        $types = DB::table('vehicles_types')->select(['id', 'type'])->orderBy('order')->get();
        return $types;
    }
}
