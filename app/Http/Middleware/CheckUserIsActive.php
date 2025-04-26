<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckUserIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        // Verificar si el usuario está autenticado y activo
        $user = $request->user();

        if (!$user || !$user->is_active) {
            return response()->json(['error' => 'Usuario inactivo o no autorizado'], 403);
        }

        return $next($request);
    }
}
