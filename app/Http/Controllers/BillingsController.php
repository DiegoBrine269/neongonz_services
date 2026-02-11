<?php

namespace App\Http\Controllers;

use ZipArchive;
use App\Models\Billing;
use App\Models\Invoice;
use Facturapi\Facturapi;
use Illuminate\Http\Request;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\StoreBillingRequest;
use App\Http\Requests\StoreComplementRequest;
use Symfony\Component\HttpKernel\HttpCache\Store;

class BillingsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreBillingRequest $request, InvoiceService $service)
    {
        $fields = $request->validated();

        $invoices = Invoice::whereIn('id', $fields['invoice_ids'])->get();

        foreach($invoices as $invoice) {
            if (!$invoice || $invoice->status != 'factura') 
                return response()->json(['error' => 'Factura no encontrada o en estado inválido.'], 404);
    
            if(!$invoice->centre->customer)
                return response()->json(['error' => 'El centro no tiene un cliente SAT asociado.'], 422);
        }

        $facturapi = new Facturapi(env('FACTURAPI_API_KEY'));

        if($fields['joined']){
            $rowsJoined = $invoices->flatMap(fn ($inv) => $inv->rows)->values();
            $invoices->first()->setRelation('rows', $rowsJoined);
        }

        $service->saveBilling($fields, $invoices, $facturapi);
    }

    /**
     * Display the specified resource.
     */
    public function storeComplement(StoreComplementRequest $request)
    {
        $fields = $request->validated();

        $invoices = Invoice::whereIn('id', $fields['invoice_ids'])->get();


        foreach($invoices as $invoice) {
            if (!$invoice || $invoice->status != 'complemento') 
                return response()->json(['error' => 'Factura no encontrada o en estado inválido.'], 404);

            if(!$invoice->centre->customer)
                return response()->json(['error' => 'El centro no tiene un cliente SAT asociado.'], 422);
        }

        $facturapi = new Facturapi(env('FACTURAPI_API_KEY'));
        $complements_path = config('complements_path');

        // foreach($invoices as $invoice) {
            $customer = $invoice->centre->customer;

            $customer_object = [
                "legal_name" => $customer->legal_name,
                "tax_id" => $customer->tax_id,
                "tax_system" => $customer->tax_system,
                "address" => [
                    "zip" => $customer->address_zip,
                ]
            ];

            $last_balance = $invoice->total * 1.16;
            $paid_amount = $fields['payment_amount'] ?? $invoice->total * 1.16;

            $billing = Billing::create([
                'payment_form' => $fields['payment_form'],
                'payment_method' => 'PPD',
                'type' => 'complemento',
            ]);

            $sat_comp = $facturapi->Invoices->create([
                'type' => 'P',
                "customer" => $customer_object,
                "date" => $fields['payment_date'],
                "complements" => [
                    [
                        "type" => "pago",
                        "data" => [
                            [
                                "payment_form" => $fields['payment_form'],
                                //TODO: Verificar si es correcto mandar varios related_documents
                                    "related_documents" => array_map(function($inv) use ($paid_amount, $last_balance) {
                                            return [
                                                "uuid" => trim($inv->billing->uuid),
                                                "amount" => $paid_amount,
                                                "installment" => 1 ,
                                                "last_balance" => $last_balance,
                                                "taxes" => [[
                                                    "base" => $inv->total,
                                                    "type" => "IVA",
                                                    "rate" => 0.16
                                                ]
                                            ]
                                        ];
                                    }, $invoices)
                                    // "related_documents" => [[
                                    //         "uuid" => trim($invoice->billing->uuid),
                                    //         "amount" => $paid_amount,
                                    //         "installment" => 1 ,
                                    //         "last_balance" => $last_balance,
                                    //         "taxes" => [[
                                    //             "base" => $invoice->total,
                                    //             "type" => "IVA",
                                    //             "rate" => 0.16
                                    //         ]
                                    //     ]
                                    // ]
                                ]
                            ]
                        ]
                    ]
                ],
                "folio_number" => $billing->id,
                "series" => "COMP"
            ]);
            
            $pdf = $facturapi->Invoices->download_pdf($sat_comp->id);
            $xml = $facturapi->Invoices->download_xml($sat_comp->id);
            
            $file_name = trim("$invoice->id $invoice->oc");
            
            $xml_path = "$complements_path/xml/$file_name.xml";
            $pdf_path = "$complements_path/pdf/$file_name.pdf";

            Storage::put($xml_path, $xml);
            Storage::put($pdf_path, $pdf);

            $billing->pdf_path = "$file_name.pdf";
            $billing->xml_path = "$file_name.xml";
            $billing->uuid = $sat_comp->uuid;
            $billing->save();

            $invoice->billings()->syncWithoutDetaching([$billing->id]);

            // En caso de que no se haya pagado la factura completa, hacer un segundo complemento con el resto del monto
            if($paid_amount < $last_balance){

                $last_balance -= $paid_amount;
                $remaining_amount = ($invoice->total * 1.16) - $paid_amount;

                $billing = Billing::create([
                    'payment_form' => $fields['payment_form'],
                    'payment_method' => $fields['payment_method'] ?? 'PPD',
                    'type' => 'complemento',
                ]);

                $sat_comp = $facturapi->Invoices->create([
                    'type' => 'P',
                    "customer" => $customer_object,
                    "date" => $fields['payment_date'],
                    "complements" => [
                        [
                            "type" => "pago",
                            "data" => [
                                [
                                    "payment_form" => "17",
                                        "related_documents" => [[
                                                "uuid" => trim($invoice->billing->uuid),
                                                "amount" => $remaining_amount,
                                                "installment" => 2 ,
                                                "last_balance" => $last_balance,
                                                "taxes" => [[
                                                    "base" => $invoice->total,
                                                    "type" => "IVA",
                                                    "rate" => 0.16
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ],
                    "folio_number" => $billing->id,
                    "series" => "COMP"
                ]);

                $pdf = $facturapi->Invoices->download_pdf($sat_comp->id);
                $xml = $facturapi->Invoices->download_xml($sat_comp->id);
                
                $file_name = trim("$invoice->id $invoice->oc COMPENSACION");
                
                $xml_path = "$complements_path/xml/$file_name.xml";
                $pdf_path = "$complements_path/pdf/$file_name.pdf";

                Storage::put($xml_path, $xml);
                Storage::put($pdf_path, $pdf);

                $billing->uuid = $sat_comp->uuid;
                $billing->xml_path = "$file_name.xml";
                $billing->pdf_path = "$file_name.pdf";
                $billing->save();

                $invoice->billings()->syncWithoutDetaching([$billing->id]);

            }

            if($invoice->status == 'complemento'){
                
                $invoice->status = 'finalizada';
                $invoice->save();
            }
        // }
    }

    public function download(Request $request, string $id)
    {
        $billing = Billing::find($id);

        $billings_path = config('billings_path');
        $complements_path = config('complements_path');

        if(!$billing) 
            return response()->json(['error' => 'Facturación no encontrada.'], 404);

        $base_path = $billing->type == 'factura' ? $billings_path : $complements_path;

        $pdf_path = "$base_path/pdf/{$billing->pdf_path}";
        $xml_path = "$base_path/xml/{$billing->xml_path}";

        if (!Storage::exists($pdf_path) || !Storage::exists($xml_path)) 
            return response()->json(['error' => 'Archivos no encontrados.'], 404);
        

        $zip = new ZipArchive();
        $zipFileName = "SAT {$billing->id}.zip";
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
