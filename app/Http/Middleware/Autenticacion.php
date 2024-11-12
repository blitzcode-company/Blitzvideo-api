<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;


class Autenticacion
{

    public function handle(Request $request, Closure $next)
    {
        $tokenHeader = ["Authorization" => $request->header("Authorization") ];

        $response = Http::withHeaders($tokenHeader)->get(env('AUTH_API_URL'));

        if ($response->successful()) {
            $data = $response->json();

            if (isset($data['bloqueado']) && $data['bloqueado']) {
                return response()->json([
                    'error' => 'Su cuenta está bloqueada. Contacte al soporte.'
                ], 403);
            }

            return $next($request);
        }

        return response()->json(['message' => 'Not Allowed'], 403);
    }
}

