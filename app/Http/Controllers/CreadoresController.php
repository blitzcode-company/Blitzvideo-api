<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Video;
use App\Models\Visita;
use App\Models\User;
use App\Models\Canal;
use App\Models\Puntua;
use App\Models\Comentario;
use App\Models\MeGusta;
use Illuminate\Support\Facades\DB;

class CreadoresController extends Controller
{

    private function obtenerCanalDelUsuario($userId)
    {
        $usuario = User::findOrFail($userId);
        $canal = $usuario->canales;

        if (!$canal) {
            throw new \Exception('El usuario no tiene un canal asociado', 404);
        }

        return $canal;
    }

    private function obtenerStatusCode(\Exception $e): int
    {
        $code = (int) $e->getCode();
        return ($code >= 100 && $code <= 599) ? $code : 500;
    }

 
    public function resumen(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'dias'    => 'sometimes|integer|min:1|max:365', 
        ]);

        try {
            $userId = $request->input('user_id');
            $dias = (int) ($request->input('dias') ?? $request->query('dias', 28));

            $canal = $this->obtenerCanalDelUsuario($userId);
            $desde = now()->subDays($dias);

            $videoIds = $canal->videos()->pluck('videos.id');

            $visitasQuery = Visita::whereIn('video_id', $videoIds)
                ->where('updated_at', '>=', $desde);

            $totalVisitas        = $visitasQuery->count();
            $totalSegundos       = (clone $visitasQuery)->sum('segundos_vistos');
            $promedioSegundos    = (clone $visitasQuery)->avg('segundos_vistos') ?? 0;

            $host   = $this->obtenerHostMinio();
            $bucket = $this->obtenerBucket();

            $videosDestacados = $canal->videos()
                ->withCount('visitas')
                ->withCount(['puntuaciones as contador_puntuacion' => function($q) {
                    $q->where('created_at', '>=', now()->subDays(28));
                }])
                ->withCount(['comentarios as comentarios_count' => function($q) {
                    $q->where('created_at', '>=', now()->subDays(28));
                }])
                ->orderByDesc('visitas_count')
                ->take(5)
                ->get(['id', 'titulo', 'miniatura', 'duracion']);

            $totalSuscriptores = $canal->suscriptores()->count();

            $tasaCompletitud = (clone $visitasQuery)->where('completado', true)->count() > 0
                ? (((clone $visitasQuery)->where('completado', true)->count() / $totalVisitas) * 100)
                : 0;

            return response()->json([
                'data' => [
                    'totalVistas' => $totalVisitas,
                    'totalSuscriptores' => $totalSuscriptores,
                    'videosSubidos' => $canal->videos()->count(),
                    'tasa_completitud' => round($tasaCompletitud, 2),
                    'tiempo_promedio' => round($promedioSegundos, 2),
                    'videos_destacados' => $videosDestacados->map(fn($video) => [
                        'id' => $video->id,
                        'titulo' => $video->titulo,
                        'miniatura' => $this->obtenerUrlArchivo($video->miniatura, $host, $bucket),
                        'vistas' => $video->visitas_count,
                        'duracion' => (int)$video->duracion,
                        'totalPuntuaciones' => $video->contador_puntuacion ?? 0,
                        'promedioPuntuacion' => $this->calcularPromedioPuntuacion($video->id),
                        'comentarios' => $video->comentarios_count ?? 0,
                    ]),
                ],
                'periodo' => [
                    'dias' => $dias,
                    'desde' => $desde->toDateString(),
                    'hasta' => now()->toDateString(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], $this->obtenerStatusCode($e));
        }
    }

    public function estadisticasVideo($videoId, Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        try {
            $userId = $request->input('user_id');
            $video = Video::findOrFail($videoId);

            if ($userId !== $video->canal?->user_id) {
                return response()->json(['error' => 'No autorizado para ver estadísticas de este video'], 403);
            }

            $dias = (int) ($request->input('dias') ?? $request->query('dias', 0));
            $desde = $dias > 0 ? now()->subDays($dias) : null;

            $visitasQuery = $video->visitas();
            if ($desde) {
                $visitasQuery = $visitasQuery->where('updated_at', '>=', $desde);
            }

            $totalVisitas = $visitasQuery->count();
            $totalSegundos = (clone $visitasQuery)->sum('segundos_vistos');
            $promedioSegundos = (clone $visitasQuery)->avg('segundos_vistos') ?? 0;
            $completados = (clone $visitasQuery)->where('completado', true)->count();

            $puntuacionesPorRating = [
                5 => $video->puntuaciones()->where('valora', 5)->count(),
                4 => $video->puntuaciones()->where('valora', 4)->count(),
                3 => $video->puntuaciones()->where('valora', 3)->count(),
                2 => $video->puntuaciones()->where('valora', 2)->count(),
                1 => $video->puntuaciones()->where('valora', 1)->count(),
            ];

            return response()->json([
                'data' => [
                    'id' => $video->id,
                    'titulo' => $video->titulo,
                    'duracion' => (int)$video->duracion,
                    'vistas' => $totalVisitas,
                    'tiempo_promedio_segundos' => round($promedioSegundos, 2),
                    'tasa_completitud' => $totalVisitas > 0 ? round(($completados / $totalVisitas) * 100, 2) : 0,
                    'totalPuntuaciones' => $video->puntuaciones()->count(),
                    'promedioPuntuacion' => $this->calcularPromedioPuntuacion($video->id),
                    'puntuacionesPorRating' => $puntuacionesPorRating,
                    'comentarios' => $video->comentarios()->count(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], $this->obtenerStatusCode($e));
        }
    }

    public function vistasPorPeriodo(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'periodo' => 'sometimes|in:semana,mes,trimestre,año'
        ]);

        try {
            $userId = $request->input('user_id');
            $periodo = $request->input('periodo', 'mes');

            $canal = $this->obtenerCanalDelUsuario($userId);
            $videoIds = $canal->videos()->pluck('videos.id');

            $dias = match($periodo) {
                'semana' => 7,
                'mes' => 28,
                'trimestre' => 84,
                'año' => 365,
                default => 28
            };

            $desde = now()->subDays($dias);

            $vistas = Visita::whereIn('video_id', $videoIds)
                ->where('updated_at', '>=', $desde)
                ->groupBy(DB::raw('DATE(updated_at)'))
                ->select(
                    DB::raw('DATE(updated_at) as fecha'),
                    DB::raw('COUNT(*) as total')
                )
                ->orderBy('fecha')
                ->get();

            $totalVistas = $vistas->sum('total');

            return response()->json([
                'data' => [
                    'periodo' => $periodo,
                    'dias' => $dias,
                    'desde' => $desde->toDateString(),
                    'hasta' => now()->toDateString(),
                    'total_vistas' => $totalVistas,
                    'vistas_por_dia' => $vistas->map(fn($v) => [
                        'fecha' => $v->fecha,
                        'vistas' => (int)$v->total
                    ])
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], $this->obtenerStatusCode($e));
        }
    }


    public function videosTopRendimiento(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'limite' => 'sometimes|integer|min:1|max:50'
        ]);

        try {
            $userId = $request->input('user_id');
            $limite = $request->input('limite', 5);

            $canal = $this->obtenerCanalDelUsuario($userId);

            $host   = $this->obtenerHostMinio();
            $bucket = $this->obtenerBucket();


            $videos = $canal->videos()
                ->withCount('visitas')
                ->withCount('puntuaciones')
                ->withCount('comentarios')
                ->orderByDesc('visitas_count')
                ->take($limite)
                ->get(['id', 'titulo', 'miniatura', 'duracion']);

            return response()->json([
                'data' => $videos->map(fn($v) => [
                    'id' => $v->id,
                    'titulo' => $v->titulo,
                    'miniatura' => $this->obtenerUrlArchivo($v->miniatura, $host, $bucket),
                    'vistas' => (int)$v->visitas_count,
                    'duracion' => (int)$v->duracion,
                    'totalPuntuaciones' => (int)$v->puntuaciones_count,
                    'promedioPuntuacion' => $this->calcularPromedioPuntuacion($v->id),
                    'comentarios' => (int)$v->comentarios_count,
                ])
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], $this->obtenerStatusCode($e));
        }
    }


    public function audiencia(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        try {
            $userId = $request->input('user_id');
            $canal = $this->obtenerCanalDelUsuario($userId);

            $ahora = now();
            $hace28Dias = $ahora->clone()->subDays(28);
            $hace56Dias = $ahora->clone()->subDays(56);

            $totalSuscriptores = $canal->suscriptores()->count();
            $nuevosSuscriptores = $canal->suscriptores()
                ->where('suscribe.created_at', '>=', $hace28Dias)
                ->count();
            $suscriptoresPeridos = $canal->suscriptores()
                ->whereNotNull('suscribe.deleted_at')
                ->where('suscribe.deleted_at', '>=', $hace28Dias)
                ->count();

            $suscriptoresHace28 = $canal->suscriptores()
                ->where('suscribe.created_at', '<', $hace28Dias)
                ->count();

            $tasaCrecimiento = $suscriptoresHace28 > 0
                ? (($nuevosSuscriptores / $suscriptoresHace28) * 100)
                : 0;

            $tasaRetencion = $totalSuscriptores > 0
                ? (((($totalSuscriptores - $suscriptoresPeridos) / $totalSuscriptores)) * 100)
                : 0;

            return response()->json([
                'data' => [
                    'totalSuscriptores' => $totalSuscriptores,
                    'nuevosSuscriptores' => $nuevosSuscriptores,
                    'suscriptoresPeridos' => $suscriptoresPeridos,
                    'tasaCrecimiento' => round($tasaCrecimiento, 2),
                    'tasaRetencion' => round($tasaRetencion, 2),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], $this->obtenerStatusCode($e));
        }
    }


    public function datosSuscriptores(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        try {
            $userId = $request->input('user_id');
            $canal = $this->obtenerCanalDelUsuario($userId);

            $totalSuscriptores = $canal->suscriptores()->count();
            
            $hoy = now()->toDateString();
            $suscriptoresHoy = $canal->suscriptores()
                ->whereDate('suscribe.created_at', $hoy)
                ->count();

            $hace7Dias = now()->subDays(7)->toDateString();
            $suscriptores7Dias = $canal->suscriptores()
                ->whereBetween('suscribe.created_at', [now()->subDays(7), now()])
                ->count();

            return response()->json([
                'data' => [
                    'totalSuscriptores' => $totalSuscriptores,
                    'suscriptoresHoy' => $suscriptoresHoy,
                    'suscriptores7Dias' => $suscriptores7Dias,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], $this->obtenerStatusCode($e));
        }
    }


    public function historialVistas(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'video_id' => 'sometimes|integer|exists:videos,id',
            'limite' => 'sometimes|integer|min:1|max:100'
        ]);

        try {
            $userId = $request->input('user_id');
            $videoId = $request->input('video_id');
            $limite = $request->input('limite', 30);

            $canal = $this->obtenerCanalDelUsuario($userId);

            $query = Visita::with(['video' => function ($q) {
                $q->select('id', 'titulo', 'duracion', 'miniatura');
            }, 'user' => function ($q) {
                $q->select('id', 'name', 'email');
            }])
            ->whereIn('video_id', $canal->videos()->pluck('videos.id'));

            if ($videoId) {
                $query->where('video_id', $videoId);
            }

            $vistas = $query->orderByDesc('updated_at')
                ->take($limite)
                ->get([
                    'id', 
                    'video_id', 
                    'user_id', 
                    'segundos_vistos', 
                    'duracion_video',
                    'view_valida',
                    'completado', 
                    'created_at',
                    'updated_at'
                ]);

            return response()->json([
                'data' => $vistas->map(fn($v) => [
                    'id' => $v->id,
                    'video_id' => $v->video_id,
                    'video_titulo' => $v->video?->titulo ?? 'Eliminado',
                    'video_miniatura' => $v->video?->miniatura ?? null,
                    'usuario_id' => $v->user_id,
                    'usuario_nombre' => $v->user?->name ?? 'Anónimo',
                    'usuario_email' => $v->user?->email ?? null,
                    'segundos_vistos' => (int)$v->segundos_vistos,
                    'duracion_video' => (int)$v->duracion_video,
                    'porcentaje_completitud' => $v->duracion_video > 0 
                        ? round(($v->segundos_vistos / $v->duracion_video) * 100, 2)
                        : 0,
                    'view_valida' => (bool)$v->view_valida,
                    'completado' => (bool)$v->completado,
                    'fecha' => $v->created_at->toDateTimeString(),
                    'fecha_actualizacion' => $v->updated_at->toDateTimeString(),
                ]),
                'periodo' => [
                    'desde' => null,
                    'hasta' => null,
                    'periodo' => 'general'
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], $this->obtenerStatusCode($e));
        }
    }

    public function tiempoPromedioVisualizacion(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        try {
            $userId = $request->input('user_id');
            $canal = $this->obtenerCanalDelUsuario($userId);

            $videoIds = $canal->videos()->pluck('videos.id');

            $tiempoPromedio = Visita::whereIn('video_id', $videoIds)
                ->avg('segundos_vistos') ?? 0;

            $tiempoTotal = Visita::whereIn('video_id', $videoIds)
                ->sum('segundos_vistos') ?? 0;

            return response()->json([
                'data' => [
                    'tiempo_promedio_segundos' => round($tiempoPromedio, 2),
                    'tiempo_promedio_minutos' => round($tiempoPromedio / 60, 2),
                    'tiempo_total_horas' => round($tiempoTotal / 3600, 2),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], $this->obtenerStatusCode($e));
        }
    }


    public function tasaCompletitud(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        try {
            $userId = $request->input('user_id');
            $canal = $this->obtenerCanalDelUsuario($userId);

            $videoIds = $canal->videos()->pluck('videos.id');

            $visitasQuery = Visita::whereIn('video_id', $videoIds)
                ->where('segundos_vistos', '>', 0);
            $totalVisitas = $visitasQuery->count();
            $visitasCompletadas = (clone $visitasQuery)->where('completado', true)->count();

            $tasa = $totalVisitas > 0 ? (($visitasCompletadas / $totalVisitas) * 100) : 0;

            return response()->json([
                'data' => [
                    'tasa_completitud' => round($tasa, 2),
                    'videos_completados' => $visitasCompletadas,
                    'total_visitas' => $totalVisitas,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], $this->obtenerStatusCode($e));
        }
    }

    public function engagement(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        try {
            $userId = $request->input('user_id');
            $canal = $this->obtenerCanalDelUsuario($userId);

            $videoIds = $canal->videos()->pluck('videos.id');
            
            $totalPuntuaciones = Puntua::whereIn('video_id', $videoIds)->count();
            $puntuacionesPor = [
                5 => Puntua::whereIn('video_id', $videoIds)->where('valora', 5)->count(),
                4 => Puntua::whereIn('video_id', $videoIds)->where('valora', 4)->count(),
                3 => Puntua::whereIn('video_id', $videoIds)->where('valora', 3)->count(),
                2 => Puntua::whereIn('video_id', $videoIds)->where('valora', 2)->count(),
                1 => Puntua::whereIn('video_id', $videoIds)->where('valora', 1)->count(),
            ];
            
            $comentarios = Comentario::whereIn('video_id', $videoIds)->count();

            $hace28Dias = now()->subDays(28);
            $puntuacionesRecientes = Puntua::whereIn('video_id', $videoIds)
                ->where('created_at', '>=', $hace28Dias)
                ->count();
            $comentariosRecientes = Comentario::whereIn('video_id', $videoIds)
                ->where('created_at', '>=', $hace28Dias)
                ->count();

            $promedioPuntuacion = $totalPuntuaciones > 0 
                ? round((
                    (5 * $puntuacionesPor[5]) +
                    (4 * $puntuacionesPor[4]) +
                    (3 * $puntuacionesPor[3]) +
                    (2 * $puntuacionesPor[2]) +
                    (1 * $puntuacionesPor[1])
                ) / $totalPuntuaciones, 2)
                : 0;

            return response()->json([
                'data' => [
                    'totalPuntuaciones' => $totalPuntuaciones,
                    'promedioPuntuacion' => $promedioPuntuacion,
                    'puntuacionesPorRating' => $puntuacionesPor,
                    'comentarios' => $comentarios,
                    'puntuacionesRecientes' => $puntuacionesRecientes,
                    'comentariosRecientes' => $comentariosRecientes,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], $this->obtenerStatusCode($e));
        }
    }

    private function calcularPromedioPuntuacion($videoId): float
    {
        $puntuaciones = Puntua::where('video_id', $videoId)->pluck('valora');
        
        if ($puntuaciones->isEmpty()) {
            return 0;
        }

        return round($puntuaciones->avg(), 2);
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

      private function obtenerHostMinio()
    {
        return str_replace('minio', env('BLITZVIDEO_HOST'), env('AWS_ENDPOINT')) . '/';
    }

    private function obtenerBucket()
    {
        return env('AWS_BUCKET') . '/';
    }

}