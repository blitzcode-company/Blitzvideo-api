<?php
namespace App\Http\Controllers;

use App\Jobs\EnviarCorreoJob;

class MailController extends Controller
{
    public function enviarCorreo($destinatario, $asunto, $mensaje)
    {
        $data = [
            'asunto'  => $asunto,
            'mensaje' => $mensaje,
        ];

        $this->dispatchCorreo($destinatario, $asunto, $data, 'emails.plantilla');

        return response()->json(['message' => 'Correo enviado exitosamente.']);
    }

    public function enviarCorreoPassword($destinatario, $asunto, $link, $name = 'Usuario')
    {
        $mensaje = '
        Está recibiendo este correo electrónico porque recibimos una solicitud de restablecimiento de contraseña para su cuenta.
        Este enlace para restablecer la contraseña caducará en 60 minutos.
        Si no solicitó un restablecimiento de contraseña, no es necesario realizar ninguna otra acción.
        Saludos,
        Equipo de Blitzvideo.
        ';

        $data = [
            'asunto'  => $asunto,
            'mensaje' => $mensaje,
            'link'    => $link,
            'name'    => $name,
        ];

        $this->dispatchCorreo($destinatario, $asunto, $data, 'emails.password');

        return response()->json(['message' => 'Correo con botón enviado exitosamente.']);
    }

    private function dispatchCorreo($destinatario, $asunto, $data, $template)
    {
        EnviarCorreoJob::dispatch($destinatario, $asunto, $data, $template)
            ->onQueue('cola_correo');
    }
}
