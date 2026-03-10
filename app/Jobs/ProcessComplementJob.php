<?php

namespace App\Jobs;

use App\Models\Billing;
use App\Models\Invoice;
use App\Services\BillingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ProcessComplementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 120;

    public function __construct(
        private readonly array $fields,
        private readonly Collection $billings,
        private readonly Collection $invoices,
    ) {}

    public function handle(BillingService $service): void
    {
        $service->saveComplement($this->fields, $this->billings, $this->invoices);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessComplementJob falló', [
            'fields'      => $this->fields,
            'billing_ids' => $this->billings->pluck('id'),
            'invoice_ids' => $this->invoices->pluck('id'),
            'error'       => $exception->getMessage(),
        ]);
    }
}