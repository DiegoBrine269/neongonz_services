<?php
    namespace App\Helpers;

    use App\Models\BusinessProfile;
    use Illuminate\Support\Facades\Log;
    use Resend\Laravel\Facades\Resend;

    class EmailHelper
    {
        public static function notify($email, $html, $attachments, $subject = 'Solicitud de órdenes de compra', $emailToReply = null){

            if(!$email) {
                return false;
            }

            $from = config('mail.mailers.smtp.username');

            if (app()->environment('local')) 
                $subject = "[CORREO DE PRUEBA] " . $subject;

            $businessProfile = BusinessProfile::current();


            Log::info($emailToReply);
            // die();

            $headers = [];

            if($emailToReply && isset($emailToReply['message_id'])) {
                $subject = "Re: " . $emailToReply['subject'];

                $headers = [
                    'headers' => [
                        'In-Reply-To' => $emailToReply['message_id'] ?? null,
                        'References'  => $emailToReply['message_id'] ?? null,
                    ],
                ];
            }

            try {
                $response = Resend::emails()->send([
                    'from'        => "$businessProfile->business_name <$from>",
                    'to'          => [$email],
                    'cc'          => [$businessProfile->email],
                    'subject'     => $subject,
                    'reply_to'    => $businessProfile->email,
                    'html'        => $html,
                    'attachments' => $attachments,
                    ...$headers,
                ]);
            }
            catch (\Exception $e) {
                Log::error("Error al enviar correo: " . $e->getMessage());
                return false;
            }

            Log::info("Correo enviado a $email con subject '$subject'. Respuesta: " . json_encode($response));

            return $emailToReply->message_id ?? $response->id ?? null;
        }
    }