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
                'vehicle:id,eco', 
                'project.centre:id,name',
                'project.service:id,name'
            ])
            ->select('id', 'vehicle_id', 'project_id') // Asegúrate de incluir 'project_id' para que la relación funcione
            ->where('has_invoice', 0)
            ->get()
            ->map(function ($projectVehicle) {
                return [
                    'id' => $projectVehicle->id,
                    'eco' => $projectVehicle->vehicle->eco ?? null, // Obtén el eco del vehículo
                    'centre_id' => $projectVehicle->project->centre->id ?? null, // Obtén el nombre del centro
                    'project_id' => $projectVehicle->project->id,
                    'project' => [
                        'id' => $projectVehicle->project->id,
                        'service' => $projectVehicle->project->service->name,
                        'centre_id' => $projectVehicle->project->centre_id,
                    ]
                ];
            });
        }
        else{
            $vehicles = Vehicle::with(['centre:id,name', 'type'])
                ->select('eco', 'centre_id', 'vehicle_type_id')
                ->get();

            $vehicles->map(function ($vehicle) {
                $vehicle->centre_name = $vehicle->centre->name;
                $vehicle->type_name = $vehicle->type->type;
                unset($vehicle->centre);
                unset($vehicle->type);
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
