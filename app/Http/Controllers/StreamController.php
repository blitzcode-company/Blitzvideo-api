<?php

namespace App\Http\Controllers;

use App\Models\Canal;
use App\Models\Stream;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StreamController extends Controller
{
    public function mostrarTodasLasTransmisiones()
    {
        $transmisiones = Stream::with('canal:id,nombre')->get();
        return response()->json($transmisiones);
    }

    public function verTransmision($transmisionId)
    {
        $transmision = Stream::with([
            'canal' => function ($query) {
                $query->select('id', 'nombre', 'user_id');
            },
            'canal.user' => function ($query) {
                $query->select('id', 'name', 'foto');
            },
        ])->findOrFail($transmisionId);

        $url_hls = $transmision->activo
        ? env('STREAM_BASE_LINK') . "{$transmision->stream_key}.m3u8"
        : null;

        $transmision->setHidden(['stream_key']);

        return response()->json([
            'transmision' => $transmision,
            'url_hls' => $url_hls,
        ], 200, [], JSON_UNESCAPED_SLASHES);
    }

    public function guardarNuevaTransmision(Request $request, $canal_id)
    {
        $canal = Canal::findOrFail($canal_id);

        $request->validate([
            'titulo' => 'required|string|max:255',
        ]);
        $transmision = Stream::create([
            'titulo' => $request->titulo,
            'descripcion' => $request->descripcion,
            'stream_key' => bin2hex(random_bytes(16)),
            'activo' => false,
            'canal_id' => $canal->id,
        ]);
        if ($request->hasFile('miniatura')) {
            $miniatura = $request->file('miniatura');
            $nombreMiniatura = "{$transmision->id}.jpg";
            $folderPath = "miniaturas-streams/{$canal_id}";
            $rutaMiniatura = $miniatura->storeAs($folderPath, $nombreMiniatura, 's3');
            $miniaturaUrl = str_replace('minio', env('BLITZVIDEO_HOST'), Storage::disk('s3')->url($rutaMiniatura));
            $transmision->miniatura = $miniaturaUrl;
            $transmision->save();
        }

        return response()->json([
            'message' => 'Transmisión creada con éxito.',
            'transmision' => $transmision,
        ], 201);
    }

    public function ListarTransmisionOBS($transmisionId, $canal_id)
    {
        $transmision = Stream::findOrFail($transmisionId);

        if ($transmision->canal_id !== (int) $canal_id) {
            return response()->json(['message' => 'No tienes permiso para acceder a esta transmisión.'], 403);
        }

        $transmision['server'] = env('RTMP_SERVER');

        return response()->json([
            'transmision' => $transmision,
        ], 200, [], JSON_UNESCAPED_SLASHES);
    }

    public function actualizarDatosDeTransmision(Request $request, $transmisionId, $canal_id)
    {
        $transmision = Stream::findOrFail($transmisionId);

        if ($transmision->canal_id !== (int) $canal_id) {
            return response()->json(['message' => 'No tienes permiso para actualizar esta transmisión.'], 403);
        }

        $request->validate([
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:255',
            'miniatura' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);
        $transmision->update($request->only(['titulo', 'descripcion']));
        if ($request->hasFile('miniatura')) {
            $miniatura = $request->file('miniatura');
            $nombreMiniatura = "{$transmision->id}.jpg";
            $folderPath = "miniaturas-streams/{$canal_id}";
            $rutaMiniatura = $miniatura->storeAs($folderPath, $nombreMiniatura, 's3');
            $miniaturaUrl = str_replace('minio', env('BLITZVIDEO_HOST'), Storage::disk('s3')->url($rutaMiniatura));
            $transmision->miniatura = $miniaturaUrl;
            $transmision->save();
        }

        return response()->json([
            'message' => 'Transmisión actualizada con éxito.',
            'transmision' => $transmision,
        ]);
    }

    public function eliminarTransmision($transmisionId, $canal_id)
    {
        $transmision = Stream::findOrFail($transmisionId);

        if ($transmision->canal_id !== (int) $canal_id) {
            return response()->json(['message' => 'No tienes permiso para eliminar esta transmisión.'], 403);
        }

        $transmision->delete();

        return response()->json([
            'message' => 'Transmisión eliminada con éxito.',
        ]);
    }

    public function cambiarEstadoDeTransmision($transmisionId, $canal_id)
    {
        $transmision = Stream::findOrFail($transmisionId);

        if ($transmision->canal_id !== (int) $canal_id) {
            return response()->json(['message' => 'No tienes permiso para cambiar el estado de esta transmisión.'], 403);
        }

        $transmision->update([
            'activo' => !$transmision->activo,
        ]);

        $message = $transmision->activo ? 'Transmisión iniciada.' : 'Transmisión finalizada.';

        return response()->json([
            'message' => $message,
            'transmision' => $transmision,
        ]);
    }
}
