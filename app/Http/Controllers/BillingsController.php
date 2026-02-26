<?php

namespace App\Http\Controllers;

use App\Helpers\EmailHelper;
use App\Http\Requests\StoreBillingRequest;
use App\Http\Requests\StoreComplementRequest;
use App\Models\Billing;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Responsible;
use App\Services\InvoiceService;
use Facturapi\Facturapi;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\HttpCache\Store;
use ZipArchive;

class BillingsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Billing::query();

        if ($request->filled('oc')) {
            $query->where('oc', $request->oc);
        }

        $billings = $query->with('invoice')->get();

        return response()->json($billings);
    }


    /**          
     * Store a newly created resource in storage.
     */
    public function store(StoreBillingRequest $request, InvoiceService $service)
    {
        $fields = $request->validated();

        $invoices = Invoice::whereIn('id', $fields['invoice_ids'])->get();

        if (!array_key_exists('joined', $fields)) {
            $fields['joined'] = false;
        }

        // Todas las invoices deben tener el mismo oc para generar un CFDI conjunto
        if($fields['joined'] && $invoices->pluck('oc')->unique()->count() > 1) {
            return response()->json(['error' => 'Todas las facturas deben tener la misma OC para generar un CFDI.'], 422);
        }

        foreach($invoices as $invoice) {
            if (!$invoice || $invoice->status != 'factura') 
                return response()->json(['error' => 'Factura no encontrada o en estado inválido.'], 404);
    
            if(!$invoice->centre->customer)
                return response()->json(['error' => 'El centro no tiene un cliente SAT asociado.'], 422);
        }

        $facturapi = new Facturapi(env('FACTURAPI_API_KEY'));

        $billings = $service->saveBilling($fields, $invoices, $facturapi);

        // dump($billings);

        // Enviando correo de notificación
        $responsiblePerson = Responsible::find($invoice->responsible_id);

        $attachments = [];
        foreach($billings as $billing) {
            // Adjuntando pdf y xml de cada factura al correo

            $pdfContent = Storage::get(config('app.billings_path') . "/pdf/" . $billing->pdf_path);
            $xmlContent = Storage::get(config('app.billings_path') . "/xml/" . $billing->xml_path);


            $attachments[] = [
                'filename' => $billing->pdf_path,
                'content' => base64_encode($pdfContent),
                'content_type' => 'application/pdf',
            ];
            $attachments[] = [
                'filename' => $billing->xml_path,
                'content' => base64_encode($xmlContent),
                'content_type' => 'application/xml',
            ];
        }

        $list = $invoices->map(
            fn($inv) => [
                'invoice_number' => $inv->invoice_number,
                'oc' => $inv->oc,
                'billing' => "FACT" . $inv->billing->folio_number,
            ]
        );

        $html = view('emails/billing', [
            'to' => $responsiblePerson->name,
            'list' => $list,

        ])->render();



        EmailHelper::notify($responsiblePerson->email, $html, $attachments, 'FACTURA(S)', $html);
    }

    /**
     * Display the specified resource.
     */
    public function storeComplement(StoreComplementRequest $request)
    {
        $fields = $request->validated();

        // dump($fields);

        $ids = array_map(fn($item) => $item['id'], $fields['data']);
        $billings = Billing::whereIn('id', $ids)->get();
        $invoices = Invoice::whereHas('billings', fn($query) => $query->whereIn('billings.id', $ids))->get();

        $items = collect($request->data);

        foreach($billings as $billing) {
            $billing->paid_amount = $items->firstWhere('id', $billing->id)['amount'] ?? $billing->total;
            
            // if (!$billings || $billings->status != 'complemento') 
            //     return response()->json(['error' => 'Factura no encontrada o en estado inválido.'], 404);

            // if(!$billings->centre->customer)
            //     return response()->json(['error' => 'El centro no tiene un cliente SAT asociado.'], 422);
        }

        $facturapi = new Facturapi(env('FACTURAPI_API_KEY'));
        $complements_path = config('app.complements_path');


        $customer = Customer::find($fields['customer_id']);

        $customer_object = [
            "legal_name" => $customer->legal_name,
            "tax_id" => $customer->tax_id,
            "tax_system" => $customer->tax_system,
            "address" => [
                "zip" => $customer->address_zip,
            ]
        ];

        $total_paid_amount = $items->sum('amount');
        $total_last_balance = $billings->sum('total' );

        $complement = Billing::create([
            'payment_form' => $fields['payment_form'],
            'payment_method' => 'PPD',
            'type' => 'complemento',
        ]);

        $related_documents = array_map(function($bill) {
                return [
                    "uuid" => trim($bill->uuid),
                    "amount" => $bill->paid_amount,
                    "installment" => 1 ,
                    "last_balance" => $bill->total,
                    "taxes" => [[
                        "base" => $bill->total / 1.16,
                        "type" => "IVA",
                        "rate" => 0.16
                    ]
                ]
            ];
        }, $billings->all());

        $sat_comp = $facturapi->Invoices->create([
            'type' => 'P',
            "customer" => $customer_object,
            "date" => $fields['payment_date'],
            "complements" => [
                [
                    "type" => "pago",
                    "data" => [[
                        "payment_form" => $fields['payment_form'],
                        "related_documents" => $related_documents
                    ]]
                ]
            ],
            // "folio_number" => $billing->id,
            "series" => "COMP"
        ]);
        
        $pdf = $facturapi->Invoices->download_pdf($sat_comp->id);
        $xml = $facturapi->Invoices->download_xml($sat_comp->id);
        
        // $file_name = trim("$invoice->id $invoice->oc");
        $file_name = "COMP" . $sat_comp->folio_number;
        
        $xml_path = "$complements_path/xml/$file_name.xml";
        $pdf_path = "$complements_path/pdf/$file_name.pdf";

        Storage::put($xml_path, $xml);
        Storage::put($pdf_path, $pdf);

        $complement->folio_number = $sat_comp->folio_number;
        $complement->pdf_path = "$file_name.pdf";
        $complement->xml_path = "$file_name.xml";
        $complement->uuid = $sat_comp->uuid;
        $complement->save();

        foreach($invoices as $invoice) {
            $invoice->billings()->syncWithoutDetaching([$complement->id]);
        }
            
        // En caso de que no se haya pagado la factura completa, hacer un segundo complemento con el resto del monto
        if($total_paid_amount < $total_last_balance){

            $total_last_balance -= $total_paid_amount;
            // $remaining_amount = ($invoice->total * 1.16) - $paid_amount;

            $complement2 = Billing::create([
                'payment_form' => $fields['payment_form'],
                'payment_method' => $fields['payment_method'] ?? 'PPD',
                'type' => 'complemento',
            ]);

            $related_documents = array_map(function($bill) use ($items) {
                    return [
                        "uuid" => trim($bill->uuid),
                        "amount" => $bill->total - $bill->paid_amount,
                        "installment" => 2 ,
                        "last_balance" => $bill->total - $bill->paid_amount,
                        "taxes" => [[
                            "base" => $bill->total/1.16,
                            "type" => "IVA",
                            "rate" => 0.16
                        ]
                    ]
                ];
            }, $billings->all());

            $sat_comp2 = $facturapi->Invoices->create([
                'type' => 'P',
                "customer" => $customer_object,
                "date" => $fields['payment_date'],
                "complements" => [
                    [
                        "type" => "pago",
                        "data" => [
                            [
                                "payment_form" => "17",
                                    "related_documents" => $related_documents,
                            ]
                        ]
                    ]
                ],
                // "folio_number" => $billing->id,
                "series" => "COMP"
            ]);

            $pdf = $facturapi->Invoices->download_pdf($sat_comp2->id);
            $xml = $facturapi->Invoices->download_xml($sat_comp2->id);
            
            $file_name = "COMP$sat_comp2->folio_number COMPENSACION";
            
            $xml_path = "$complements_path/xml/$file_name.xml";
            $pdf_path = "$complements_path/pdf/$file_name.pdf";

            Storage::put($xml_path, $xml);
            Storage::put($pdf_path, $pdf);

            $complement2->folio_number = $sat_comp2->folio_number;
            $complement2->uuid = $sat_comp2->uuid;
            $complement2->xml_path = "$file_name.xml";
            $complement2->pdf_path = "$file_name.pdf";
            $complement2->offsetUnset('paid_amount');
            $complement2->save();

            // $invoice->billings()->syncWithoutDetaching([$billing->id]);
            foreach($invoices as $invoice) {
                $invoice->billings()->syncWithoutDetaching([$complement2->id]);
            }
        }

        foreach($invoices as $invoice) {
            if($invoice->status == 'complemento'){
                $invoice->status = 'finalizada';
                $invoice->save();
            }
        }
    }

    public function show(Request $request, string $id)
    {
        $billing = Billing::find($id);

        $billings_path = config('app.billings_path');
        $complements_path = config('app.complements_path');

        if(!$billing) 
            return response()->json(['error' => 'Facturación no encontrada.'], 404);

        $base_path = $billing->type == 'factura' ? $billings_path : $complements_path;

        $pdf_path = "$base_path/pdf/{$billing->pdf_path}";
        $xml_path = "$base_path/xml/{$billing->xml_path}";

        if (!Storage::exists($pdf_path) || !Storage::exists($xml_path)) 
            return response()->json(['error' => 'Archivos no encontrados.'], 404);
        

        $zip = new ZipArchive();
        $type = $billing->type == 'factura' ? 'FACT' : 'COMP';
        $zipFileName = "SAT {$type}{$billing->folio_number}.zip";
        $tempPath = storage_path("temp/{$zipFileName}");

        // Create temp directory if it doesn't exist
        if (!file_exists(storage_path('temp'))) {
            mkdir(storage_path('temp'), 0755, true);
        }

        // Create and populate zip file
        if ($zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            $pdfFullPath = Storage::path($pdf_path);
            $xmlFullPath = Storage::path($xml_path);

            $zip->addFile($pdfFullPath, basename($billing->pdf_path));
            $zip->addFile($xmlFullPath, basename($billing->xml_path));
            $zip->close();

            return response()->download($tempPath, $zipFileName, [
                'Content-Type' => 'application/zip',
            ])->deleteFileAfterSend(true);
        }

          response()->json(['error' => 'Error al crear el archivo comprimido.'], 500);
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
}
