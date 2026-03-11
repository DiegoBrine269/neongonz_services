<?php
    namespace App\Helpers;

use App\Models\BusinessProfile;
use Resend\Laravel\Facades\Resend;


    class EmailHelper
    {
        public static function notify($email, $html, $attachments, $subject = 'Notificación de cotización'): bool
        {
            if(!$email) {
                return false;
            }

            $from = config('mail.mailers.smtp.username');

            $businessProfile = BusinessProfile::current();

            if (app()->environment('local')) 
                $subject = "[CORREO DE PRUEBA] " . $subject;

            Resend::emails()->send([
                'from' => "$businessProfile->business_name <$from>",
                'to' => [$email],
                'cc' => [$businessProfile->email],
                'subject' => $subject,
                'reply_to' => $businessProfile->email,
                'html' => $html,
                'attachments' => $attachments,
            ]);

            return true;
        }
    }