<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Visita;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Http\Response;

class VisitaController extends Controller
{
    public function registrarVisita($userId, $videoId)
    {
        $ultimaVisita = $this->obtenerUltimaVisita($userId, $videoId);
        if ($ultimaVisita && !$this->puedeRegistrarVisita($ultimaVisita)) {
            return response()->json(['message' => 'Debe esperar un minuto antes de registrar una nueva visita.'], 429);
        }
        $this->crearVisita($userId, $videoId);
        return response()->json(['message' => 'Visita registrada exitosamente.'], 201);
    }
    
    public function registrarVisitaComoInvitado($videoId)
    {
        $usuario = User::where('name', 'Invitado')->first();
        $userId = $usuario->id;
        return $this->registrarVisita($userId, $videoId);
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


    private function obtenerHostMinio()
    {
        return str_replace('minio', env('BLITZVIDEO_HOST'), env('AWS_ENDPOINT')) . '/';
    }

    private function obtenerBucket()
    {
        return env('AWS_BUCKET') . '/';
    }


    private function obtenerUrlArchivo($rutaRelativa, $host, $bucket)
    {
        if (! $rutaRelativa) {
            return null;
        }
        if (str_starts_with($rutaRelativa, $host . $bucket)) {
            return $rutaRelativa;
        }
        if (filter_var($rutaRelativa, FILTER_VALIDATE_URL)) {
            return $rutaRelativa;
        }
        return $host . $bucket . $rutaRelativa;
    }

    public function obtenerProgreso(Video $video, Request $request)
    {
        $request->validate(['user_id' => 'required|integer|exists:users,id']);
        $userId = $request->input('user_id');

        $visita = Visita::where('user_id', $userId)
                        ->where('video_id', $video->id)
                        ->orderBy('updated_at', 'desc')  
                        ->first();

        if (!$visita) {
            return response()->json(['progreso' => 0]);
        }

        return response()->json([
            'progreso' => (int) $visita->segundos_vistos,
            'completado' => $visita->completado,
            'ultimo_heartbeat' => $visita->ultimo_heartbeat?->toIso8601String(),
        ]);
    }
public function actualizarProgreso(Request $request)
{
    $request->validate([
        'user_id'         => 'required|integer|exists:users,id',
        'video_id'        => 'required|integer|exists:videos,id',
        'segundos_vistos' => 'required|integer|min:0',
        'duracion'        => 'sometimes|integer|min:1',
    ]);

    $userId   = $request->input('user_id');
    $videoId  = $request->input('video_id');
    $segundos = (int) $request->input('segundos_vistos');

    $visita = Visita::where('user_id', $userId)
                ->where('video_id', $videoId)
                ->orderBy('updated_at', 'desc')   
                ->first();

    if (!$visita) {
        $visita = Visita::create([
            'user_id'          => $userId,
            'video_id'         => $videoId,
            'segundos_vistos'  => $segundos,
            'duracion_video'   => $request->input('duracion') ?? null,
            'ultimo_heartbeat' => now(),
        ]);

    } else {
        $segundosAnteriores = $visita->segundos_vistos ?? 0;
        $nuevoValor = max($segundosAnteriores, $segundos);

        $visita->segundos_vistos = $nuevoValor;
        $visita->isDirty('segundos_vistos') || $visita->syncOriginalAttribute('segundos_vistos'); 

        $visita->duracion_video   = $request->input('duracion') ?? $visita->duracion_video;
        $visita->ultimo_heartbeat = now();
    }

    $minParaView = min(30, $visita->duracion_video ?? 999999);
    $visita->view_valida = $visita->segundos_vistos >= $minParaView;

    if ($visita->duracion_video) {
        $visita->completado = $visita->segundos_vistos >= ($visita->duracion_video * 0.90);
    }

    $visita->save();
    return response()->json([
        'success' => true,
        'segundos_registrados' => $visita->segundos_vistos,
        'completado' => $visita->completado,
    ]);
}
    public function historial($userId)
    {
        $visitas = Visita::with(['video.canal.user'])
            ->with(['video' => function ($query) {
                $query->withCount('visitas as visitas_totales');
            }])
            ->where('user_id', $userId)
            ->whereHas('video', fn($q) => 
                $q->where('bloqueado', false)
                ->where('acceso', 'publico')
            )
            ->get();
    
        if ($visitas->isEmpty()) {
            return response()->json([
                'message' => 'El usuario no tiene historial de visualizaciones.'
            ], 404);
        }

        $visitasUnicas = $visitas
        ->sortByDesc('created_at')     
        ->unique('video_id')        
        ->values();
    
        $host   = $this->obtenerHostMinio();    
        $bucket = $this->obtenerBucket();      
    
        $historial = $visitasUnicas->map(function ($visita) use ($host, $bucket) {
            $video = $visita->video;
            $canal = $video->canal;
            $usuario = $canal?->user;
    
            $miniaturaUrl = $this->obtenerUrlArchivo($video->miniatura, $host, $bucket);
            $fotoUrl      = $this->obtenerUrlArchivo($usuario?->foto, $host, $bucket);
    
            return [
                'video_id'   => $video->id,
                'titulo'     => $video->titulo,
                'miniatura'  => $miniaturaUrl,
                'duracion'   => $video->duracion ?? null,
                'visitas_totales' => $video->visitas_totales ?? 0,
                'visto_en'   => $visita->created_at->toIso8601String(),
    
                'canal' => [
                    'id'     => $canal?->id,
                    'nombre' => $canal?->nombre ?? 'Canal desconocido',
                ],
    
                'user' => [
                    'id'     => $usuario?->id,
                    'nombre' => $usuario?->name ?? $usuario?->email ?? 'Anónimo',
                    'foto'   => $fotoUrl,
                ]
            ];
        });
    
        return response()->json($historial, 200);
    }

}
