<?php

namespace App\Http\Controllers;

use App\Http\Controllers\MailController;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use \App\Models\User;

class PasswordResetController extends Controller
{
    public function enviarRestablecerEnlaceCorreo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['error' => 'El usuario no existe'], 404);
        }
        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT) {
            $token = Password::createToken($user);
            $resetLink = env('VISUALIZER_HOST') . "password/reset?token={$token}&email={$request->email}";
            $mensaje = "Para restablecer tu contraseña, haz clic en el siguiente botón:";
            $mailController = new MailController();
            $mailController->enviarCorreoPassword($request->email, 'Restablecimiento de Contraseña', $mensaje, $resetLink);
            return response()->json(['message' => __($status)]);
        } else {
            return response()->json(['error' => __($status)], 400);
        }
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'token' => 'required',
            'password' => 'required|min:8|confirmed',
        ]);
    
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }
    
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->password = Hash::make($password);
                $user->setRememberToken(Str::random(60));
                $user->save();
    
                event(new PasswordReset($user));
            }
        );
    
        if ($status === Password::PASSWORD_RESET) {
            $asunto = 'Cambio de Contraseña Exitoso';
            $mensaje = 'Tu contraseña ha sido restablecida con éxito.';
            $mailController = new MailController();
            $mailController->enviarCorreo($request->email, $asunto, $mensaje);
    
            return response()->json(['message' => __($status)]);
        } else {
            return response()->json(['error' => __($status)], 400);
        }
    }
}
