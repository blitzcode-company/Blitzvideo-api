<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Visita;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    public function listarUsuarios()
    {

        $usuarios = User::with('canales')->get();
        return response()->json($usuarios, 200);
    }

    public function visita($userId, $videoId)
    {
        Visita::create([
            'user_id' => $userId,
            'video_id' => $videoId,
        ]);
        return response()->json(['message' => 'Visita registrada con éxito']);
    }

    public function darDeBajaUsuario($userId)
    {
        try {
            $usuario = User::findOrFail($userId);
            $usuario->delete();
            return response()->json(['message' => 'El usuario se ha dado de baja correctamente'], 200);
        } catch (\Exception $exception) {
            return response()->json(['message' => 'Ocurrió un error al dar de baja al usuario'], 500);
        }
    }

    public function editarUsuario(Request $request, $userId)
    {
        try {
            $usuario = User::findOrFail($userId);
            $usuario->name = $request->input('name');
            $usuario->email = $request->input('email');
            if ($request->hasFile('foto')) {
                $foto = $request->file('foto');
                $folderPath = 'perfil/' . $userId;
                $rutaFoto = $foto->store($folderPath, 's3');
                $urlFoto = Storage::disk('s3')->url($rutaFoto);
                $usuario->foto = $urlFoto;
            }
            $usuario->save();
            return response()->json(['message' => 'Usuario actualizado correctamente'], 200);
        } catch (\Exception $exception) {
            return response()->json(['message' => 'Ocurrió un error al actualizar el usuario'], 500);
        }
    }
}
