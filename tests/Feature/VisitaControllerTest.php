<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Video;
use App\Models\Visita;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Http\Response;
use Tests\TestCase;

class VisitaControllerTest extends TestCase
{
    use WithoutMiddleware;

    public function testPuedeRegistrarVisita()
    {
        $usuario = User::skip(1)->take(1)->first();
        $video = Video::skip(1)->take(1)->first();

        if (!$usuario || !$video) {
            $this->markTestSkipped('No hay usuarios o videos válidos para probar.');
        }

        $response = $this->getJson(env('BLITZVIDEO_BASE_URL') . "usuario/{$usuario->id}/visita/{$video->id}");
        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJson(['message' => 'Visita registrada exitosamente.']);
    }

    public function testPuedeRegistrarVisitaInvitado()
    {
        $usuarioInvitado = User::where('name', 'Invitado')->first();
        $video = Video::inRandomOrder()->first();

        if (!$usuarioInvitado) {
            $this->markTestSkipped('No hay usuario invitado para probar.');
        }

        $response = $this->getJson(env('BLITZVIDEO_BASE_URL') . "invitado/visita/{$video->id}");
        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJson(['message' => 'Visita registrada exitosamente.']);
    }

    public function testNoPuedeRegistrarVisitaAntesDeUnMinuto()
    {
        $usuario = User::skip(1)->take(1)->first();
        $video = Video::skip(1)->take(1)->first();
        if (!$usuario || !$video) {
            $this->markTestSkipped('No hay usuarios o videos válidos para probar.');
        }
        Visita::create([
            'user_id' => $usuario->id,
            'video_id' => $video->id,
            'created_at' => Carbon::now()->subSeconds(30),
        ]);
        $response = $this->getJson(env('BLITZVIDEO_BASE_URL') . "usuario/{$usuario->id}/visita/{$video->id}");
        $response->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);
        $response->assertJson(['message' => 'Debe esperar un minuto antes de registrar una nueva visita.']);
    }
}
