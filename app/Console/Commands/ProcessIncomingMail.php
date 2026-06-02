<?php

namespace App\Console\Commands;

use App\Models\Email;
use App\Models\EmailReply;
use Illuminate\Console\Command;
use Webklex\IMAP\Facades\Client;

class ProcessIncomingMail extends Command
{
    protected $signature   = 'mail:process-incoming';
    protected $description = 'Procesa respuestas de correo entrante';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $client = Client::account('default');
        $client->connect();

        $inbox    = $client->getFolder('INBOX');
        $messages = $inbox->messages()->unseen()->get();

        foreach ($messages as $message) {
            $inReplyTo = $message->getHeader()->get('in-reply-to');

            if (!$inReplyTo) {
                // No es una respuesta a algo enviado desde la app, ignorar
                $message->setFlag('Seen');
                continue;
            }

            $messageId = trim((string) $inReplyTo, '<>');
            $original  = Email::where('message_id', $messageId)->first();

            if (!$original) {
                $message->setFlag('Seen');
                continue;
            }

            EmailReply::create([
                'email_id' => $original->id,
                'from'     => $message->getFrom()[0]->mail,
                'body'     => $message->getTextBody(),
            ]);

            $message->setFlag('Seen');
        }

        $this->info('Correos procesados: ' . $messages->count());
    }
}
