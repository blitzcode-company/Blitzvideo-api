<?php

namespace App\Http\Controllers;

use App\Models\Suscribe;
use Illuminate\Http\Request;

class SuscribeController extends Controller
{
    public function Suscribirse(Request $request, $canal_id)
    {
        $this->validarUsuario($request);

        $suscripcionExistente = Suscribe::where('user_id', $request->user_id)
            ->where('canal_id', $canal_id)
            ->first();

        if ($suscripcionExistente) {
            return response()->json([
                'message' => 'Ya estás suscrito a este canal.',
            ], 409);
        }

        $suscribe = Suscribe::create([
            'user_id' => $request->user_id,
            'canal_id' => $canal_id,
        ]);

        return response()->json([
            'message' => 'Suscripción creada exitosamente.',
            'data' => $suscribe,
        ], 201);
    }

    public function AnularSuscripcion(Request $request, $canal_id)
    {
        $this->validarUsuario($request);

        $suscribe = Suscribe::where('user_id', $request->user_id)
            ->where('canal_id', $canal_id)
            ->first();

        if (!$suscribe) {
            return response()->json([
                'message' => 'No estás suscrito a este canal.',
            ], 404);
        }

        $suscribe->delete();

        return response()->json(['message' => 'Suscripción anulada exitosamente.'], 200);
    }

    public function VerificarSuscripcion(Request $request, $canal_id)
    {
        $this->validarUsuario($request);

        $suscripcionExistente = Suscribe::where('user_id', $request->user_id)
            ->where('canal_id', $canal_id)
            ->exists();

        return response()->json(['suscrito' => $suscripcionExistente], 200);
    }

    public function ListarSuscripciones()
    {
        $suscripciones = Suscribe::with(['canal.user' => function ($query) {
            $query->select('id', 'foto'); 
        }])->get();
    
        return response()->json($suscripciones, 200);
    }
    
    public function ListarSuscripcionesUsuario($user_id)
    {
        $suscripciones = Suscribe::where('user_id', $user_id)
            ->with(['canal.user' => function ($query) {
                $query->select('id', 'foto'); 
            }])->get();
    
        if ($suscripciones->isEmpty()) {
            return response()->json([
                'message' => 'Este usuario no tiene suscripciones.',
            ], 404);
        }
    
        return response()->json($suscripciones, 200);
    }

    public function ContarSuscripciones($canal_id)
    {
        $numeroSuscripciones = Suscribe::where('canal_id', $canal_id)->count();
    
        return response()->json([
            'canal_id' => (int) $canal_id, 
            'numero_suscripciones' => $numeroSuscripciones,
        ]);
    }


    private function validarUsuario(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);
    }
}
