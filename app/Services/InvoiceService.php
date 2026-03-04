<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Centre;
use App\Models\Billing;
use App\Models\Invoice;
use App\Models\VehicleType;
use App\Models\InvoiceBilling;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    private $billings_path;

    public function __construct() {
        $this->billings_path = config('app.billings_path');
    }


    public function saveInvoice(array $fields, ?Invoice $invoice = null)
    {
        $centre = Centre::find($fields['vehicles'][0]['centre_id']); // puede ser null si no existe
        $centreId = $centre?->id; // int|null

        $responsible_id = (int)$fields['responsible_id'] ?? $invoice->responsible_id ?? $centre->responsibles()->first()?->id ?? null;
        
        $centre->responsible = $centre->responsibles()->find($responsible_id);
        $date = $fields['date'] ?? today();

        // 1. Agrupar por proyecto y calcular totales
        $groupedByProject = collect($fields['vehicles'])->groupBy(function ($vehicle)  {
            return $vehicle['project']['id'];
        })->map(function ($vehicles, $projectId) use ($centreId) {
            $service = $vehicles[0]['project']['service'];
            $service_id = $vehicles[0]['project']['service_id'];

            $serviceVehicleTypes = DB::table('service_vehicle_type')
                ->where('service_id', $service_id)
                ->where(function ($q) use ($centreId) {
                    $q->whereNull('centre_id'); // general siempre
                    if ($centreId !== null) {
                        $q->orWhere('centre_id', $centreId); // específico del centro
                    }
                })
                ->get()
                ->groupBy('vehicle_type_id');

            $_vehicles = $vehicles->map(function ($vehicle) use ($serviceVehicleTypes, $centreId) {
                $vehicleTypeId = (int) $vehicle['vehicle_type_id'];

                $rows = $serviceVehicleTypes->get($vehicleTypeId, collect());

                $row = $centreId !== null
                    ? ($rows->firstWhere('centre_id', $centreId) ?? $rows->firstWhere('centre_id', null))
                    : $rows->firstWhere('centre_id', null);

                $vehicle['price'] = $row?->price ?? 0;

                unset($vehicle['project']);

                return (object) $vehicle;
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
                'service_id' => $service_id,
                'vehicles_grouped_by_price' => $vehiclesGroupedByPrice,
                'service_vehicle_types' => $serviceVehicleTypes,
            ];
        });

        // 2. Guardar en BD dentro de transacción
        $invoice_number = null;
        DB::transaction(function () use (&$invoice, &$invoice_number, $centre, $fields, $groupedByProject, $date, $responsible_id) {
            if (!$invoice) {
                $invoice = new Invoice();
            }

            $grandTotal = 0;
            foreach ($groupedByProject as $project) {
                foreach ($project->vehicles_grouped_by_price as $price => $grouped_by_price) {
                    foreach ($grouped_by_price as $data) {
                        $groupedVehicles = $data['group'];
                        $grandTotal += $groupedVehicles->sum('price');
                    }
                }
            }

            $includedServices = $groupedByProject->pluck('service')->unique()->toArray();

            $invoice->fill([
                'centre_id' => $centre->id,
                'date' => $date,
                'comments' => $fields['comments'] ?? null,
                'total' => $grandTotal,
                'services' => implode(", ", $includedServices),
                'responsible_id' => $responsible_id,
            ]);

            $invoice->save();

            foreach ($fields['vehicles'] as $vehicle) {
                $invoice->invoiceVehicles()->updateOrCreate(
                    [
                        'vehicle_id' => $vehicle['vehicle_id'],
                        'project_id' => $vehicle['project']['id'],
                    ]
                );

                DB::table('project_vehicles')
                    ->where('vehicle_id', $vehicle['vehicle_id'])
                    ->where('project_id', $vehicle['project']['id'])
                    ->update(['invoice_id' => $invoice->id]);
            }

            $invoice_number = "COT_" . $invoice->id;
            $invoice->invoice_number = $invoice_number;
        });

        $rows = [];

        foreach ($groupedByProject as $project) {
            foreach ($project->vehicles_grouped_by_price as $price => $grouped_by_price) {
                foreach ($grouped_by_price as $data) {
                    $groupedVehicles = $data['group'];
                    $quantity = count($groupedVehicles);
                    $concept = $project->service . " (" . $data['type'] . "): " . implode(', ', $groupedVehicles->pluck('eco')->toArray());
                    $totalForGroup = $groupedVehicles->sum('price');

                    $rows[] = [
                        'quantity' => $quantity,
                        'concept' => $concept,
                        'price' => $price,
                        'total' => $totalForGroup,
                        'service_id' => $project->service_id,
                    ];
                }
            }
        }

        $invoice->rows()->createMany($rows);


        // 3. Generar PDF
        $pdf = Pdf::loadView('invoice', [
            'invoice' => $invoice,
            'date' => Carbon::parse($date)->locale('es')->translatedFormat('j \\d\\e F \\d\\e Y'),
            'projects' => $groupedByProject,
            'responsible' => $centre->responsible,
        ]);

        $pdfContent = $pdf->output();
        $filename = $invoice_number . '.pdf';

        if (!Storage::exists('invoices')) {
            Storage::makeDirectory('invoices');
        }
        Storage::put("invoices/$filename", $pdfContent);

        $invoice->path = $filename;
        $invoice->save();

        return [$invoice, $pdfContent, $filename];
    }

    public function saveBilling(array $fields, Collection $invoices, $facturapi)
    {
        // Extrayendo info. del cliente

        $invoice = $invoices->first();
        $customer = $invoice->centre->customer;


        // Variable que indica si solo se debe crear una factura
        $createOnce = false;

        // dump($fields);

        if($fields['joined']){
            $createOnce = true;
        }


        // Extrayendo los items de la factura

        
        $billings = [];

        foreach($invoices as $_invoice)
        {
            if ($_invoice->status === 'factura') {

                // 1) Crear el Billing (tipo factura)
                if(!$createOnce){
                    // dump($_invoice);
                    $items = $this->extractItems(new Collection([$_invoice]), $fields['joined']);
                    $billing = $this->createBilling($facturapi, $customer, $fields, $_invoice, $items);


                    // 2) Si ya había una factura vinculada, desvincúlala
                    $_invoice->billings()->sync([$billing->id]);
    
                    // 3) Actualizar status
                    $_invoice->status = 'f';
                    $_invoice->save();
                
    
                    $billings[] = $billing;
                }
            }
        }

        if($createOnce){
            $items = $this->extractItems($invoices, $fields['joined']);
            $billing = $this->createBilling($facturapi, $customer, $fields, $invoice, $items);

            // $invoice->billings()->sync([$billing->id]);
            $invoices->each(function ($inv) use ($billing) {
                $inv->billings()->sync([$billing->id]);
            });

            // 3) Actualizar status si aplica
            if ($invoice->status === 'factura') {
                $invoice->status = 'f';
                $invoice->save();
            }

            $billings[] = $billing;
        }

        return $billings;
    }

    private function createBilling($facturapi, $customer, $fields, $invoice, $items)
    {
        $sat_invoice = $facturapi->Invoices->create([
            "customer" => [
                "legal_name" => $customer->legal_name,
                // "email" => "email@example.com",
                "tax_id" => $customer->tax_id,
                "tax_system" => intval($customer->tax_system),
                "address" => [
                    "zip" => $customer->address_zip,
                ]
            ],
            "items" => $items,
            "payment_form" => $fields['payment_form'],
            "payment_method" => $fields['payment_method'],
            // "folio_number" => $invoice->id,
            "series" => "FACT"
        ]);    
        
        $pdf = $facturapi->Invoices->download_pdf($sat_invoice->id);
        $xml = $facturapi->Invoices->download_xml($sat_invoice->id);

        $folio = $sat_invoice->folio_number;
        $fileName = "FACT".trim("$folio") . " " . trim($invoice->oc);
        // $fileName = trim("$invoice->id $invoice->oc");
        
        $xmlPath = "$this->billings_path/xml/$fileName.xml";
        $pdfPath = "$this->billings_path/pdf/$fileName.pdf";

        Storage::put($xmlPath, $xml);
        Storage::put($pdfPath, $pdf);

        //Sumando el total de los items para guardarlo en la BD
        $total = collect($items)->sum(function ($item) {
            return $item['quantity'] * $item['product']['price'] * 1.16;
        });

        $billing = Billing::create([
            'uuid' => trim($sat_invoice->uuid),
            'folio_number' => $folio,
            'payment_form' => $fields['payment_form'],
            'payment_method' => $fields['payment_method'],
            'total' => $total,
            'type' => 'factura',
            'pdf_path' => "$fileName.pdf",
            'xml_path' => "$fileName.xml",
        ]);

        return $billing;
    }

    private function extractItems(Collection $invoices, $joined)
    {
        $invoice = $invoices->first();
        $invoiceMap = $invoices->keyBy('id');

        if($joined){
            $rowsJoined = $invoices->flatMap(fn ($inv) => $inv->rows)->values();
            $invoice->setRelation('rows', $rowsJoined);
        }


        $items = $invoice->rows->map(function ($row) use ($invoices, $invoice, $joined, $invoiceMap) {
            $product_key = $row->sat_key_prod_serv ?? $row->service->sat_key_prod_serv;
            $sat_unit_key = $row->sat_unit_key ?? $row->service->sat_unit_key;
    
            if(!$product_key)
                throw ValidationException::withMessages([
                    'product_key' => ["El servicio '{$row->concept}' no tiene clave de producto o servicio SAT asignada."],
                ]);

            if(!$sat_unit_key)
                throw ValidationException::withMessages([
                    'sat_unit_key' => ["El servicio '{$row->concept}' no tiene clave de unidad SAT asignada."],
                ]);

            // dump($row);

            // La OC se debe extraer de la invoice en particular, ya que este dato no es común (solo en caso de joined)
            $oc = $joined
                ? $invoiceMap[$row->invoice_id]?->oc
                : $invoice->oc;

            return [
                'quantity' => $row->quantity,
                'product' => [
                    'description' => "$row->concept. $oc.",   
                    'product_key' => $product_key,
                    'price' => (float) $row->price,
                    "tax_included" => false,
                    'unit_key' => $sat_unit_key,
                ],
            ];
        })->values()->toArray();

        return $items;
    }
}
