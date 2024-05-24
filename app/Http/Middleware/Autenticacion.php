<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;


class Autenticacion
{

    public function handle(Request $request, Closure $next)
    {

        $tokenHeader = [ "Authorization" => $request -> header("Authorization")];

        $response = Http::withHeaders($tokenHeader)->get(env('AUTH_API_URL'));

        
        if($response -> successful())
            return $next($request);
        
        return response(['message' => 'Not Allowed'], 403);

    }
}

