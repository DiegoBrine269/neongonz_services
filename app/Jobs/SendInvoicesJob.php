<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\Responsible;
use App\Services\EmailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class SendInvoicesJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public array $invoiceIds
    ) {}

    public function handle(EmailService $emailService): void
    {
        $invoices = Invoice::with('centre')
            ->whereIn('id', $this->invoiceIds)
            ->get();

        $grouped = $invoices->groupBy('responsible_id');

        foreach ($grouped as $centreId => $invoicesGroup) {
            $centre = $invoicesGroup->first()->centre;
            $attachments = [];
            $responsiblePerson = null;

            foreach ($invoicesGroup as $invoice) {
                $invoice->sent_at = Carbon::now();

                if ($invoice->status == 'envio') {
                    $invoice->status = 'oc';
                }
                $invoice->save();

                $filename = $invoice->invoice_number . ".pdf";
                $pdfContent = Storage::get("invoices/{$filename}");

                $attachments[] = [
                    'filename' => $filename,
                    'content' => base64_encode($pdfContent),
                    'contentType' => 'application/pdf',
                ];

                $responsiblePerson = Responsible::find($invoice->responsible_id);
            }

            $type = $invoicesGroup->first()->is_budget ? 'PRE' : 'COT';
            $html = view('emails/invoice', [
                'destinatario' => $responsiblePerson->name,
                'type' => $type
            ])->render();


            $subject = ($invoicesGroup->first()->is_budget ? 'Presupuestos' : 'Solicitud de órdenes de compra') . " - {$centre->name}";

            $responseEmail = $emailService->notify($responsiblePerson->email, $html, $attachments, $subject);

            if (!$responseEmail) {
                foreach ($invoicesGroup as $invoice) {
                    $invoice->sent_at = null;
                    $invoice->save();
                }
            }

            usleep(600000);
        }
    }
}