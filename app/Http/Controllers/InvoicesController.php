<?php

namespace App\Http\Controllers;

use App\Models\Centre;
use App\Models\Invoice;
use App\Models\VehicleType;
use Illuminate\Http\Request;
use App\Models\InvoiceVehicle;
use App\Models\ProjectVehicle;
use Illuminate\Support\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Resend\Laravel\Facades\Resend;
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
    
                    elseif ($field === 'centre') {
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
                    elseif ($field === 'services') {
                        $query->where('services', 'like', '%'. $value . '%');
                    }
                    elseif ($field === 'internal_commentary') {
                        $query->where('internal_commentary', 'like', '%'. $value . '%');
                    }
                }
            }
        }

        $invoices =    
            $query->when(request('sent_at') === 'null', fn($q) =>
                $q->whereNull('sent_at')
            )
            ->when(request('sent_at') !== null && request('sent_at') !== 'null', fn($q) =>
                $q->where('sent_at', request('sent_at'))
            );
    
        $invoices = $query->where('completed', true)->orderBy('updated_at', 'desc');

        $shouldPaginate = filter_var($request->query('paginate', true), FILTER_VALIDATE_BOOLEAN);

        $invoices = $shouldPaginate
                    ? $invoices->paginate(20)
                    : $invoices->get();
        
    
        $invoices->map(function ($invoice) {
            $centre = $invoice->centre->name;
            unset($invoice->centre);
            $invoice->centre = $centre;
        });
    
        return $invoices;
    }
    

    public function pending()
    {

        $invoices = Invoice::with('centre')
        ->where('completed', false)
        ->orderBy('id', 'desc')
        ->get();


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


            $includedServices = $groupedByProject->pluck('service')->unique()->toArray();

            $invoice = Invoice::create([
                'centre_id' => $centre->id,
                'date' => today(),
                'comments' => $fields['comments'],
                'total' => $grandTotal,
                'services' => implode(", ", $includedServices)
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
            'custom' => false
        ]);
        
        $pdfContent = $pdf->output();
        
        // Nombre del archivo PDF
        $filename = $invoice_number . '.pdf';



        // Crear el directorio si no existe
        if (!Storage::exists('invoices')) {
            Storage::makeDirectory('invoices');
        }

        // Guardar en el bucket
        Storage::put("invoices/$filename", $pdfContent);

        $html = view('email', [
            'destinatario' => $centre->responsible
        ])->render();

        Resend::emails()->send([
            'from' => 'Neon Gonz <servicios@neongonz.com>',
            'to' => ['diegooloarte269@gmail.com'],
            'cc' => ['neongonz@hotmail.com'],
            'subject' => 'Solicitud de órdenes de compra',
            'reply_to' => 'neongonz@hotmail.com',
            'html' => $html,
            'attachments' => [
                [
                    'filename' => $filename,
                    'content' => base64_encode($pdfContent),
                    'contentType' => 'application/pdf',
                ]
            ],
        ]);

        $invoice->path = $filename; 
        $invoice->save();
        
        // Devolver la respuesta (opcional, si también quieres mostrarlo al usuario)
        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $invoice_number . '.pdf"')
            ->header('Access-Control-Expose-Headers', 'Content-Disposition');
    }

    public function createCustom(Request $request)
    {
        $fields = $request->validate([
            'invoice_id' => 'nullable|exists:invoices,id',
            'centre_id' => 'required|exists:centres,id',
            'concept' => 'required|string|max:255',
            'quantity' => 'required|numeric|min:1',
            'price' => 'required|numeric|min:1',
            'comments' => 'nullable|string|max:255',
            'completed' => 'boolean',
            'internal_commentary' => 'nullable|string|max:255',
            'date' => 'required|date|before_or_equal:today',
            
        ], [
            'invoice_id.exists' => 'La cotización que intentas imprimir no existe', 
            'centre_id.required' => 'El centro es obligatorio.',
            'centre_id.exists' => 'El centro seleccionado no existe.',
            'concept.required' => 'El concepto es obligatorio.',
            'quantity.required' => 'La cantidad es obligatoria.',
            'price.required' => 'El precio es obligatorio.',
            'quantity.numeric' => 'La cantidad debe ser un número.',
            'price.numeric' => 'El precio debe ser un número.',
            'quantity.min' => 'La cantidad debe ser al menos 1.',
            'price.min' => 'El precio debe ser al menos 1.',
            'comments.max' => 'El comentario debe ser menor a 255 caracteres.',
            'concept.max' => 'El concepto debe ser menor a 255 caracteres.',
            'completed.boolean' => 'El campo completado debe ser verdadero o falso.',
            'internal_commentary.max' => 'El comentario debe ser menor a 255 caracteres.',
            'date.required' => 'La fecha es obligatoria.',
            'date.date' => 'La fecha no es válida.',
            'date.before_or_equal' => 'La fecha no puede ser futura.',
        ]);
    

    
        // Variables para usar fuera de la transacción
        $invoice = null;
        $invoice_number = null;
    
        

        if($fields['invoice_id']){
            $invoice = Invoice::find($fields['invoice_id']);
            $invoice->update($fields);
            
            // dump($invoice);
        }else {
            $invoice = Invoice::create([
                'centre_id' => $fields['centre_id'],
                'date' => $fields['date'],
                'comments' => $fields['comments'] ?? null,
                'total' => $fields['quantity'] * $fields['price'],
                'completed' => $fields['completed'] , 
                'concept' => $fields['concept'],
                'quantity' => $fields['quantity'],
                'price' => $fields['price'],
                'services' => $fields['concept'] ?? null,
                'internal_commentary' => $fields['internal_commentary'] ?? null,
            ]);
        }

        
        
        $today = today()->format('Ymd');
        $invoice_number = "COT_$today" . "_" . $fields['centre_id'] . "_" . $invoice->id;
        $invoice->invoice_number = $invoice_number;
        $invoice->save();
        
        
        $centre = Centre::find($fields['centre_id']);
        
        // dump($fields['completed']);

        if(!$fields['completed']) {
            return response()->json([
                'success' => true,
                'message' => 'Factura creada como borrador.',
                'invoice_number' => $invoice_number,
                'invoice_id' => $invoice->id,


            ], 201);
        }


        // dump($fields);

        $pdf = Pdf::loadView('invoice', [
            'invoice_number' => $invoice_number,
            'date' => Carbon::now()->locale('es')->translatedFormat('j \\d\\e F \\d\\e Y'),
            'centre' => $centre,
            'comments' => $fields['comments'] ?? null,
            'custom' => true,
            'concept' => $fields['concept'],
            'quantity' => $fields['quantity'],
            'price' => $fields['price'],
            
        ]);
        
        $pdfContent = $pdf->output();
        
        // Nombre del archivo PDF
        $filename = $invoice_number . '.pdf';



        // Crear el directorio si no existe
        if (!Storage::exists('invoices')) {
            Storage::makeDirectory('invoices');
        }

        // Guardar en el bucket
        Storage::put("invoices/$filename", $pdfContent);

        $invoice->path = $filename; 
        $invoice->save();



        // Enviando factura por correo
        
        // Devolver la respuesta (opcional, si también quieres mostrarlo al usuario)
        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $invoice_number . '.pdf"')
            ->header('Access-Control-Expose-Headers', 'Content-Disposition');
    }


    public function downloadPdf(Invoice $invoice)
    {
        // Validar que el usuario puede acceder a esta factura
        // $this->authorize('view', $invoice);

        // Obtener la ruta almacenada en el campo path
        $path = "invoices/".$invoice->path;

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
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $invoice = Invoice::find($id);

        if (!$invoice) {
            return response()->json(['error' => 'Factura no encontrada.'], 404);
        }

        $fields = $request->validate([
            'total' => 'required|numeric|min:0',
            'comments' => 'nullable|string|max:255',
            'completed' => 'boolean',
            'concept' => 'required|string|max:255',
            'quantity' => 'required|numeric|min:1',
            'price' => 'required|numeric|min:1',
            'internal_commentary' => 'nullable|string|max:255',
            'date' => 'required|date|before_or_equal:today',

        ], [

            'total.required' => 'El total es obligatorio.', 
            'total.numeric' => 'El total debe ser un número.',
            'total.min' => 'El total debe ser al menos 0.',
            'comments.max' => 'El comentario debe ser menor a 255 caracteres.',
            'concept.required' => 'El concepto es obligatorio.',
            'concept.max' => 'El concepto debe ser menor a 255 caracteres.',
            'quantity.required' => 'La cantidad es obligatoria.',
            'quantity.numeric' => 'La cantidad debe ser un número.',
            'quantity.min' => 'La cantidad debe ser al menos 1.',
            'price.required' => 'El precio es obligatorio.',
            'price.numeric' => 'El precio debe ser un número.',
            'price.min' => 'El precio debe ser al menos 1.',
            'completed.boolean' => 'El campo completado debe ser verdadero o falso.',
            'internal_commentary.max' => 'El comentario debe ser menor a 255 caracteres.',
            'date.required' => 'La fecha es obligatoria.',
            'date.date' => 'La fecha no es válida.',
            'date.before_or_equal' => 'La fecha no puede ser futura.',
        ]);


        $invoice->update($fields);

        return response()->json(['message' => 'Factura actualizada correctamente.', 'invoice' => $invoice]);
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

        InvoiceVehicle::where('invoice_id', $invoice->id)->delete();

        if ($invoice->path)
            Storage::delete("invoices/".$invoice->path); 

        // Eliminar el pdf
        $invoice->delete();

        return response()->json(['message' => 'Cotización eliminada y vehículos actualizados correctamente.']);
    }
}
