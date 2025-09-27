<?php

namespace App\Http\Controllers;

use App\Models\Centre;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Responsible;
use App\Models\VehicleType;
use Illuminate\Http\Request;
use App\Models\InvoiceVehicle;
use App\Models\ProjectVehicle;
use Illuminate\Support\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\InvoiceService;
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

    public function emailPending()
    {

        $invoices = Invoice::with('centre')
            ->whereNull('sent_at')
            ->orderBy('id', 'desc')
            ->get();


        return $invoices;
    }

    public function send(Request $request)
    {

        // Si es un solo int, conviértelo a array
        if (is_numeric($request->invoice_ids)) {
            $request->merge(['invoice_ids' => [(int)$request->invoice_ids]]);
        }

        $fields = $request->validate([
            'invoice_ids' => 'required|array|min:1',
            'invoice_ids.*' => 'exists:invoices,id',
        ], [
            'invoice_ids.required' => 'Debes seleccionar al menos una cotización para enviar.',
            'invoice_ids.array' => 'El formato de los IDs de las cotizaciones no es válido.',
            'invoice_ids.min' => 'Debes seleccionar al menos una cotización para enviar.',
            'invoice_ids.*.exists' => 'Una o más cotizaciones seleccionadas no existen.',
        ]);

        // Obtener los invoices
        $invoices = Invoice::whereIn('id', $fields['invoice_ids'])->pluck('is_budget');

        // Validar que todos los valores de is_budget sean iguales, NO SE PUEDEN ENVIAR COTIZACIONES Y PRESUPUESTOS JUNTOS
        if ($invoices->unique()->count() > 1) {
            return response()->json([
                'error' => 'No puedes enviar cotizaciones y presupuestos juntos. Selecciona solo un tipo.'
            ], 422);
        }

        $invoices = Invoice::with('centre')
            ->whereIn('id', $fields['invoice_ids'])
            ->get();

        $grouped = $invoices->groupBy('responsible_id');

        foreach ($grouped as $centreId => $invoicesGroup) {
            $centre = $invoicesGroup->first()->centre;

            $attachments = [];

            $responsiblePerson = null;

            foreach ($invoicesGroup as $invoice) {
                $invoice->sent_at = Carbon::now();
                $invoice->save();

                $filename = $invoice->invoice_number . ".pdf";
                $pdfContent = Storage::get("invoices/{$filename}");

                $attachments[] = [
                    'filename' => $filename,
                    'content' => base64_encode($pdfContent),
                    'contentType' => 'application/pdf',
                ];

                $responsiblePerson = Responsible::find($invoice->responsible_id);
            }


            $type = $invoicesGroup->first()->is_budget ? 'PRE' : 'COT';

            $html = view('email', [
                'destinatario' => $responsiblePerson->name,
                'type' => $type
            ])->render();
        
    
            $subject = ($invoicesGroup->first()->is_budget ? 'Presupuestos' : 'Solicitud de órdenes de compra') . " - {$centre->name}";


            $responseEmail = $this->notify($responsiblePerson->email, $html, $attachments, $subject);

            // Si no se pudo enviar el correo, revertir la fecha de envío
            if (!$responseEmail) {
                foreach ($invoicesGroup as $invoice) {
                    $invoice->sent_at = null;
                    $invoice->save();
                }
                //Retornar mensaje de error si no se pudo enviar el correo
                return response()->json(['error' => "No se pudo enviar el correo a {$centre->name}. Verifica que el correo electrónico esté configurado correctamente."], 500); 
            }


            usleep(600000);
        }

        // Retornar OK 200
        return response()->json(['message' => 'Cotizaciones enviadas correctamente.']);
        
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, InvoiceService $service)
    {    
        $fields = $request->validate([
            'vehicles' => 'required|array|min:1',
            'comments' => 'max:255',
            'responsible_id' => 'required|exists:responsibles,id',
        ],[
            'vehicles.required' => 'Debes seleccionar al menos un vehículo para la cotización.',
            'vehicles.array' => 'El formato de los vehículos no es válido.',
            'vehicles.min' => 'Debes seleccionar al menos un vehículo para la cotización.',
            'comments.max' => 'El comentario debe ser menor a 255 caracteres.',
            'responsible_id.required' => 'El responsable es obligatorio.',
            'responsible_id.exists' => 'El responsable seleccionado no existe.',
        ]);
        
        $projectVehicleIds = collect($fields['vehicles'])->pluck('id')->toArray();

        $alreadyAssigned = ProjectVehicle::whereIn('id', $projectVehicleIds)
            ->whereNotNull('invoice_id')
            ->exists();

        if ($alreadyAssigned) {
            return response()->json([
                'error' => 'Uno o más vehículos ya están asignados a una cotización.'
            ], 422);
        }

        [$invoice, $pdfContent, $filename] = $service->saveInvoice($fields);

        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Access-Control-Expose-Headers', 'Content-Disposition');
    }

    public function createCustom(Request $request)
    {
        $fields = $request->validate([
            'invoice_id' => 'nullable|exists:invoices,id',
            'centre_id' => 'required|exists:centres,id',
            'concept' => 'required|string',
            'quantity' => 'required|numeric|min:1',
            'price' => 'required|numeric|min:1',
            'comments' => 'nullable|string|max:255',
            'completed' => 'boolean',
            'internal_commentary' => 'nullable|string|max:255',
            'date' => 'required|date|before_or_equal:today',
            'is_budget' => 'boolean',
            
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
            'completed.boolean' => 'El campo completado debe ser verdadero o falso.',
            'internal_commentary.max' => 'El comentario debe ser menor a 255 caracteres.',
            'date.required' => 'La fecha es obligatoria.',
            'date.date' => 'La fecha no es válida.',
            'date.before_or_equal' => 'La fecha no puede ser futura.',
            'is_budget.boolean' => 'El campo tipo debe ser verdadero o falso.',
        ]);
    

    
        // Variables para usar fuera de la transacción
        $invoice = null;
        $invoice_number = null;
    
        

        if($fields['invoice_id']){
            $invoice = Invoice::find($fields['invoice_id']);
            $invoice->update($fields);
            
            // dump($invoice);
        }else {

            // dump($fields);
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
                'is_budget' => $fields['is_budget'] ,
            ]);
        }

        $invoice_number = "COT_" . $invoice->id;
        $invoice->invoice_number = $invoice_number;
        $invoice->save();        
        
        $centre = Centre::with('responsibles')->find($fields['centre_id']);
        
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
    public function update(Request $request, string $id, InvoiceService $service)
    {
        $fields = $request->validate([
            'vehicles' => 'required|array|min:1',
            'date' => 'required|date|before_or_equal:today',
            'comments' => 'max:255',
        ]);

        $invoice = Invoice::findOrFail($id);

        [$invoice, $pdfContent, $filename] = $service->saveInvoice($fields, $invoice);
        // dump($filename);

        //Eliminando el resto de vehículos
        $invoiceVehicles = InvoiceVehicle::where('invoice_id', $invoice->id)->get();

        //Detach vehicles no seleccionados
        $selectedVehicleIds = collect($fields['vehicles'])->pluck('vehicle_id')->toArray();



        foreach ($invoiceVehicles as $invVehicle) {
            // dump($invVehicle->vehicle_id);
            if (!in_array($invVehicle->vehicle_id, $selectedVehicleIds)) {
                
                ProjectVehicle::where('vehicle_id', $invVehicle->vehicle_id)
                    ->where('invoice_id', $invoice->id)
                    ->update(['invoice_id' => null]);  
                     
                // Eliminar de invoice_vehicles
                $invVehicle->delete();
            }
        }

        $invoice->date = $fields['date'];


        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Access-Control-Expose-Headers', 'Content-Disposition');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $invoice = Invoice::with([
            'centre',
            'projectVehicles' => function ($query) {
                $query->with([
                    'vehicle:id,eco,vehicle_type_id', 
                    'vehicle.type:id,type',
                    'project',
                    'project.service'=> function ($query) {
                        return $query->with('vehicleTypes')->get();
                    }


                ]);
            },
        ])->find($id);


        if (!$invoice) {
            return response()->json(['error' => 'Factura no encontrada.'], 404);
        }


        // Asignar el proyecto correcto a cada vehículo
        $vehicles = $invoice->projectVehicles->map(function ($pv) {
            $v = $pv->vehicle;
            $p = $pv->project;

            // dump($v);
            $filteredType = $p->service->vehicleTypes->firstWhere('id', $v->type->id);

            return [
                    'id' => $pv->id,
                    'vehicle_id' => $v->id ?? null,
                    'eco' => $v->eco ?? null,
                    'centre_id' => $p->centre->id,
                    'vehicle_type_id' => $v->vehicle_type_id ?? null,
                    'type' => $v->type->type ?? null,
                    'project_id' => $pv->project_id,
                    'project' => $p ? [
                        'id' => $p->id,
                        'service' => $p->service->name ?? null, // <-- solo el string
                        'service_id' => $p->service_id,
                        'centre_id' => $p->centre_id,
                        'date' => $p->date,
                    ] : null,
                    'price' => $filteredType?->pivot?->price ,

                ];
            
        });

        $invoice->vehicles = $vehicles;
        unset($invoice->projectVehicles);
        
        return $invoice;
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
                ->update(['invoice_id' => null]);
        }

        InvoiceVehicle::where('invoice_id', $invoice->id)->delete();

        if ($invoice->path)
            Storage::delete("invoices/".$invoice->path); 

        // Eliminar el pdf
        $invoice->delete();

        return response()->json(['message' => 'Cotización eliminada y vehículos actualizados correctamente.']);
    }

    public function destroyMultiple(Request $request)
    {
        $ids = $request->input('ids'); // array de IDs

        foreach ($ids as $id) {
            $invoice = Invoice::find($id);

            if ($invoice) {
                $vehicles = $invoice->vehicles;

                foreach ($vehicles as $vehicle) {
                    ProjectVehicle::
                        where('vehicle_id', $vehicle->id)
                        ->where('project_id', $vehicle->pivot->project_id) // Usar el project_id de la tabla pivote
                        ->update(['invoice_id' => null]);
                }

                InvoiceVehicle::where('invoice_id', $invoice->id)->delete();

                if ($invoice->path)
                    Storage::delete("invoices/".$invoice->path); 

                $invoice->delete();
            }
        }

        Invoice::whereIn('id', $ids)->delete();

        return response()->json(['message' => 'Cotizaciones eliminadas correctamente']);
    }

    public function notify($email, $html, $attachments, $subject = 'Solicitud de órdenes de compra'){

        if(!$email) {
            return false;
        }

        Resend::emails()->send([
            'from' => 'Neón Gonz <servicios@neongonz.com>',
            'to' => [$email],
            'cc' => ['neongonz@hotmail.com'],
            'subject' => "CORREO DE PRUEBA $subject",
            'reply_to' => 'neongonz@hotmail.com',
            'html' => $html,
            'attachments' => $attachments,
        ]);

        return true;
            
    }
}
