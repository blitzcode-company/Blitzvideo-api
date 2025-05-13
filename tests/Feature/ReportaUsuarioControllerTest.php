<?php
namespace Tests\Feature;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Http\Response;
use Tests\TestCase;

class ReportaUsuarioControllerTest extends TestCase
{
    use WithoutMiddleware;

    protected function baseUrl()
    {
        return env('BLITZVIDEO_BASE_URL');
    }

    public function testPuedeCrearReporteDeUsuario()
    {
        $response = $this->postJson($this->baseUrl() . 'reporte/usuario', [
            'id_reportante'          => 2,
            'id_reportado'           => 4,
            'detalle'                => 'Reporte creado por Usuario 2',
            'ciberacoso'             => false,
            'privacidad'             => false,
            'suplantacion_identidad' => false,
            'amenazas'               => false,
            'incitacion_odio'        => false,
            'otros'                  => false,
        ]);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJson(['message' => 'Reporte creado exitosamente.']);
        $this->assertDatabaseHas('reporta_usuario', [
            'id_reportante' => 2,
            'id_reportado'  => 4,
            'detalle'       => 'Reporte creado por Usuario 2',
        ]);

    }
    public function testPuedeListarReportesDeUsuario()
    {
        $response = $this->getJson($this->baseUrl() . 'reporte/usuario');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            '*' => [
                'id',
                'id_reportante',
                'id_reportado',
                'detalle',
                'ciberacoso',
                'privacidad',
                'suplantacion_identidad',
                'amenazas',
                'incitacion_odio',
                'otros',
            ],
        ]);
    }
    public function testPuedeListarReportesPorUsuario()
    {
        $response = $this->getJson($this->baseUrl() . 'reporte/usuario/4');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            '*' => [
                'id',
                'id_reportante',
                'id_reportado',
                'detalle',
                'ciberacoso',
                'privacidad',
                'suplantacion_identidad',
                'amenazas',
                'incitacion_odio',
                'otros',
            ],
        ]);
    }
    public function testPuedeListarReportesPorReportante()
    {
        $response = $this->getJson($this->baseUrl() . 'reporte/usuario/reportante/2');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            '*' => [
                'id',
                'id_reportante',
                'id_reportado',
                'detalle',
                'ciberacoso',
                'privacidad',
                'suplantacion_identidad',
                'amenazas',
                'incitacion_odio',
                'otros',
            ],
        ]);
    }
}
