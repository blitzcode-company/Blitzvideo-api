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

    public function mostrarUsuarioPorId($id)
{
    $usuario = User::with('canales')->findOrFail($id);
    return response()->json($usuario, 200);
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
            if ($request->has('name')) {
                $usuario->name = $request->input('name');
            }
            
            if ($request->has('email')) {
                $usuario->email = $request->input('email');
            }
            if ($request->hasFile('foto')) {
                $foto = $request->file('foto');
                $folderPath = 'perfil/' . $userId;
                $rutaFoto = $foto->store($folderPath, 's3');
                $urlFoto = str_replace('minio', env('BLITZVIDEO_HOST'), Storage::disk('s3')->url($rutaFoto));
                
                $usuario->foto = $urlFoto;
            }
            $usuario->save();
            return response()->json(['message' => 'Usuario actualizado correctamente'], 200);
        } catch (\Exception $exception) {
            return response()->json(['message' => 'Ocurrió un error al actualizar el usuario'], 500);
        }
    }

    public function convertirUsuarioEnPremium(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);
    
        $user = User::find($request->user_id);
        $user->premium = 1; 
        $user->save();
    
        return response()->json(['success' => true, 'message' => 'Usuario actualizado a premium.']);
    }
    

}