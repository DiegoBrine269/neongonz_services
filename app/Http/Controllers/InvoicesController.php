<?php

namespace App\Http\Controllers;

use App\Models\Centre;
use App\Models\Invoice;
use App\Models\VehicleType;
use Illuminate\Http\Request;
use App\Models\ProjectVehicle;
use Illuminate\Support\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class InvoicesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Invoice::with('centre');
    
        if ($request->has('filter')) {
            foreach ($request->filter as $filter) {
                if (isset($filter['field'], $filter['type'], $filter['value'])) {
    
                    $field = $filter['field'];
                    $type = $filter['type'];
                    $value = $filter['value'];
    
                    // Si es un filtro por fecha, convertirla a formato Y-m-d
                    if ($field === 'date') {
                        try {
                            $value = \Carbon\Carbon::createFromFormat('d/m/Y', $value)->format('Y-m-d');
                        } catch (\Exception $e) {
                            continue; // Ignorar si la fecha no se puede convertir
                        }
                    }
    
                    if ($field === 'centre') {
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
                        
                    } elseif ($field === 'total') {
                        // Comparación exacta por total (numérica)
                        if ($type === '=') {
                            $query->where($field, '=', $value);
                        }
                    }
                }
            }
        }
    
        $invoices = $query->orderBy('id', 'desc')->paginate(20);
        
    
        $invoices->map(function ($invoice) {
            $centre = $invoice->centre->name;
            unset($invoice->centre);
            $invoice->centre = $centre;
        });
    
        return $invoices;
    }
    


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $fields = $request->validate([
            'vehicles' => [
                'required',
                'array',
                'min:1',
                function ($attribute, $value, $fail) {
                    $serviceIds = collect($value)->pluck('project.service_id')->unique();
                    $existing = DB::table('service_vehicle_type')
                        ->whereIn('service_id', $serviceIds)
                        ->pluck('service_id');
                    $missing = $serviceIds->diff($existing);
                    if ($missing->isNotEmpty()) {
                        $fail('Faltan precios por registrar.');
                    }
                }
            ],
            'comments' => 'max:255'
        ], [
            'vehicles.required' => 'Debes seleccionar al menos un vehículo.',
            'vehicles.array' => 'El campo vehicles debe ser un array.',
            'vehicles.min' => 'Debes seleccionar al menos un vehículo.',
            'comments.max' => 'El comentario debe ser menor a 255 caracteres.',
        ]);
    
        $centre = Centre::find($fields['vehicles'][0]['centre_id']);
    
        $groupedByProject = collect($fields['vehicles'])->groupBy(function ($vehicle) {
            return $vehicle['project']['id'];
        })->map(function ($vehicles, $projectId) {
            $service = $vehicles[0]['project']['service'];
            $service_id = $vehicles[0]['project']['service_id'];
    
            $serviceVehicleTypes = DB::table('service_vehicle_type')
                ->where('service_id', $service_id)
                ->get()
                ->keyBy('vehicle_type_id');
    
            $_vehicles = $vehicles->map(function ($vehicle) use ($serviceVehicleTypes) {
                $vehicleTypeId = $vehicle['vehicle_type_id'];
                $price = $serviceVehicleTypes->get($vehicleTypeId)->price ?? 0;
                $vehicle['price'] = $price;
                unset($vehicle['project']);
                return (object)$vehicle;
            });
    
            $vehiclesGroupedByPrice = $_vehicles->groupBy('price')->map(function ($group) {
                return $group->groupBy('vehicle_type_id')->map(function ($_vehicles, $vehicleTypeId) {
                    $type = VehicleType::find($vehicleTypeId)->type ?? 'Desconocido';
                    return [
                        'type' => $type,
                        'group' => $_vehicles,
                    ];
                });
            });
    
            return (object)[
                'service' => $service,
                'vehicles_grouped_by_price' => $vehiclesGroupedByPrice,
                'service_vehicle_types' => $serviceVehicleTypes,
            ];
        });
    
        // Variables para usar fuera de la transacción
        $invoice = null;
        $invoice_number = null;
    
        // Transacción solo para guardar
        DB::transaction(function () use (&$invoice, &$invoice_number, $centre, $fields, $groupedByProject) {
            // Calculando el total de la cotización
            $grandTotal = 0;
            foreach ($groupedByProject as $project) {
                foreach ($project->vehicles_grouped_by_price as $price => $grouped_by_price) {
                    foreach ($grouped_by_price as $data) {
                        $groupedVehicles = $data['group'];
                        $totalForGroup = $groupedVehicles->sum('price');
                        $grandTotal += $totalForGroup;
                    }
                }
            }

            $invoice = Invoice::create([
                'centre_id' => $centre->id,
                'date' => today(),
                'comments' => $fields['comments'],
                'total' => $grandTotal,
            ]);
            
    
            foreach ($fields['vehicles'] as $vehicle) {
                $invoice->invoiceVehicles()->create([
                    'vehicle_id' => $vehicle['vehicle_id'],
                    'project_id' => $vehicle['project']['id'],
                ]);
    
                DB::table('project_vehicles')
                    ->where('vehicle_id', $vehicle['vehicle_id'])
                    ->where('project_id', $vehicle['project']['id'])
                    ->update(['has_invoice' => true]);
            }
    
            $today = today()->format('Ymd');
            $invoice_number = "COT_$today" . "_" . $centre->id . "_" . $invoice->id;
            $invoice->invoice_number = $invoice_number;
            $invoice->save();
        });


    
        $pdf = Pdf::loadView('invoice', [
            'invoice_number' => $invoice_number,
            'date' => Carbon::now()->locale('es')->translatedFormat('j \\d\\e F \\d\\e Y'),
            'centre' => $centre,
            'projects' => $groupedByProject,
            'comments' => $fields['comments'],
        ]);
        
        $pdfContent = $pdf->output();
        
        // Nombre del archivo PDF
        $filename = $invoice_number . '.pdf';

        // Guardar en el bucket
        Storage::put($filename, $pdfContent);

        $invoice->path = $filename; // Solo el nombre, o puedes usar "bucket/$filename" si prefieres
        $invoice->save();
        
        // Devolver la respuesta (opcional, si también quieres mostrarlo al usuario)
        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . $invoice_number . '.pdf"');
    }

    public function downloadPdf(Invoice $invoice)
    {
        // Validar que el usuario puede acceder a esta factura
        // $this->authorize('view', $invoice);

        // Obtener la ruta almacenada en el campo path
        $path = $invoice->path;

        // Verificar si el archivo existe en el almacenamiento
        if (!Storage::exists($path)) {
            return response()->json(['error' => 'Archivo no encontrado.'], 404);
        }

        // Obtener el contenido del archivo
        $fileContent = Storage::get($path);

        // Devolver el archivo como respuesta
        return response($fileContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . basename($path) . '"');
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
        $invoice = Invoice::find($id);

        if (!$invoice) {
            return response()->json(['error' => 'Factura no encontrada.'], 404);
        }

        $vehicles = $invoice->vehicles;


        foreach ($vehicles as $vehicle) {
            ProjectVehicle::
                where('vehicle_id', $vehicle->id)
                ->where('project_id', $vehicle->pivot->project_id) // Usar el project_id de la tabla pivote
                ->update(['has_invoice' => false]);
        }

        if ($invoice->path)
            Storage::delete($invoice->path); 

        // Eliminar la factura
        $invoice->delete();

        return response()->json(['message' => 'Cotización eliminada y vehículos actualizados correctamente.']);
    }
}
