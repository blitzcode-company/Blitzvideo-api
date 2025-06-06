<?php

namespace Tests\Feature;

use App\Models\Suscribe;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Http\Response;
use Tests\TestCase;

class SuscribeControllerTest extends TestCase
{
    use WithoutMiddleware;

    protected function baseUrl()
    {
        return env('BLITZVIDEO_BASE_URL') . 'canal';
    }

    public function testPuedeSuscribirse()
    {
        $response = $this->postJson($this->baseUrl() . '/10/suscripcion', [
            'user_id' => 2,
        ]);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJsonStructure([
            'data' => ['id', 'user_id', 'canal_id', 'created_at', 'updated_at'],
        ]);
        $this->assertDatabaseHas('suscribe', [
            'user_id' => 2,
            'canal_id' => 10,
        ]);
    }

    public function testNoPuedeSuscribirseDosVeces()
    {
        $response = $this->postJson($this->baseUrl() . '/2/suscripcion', [
            'user_id' => 2,
        ]);

        $response->assertStatus(Response::HTTP_CONFLICT);
        $response->assertJson(['message' => 'Ya estás suscrito a este canal.']);
    }

    public function testPuedeAnularSuscripcion()
    {
        $response = $this->deleteJson($this->baseUrl() . '/2/suscripcion', [
            'user_id' => 2,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseMissing('suscribe', [
            'user_id' => 2,
            'canal_id' => 1,
        ]);
    }

    public function testNoPuedeAnularSuscripcionNoExistente()
    {
        $response = $this->deleteJson($this->baseUrl() . '/2/suscripcion', [
            'user_id' => 2,
        ]);

        $response->assertStatus(Response::HTTP_NOT_FOUND);
        $response->assertJson(['message' => 'No estás suscrito a este canal.']);
    }

    public function testPuedeListarSuscripciones()
    {
        $response = $this->getJson($this->baseUrl() . '/suscripciones');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            '*' => ['id', 'user_id', 'canal_id', 'created_at', 'updated_at'],
        ]);
    }

    public function testPuedeListarSuscripcionesPorUsuario()
    {
        $response = $this->getJson($this->baseUrl() . '/usuario/2/suscripciones');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            '*' => [
                'id',
                'nombre',
                'descripcion',
                'portada',
                'user' => [
                    'id',
                    'foto',
                ],
                'canal_online'
            ],
        ]);
    }

    public function testNoPuedeListarSuscripcionesUsuarioSinSuscripciones()
    {
        $response = $this->getJson($this->baseUrl() . '/usuario/10/suscripciones');

        $response->assertStatus(Response::HTTP_NOT_FOUND);
        $response->assertJson(['message' => 'Este usuario no tiene suscripciones.']);
    }

    public function testUsuarioEstaSuscrito()
    {
        Suscribe::create([
            'user_id' => 2,
            'canal_id' => 1,
        ]);

        $response = $this->postJson($this->baseUrl() . '/1/suscripcion', [
            'user_id' => 2,
        ]);

        $response->assertStatus(Response::HTTP_CONFLICT);
        $response->assertJson([
            'message' => 'Ya estás suscrito a este canal.',
        ]);
    }
    public function testUsuarioNoEstaSuscrito()
    {
        $response = $this->postJson($this->baseUrl() . '/1/suscripcion', [
            'user_id' => 10,
        ]);

        $response->assertStatus(Response::HTTP_CREATED);

        $response->assertJson([
            'data' => [
                'user_id' => 10,
                'canal_id' => '1',
            ],
        ]);
    }

    public function testValidacionFallaSinUserId()
    {
        $response = $this->postJson($this->baseUrl() . '/1/suscripcion', []);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['user_id']);
    }

    public function testValidacionFallaConUserIdInvalido()
    {
        $response = $this->postJson($this->baseUrl() . '/1/suscripcion', [
            'user_id' => 999,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['user_id']);
    }

    public function testUsuarioEstaSuscritoVerificacion()
    {
        Suscribe::create([
            'user_id' => 2,
            'canal_id' => 10,
        ]);

        $response = $this->getJson($this->baseUrl() . '/10/usuario/2/suscripcion?user_id=2');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson([
            'estado' => 'suscrito',
        ]);
    }

    public function testUsuarioNoEstaSuscritoVerificacion()
    {
        $response = $this->getJson($this->baseUrl() . '/10/usuario/5/suscripcion?user_id=5');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson([
            'estado' => 'desuscrito',
        ]);
    }

    public function testUsuarioPropietarioVerificacion()
    {
        $response = $this->getJson($this->baseUrl() . '/2/usuario/2/suscripcion?user_id=2');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson([
            'estado' => 'propietario',
        ]);
    }

    public function testPuedeContarSuscripciones()
    {

        $response = $this->getJson($this->baseUrl() . '/10/suscripciones/count');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson([
            'canal_id' => 10,
            'numero_suscripciones' => 2,
        ]);
    }

    public function testContarSuscripcionesSinSuscriptores()
    {
        $response = $this->getJson($this->baseUrl() . '/20/suscripciones/count');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson([
            'canal_id' => 20,
            'numero_suscripciones' => 0,
        ]);
    }

}
