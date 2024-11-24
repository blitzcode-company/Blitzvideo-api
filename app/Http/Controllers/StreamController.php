<?php

namespace App\Http\Controllers;

use App\Models\Stream;
use Illuminate\Http\Request;

class StreamController extends Controller
{
    public function mostrarTodasLasTransmisiones()
    {
        $transmisiones = Stream::all();
        return response()->json($transmisiones);
    }

    public function verTransmision($transmisionId)
    {
        $transmision = Stream::with([
            'user' => function ($query) {
                $query->select('id', 'name', 'foto');
            },
            'user.canales' => function ($query) {
                $query->select('id', 'nombre', 'user_id');
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

    public function guardarNuevaTransmision(Request $request, $user_id)
    {
        $request->validate([
            'titulo' => 'required|string|max:255',
        ]);

        $transmision = Stream::create([
            'titulo' => $request->titulo,
            'descripcion' => $request->descripcion,
            'stream_key' => bin2hex(random_bytes(16)),
            'activo' => false,
            'user_id' => $user_id,
        ]);

        return response()->json([
            'message' => 'Transmisión creada con éxito.',
            'transmision' => $transmision,
        ], 201);
    }

    public function ListarTransmisionOBS($transmisionId, $user_id)
    {
        $transmision = Stream::findOrFail($transmisionId);
        if ($transmision->user_id !== (int) $user_id) {
            return response()->json(['message' => 'No tienes permiso para acceder a esta trasnmision.'], 403);
        }
        $transmision = Stream::findOrFail($transmisionId);
        $transmision['server'] = env('RTMP_SERVER');
        return response()->json([
            'transmision' => $transmision,
        ], 200, [], JSON_UNESCAPED_SLASHES);
    }

    public function actualizarDatosDeTransmision(Request $request, $transmisionId, $user_id)
    {
        $transmision = Stream::findOrFail($transmisionId);
        if ($transmision->user_id !== (int) $user_id) {
            return response()->json(['message' => 'No tienes permiso para actualizar esta transmisión.'], 403);
        }

        $request->validate([
            'titulo' => 'required|string|max:255',
            'descripcion' => 'required|string|max:255',
        ]);

        if (isset($request->titulo) && empty($request->titulo)) {
            return response()->json(['message' => 'El título no puede estar vacío.'], 400);
        }
        if (isset($request->descripcion) && empty($request->descripcion)) {
            return response()->json(['message' => 'La descripción no puede estar vacía.'], 400);
        }
        if (isset($request->titulo)) {
            $transmision->titulo = $request->titulo;
        }
        if (isset($request->descripcion)) {
            $transmision->descripcion = $request->descripcion;
        }
        $transmision->save();
        return response()->json([
            'message' => 'Transmisión actualizada con éxito.',
            'transmision' => $transmision,
        ]);
    }

    public function eliminarTransmision($transmisionId, $user_id)
    {
        $transmision = Stream::findOrFail($transmisionId);
        if ($transmision->user_id !== (int) $user_id) {
            return response()->json(['message' => 'No tienes permiso para eliminar esta transmisión.'], 403);
        }

        $transmision->delete();
        return response()->json([
            'message' => 'Transmisión eliminada con éxito.',
        ]);
    }

    public function cambiarEstadoDeTransmision($transmisionId, $user_id)
    {
        $transmision = Stream::findOrFail($transmisionId);

        if ($transmision->user_id !== (int) $user_id) {
            return response()->json(['message' => 'No tienes permiso para cambiar el estado de esta transmisión.'], 403);
        }

        $transmision->update([
            'activo' => !$transmision->activo,
        ]);

        if ($transmision->activo) {
            return response()->json([
                'message' => 'Transmisión iniciada.',
                'transmision' => $transmision,
            ]);
        }

        return response()->json([
            'message' => 'Transmisión finalizada.',
            'transmision' => $transmision,
        ]);
    }
}
