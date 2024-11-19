<?php

namespace App\Http\Controllers;

use App\Jobs\EnviarCorreoJob;

class MailController extends Controller
{
    public function enviarCorreo($destinatario, $asunto, $mensaje)
    {
        $data = [
            'asunto' => $asunto,
            'mensaje' => $mensaje,
        ];

        EnviarCorreoJob::dispatch($destinatario, $asunto, $data, 'emails.plantilla')
            ->onQueue('cola_correo');

        return response()->json(['message' => 'Correo enviado exitosamente.']);
    }

    public function enviarCorreoPassword($destinatario, $asunto, $link, $name)
    {
        $mensaje = '
        Está recibiendo este correo electrónico porque recibimos una solicitud de restablecimiento de contraseña para su cuenta.
        Este enlace para restablecer la contraseña caducará en 60 minutos.
        Si no solicitó un restablecimiento de contraseña, no es necesario realizar ninguna otra acción.
        Saludos,
        Equipo de Blitzvideo.
        ';

        $data = [
            'asunto' => $asunto,
            'mensaje' => $mensaje,
            'link' => $link,
            'name' => $name ?? 'Usuario',
        ];

        EnviarCorreoJob::dispatch($destinatario, $asunto, $data, 'emails.password')
            ->onQueue('cola_correo');

        return response()->json(['message' => 'Correo con botón enviado exitosamente.']);
    }

}
