<?php

namespace App\Http\Controllers;

use App\Helpers\EmailHelper;
use App\Http\Requests\StoreBillingRequest;
use App\Http\Requests\StoreComplementRequest;
use App\Jobs\ProcessBillingJob;
use App\Jobs\ProcessComplementJob;
use App\Models\Billing;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Responsible;
use App\Services\BillingService;
use App\Services\InvoiceService;
use Facturapi\Facturapi;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
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
    public function store(StoreBillingRequest $request, BillingService $service)
    {
        $fields = $request->validated();

        $invoices = Invoice::with(['billings', 'centre.customer', 'rows.service'])
            ->whereIn('id', $fields['invoice_ids'])
            ->get();

        if ($fields['joined'] && $invoices->pluck('oc')->unique()->count() > 1) {
            throw ValidationException::withMessages([
                'joined' => 'Todas las facturas deben tener la misma OC para generar un CFDI.',
            ]);
        }

        
        $service->validateInvoicesForBilling($invoices);
        
        if ($request->boolean('dry_run')) {
            return response()->json(['message' => 'Validación correcta']);
        }
        
        ProcessBillingJob::dispatch($fields, $invoices);

        return response()->json(['message' => 'La facturación está siendo procesada.'], 202);
    }

    /**
     * Display the specified resource.
     */
    public function storeComplement(StoreComplementRequest $request)
    {
        $fields = $request->validated();

        if ($request->boolean('dry_run')) {
            return response()->json(['message' => 'Validación correcta']);
        }

        $ids    = array_column($fields['data'], 'id');

        $billings = Billing::whereIn('id', $ids)->get();
        $invoices = Invoice::with('rows.service')
            ->whereHas('billings', fn($q) => $q->whereIn('billings.id', $ids))
            ->get();

        ProcessComplementJob::dispatch($fields, $billings, $invoices);

        return response()->json(['message' => 'El complemento está siendo procesado.'], 202);
    }

    public function show(Request $request, string $id)
    {
        try {
            $billing = Billing::find($id);

            if (!$billing)
                return response()->json(['error' => 'Facturación no encontrada.'], 404);

            $billings_path = config('app.billings_path');
            $complements_path = config('app.complements_path');
            $base_path = $billing->type == 'factura' ? $billings_path : $complements_path;

            $pdf_path = "$base_path/pdf/{$billing->pdf_path}";
            $xml_path = "$base_path/xml/{$billing->xml_path}";

            if (!Storage::exists($pdf_path) || !Storage::exists($xml_path))
                return response()->json(['error' => 'Archivos no encontrados.'], 404);

            if (!class_exists('ZipArchive'))
                return response()->json(['error' => 'Extensión ZIP no disponible.'], 500);

            $type = $billing->type == 'factura' ? 'FACT' : 'COMP';
            $zipFileName = "SAT {$type}{$billing->folio_number}.zip";

            $tempDir = storage_path('temp');
            $tempPath = "{$tempDir}/{$zipFileName}";

            if (!file_exists($tempDir)) {
                if (!mkdir($tempDir, 0775, true))
                    return response()->json(['error' => 'No se pudo crear directorio temporal.'], 500);
            }

            if (!is_writable($tempDir))
                return response()->json(['error' => 'Directorio temporal sin permisos de escritura.'], 500);

            $zip = new ZipArchive();

            if ($zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE)
                return response()->json(['error' => 'No se pudo abrir el archivo ZIP.'], 500);

            $zip->addFromString(basename($billing->pdf_path), Storage::get($pdf_path));
            $zip->addFromString(basename($billing->xml_path), Storage::get($xml_path));
            $zip->close();

            $zipContent = file_get_contents($tempPath);
            unlink($tempPath);

            return response($zipContent, 200, [
                'Content-Type'                     => 'application/zip',
                'Content-Length'                   => strlen($zipContent),
                'Content-Disposition'              => 'attachment; filename="' . $zipFileName . '"',
                'Access-Control-Allow-Origin'      => $request->header('Origin'),
                'Access-Control-Allow-Credentials' => 'true',
                'Access-Control-Expose-Headers'    => 'Content-Disposition, Content-Length',
            ]);

        } catch (\Exception $e) {
            Log::error('Error en show billing: ' . $e->getMessage(), [
                'id'    => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error'   => 'Error interno del servidor.',
                'message' => $e->getMessage(), // quita esto en producción real
            ], 500);
        }
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
