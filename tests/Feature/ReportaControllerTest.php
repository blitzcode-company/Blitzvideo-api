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

    public function testPuedeListarReportes()
    {
        $response = $this->getJson($this->baseUrl() . 'reporte');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'reportes' => [
                '*' => ['id', 'user_id', 'video_id', 'detalle', 'contenido_inapropiado', 'spam', 'contenido_enganoso', 'violacion_derechos_autor', 'incitacion_al_odio', 'violencia_grafica', 'otros', 'created_at', 'updated_at'],
            ],
        ]);
    }

    public function testPuedeListarReportesPorVideo()
    {
        $response = $this->getJson($this->baseUrl() . 'reporte/video/3');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'reportes' => [
                '*' => ['id', 'user_id', 'video_id', 'detalle', 'contenido_inapropiado', 'spam', 'contenido_enganoso', 'violacion_derechos_autor', 'incitacion_al_odio', 'violencia_grafica', 'otros', 'created_at', 'updated_at'],
            ],
        ]);
    }

    public function testPuedeListarReportesPorUsuario()
    {
        $response = $this->getJson($this->baseUrl() . 'reporte/usuario/2');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'reportes' => [
                '*' => ['id', 'user_id', 'video_id', 'detalle', 'contenido_inapropiado', 'spam', 'contenido_enganoso', 'violacion_derechos_autor', 'incitacion_al_odio', 'violencia_grafica', 'otros', 'created_at', 'updated_at'],
            ],
        ]);
    }

    public function testPuedeModificarReporte()
    {
        $reporte = Reporta::where('video_id', 3)->first();

        $response = $this->putJson($this->baseUrl() . "reporte/{$reporte->id}", [
            'detalle' => 'Reporte modificado por Usuario 3',
            'contenido_inapropiado' => true,
            'spam' => true,
            'contenido_enganoso' => true,
            'violacion_derechos_autor' => true,
            'incitacion_al_odio' => true,
            'violencia_grafica' => true,
            'otros' => true,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['message' => 'Reporte modificado exitosamente.']);
        $this->assertDatabaseHas('reporta', [
            'id' => $reporte->id,
            'detalle' => 'Reporte modificado por Usuario 3',
            'contenido_inapropiado' => true,
            'spam' => true,
            'contenido_enganoso' => true,
            'violacion_derechos_autor' => true,
            'incitacion_al_odio' => true,
            'violencia_grafica' => true,
            'otros' => true,
        ]);
    }

    public function testPuedeBorrarReporte()
    {
        $reporte = Reporta::where('video_id', 3)->firstOrFail();

        $response = $this->deleteJson($this->baseUrl() . "reporte/{$reporte->id}");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['message' => 'Reporte borrado exitosamente.']);
        $this->assertDatabaseHas('reporta', [
            'id' => $reporte->id,
            'deleted_at' => $this->getDeletedAtTimestamp(),
        ]);
    }

    public function testPuedeBorrarTodosReportesDeVideo()
    {
        $reportes = Reporta::where('video_id', 3)->get();

        $response = $this->deleteJson($this->baseUrl() . 'reporte/video/3');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['message' => 'Todos los reportes del video han sido borrados exitosamente.']);
        foreach ($reportes as $reporte) {
            $this->assertDatabaseHas('reporta', [
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
