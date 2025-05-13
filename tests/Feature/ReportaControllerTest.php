<?php
namespace Tests\Feature;

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
            'user_id'                  => 3,
            'video_id'                 => 3,
            'detalle'                  => 'Reporte creado por Usuario 3',
            'contenido_inapropiado'    => false,
            'spam'                     => false,
            'contenido_enganoso'       => false,
            'violacion_derechos_autor' => false,
            'incitacion_al_odio'       => false,
            'violencia_grafica'        => false,
            'otros'                    => false,
        ]);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJson(['message' => 'Reporte creado exitosamente.']);
        $this->assertDatabaseHas('reporta', [
            'user_id'  => 3,
            'video_id' => 3,
            'detalle'  => 'Reporte creado por Usuario 3',
        ]);
    }
    public function testPuedeListarReportes()
    {
        $response = $this->getJson($this->baseUrl() . 'reporte');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            '*' => [
                'id',
                'user_id',
                'video_id',
                'detalle',
                'contenido_inapropiado',
                'spam',
                'contenido_enganoso',
                'violacion_derechos_autor',
                'incitacion_al_odio',
                'violencia_grafica',
                'otros',
            ],
        ]);
    }
    public function testPuedeListarReportesPorVideo()
    {
        $videoId  = 3;
        $response = $this->getJson($this->baseUrl() . 'reporte/video/' . $videoId);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            '*' => [
                'id',
                'user_id',
                'video_id',
                'detalle',
                'contenido_inapropiado',
                'spam',
                'contenido_enganoso',
                'violacion_derechos_autor',
                'incitacion_al_odio',
                'violencia_grafica',
                'otros',
            ],
        ]);
    }
    public function testPuedeListarReportesPorUsuario()
    {
        $userId   = 3;
        $response = $this->getJson($this->baseUrl() . 'reporte/usuario/' . $userId);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            '*' => [
                'id',
                'user_id',
                'video_id',
                'detalle',
                'contenido_inapropiado',
                'spam',
                'contenido_enganoso',
                'violacion_derechos_autor',
                'incitacion_al_odio',
                'violencia_grafica',
                'otros',
            ],
        ]);
    }
}
