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

        $centre = Centre::find($fields['vehicles'][0]['centre_id']);
        $date = $fields['date'] ?? today();

        // 1. Agrupar por proyecto y calcular totales
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

        // 2. Guardar en BD dentro de transacción
        $invoice_number = null;
        DB::transaction(function () use (&$invoice, &$invoice_number, $centre, $fields, $groupedByProject, $date) {
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

            $today = today()->format('Ymd');
            $invoice_number = "COT_$today" . "_" . $centre->id . "_" . $invoice->id;
            $invoice->invoice_number = $invoice_number;
            $invoice->save();
        });

        // 3. Generar PDF
        $pdf = Pdf::loadView('invoice', [
            'invoice_number' => $invoice_number,
            'date' => Carbon::parse($date)->locale('es')->translatedFormat('j \\d\\e F \\d\\e Y'),
            'centre' => $centre,
            'projects' => $groupedByProject,
            'comments' => $fields['comments'] ?? null,
            'custom' => false,
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
