<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerificarBloqueoUsuario
{
    public function handle(Request $request, Closure $next)
    {
        $bloqueado = $request->input('bloqueado');
        if ($bloqueado) {
            return response()->json(['error' => 'Su cuenta está bloqueada. Contacte al soporte.'], 403);
        }
        return $next($request);
    }
}
