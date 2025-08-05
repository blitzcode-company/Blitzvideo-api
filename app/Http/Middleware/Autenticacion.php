<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use App\Models\User; // Asegurate de importar tu modelo

class Autenticacion
{
    public function handle(Request $request, Closure $next)
    {
        $tokenHeader = [ "Authorization" => $request->header("Authorization") ];

        $response = Http::withHeaders($tokenHeader)->get(env('AUTH_API_URL'));

        if ($response->successful()) {
            $userData = $response->json();

            // Crear un modelo User temporal con los datos necesarios
            $user = new User([
                'id' => $userData['id'],
                'name' => $userData['name'] ?? 'Sin Nombre'
            ]);

            // Muy importante: setear el usuario autenticado manualmente
            Auth::setUser($user);

            return $next($request);
        }

        return response(['message' => 'Not Allowed'], 403);
    }
}
