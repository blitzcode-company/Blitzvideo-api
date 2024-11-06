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

    public function testPuedeListarReportes()
    {
        $response = $this->getJson($this->baseUrl() . 'reporte/comentario');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'reportes' => [
                '*' => ['id', 'user_id', 'comentario_id', 'detalle', 'lenguaje_ofensivo', 'spam', 'contenido_enganoso', 'incitacion_al_odio', 'acoso', 'contenido_sexual', 'otros', 'created_at', 'updated_at'],
            ],
        ]);
    }

    public function testPuedeListarReportesPorComentario()
    {
        $response = $this->getJson($this->baseUrl() . 'reporte/comentario/1');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'reportes' => [
                '*' => ['id', 'user_id', 'comentario_id', 'detalle', 'lenguaje_ofensivo', 'spam', 'contenido_enganoso', 'incitacion_al_odio', 'acoso', 'contenido_sexual', 'otros', 'created_at', 'updated_at'],
            ],
        ]);
    }

    public function testPuedeListarReportesPorUsuario()
    {
        $response = $this->getJson($this->baseUrl() . 'reporte/comentario/usuario/2');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'reportes' => [
                '*' => ['id', 'user_id', 'comentario_id', 'detalle', 'lenguaje_ofensivo', 'spam', 'contenido_enganoso', 'incitacion_al_odio', 'acoso', 'contenido_sexual', 'otros', 'created_at', 'updated_at'],
            ],
        ]);
    }

    public function testPuedeModificarReporte()
    {
        $response = $this->putJson($this->baseUrl() . "reporte/1/comentario", [
            'detalle' => 'Reporte modificado por Usuario 2',
            'lenguaje_ofensivo' => true,
            'spam' => true,
            'contenido_enganoso' => true,
            'incitacion_al_odio' => true,
            'acoso' => true,
            'contenido_sexual' => true,
            'otros' => true,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['message' => 'Reporte de comentario modificado exitosamente.']);
        $this->assertDatabaseHas('reporta_comentario', [
            'id' => 1,
            'detalle' => 'Reporte modificado por Usuario 2',
            'lenguaje_ofensivo' => true,
            'spam' => true,
            'contenido_enganoso' => true,
            'incitacion_al_odio' => true,
            'acoso' => true,
            'contenido_sexual' => true,
            'otros' => true,
        ]);
    }

    public function testPuedeBorrarReporte()
    {
        $response = $this->deleteJson($this->baseUrl() . "reporte/2/comentario");
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['message' => 'Reporte de comentario borrado exitosamente.']);
        $this->assertDatabaseHas('reporta_comentario', [
            'id' => 2,
            'deleted_at' => $this->getDeletedAtTimestamp(),
        ]);
    }

    public function testPuedeBorrarTodosReportesDeComentario()
    {
        $reportes = ReportaComentario::where('comentario_id', 4)->get();
        $response = $this->deleteJson($this->baseUrl() . 'reporte/comentario/4');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['message' => 'Todos los reportes del comentario han sido borrados exitosamente.']);
        foreach ($reportes as $reporte) {
            $this->assertDatabaseHas('reporta_comentario', [
                'id' => $reporte->id,
                'deleted_at' => $this->getDeletedAtTimestamp(),
            ]);
        }
    }
    protected function getDeletedAtTimestamp()
    {
        return now()->format('Y-m-d H:i:s');
    }

}
