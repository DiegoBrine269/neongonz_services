<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class VehiclesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $vehicles = Vehicle::with(['centre:id,name', 'type'])
            ->select('eco', 'centre_id', 'vehicle_type_id')
            ->get()
        ;

        $vehicles->map(function ($vehicle) {
            $vehicle->centre_name = $vehicle->centre->name;
            $vehicle->type_name = $vehicle->type->type;
            unset($vehicle->centre);
            unset($vehicle->type);
        });

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
