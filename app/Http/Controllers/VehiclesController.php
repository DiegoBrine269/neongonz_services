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

        if ($request->invoice == 'pending') {
            $vehicles = ProjectVehicle::with([
                'vehicle:id,eco,vehicle_type_id', 
                'vehicle.type:id,type',
                'project.centre:id,name',
                'project.service:id,name',
            ])
            ->select('id', 'vehicle_id', 'project_id') 
            ->where('has_invoice', 0)
            ->get()
            ->map(function ($projectVehicle) {
                return [
                    'id' => $projectVehicle->id,
                    'vehicle_id' => $projectVehicle->vehicle->id,
                    'eco' => $projectVehicle->vehicle->eco ?? null, // Obtén el eco del vehículo
                    'centre_id' => $projectVehicle->project->centre->id ?? null, // Obtén el nombre del centro
                    'vehicle_type_id' => $projectVehicle->vehicle->vehicle_type_id,
                    'type' => $projectVehicle->vehicle->type->type,

                    'project_id' => $projectVehicle->project->id,
                    'project' => [
                        'id' => $projectVehicle->project->id,
                        'service' => $projectVehicle->project->service->name,
                        'service_id' => $projectVehicle->project->service->id,
                        'centre_id' => $projectVehicle->project->centre_id,
                    ]
                ];
            })
            ;
        }
        else{
            $query = Vehicle::with(['centre:id,name', 'type'])
                ->select('eco', 'centre_id', 'vehicle_type_id');
            
            // Apply filters from the request
            if ($request->has('filter')) {
                foreach ($request->filter as $filter) {
                    if (isset($filter['field'], $filter['type'], $filter['value'])) {
            
                        $field = $filter['field'];
                        $type = $filter['type'];
                        $value = $filter['value'];
            
                        // Si el filtro es para un campo relacionado
                        if ($field === 'centre') {
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
        

            $vehicles = $query->paginate(20);

            $vehicles->map(function ($vehicle) {
                $name = $vehicle->centre->name;
                $type = $vehicle->type->type;
                unset($vehicle->centre);
                unset($vehicle->type);

                $vehicle->centre = $name;
                $vehicle->type = $type;
                
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
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function types()
    {
        $types = DB::table('vehicles_types')->get();
        return $types;
    }
}
