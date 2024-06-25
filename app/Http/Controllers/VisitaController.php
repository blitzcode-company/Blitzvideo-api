<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Visita;
use Carbon\Carbon;
use Illuminate\Http\Response;

class VisitaController extends Controller
{
    public function visita($videoId, $userId = null)
    {
        if ($userId === null) {
            $usuario = User::where('name', 'Invitado')->first();

            if (!$usuario) {
                return response()->json(['message' => 'Usuario Invitado no encontrado.'], 404);
            }

            $userId = $usuario->id;
        }

        $ultimaVisita = $this->obtenerUltimaVisita($userId, $videoId);

        if ($ultimaVisita && !$this->puedeRegistrarVisita($ultimaVisita)) {
            return response()->json(['message' => 'Debe esperar un minuto antes de registrar una nueva visita.'], 429);
        }

        $this->crearVisita($userId, $videoId);

        return response()->json(['message' => 'Visita registrada exitosamente.'], 201);
    }

    private function obtenerUltimaVisita($userId, $videoId)
    {
        return Visita::where('user_id', $userId)
            ->where('video_id', $videoId)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    private function puedeRegistrarVisita($ultimaVisita)
    {
        $unMinutoDespues = Carbon::parse($ultimaVisita->created_at)->addMinute();
        return Carbon::now()->greaterThanOrEqualTo($unMinutoDespues);
    }

    private function crearVisita($userId, $videoId)
    {
        Visita::create([
            'user_id' => $userId,
            'video_id' => $videoId,
        ]);
    }
}
