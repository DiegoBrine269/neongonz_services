<?php
    namespace App\Helpers;

    use Resend\Laravel\Facades\Resend;


    class EmailHelper
    {
        public static function notify($email, $html, $attachments, $subject = 'Notificación de Neón Gonz'): bool
        {
            if(!$email) {
                return false;
            }

            $from = config('mail.mailers.smtp.username');

            if (app()->environment('local')) 
                $subject = "[CORREO DE PRUEBA] " . $subject;

            Resend::emails()->send([
                'from' => "Neón Gonz <$from>",
                'to' => [$email],
                'cc' => ['neongonz@hotmail.com'],
                'subject' => $subject,
                'reply_to' => 'neongonz@hotmail.com',
                'html' => $html,
                'attachments' => $attachments,
            ]);

            return true;
        }
    }