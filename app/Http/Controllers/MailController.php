<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Mail;

class MailController extends Controller
{
    public function enviarCorreo($destinatario, $asunto, $mensaje)
    {
        Mail::send([], [], function ($correo) use ($destinatario, $asunto, $mensaje) {
            $correo->to($destinatario)
                ->subject($asunto)
                ->setBody(view('emails.plantilla', ['asunto' => $asunto, 'mensaje' => $mensaje])->render(), 'text/html');
        });

        return response()->json(['message' => 'Correo enviado exitosamente.']);
    }

    public function enviarCorreoPassword($destinatario, $asunto, $mensaje, $link)
    {
        Mail::send([], [], function ($correo) use ($destinatario, $asunto, $mensaje, $link) {
            $correo->to($destinatario)
                ->subject($asunto)
                ->setBody(view('emails.password', ['asunto' => $asunto, 'mensaje' => $mensaje, 'link' => $link])->render(), 'text/html');
        });

        return response()->json(['message' => 'Correo con botón enviado exitosamente.']);
    }
}
