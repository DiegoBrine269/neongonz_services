<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Webklex\IMAP\Facades\Client;


class FetchInboxJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60; // 👈 por si IMAP tarda
    public int $tries   = 3;
    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $client = Client::account('default');
        $client->connect();

        $inbox    = $client->getFolder('INBOX');
        $messages = $inbox->messages()->all()->setFetchOrder('desc')->limit(20)->get();

        $correos = $messages->map(fn($m) => [
            'uid'        => $m->getUid(),
            'message_id' => (string) $m->getHeader()->get('message-id'),
            'subject'    => mb_decode_mimeheader((string) $m->getSubject()),
            'from'       => $m->getFrom()[0]->mail,
            'date'       => (string) $m->getDate(),
            'seen'       => $m->getFlags()->has('Seen'),
            'html'       => $m->getHtmlBody(),
        ])->sortByDesc('date')->values()->toArray();

        Cache::forever('inbox', $correos);
    }
}
