<?php

namespace App\Jobs;

use App\Services\BillingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ProcessBillingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 120;

    public function __construct(
        private readonly array $fields,
        private readonly Collection $invoices,
        private readonly bool $resend = false,
    ) {}

    public function handle(BillingService $service): void
    {

        Log::info('Iniciando ProcessBillingJob', [
            'fields'      => $this->fields,
            'invoice_ids' => $this->invoices->pluck('id'),
            'resend'      => $this->resend,
        ]);

        if($this->resend) {
            $billings = $this->invoices->pluck('billing')->flatten()->filter()->values()->all();
            $service->sendBillingEmail($this->invoices, $billings);
            return;
        }

        $billings = $service->saveBilling($this->fields, $this->invoices);    
        $this->invoices->load('billing');
        $service->sendBillingEmail($this->invoices, $billings);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessBillingJob falló', [
            'fields'      => $this->fields,
            'invoice_ids' => $this->invoices->pluck('id'),
            'error'       => $exception->getMessage(),
        ]);
    }
}