<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CustomMail extends Mailable
{
    use Queueable, SerializesModels;

    public $destinatario;
    public $asunto;
    public $mensaje;

    public function __construct($destinatario, $asunto, $mensaje)
    {
        $this->destinatario = $destinatario;
        $this->asunto = $asunto;
        $this->mensaje = $mensaje;
    }

    public function build()
    {
        return $this->to($this->destinatario)
            ->subject($this->asunto)
            ->view('emails.custom')
            ->with('mensaje', $this->mensaje);
    }
}
