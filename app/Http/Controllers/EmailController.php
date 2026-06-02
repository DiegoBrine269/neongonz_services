<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Cache;

class EmailController extends Controller
{
    public function inbox()
    {
       return response()->json(Cache::get('inbox', []));
    }
}
