<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function resend(Request $request)
    {
        $payload = $request->all();
        
        Log::info('Resend webhook: ' . json_encode($payload));

        return response()->json(['ok' => true]);
    }
}
