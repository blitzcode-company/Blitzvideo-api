<?php

namespace App\Http\Controllers;

use App\Models\Visita;
use Carbon\Carbon;
use Illuminate\Http\Request;

class VisitaController extends Controller
{
    public function visita($usuarioId, $videoId)
    {
        $ultimaVisita = $this->obtenerUltimaVisita($usuarioId, $videoId);

        if ($ultimaVisita && !$this->puedeRegistrarVisita($ultimaVisita)) {
            return response()->json(['message' => 'Debe esperar un minuto antes de registrar una nueva visita.'], 429);
        }

        $this->crearVisita($usuarioId, $videoId);

        return response()->json(['message' => 'Visita registrada exitosamente.'], 201);
    }

    private function obtenerUltimaVisita($usuarioId, $videoId)
    {
        return Visita::where('user_id', $usuarioId)
            ->where('video_id', $videoId)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    private function puedeRegistrarVisita($ultimaVisita)
    {
        $unMinutoDespues = Carbon::parse($ultimaVisita->created_at)->addMinute();
        return Carbon::now()->greaterThanOrEqualTo($unMinutoDespues);
    }

    private function crearVisita($usuarioId, $videoId)
    {
        Visita::create([
            'user_id' => $usuarioId,
            'video_id' => $videoId,
        ]);
    }
}
