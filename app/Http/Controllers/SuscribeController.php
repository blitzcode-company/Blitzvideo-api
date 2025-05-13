<?php
namespace App\Http\Controllers;

use App\Models\Canal;
use App\Models\Suscribe;
use Illuminate\Http\Request;

class SuscribeController extends Controller
{
    public function suscribirse(Request $request, $canal_id)
    {
        $this->validarUsuario($request);

        if ($this->existeSuscripcion($request->user_id, $canal_id)) {
            return response()->json(['message' => 'Ya estás suscrito a este canal.'], 409);
        }

        $suscribe = Suscribe::create([
            'user_id'  => $request->user_id,
            'canal_id' => $canal_id,
        ]);

        return response()->json([
            'message' => 'Suscripción creada exitosamente.',
            'data'    => $suscribe,
        ], 201);
    }

    public function anularSuscripcion(Request $request, $canal_id)
    {
        $this->validarUsuario($request);

        $suscribe = $this->obtenerSuscripcion($request->user_id, $canal_id);

        if (! $suscribe) {
            return response()->json(['message' => 'No estás suscrito a este canal.'], 404);
        }

        $suscribe->delete();

        return response()->json(['message' => 'Suscripción anulada exitosamente.'], 200);
    }

    public function verificarSuscripcion(Request $request, $canal_id, $user_id)
    {
        $this->validarUsuario($request);

        $canal = Canal::find($canal_id);

        if ($canal && $canal->user_id == $user_id) {
            return response()->json(['estado' => 'propietario'], 200);
        }

        $estado = $this->existeSuscripcion($user_id, $canal_id) ? 'suscrito' : 'desuscrito';

        return response()->json(['estado' => $estado], 200);
    }

    public function listarSuscripciones()
    {
        $suscripciones = Suscribe::with(['canal.user:id,foto'])->get();

        return response()->json($suscripciones, 200);
    }

    public function listarSuscripcionesUsuario($user_id)
    {
        $suscripciones = Suscribe::where('user_id', $user_id)
            ->with([
                'canal.user:id,foto',
                'canal.streams' => fn($query) => $query->latest(),
            ])->get();

        if ($suscripciones->isEmpty()) {
            return response()->json(['message' => 'Este usuario no tiene suscripciones.'], 404);
        }

        $resultado = $suscripciones->map(fn($suscripcion) => [
            'id'           => $suscripcion->canal->id,
            'nombre'       => $suscripcion->canal->nombre,
            'descripcion'  => $suscripcion->canal->descripcion,
            'portada'      => $suscripcion->canal->portada,
            'user'         => $suscripcion->canal->user,
            'canal_online' => $suscripcion->canal->streams ? (bool) $suscripcion->canal->streams->activo : false,
        ]);

        return response()->json($resultado, 200);
    }

    public function contarSuscripciones($canal_id)
    {
        $numeroSuscripciones = Suscribe::where('canal_id', $canal_id)->count();

        return response()->json([
            'canal_id'             => (int) $canal_id,
            'numero_suscripciones' => $numeroSuscripciones,
        ]);
    }

    private function validarUsuario(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);
    }

    private function existeSuscripcion($user_id, $canal_id)
    {
        return Suscribe::where('user_id', $user_id)
            ->where('canal_id', $canal_id)
            ->exists();
    }

    private function obtenerSuscripcion($user_id, $canal_id)
    {
        return Suscribe::where('user_id', $user_id)
            ->where('canal_id', $canal_id)
            ->first();
    }
}
