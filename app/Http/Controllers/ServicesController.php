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
        $query = Service::orderBy('name', 'asc')->select('id', 'name');

        $services = $query->get();


        return $services;
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        $fields = $request->validate([
            'name' => 'required|unique:services,name',
            // 'is_public' => 'boolean|required',
        ],[
            'name.required' => 'El nombre del servicio es obligatorio',
            'name.unique' => 'El nombre del servicio ya existe',
            // 'is_public.boolean' => 'El campo is_public debe ser verdadero o falso',
            // 'is_public.required' => 'El campo is_public es obligatorio',
        ]);

        // dump($fields);

        $service = Service::create([
            'name' => $fields['name'],
            // 'is_public' => $fields['is_public'],
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
            'centre_id' => 'nullable|exists:centres,id',
            'sat_unit_key' => 'required|string',
        ],[
            'name.required' => 'El nombre del servicio es obligatorio',
            'name.unique' => 'El nombre del servicio ya existe',
            'vehicles_types_prices.array' => 'El precio de los tipos de vehículos debe ser un array',
            'vehicles_types_prices.*.vehicle_type_id.exists' => 'El id del tipo de vehículo no existe',
            'centre_id.exists' => 'El id del centro no existe',
            'sat_unit_key.required' => 'La clave de unidad SAT es obligatoria',
            'sat_unit_key.string' => 'La clave de unidad SAT debe ser una cadena de texto',
        ]);

        $centre_id = $fields['centre_id'] ?? null;

        // Actualizar los precios de los tipos de vehículos
        if ($request->vehicles_types_prices) {
            foreach ($request->vehicles_types_prices as $item) {
            // Insertando o actualizando en la tabla pivote
            DB::table('service_vehicle_type')
                ->updateOrInsert(
                    [
                        'service_id' => $service->id,
                        'vehicle_type_id' => $item['vehicle_type_id'],
                        'centre_id' => $centre_id,
                    ],
                    [
                        'price' => $item['price'],
                    ]
                );
            }
        }
        dump($fields);
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
