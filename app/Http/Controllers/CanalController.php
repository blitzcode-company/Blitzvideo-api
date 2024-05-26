<?php

namespace App\Http\Controllers;

use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use App\Models\Canal;
use App\Models\Video;
use App\Models\User;

class CanalController extends Controller
{
    public function listarCanales()
    {
        $canales = Canal::with('user')->get();
        return response()->json($canales, 200);
    }

    public function listarVideosDeCanal($canalId)
    {
        $videos = Video::where('canal_id', $canalId)->get();
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

    private function validarDatos(Request $request)
    {
        return $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'portada' => 'nullable|image|mimes:jpeg,png,jpg,gif,avif|max:2048',
        ]);
    }

    private function crearNuevoCanal(array $datosValidados, $userId)
    {
        return new Canal([
            'nombre' => $datosValidados['nombre'],
            'descripcion' => $datosValidados['descripcion'],
            'user_id' => $userId,
        ]);
    }

    private function guardarPortada(Request $request, Canal $canal)
    {
        if ($request->hasFile('portada')) {
            $portada = $request->file('portada');
            $userId = $canal->user_id;
            $folderPath = 'portadas/' . $userId;
            $rutaPortada = $portada->store($folderPath, 's3');
            $urlPortada = Storage::disk('s3')->url($rutaPortada);
            $canal->portada = $urlPortada;
        }
    }
    
    private function guardarCanal(Canal $canal)
    {
        return $canal->save();
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
}
