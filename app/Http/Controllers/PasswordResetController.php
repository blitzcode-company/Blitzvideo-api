<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function enviarRestablecerEnlaceCorreo(Request $request)
    {
        $validador = $this->validarCorreo($request);
        if ($validador->fails()) {
            return $this->respuestaErrorValidacion($validador);
        }

        $usuario = User::where('email', $request->email)->first();
        if (! $usuario) {
            return response()->json(['error' => 'El usuario no existe'], 404);
        }

        $enlaceRestablecimiento = $this->generarEnlaceRestablecimiento($usuario, $request->email);
        return $this->enviarCorreoRestablecimiento($request->email, $enlaceRestablecimiento, $usuario->name);
    }

    public function resetPassword(Request $request)
    {
        $validador = $this->validarRestablecimientoContrasena($request);
        if ($validador->fails()) {
            return $this->respuestaErrorValidacion($validador);
        }

        $estado = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($usuario, $contrasena) {
                $this->actualizarContrasenaUsuario($usuario, $contrasena);
            }
        );

        return $this->manejarRespuestaRestablecimiento($estado, $request->email);
    }

    private function validarCorreo(Request $request)
    {
        return Validator::make($request->all(), [
            'email' => 'required|email',
        ]);
    }

    private function validarRestablecimientoContrasena(Request $request)
    {
        return Validator::make($request->all(), [
            'email'    => 'required|email',
            'token'    => 'required',
            'password' => 'required|min:8|confirmed',
        ]);
    }

    private function respuestaErrorValidacion($validador)
    {
        return response()->json(['error' => $validador->errors()], 422);
    }

    private function generarEnlaceRestablecimiento($usuario, $correo)
    {
        $token = Password::createToken($usuario);
        return env('VISUALIZER_HOST') . "password/reset?token={$token}&email={$correo}";
    }

    private function enviarCorreoRestablecimiento($correo, $enlace, $nombreUsuario)
    {
        $controladorCorreo = new MailController();
        return $controladorCorreo->enviarCorreoPassword($correo, 'Restablecimiento de Contraseña', $enlace, $nombreUsuario);
    }

    private function actualizarContrasenaUsuario($usuario, $contrasena)
    {
        $usuario->password = Hash::make($contrasena);
        $usuario->setRememberToken(Str::random(60));
        $usuario->save();
        event(new PasswordReset($usuario));
    }

    private function manejarRespuestaRestablecimiento($estado, $correo)
    {
        if ($estado === Password::PASSWORD_RESET) {
            $this->enviarCorreoExitoRestablecimiento($correo);
            return response()->json(['message' => __($estado)]);
        }

        return response()->json(['error' => __($estado)], 400);
    }

    private function enviarCorreoExitoRestablecimiento($correo)
    {
        $asunto         = 'Cambio de Contraseña Exitoso';
        $mensaje        = 'Tu contraseña ha sido restablecida con éxito.';
        $controladorCorreo = new MailController();
        $controladorCorreo->enviarCorreo($correo, $asunto, $mensaje);
    }
}
