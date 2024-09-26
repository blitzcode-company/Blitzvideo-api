<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class MailController extends Controller
{
    public function enviarCorreo(Request $destinatario, $asunto, $mensaje)
    {
        Mail::send([], [], function ($correo) use ($destinatario, $asunto, $mensaje) {
            $correo->to($destinatario)
                   ->subject($asunto)
                   ->setBody(view('emails.plantilla', ['asunto' => $asunto, 'mensaje' => $mensaje])->render(), 'text/html');
        });
    
        return response()->json(['message' => 'Correo enviado exitosamente.']);
    }    
}
