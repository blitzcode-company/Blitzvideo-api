<?php

namespace Tests\Feature;

use App\Models\ReportaComentario;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Http\Response;
use Tests\TestCase;

class ReportaComentarioControllerTest extends TestCase
{
    use WithoutMiddleware;

    protected function baseUrl()
    {
        return env('BLITZVIDEO_BASE_URL');
    }

    public function testPuedeCrearReporte()
    {
        $response = $this->postJson($this->baseUrl() . 'reporte/comentario', [
            'user_id' => 2,
            'comentario_id' => 4,
            'detalle' => 'Reporte creado por Usuario 2',
            'lenguaje_ofensivo' => false,
            'spam' => false,
            'contenido_enganoso' => false,
            'incitacion_al_odio' => false,
            'acoso' => false,
            'contenido_sexual' => false,
            'otros' => false,
        ]);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJson(['message' => 'Reporte de comentario creado exitosamente.']);
        $this->assertDatabaseHas('reporta_comentario', [
            'user_id' => 2,
            'comentario_id' => 4,
            'detalle' => 'Reporte creado por Usuario 2',
        ]);
    }
}
