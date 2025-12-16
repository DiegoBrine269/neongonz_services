<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Centre;
use App\Models\VehicleType;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InvoiceService
{
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

            // $today = today()->format('Ymd');
            $invoice_number = "COT_" . $invoice->id;
            $invoice->invoice_number = $invoice_number;
            // dump($invoice->save());
        });

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
}
