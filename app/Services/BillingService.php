<?php

namespace App\Services;

use App\Helpers\EmailHelper;
use App\Models\Billing;
use App\Models\BusinessProfile;
use App\Models\Responsible;
use Facturapi\Facturapi;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class BillingService
{
    private string $billings_path;
    private string $complements_path;

    public function __construct()
    {
        $this->billings_path = config('app.billings_path');
        $this->complements_path = config('app.complements_path');
    }

    private function getFacturapi(): Facturapi
    {
        return new Facturapi(config('app.facturapi_key'));
    }

    public function validateInvoicesForBilling(Collection $invoices): void
    {

        $servicesMissingKeys = $invoices->flatMap(function ($invoice) {
            return $invoice->rows->filter(function ($row) {
                return ((empty($row->service->sat_key_prod_serv) || empty($row->service->sat_unit_key)) && (empty($row->sat_key_prod_serv) || empty($row->sat_unit_key)));
            })->pluck('service');
        })->unique('id');

        if ($servicesMissingKeys->isNotEmpty()) {
            throw ValidationException::withMessages([
                'error' => 'Los siguientes servicios no tienen claves SAT asignadas: ' . $servicesMissingKeys->pluck('name')->join(', '),
            ]);
        }

        $invoices->each(function ($invoice) {
            if ($invoice->status !== 'factura') {
                throw ValidationException::withMessages([
                    'invoice_ids' => "La factura #{$invoice->id} está en un estado inválido.",
                ]);
            }

            if (!$invoice->centre->customer) {
                throw ValidationException::withMessages([
                    'invoice_ids' => "El centro '{$invoice->centre->name}' no tiene un cliente fiscal asociado.",
                ]);
            }
        });
    }

    public function saveBilling(array $fields, Collection $invoices): array
    {
        $facturapi = $this->getFacturapi();
        $customer  = $invoices->first()->centre->customer;
        $joined    = $fields['joined'];
        $billings  = [];

        if ($joined) {
            $items    = $this->extractItems($invoices, true);
            $billing  = $this->createBilling($customer, $fields, $invoices->first(), $items);

            $invoices->each(function ($invoice) use ($billing) {
                $invoice->billings()->attach($billing->id);
                $invoice->status = 'f';
                $invoice->save();
            });

            $billings[] = $billing;
        } else {
            foreach ($invoices as $invoice) {
                $items    = $this->extractItems(new Collection([$invoice]), false);
                $billing  = $this->createBilling($customer, $fields, $invoice, $items);

                $invoice->billings()->attach($billing->id);
                $invoice->status = 'f';
                $invoice->save();

                $billings[] = $billing;
            }
        }

        return $billings;
    }

    private function createBilling($customer, array $fields, $invoice, array $items): Billing
    {
        $facturapi = $this->getFacturapi();

        $sat_invoice = $facturapi->Invoices->create([
            "customer" => $this->buildCustomerObject($customer),
            "items"          => $items,
            "payment_form"   => $fields['payment_form'],
            "payment_method" => $fields['payment_method'],
            "series"         => "FACT",
        ]);

        $folio    = $sat_invoice->folio_number;
        $fileName = "FACT" . trim($folio) . " " . trim($invoice->oc);

        Storage::put("{$this->billings_path}/xml/{$fileName}.xml", $facturapi->Invoices->download_xml($sat_invoice->id));
        Storage::put("{$this->billings_path}/pdf/{$fileName}.pdf", $facturapi->Invoices->download_pdf($sat_invoice->id));

        Log::debug('Creando billing para cliente', ['customer' => $customer->toArray()]);

        return Billing::create([
            'uuid'           => trim($sat_invoice->uuid),
            'folio_number'   => $folio,
            'payment_form'   => $fields['payment_form'],
            'payment_method' => $fields['payment_method'],
            'total'          => $sat_invoice->total,
            'type'           => 'factura',
            'pdf_path'       => "{$fileName}.pdf",
            'xml_path'       => "{$fileName}.xml",
        ]);
    }

    private function extractItems(Collection $invoices, bool $joined): array
    {
        $invoice    = $invoices->first();
        $invoiceMap = $joined ? $invoices->keyBy('id') : null;

        $rows = $joined
            ? $invoices->flatMap(fn($inv) => $inv->rows)->values()
            : $invoice->rows;

        return $rows->map(function ($row) use ($invoice, $joined, $invoiceMap) {
            $product_key  = $row->sat_key_prod_serv ?? $row->service->sat_key_prod_serv;
            $sat_unit_key = $row->sat_unit_key ?? $row->service->sat_unit_key;

            if (!$product_key) {
                throw ValidationException::withMessages([
                    'product_key' => ["El servicio '{$row->concept}' no tiene clave de producto SAT asignada."],
                ]);
            }

            if (!$sat_unit_key) {
                throw ValidationException::withMessages([
                    'sat_unit_key' => ["El servicio '{$row->concept}' no tiene clave de unidad SAT asignada."],
                ]);
            }

            $oc = $joined
                ? $invoiceMap[$row->invoice_id]?->oc
                : $invoice->oc;

            return [
                'quantity' => $row->quantity,
                'product'  => [
                    'description'  => "{$row->concept}. {$oc}.",
                    'product_key'  => $product_key,
                    'price'        => (float) $row->price,
                    'tax_included' => false,
                    'unit_key'     => $sat_unit_key,
                ],
            ];
        })->values()->toArray();
    }

    public function sendBillingEmail(Collection $invoices, array $billings): void
    {
        $responsiblePerson = Responsible::findOrFail($invoices->first()->responsible_id);

        $attachments = [];
        foreach ($billings as $billing) {
            $pdfContent = Storage::get("{$this->billings_path}/pdf/{$billing->pdf_path}");
            $xmlContent = Storage::get("{$this->billings_path}/xml/{$billing->xml_path}");

            if (!$pdfContent || !$xmlContent) {
                throw new \RuntimeException("No se pudieron obtener los archivos de la factura {$billing->id}.");
            }

            $attachments[] = [
                'filename'     => $billing->pdf_path,
                'content'      => base64_encode($pdfContent),
                'content_type' => 'application/pdf',
            ];
            $attachments[] = [
                'filename'     => $billing->xml_path,
                'content'      => base64_encode($xmlContent),
                'content_type' => 'application/xml',
            ];
        }

        $list = $invoices->map(fn($inv) => [
            'invoice_number' => $inv->invoice_number,
            'oc'             => $inv->oc,
            'billing'        => "FACT" . $inv->billing->folio_number,
        ]);

        $html = view('emails/billing', [
            'to'   => $responsiblePerson->name,
            'list' => $list,
            'businessProfile' => BusinessProfile::current(),
        ])->render();

        EmailHelper::notify($responsiblePerson->email, $html, $attachments, 'FACTURA(S)', $html);
    }


    /*COMPLEMENTOS DE PAGO*/
    public function saveComplement(array $fields, Collection $billings, Collection $invoices): void
    {
        $facturapi     = $this->getFacturapi();
        $customer      = Customer::findOrFail($fields['customer_id']);
        $items         = collect($fields['data']);
        $totalPaid     = $items->sum('amount');
        $totalBalance  = $billings->sum('total');

        // Asignar paid_amount virtual a cada billing
        $billings->each(function ($billing) use ($items) {
            $billing->paid_amount = (float) ($items->firstWhere('id', $billing->id)['amount'] ?? $billing->total);
        });

        $customerObject = $this->buildCustomerObject($customer);

        dump([
        'fields'   => $fields,
        'billings' => $billings->map(fn($b) => [
            'id'          => $b->id,
            'total'       => $b->total,
            'paid_amount' => $b->paid_amount,
        ])->toArray(),
    ]);

        DB::transaction(function () use (
            $facturapi, $fields, $billings, $invoices,
            $customerObject, $totalPaid, $totalBalance
        ) {
            // Primer complemento
            $complement = $this->createComplement(
                $facturapi,
                $customerObject,
                $fields,
                $billings,
                installment: 1,
                paymentForm: $fields['payment_form'],
                amountCallback: fn($bill) => $bill->paid_amount,
            );

            $invoices->each(fn($inv) => $inv->billings()->syncWithoutDetaching([$complement->id]));

            // Segundo complemento si el pago fue parcial
            if ($totalPaid < $totalBalance) {
                $complement2 = $this->createComplement(
                    $facturapi,
                    $customerObject,
                    $fields,
                    $billings,
                    installment: 2,
                    paymentForm: '17',
                    amountCallback: fn($bill) => $bill->total - $bill->paid_amount,
                    isCompensation: true,
                );

                $invoices->each(fn($inv) => $inv->billings()->syncWithoutDetaching([$complement2->id]));
            }

            // Actualizar status de invoices
            $invoices->each(function ($invoice) {
                if ($invoice->status === 'complemento') {
                    $invoice->status = 'finalizada';
                    $invoice->save();
                }
            });
        });
    }

    private function createComplement(
        Facturapi $facturapi,
        array $customerObject,
        array $fields,
        Collection $billings,
        int $installment,
        string $paymentForm,
        callable $amountCallback,
        bool $isCompensation = false,
    ): Billing {
        $relatedDocuments = $billings->map(function ($bill) use ($installment, $amountCallback) {
            $amount = $amountCallback($bill);
            $lastBalance = $installment === 1
                ? $bill->total
                : $bill->total - $bill->paid_amount;

            return [
                "uuid"         => trim($bill->uuid),
                "amount"       => (float) $amountCallback($bill),
                "last_balance" => (float) ($installment === 1 ? $bill->total : $bill->total - $bill->paid_amount),
                "installment"  => $installment,
                "taxes"        => [[
                    "base"         => (float) $bill->total / 1.16,
                    "type" => "IVA",
                    "rate" => 0.16,
                ]],
            ];
        })->values()->toArray();

        // Log::debug('related_documents', [
        //     'installment' => $installment,
        //     'documents'   => $relatedDocuments,
        // ]);

        // dd($relatedDocuments);

        $sat_comp = $facturapi->Invoices->create([
            'type'     => 'P',
            'customer' => $customerObject,
            'series'   => 'COMP',
            'complements' => [[
                'type' => 'pago',
                'data' => [[
                    'date'               => $fields['payment_date'],
                    'payment_form'       => $paymentForm,
                    'related_documents'  => $relatedDocuments,
                ]],
            ]],
        ]);

        $folio    = $sat_comp->folio_number;
        $fileName = $isCompensation
            ? "COMP{$folio} COMPENSACION"
            : "COMP{$folio}";

        Storage::put("{$this->complements_path}/xml/{$fileName}.xml", $facturapi->Invoices->download_xml($sat_comp->id));
        Storage::put("{$this->complements_path}/pdf/{$fileName}.pdf", $facturapi->Invoices->download_pdf($sat_comp->id));

        return Billing::create([
            'uuid'           => trim($sat_comp->uuid),
            'folio_number'   => $folio,
            'payment_form'   => $paymentForm,
            'payment_method' => 'PPD',
            'type'           => 'complemento',
            'pdf_path'       => "{$fileName}.pdf",
            'xml_path'       => "{$fileName}.xml",
        ]);
    }

    private function buildCustomerObject(Customer $customer): array
    {
        return [
            'legal_name' => $customer->legal_name,
            'tax_id'     => $customer->tax_id,
            'tax_system' => $customer->tax_system,
            'address'    => ['zip' => $customer->address_zip],
        ];
    }
}