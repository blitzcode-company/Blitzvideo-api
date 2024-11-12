<?php

namespace Tests\Feature;

use App\Models\Reporta;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Http\Response;
use Tests\TestCase;

class ReportaControllerTest extends TestCase
{
    use WithoutMiddleware;

    protected function baseUrl()
    {
        return env('BLITZVIDEO_BASE_URL');
    }

    public function testPuedeCrearReporte()
    {
        $response = $this->postJson($this->baseUrl() . 'reporte', [
            'user_id' => 3,
            'video_id' => 3,
            'detalle' => 'Reporte creado por Usuario 3',
            'contenido_inapropiado' => false,
            'spam' => false,
            'contenido_enganoso' => false,
            'violacion_derechos_autor' => false,
            'incitacion_al_odio' => false,
            'violencia_grafica' => false,
            'otros' => false,
        ]);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJson(['message' => 'Reporte creado exitosamente.']);
        $this->assertDatabaseHas('reporta', [
            'user_id' => 3,
            'video_id' => 3,
            'detalle' => 'Reporte creado por Usuario 3',
        ]);
    }
}
