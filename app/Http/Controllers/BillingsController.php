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
            return response()->json([
                'error' => 'Todas las facturas deben tener la misma OC para generar un CFDI.'
            ], 422);
        }

        $service->validateInvoicesForBilling($invoices);

        ProcessBillingJob::dispatch($fields, $invoices);

        return response()->json(['message' => 'La facturación está siendo procesada.'], 202);
    }

    /**
     * Display the specified resource.
     */
    public function storeComplement(StoreComplementRequest $request)
    {
        $fields = $request->validated();
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
        try 
        {

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
    
            // Verificar que la extensión zip esté disponible
            if (!class_exists('ZipArchive'))
                return response()->json(['error' => 'Extensión ZIP no disponible en el servidor.'], 500);
    
            $type = $billing->type == 'factura' ? 'FACT' : 'COMP';
            $zipFileName = "SAT {$type}{$billing->folio_number}.zip";
    
            // Usar sys_get_temp_dir() es más seguro en producción
            $tempDir = storage_path('temp');
            $tempPath = "{$tempDir}/{$zipFileName}";
    
            // Crear directorio temp con manejo de errores
            if (!file_exists($tempDir)) {
                if (!mkdir($tempDir, 0775, true)) {
                    return response()->json(['error' => 'No se pudo crear directorio temporal.'], 500);
                }
            }
    
            // Verificar que el directorio sea escribible
            if (!is_writable($tempDir)) {
                return response()->json(['error' => 'Directorio temporal sin permisos de escritura.'], 500);
            }
    
            Log::info([
                'pdf' => Storage::path($pdf_path),
                'xml' => Storage::path($xml_path),
            ]);
    
            $zip = new ZipArchive();
    
            if ($zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE)
                return response()->json(['error' => 'No se pudo abrir el archivo ZIP.'], 500);
    
            $zip->addFromString(basename($billing->pdf_path), Storage::get($pdf_path));
            $zip->addFromString(basename($billing->xml_path), Storage::get($xml_path));
            $zip->close();
    
    
            if (!file_exists($tempPath))
                return response()->json(['error' => 'El ZIP no fue generado correctamente.'], 500);
    
            return response()->download($tempPath, $zipFileName, [
                'Content-Type'                   => 'application/zip',
                'Access-Control-Allow-Origin'    => $request->header('Origin'),
                'Access-Control-Allow-Credentials' => 'true',
                'Access-Control-Expose-Headers'  => 'Content-Disposition',
            ])->deleteFileAfterSend(true);
        }
        catch (ValidationException $e) {
            return response()->json(['error' => 'Error de validación: ' . $e->getMessage()], 422);
        }
        catch (\Exception $e) {
            Log::error('Error al generar ZIP: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['error' => 'Ocurrió un error al generar el archivo ZIP.'], 500);
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
