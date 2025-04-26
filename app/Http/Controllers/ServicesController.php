<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class ServicesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {

        // Cargar las relaciones con services y centres
        $serviceCentres = Service::orderBy('name', 'asc')->select('id', 'name')->get();

        return response()->json($serviceCentres); 
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $fields = $request->validate([
            'name' => 'required|unique:services,name',
        ],[
            'name.required' => 'El nombre del servicio es obligatorio',
            'name.unique' => 'El nombre del servicio ya existe',
        ]);

        $service = Service::create([
            'name' => $fields['name'],
        ]);

        return response()->json([
            'message' => 'Servicio creado correctamente',
            'service' => $service,
        ], 201);

    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $service = Service::with('vehicleTypes')->find($id);


        if (!$service) {
            return response()->json([
                'message' => 'Servicio no encontrado',
            ], 404);
        }

        return response()->json($service);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
    

        $service = Service::find($id);

        if (!$service) {
            return response()->json([
                'message' => 'Servicio no encontrado',
            ], 404);
        }

        $fields = $request->validate([
            'name' => 'required|unique:services,name,' . $service->id,
            'vehicles_types_prices' => 'array',
            'vehicles_types_prices.*.vehicle_type_id' => 'exists:vehicles_types,id',
        ],[
            'name.required' => 'El nombre del servicio es obligatorio',
            'name.unique' => 'El nombre del servicio ya existe',
            'vehicles_types_prices.array' => 'El precio de los tipos de vehículos debe ser un array',
            'vehicles_types_prices.*.vehicle_type_id.exists' => 'El id del tipo de vehículo no existe',
        ]);

        // return response()->json([
        //     'request' => $request->vehicles_types_prices,
        // ]);
 
        // Actualizar los precios de los tipos de vehículos
        if ($request->vehicles_types_prices) {
            foreach ($request->vehicles_types_prices as $item) {

            // Insertando o actualizando en la tabla pivote
            DB::table('service_vehicle_type')
                ->updateOrInsert(
                [
                    'service_id' => $service->id,
                    'vehicle_type_id' => $item['vehicle_type_id'],
                ],
                [
                    'price' => $item['price'],
                ]
                );
            }
        }
        
        $service->update($fields);

        return response()->json([
            'message' => 'Servicio actualizado correctamente',
            'service' => $service,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
