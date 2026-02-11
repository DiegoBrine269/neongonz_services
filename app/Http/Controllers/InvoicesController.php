<?php

namespace App\Http\Controllers;

use App\Models\Centre;
use App\Models\Billing;
use App\Models\Invoice;
use App\Models\Project;
use Facturapi\Facturapi;
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
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use Illuminate\Validation\ValidationException;
use App\Http\Requests\StoreCustomInvoiceRequest;

class InvoicesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Invoice::with(['centre', 'billing', 'complements']);
    
        if ($request->has('filter')) {
            foreach ($request->filter as $filter) {
                if (isset($filter['field'], $filter['type'], $filter['value'])) {
    
                    $field = $filter['field'];
                    $type = $filter['type'];
                    $value = $filter['value'];
    
                    // Si es un filtro por fecha, convertirla a formato Y-m-d
                    if ($field === 'date') {
                        try {
                            // $value = \Carbon\Carbon::createFromFormat('d/m/Y', $value)->format('Y-m-d');
                            $query->where('date', '=', $value);
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
                    elseif ($field === 'status') {
                        if (in_array($value, ['envio', 'oc', 'factura', 'f', 'complemento', 'finalizada'])) {
                            $query->where('status', $value);
                        }
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

        $invoices = Invoice::with('centre', 'rows')
            ->where('completed', false)
            ->orderBy('id', 'desc')
            ->get();


        return $invoices;
    }

    public function emailPending()
    {

        $invoices = Invoice::with('centre')
            ->whereNull('sent_at')
            ->where('completed', true)
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
                
                if($invoice->status == 'envio'){
                    $invoice->status = 'oc';
                }
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

            $html = view('emails/invoice', [
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
    public function store(StoreInvoiceRequest $request, InvoiceService $service)
    {    
        $fields = $request->validated();
        
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

    public function createCustom(StoreCustomInvoiceRequest $request)
    {
        $fields = $request->validated();
    
    
        // Variables para usar fuera de la transacción
        $invoice = null;
        $invoice_number = null;
    
        $rows = collect($fields['rows']);
        $total = $rows->sum(fn ($item) => $item['quantity'] * $item['price']);
        $services = $rows->pluck('concept')->implode(', ');

        // Datos comunes para create / update
        $data = [
            'centre_id'           => $fields['centre_id'],
            'date'                => $fields['date'],
            'comments'            => $fields['comments'] ?? null,
            'total'               => $total,
            'services'            => $services,
            'completed'           => $fields['completed'],
            'internal_commentary' => $fields['internal_commentary'] ?? null,
            'is_budget'           => $fields['is_budget'],
            'responsible_id'      => $fields['responsible_id'],
        ];

        if (!empty($fields['invoice_id'])) {
            $invoice = Invoice::findOrFail($fields['invoice_id']);
            $invoice->update($data);
        } else {
            $invoice = Invoice::create($data);
        }

        $invoice_number = "COT_" . $invoice->id;
        $invoice->invoice_number = $invoice_number;
        
        $invoice->rows()->delete();   
        $invoice->rows()->createMany($fields['rows']);
        

        $centre = Centre::with('responsibles')->find($fields['centre_id']);
        $centre->responsible = $centre->responsibles()->find($fields['responsible_id']);


        if(!$fields['completed']) {
            return response()->json([
                'success' => true,
                'message' => 'Factura creada como borrador.',
                'invoice_number' => $invoice_number,
                'invoice_id' => $invoice->id,
            ], 201);
        }


        $pdf = Pdf::loadView('invoice', [
            'invoice' => $invoice,
            'date' => Carbon::now()->locale('es')->translatedFormat('j \\d\\e F \\d\\e Y'),
            'responsible' => $centre->responsible,
            'customInvoice' => true,
        ]);
        
        $pdfContent = $pdf->output();
        
        // Nombre del archivo PDF
        $filename = $invoice_number . '.pdf';

        // Guardar en el bucket
        Storage::put("invoices/$filename", $pdfContent);

        $invoice->path = $filename; 

        if($fields['completed'])
            $invoice->status = 'envio';

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

        $url = Storage::temporaryUrl(
            $path, now()->addMinutes(5)
        );

        return response()->json(['url' => $url], 200);
    }
    


    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateInvoiceRequest $request, string $id, InvoiceService $service)
    {
        $fields = $request->validated();

        $invoice = Invoice::findOrFail($id);

        
        if(!empty($fields['vehicles'])){
            
            [$invoice, $pdfContent, $filename] = $service->saveInvoice($fields, $invoice);
            //Eliminando el resto de vehículos
            $invoiceVehicles = InvoiceVehicle::where('invoice_id', $invoice->id)->get();
    
            //Detach vehicles no seleccionados
            $selectedVehicleIds = collect($fields['vehicles'])->pluck('vehicle_id')->toArray();
    
    
    
            foreach ($invoiceVehicles as $invVehicle) {
                if (!in_array($invVehicle->vehicle_id, $selectedVehicleIds)) {
                    
                    ProjectVehicle::where('vehicle_id', $invVehicle->vehicle_id)
                        ->where('invoice_id', $invoice->id)
                        ->update(['invoice_id' => null]);  
                         
                    // Eliminar de invoice_vehicles
                    $invVehicle->delete();
                }
            }
            return response($pdfContent, 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->header('Access-Control-Expose-Headers', 'Content-Disposition');
        }

        $invoice->update($fields);
        return response()->json(['message' => 'Factura actualizada correctamente.']);

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

        $from = config('mail.mailers.smtp.username');

        if (app()->environment('local')) 
            $subject = "[CORREO DE PRUEBA] " . $subject;

        Resend::emails()->send([
            'from' => "Neón Gonz <$from>",
            'to' => [$email],
            'cc' => ['neongonz@hotmail.com'],
            'subject' => $subject,
            'reply_to' => 'neongonz@hotmail.com',
            'html' => $html,
            'attachments' => $attachments,
        ]);

        return true;
            
    }



    public function showUnits()
    {  
        $units = DB::table('sat_units')
            ->where('active', true)
            ->orderBy('name', 'asc')
            ->get();

        return $units;
    }

    public function updateStatus(Invoice $invoice, Request $request)
    {
        // Lógica para actualizar el estado de la factura
        $previousStatus = $invoice->status;

        switch ($invoice->status) {
            case 'envio':
                $invoice->status = 'oc';
                break;
            case 'oc':
                $request->validate([
                    'oc' => 'required|string|max:50',
                ], [
                    'oc.required' => 'El número de orden de compra es obligatorio.',
                    'oc.string' => 'El número de orden de compra debe ser una cadena de texto.',
                    'oc.max' => 'El número de orden de compra no debe exceder los 50 caracteres.',
                ]);

                $invoice->oc = trim($request->input('oc'));
                $invoice->status = 'factura';
                break;
                
            case 'f':
                $request->validate([
                    'f_receipt' => 'required|string|max:50',
                    'validation_date' => [
                        'required',
                        'date',
                        function ($attribute, $value, $fail) use ($invoice) {
                            if (Carbon::parse($value)->endOfDay()->lte($invoice->billing->created_at)) {
                                $fail('La fecha de validación debe ser igual o posterior a la fecha de facturación.');
                            }
                        },
                    ],
                ], [
                    'f_receipt.required' => 'El número de recibo es obligatorio.',
                    'f_receipt.string' => 'El número de recibo debe ser una cadena de texto.',
                    'f_receipt.max' => 'El número de recibo no debe exceder los 50 caracteres.',
                    'validation_date.required' => 'La fecha de validación es obligatoria.',
                    'validation_date.date' => 'La fecha de validación no es una fecha válida.',
                ]);

                $invoice->f_receipt = $request->input('f');
                $invoice->validation_date = $request->input('validation_date');

                if($invoice->billing->payment_method == 'PPD'){
                    $invoice->status = 'complemento';
                } else {
                    $invoice->status = 'finalizada';
                }
                break;
        
            default:
                return response()->json(['error' => 'El estado de la factura no se puede actualizar.'], 400);
        }

        $invoice->save();

        return response()->json([
            'message' => 'Estado de la factura actualizado correctamente.',
            'previous_status' => $previousStatus,
            'new_status' => $invoice->status,
        ]);
    }

}
