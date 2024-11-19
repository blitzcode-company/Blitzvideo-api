<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class EnviarCorreoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $destinatario;
    public $asunto;
    public $data;
    public $template;

    public function __construct($destinatario, $asunto, $data, $template = 'emails.plantilla')
    {
        $this->destinatario = $destinatario;
        $this->asunto = $asunto;
        $this->data = $data;
        $this->template = $template;
    }

    public function handle()
    {
        $viewData = [
            'asunto' => $this->asunto,
            'mensaje' => $this->data['mensaje'],
            'name' => $this->data['name'],
        ];
        if (isset($this->data['link'])) {
            $viewData['link'] = $this->data['link'];
        }
        Mail::send([], [], function ($correo) use ($viewData) {
            $correo->to($this->destinatario)
                ->subject($viewData['asunto'])
                ->setBody(view($this->template, $viewData)->render(), 'text/html');
        });
    }
}
