<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function listarUsuarios()
    {
        $usuarios = User::with('canales')->get();
        $host     = $this->obtenerHostMinio();
        $bucket   = $this->obtenerBucket();
        $usuarios->each(function ($usuario) use ($host, $bucket) {
            $usuario->foto = $this->generarUrlSiExiste($usuario->foto, $host, $bucket);
            if ($usuario->canales) {
                $usuario->canales->portada = $this->generarUrlSiExiste($usuario->canales->portada, $host, $bucket);
            }
        });
        return response()->json($usuarios, 200);
    }

    private function generarUrlSiExiste($rutaRelativa, $host, $bucket)
    {
        return $rutaRelativa ? $this->obtenerUrlArchivo($rutaRelativa, $host, $bucket) : null;
    }

    public function mostrarUsuarioPorId($id)
    {
        $usuario = User::with('canales')->findOrFail($id);
        return response()->json($usuario, 200);
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
        return $rutaRelativa ? $host . $bucket . $rutaRelativa : null;
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

            $this->actualizarAtributosUsuario($usuario, $request);
            $usuario->save();

            return response()->json(['message' => 'Usuario actualizado correctamente'], 200);
        } catch (\Exception $exception) {
            return response()->json(['message' => 'Ocurrió un error al actualizar el usuario'], 500);
        }
    }

    private function actualizarAtributosUsuario($usuario, $request)
    {
        $atributos = ['name', 'email', 'fecha_de_nacimiento'];

        foreach ($atributos as $atributo) {
            if ($request->has($atributo)) {
                $usuario->$atributo = $request->input($atributo);
            }
        }

        if ($request->hasFile('foto')) {
            $usuario->foto = $this->guardarFotoEnS3($request->file('foto'), $usuario->id);
        }
    }

    private function guardarFotoEnS3($foto, $userId)
    {
        $folderPath = 'perfil/' . $userId;
        return $foto->store($folderPath, 's3');
    }
}
