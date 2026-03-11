<?php

namespace App\Services;

use App\Models\BusinessProfile;
use Illuminate\Support\Facades\Log;
use Resend\Laravel\Facades\Resend;

class EmailService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public function notify($email, $html, $attachments, $subject = 'Solicitud de órdenes de compra'){

        if(!$email) {
            return false;
        }

        $from = config('mail.mailers.smtp.username');

        if (app()->environment('local')) 
            $subject = "[CORREO DE PRUEBA] " . $subject;

        $businessProfile = BusinessProfile::current();

        try {
            $email = Resend::emails()->send([
                'from' => "$businessProfile->business_name <$from>",
                'to' => [$email],
                'cc' => [$businessProfile->email],
                'subject' => $subject,
                'reply_to' => $businessProfile->email,
                'html' => $html,
                'attachments' => $attachments,
            ]);
        }
        catch (\Exception $e) {
            Log::error("Error al enviar correo: " . $e->getMessage());
            return false;
        }

        // $email =Resend::emails()->get($email->id);

        // dump($email);
        return true;
    }
}
