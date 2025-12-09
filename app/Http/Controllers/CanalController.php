<?php
namespace App\Http\Controllers;

use App\Models\Canal;
use App\Models\Suscribe;
use App\Models\Video;
use App\Models\Visita;
use App\Models\User;
use App\Models\Playlist;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;


class CanalController extends Controller
{
    public function listarCanales()
    {
        $canales = Canal::with('user')->get();
        $host    = $this->obtenerHostMinio();
        $bucket  = $this->obtenerBucket();
        $canales->each(function ($canal) use ($host, $bucket) {
            $canal->portada = $this->obtenerUrlArchivo($canal->portada, $host, $bucket);
        });
        return response()->json($canales, 200);
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
    
    public function listarVideosDeCanal($canalId)
    {
        $videos = $this->obtenerVideosConRelaciones($canalId);
        $host   = $this->obtenerHostMinio();
        $bucket = $this->obtenerBucket();
        $videos->each(function ($video) use ($host, $bucket) {
            $this->procesarVideo($video, $host, $bucket);
        });
        return response()->json($videos, 200);
    }

    public function obtenerCanalPorId($id)
    {
        $canal = Canal::with('user')->find($id);
    
        if (! $canal) {
            return response()->json(['message' => 'Canal no encontrado'], 404);
        }
    
        $host   = $this->obtenerHostMinio();
        $bucket = $this->obtenerBucket();
    
        if ($canal->portada) {
            $canal->portada = $this->obtenerUrlArchivo($canal->portada, $host, $bucket);
        }
    
        if ($canal->user && $canal->user->foto) {
            $canal->user->foto = $this->obtenerUrlArchivo($canal->user->foto, $host, $bucket);
        }
    
    
        $stats = $this->estadisticasCanal($id);

        return response()->json([
            'canal' => $canal,
            'stats' => $stats
        ]);
    }


    private function estadisticasCanal($id)
    {
        $suscriptores = Suscribe::where('canal_id', $id)->count();

        $visitasTotales = Video::where('canal_id', $id)
            ->withCount('visitas')
            ->get()
            ->sum('visitas_count');

        $totalVideos = Video::where('canal_id', $id)->count();

        $crecimiento30dias = Suscribe::where('canal_id', $id)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        return [
            'suscriptores'       => $suscriptores,
            'visitas_totales'    => (int)$visitasTotales,
            'total_videos'       => $totalVideos,
            'crecimiento_30dias' => $crecimiento30dias,
        ];
    }

    private function obtenerVideosConRelaciones($canalId)
    {
        return Video::where('canal_id', $canalId)
            ->with([
                'canal:id,nombre,portada,descripcion,user_id',
                'canal.user:id,name,foto,email',
                'etiquetas:id,nombre',
            ])
            ->withCount($this->obtenerContadoresDePuntuaciones())
            ->get();
    }

    private function obtenerContadoresDePuntuaciones()
    {
        return [
            'puntuaciones as puntuacion_1' => fn($query) => $query->where('valora', 1),
            'puntuaciones as puntuacion_2' => fn($query) => $query->where('valora', 2),
            'puntuaciones as puntuacion_3' => fn($query) => $query->where('valora', 3),
            'puntuaciones as puntuacion_4' => fn($query) => $query->where('valora', 4),
            'puntuaciones as puntuacion_5' => fn($query) => $query->where('valora', 5),
            'visitas',
        ];
    }

    private function procesarVideo($video, $host, $bucket)
    {
        $video->miniatura = $this->obtenerUrlArchivo($video->miniatura, $host, $bucket);
        $video->link      = $this->obtenerUrlArchivo($video->link, $host, $bucket);
        if ($video->canal) {
            $video->canal->portada = $this->obtenerUrlArchivo($video->canal->portada, $host, $bucket);
            if ($video->canal->user) {
                $video->canal->user->foto = $this->obtenerUrlArchivo($video->canal->user->foto, $host, $bucket);
            }
        }
        $video->promedio_puntuaciones = $video->puntuacion_promedio;
    }

    public function crearCanal(Request $request, $userId)
    {
        $canalExistente = Canal::where('user_id', $userId)->first();
        if ($canalExistente) {
            return response()->json(['message' => 'El usuario ya tiene un canal'], 500);
        }
        $datosValidados = $this->validarDatos($request);
        $canal          = $this->crearNuevoCanal($datosValidados, $userId);
        $this->guardarPortada($request, $canal);
        $this->guardarCanal($canal);
        return response()->json(['message' => 'Canal creado correctamente'], 201);
    }

    private function validarDatos(Request $request)
    {
        return $request->validate([
            'nombre'      => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'portada'     => 'nullable|image|mimes:jpeg,png,jpg,gif,avif|max:2048',
        ]);
    }

    private function crearNuevoCanal(array $datosValidados, $userId)
    {
        return new Canal([
            'nombre'      => $datosValidados['nombre'],
            'descripcion' => $datosValidados['descripcion'],
            'user_id'     => $userId,
            'stream_key'  => bin2hex(random_bytes(16)),
        ]);
    }

    public function darDeBajaCanal($canalId)
    {
        try {
            $canal  = Canal::findOrFail($canalId);
            $videos = Video::where('canal_id', $canalId)->get();
            foreach ($videos as $video) {
                $video->delete();
            }
            $canal->delete();

            return response()->json(['message' => 'Tu canal y todos tus videos se han dado de baja correctamente'], 200);
        } catch (ModelNotFoundException $exception) {
            return response()->json(['message' => 'Lo sentimos, tu canal no pudo ser encontrado'], 404);
        } catch (QueryException $exception) {
            return response()->json(['message' => 'Ocurrió un error al dar de baja tu canal y tus videos, por favor inténtalo de nuevo más tarde'], 500);
        }
    }





    public function editarCanal(Request $request, $canalId)
    {
        try {
            $datosValidados = $this->validarDatosDeEdicionDeCanal($request);
            $canal          = Canal::findOrFail($canalId);

            $this->actualizarDatosCanal($canal, $datosValidados);
            $canal->save();

            return response()->json(['message' => 'Canal actualizado correctamente', 'canal' => $canal], 200);
        } catch (ModelNotFoundException $exception) {
            return response()->json(['message' => 'Lo sentimos, tu canal no pudo ser encontrado'], 404);
        } catch (QueryException $exception) {
            return response()->json(['message' => 'Ocurrió un error al actualizar tu canal, por favor inténtalo de nuevo más tarde'], 500);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return response()->json(['message' => 'Error de validación', 'errors' => $exception->errors()], 422);
        }
    }

    private function validarDatosDeEdicionDeCanal(Request $request)
    {
        return $request->validate([
            'nombre'      => 'sometimes|required|string|max:255',
            'descripcion' => 'sometimes|nullable|string',
            'portada'     => 'sometimes|nullable|image|mimes:jpeg,png,jpg,gif,avif|max:2048',
        ]);
    }

    private function actualizarDatosCanal(Canal $canal, array $datosValidados)
    {
        if (isset($datosValidados['nombre'])) {
            $canal->nombre = $datosValidados['nombre'];
        }

        if (isset($datosValidados['descripcion'])) {
            $canal->descripcion = $datosValidados['descripcion'];
        }

        if (isset($datosValidados['portada'])) {
            $this->actualizarPortadaCanal($canal, $datosValidados['portada']);
        }
    }

    private function actualizarPortadaCanal(Canal $canal, $portada)
    {
        $userId     = $canal->user_id;
        $folderPath = 'portada/' . $userId;
        $path       = $portada->store($folderPath, 's3');
    
        $host   = str_replace('minio', env('BLITZVIDEO_HOST'), env('AWS_ENDPOINT')) . '/';
        $bucket = env('AWS_BUCKET') . '/';
    
        $canal->portada = $host . $bucket . $path;
    }

    private function guardarCanal(Canal $canal)
    {
        return $canal->save();
    }

    private function guardarPortada(Request $request, Canal $canal)
    {
        if ($request->hasFile('portada')) {
            $portada    = $request->file('portada');
            $userId     = $canal->user_id;
            $folderPath = 'portadas/' . $userId;
            $path       = $portada->store($folderPath, 's3');
    
            $host   = str_replace('minio', env('BLITZVIDEO_HOST'), env('AWS_ENDPOINT')) . '/';
            $bucket = env('AWS_BUCKET') . '/';
    
            $canal->portada = $host . $bucket . $path;
        }
    }
    
    public function cambiarEstadoNotificaciones(Request $request, $canalId, $userId)
    {
        $request->validate([
            'estado' => 'required|boolean',
        ]);

        $suscripcion = $this->obtenerSuscripcion($canalId, $userId);

        if (! $suscripcion) {
            return response()->json(['message' => 'No estás suscrito a este canal'], 404);
        }

        $suscripcion->notificaciones = $request->estado;
        $suscripcion->save();

        $mensaje = $request->estado
        ? 'Notificaciones activadas para el canal'
        : 'Notificaciones desactivadas para el canal';

        return response()->json(['message' => $mensaje], 200);
    }

    public function estadoNotificaciones($canalId, $userId)
    {
        $suscripcion = $this->obtenerSuscripcion($canalId, $userId);

        if (! $suscripcion) {
            return response()->json(['message' => 'No estás suscrito a este canal'], 404);
        }

        return response()->json([
            'notificaciones' => (bool) $suscripcion->notificaciones,
        ], 200);
    }

    private function obtenerSuscripcion($canalId, $userId)
    {
        return Suscribe::where('canal_id', $canalId)
            ->where('user_id', $userId)
            ->first();
    }

}
