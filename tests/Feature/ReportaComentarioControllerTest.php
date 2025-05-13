<?php
namespace Tests\Feature;

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
            'user_id'            => 2,
            'comentario_id'      => 4,
            'detalle'            => 'Reporte creado por Usuario 2',
            'lenguaje_ofensivo'  => false,
            'spam'               => false,
            'contenido_enganoso' => false,
            'incitacion_al_odio' => false,
            'acoso'              => false,
            'contenido_sexual'   => false,
            'otros'              => false,
        ]);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJson(['message' => 'Reporte de comentario creado exitosamente.']);
        $this->assertDatabaseHas('reporta_comentario', [
            'user_id'       => 2,
            'comentario_id' => 4,
            'detalle'       => 'Reporte creado por Usuario 2',
        ]);
    }
    public function testPuedeListarReportes()
    {
        $response = $this->getJson($this->baseUrl() . 'reporte/comentario');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            '*' => [
                'id',
                'user_id',
                'comentario_id',
                'detalle',
                'lenguaje_ofensivo',
                'spam',
                'contenido_enganoso',
                'incitacion_al_odio',
                'acoso',
                'contenido_sexual',
                'otros',
            ],
        ]);
    }
    public function testPuedeListarReportesPorComentario()
    {
        $comentarioId = 4;
        $response     = $this->getJson($this->baseUrl() . 'reporte/comentario/' . $comentarioId);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            '*' => [
                'id',
                'user_id',
                'comentario_id',
                'detalle',
                'lenguaje_ofensivo',
                'spam',
                'contenido_enganoso',
                'incitacion_al_odio',
                'acoso',
                'contenido_sexual',
                'otros',
            ],
        ]);
    }
    public function testPuedeListarReportesPorUsuario()
    {
        $userId   = 2;
        $response = $this->getJson($this->baseUrl() . 'reporte/comentario/usuario/' . $userId);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            '*' => [
                'id',
                'user_id',
                'comentario_id',
                'detalle',
                'lenguaje_ofensivo',
                'spam',
                'contenido_enganoso',
                'incitacion_al_odio',
                'acoso',
                'contenido_sexual',
                'otros',
            ],
        ]);
    }
}
