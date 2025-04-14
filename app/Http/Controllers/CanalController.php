<?php

namespace App\Http\Controllers;

use App\Models\Canal;
use App\Models\Suscribe;
use App\Models\User;
use App\Models\Video;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CanalController extends Controller
{
    public function listarCanales()
    {
        $canales = Canal::with('user')->get();
        return response()->json($canales, 200);
    }

    public function listarVideosDeCanal($canalId)
    {
        $videos = Video::where('canal_id', $canalId)
            ->with([
                'canal:id,nombre,portada,descripcion,user_id',
                'canal.user:id,name,foto,email',
                'etiquetas:id,nombre',
            ])
            ->withCount([
                'puntuaciones as puntuacion_1' => function ($query) {
                    $query->where('valora', 1);
                },
                'puntuaciones as puntuacion_2' => function ($query) {
                    $query->where('valora', 2);
                },
                'puntuaciones as puntuacion_3' => function ($query) {
                    $query->where('valora', 3);
                },
                'puntuaciones as puntuacion_4' => function ($query) {
                    $query->where('valora', 4);
                },
                'puntuaciones as puntuacion_5' => function ($query) {
                    $query->where('valora', 5);
                },
                'visitas',
            ])->get();

        $videos->each(function ($video) {
            $video->promedio_puntuaciones = $video->puntuacion_promedio;
        });

        return response()->json($videos, 200);
    }

    public function crearCanal(Request $request, $userId)
    {
        $usuario = User::findOrFail($userId);
        $canalExistente = Canal::where('user_id', $userId)->first();
        if ($canalExistente) {
            return response()->json(['message' => 'El usuario ya tiene un canal'], 500);
        }
        $datosValidados = $this->validarDatos($request);
        $canal = $this->crearNuevoCanal($datosValidados, $userId);
        $this->guardarPortada($request, $canal);
        $this->guardarCanal($canal);
        return response()->json(['message' => 'Canal creado correctamente'], 201);
    }

    public function darDeBajaCanal($canalId)
    {
        try {
            $canal = Canal::findOrFail($canalId);
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

            $canal = Canal::findOrFail($canalId);

            if ($request->has('nombre')) {
                $canal->nombre = $request->input('nombre');
            }

            if ($request->has('descripcion')) {
                $canal->descripcion = $request->input('descripcion');
            }

            if ($request->hasFile('portada')) {
                $foto = $request->file('portada');
                $userId = $canal->user_id;
                $folderPath = 'portada/' . $userId;
                $rutaFoto = $foto->store($folderPath, 's3');
                $urlFoto = str_replace('minio', env('BLITZVIDEO_HOST'), Storage::disk('s3')->url($rutaFoto));

                $canal->portada = $urlFoto;
            }

            $canal->save();

            return response()->json(['message' => 'Canal actualizado correctamente', 'canal' => $canal], 200);
        } catch (ModelNotFoundException $exception) {
            return response()->json(['message' => 'Lo sentimos, tu canal no pudo ser encontrado'], 404);
        } catch (QueryException $exception) {
            return response()->json(['message' => 'Ocurrió un error al actualizar tu canal, por favor inténtalo de nuevo más tarde'], 500);
        }
    }

    private function guardarCanal(Canal $canal)
    {
        return $canal->save();
    }

    private function validarDatos(Request $request)
    {
        return $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'portada' => 'nullable|image|mimes:jpeg,png,jpg,gif,avif|max:2048',
        ]);
    }

    private function validarDatosDeEdicionDeCanal(Request $request)
    {
        return $request->validate([
            'nombre' => 'sometimes|required|string|max:255',
            'descripcion' => 'sometimes|nullable|string',
            'portada' => 'sometimes|nullable|image|mimes:jpeg,png,jpg,gif,avif|max:2048',
        ]);
    }
    private function crearNuevoCanal(array $datosValidados, $userId)
    {
        return new Canal([
            'nombre' => $datosValidados['nombre'],
            'descripcion' => $datosValidados['descripcion'],
            'user_id' => $userId,
            'stream_key'  => bin2hex(random_bytes(16)),
        ]);
    }

    private function guardarPortada(Request $request, Canal $canal)
    {
        if ($request->hasFile('portada')) {
            $portada = $request->file('portada');
            $userId = $canal->user_id;
            $folderPath = 'portadas/' . $userId;
            $rutaPortada = $portada->store($folderPath, 's3');
            $urlPortada = str_replace('minio', env('BLITZVIDEO_HOST'), Storage::disk('s3')->url($rutaPortada));
            $canal->portada = $urlPortada;

        }
    }

    public function activarNotificaciones($canalId, $userId)
    {
        $suscripcion = Suscribe::where('canal_id', $canalId)
            ->where('user_id', $userId)
            ->first();
        if (!$suscripcion) {
            return response()->json(['message' => 'No estás suscrito a este canal'], 404);
        }
        $suscripcion->notificaciones = true;
        $suscripcion->save();
        return response()->json(['message' => 'Notificaciones activadas para el canal'], 200);
    }

    public function desactivarNotificaciones($canalId, $userId)
    {
        $suscripcion = Suscribe::where('canal_id', $canalId)
            ->where('user_id', $userId)
            ->first();
        if (!$suscripcion) {
            return response()->json(['message' => 'No estás suscrito a este canal'], 404);
        }
        $suscripcion->notificaciones = false;
        $suscripcion->save();
        return response()->json(['message' => 'Notificaciones desactivadas para el canal'], 200);
    }
}
