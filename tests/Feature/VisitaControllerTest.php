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
        $usuario = User::inRandomOrder()->first();
        $video = Video::inRandomOrder()->first();

        $response = $this->getJson(env('BLITZVIDEO_BASE_URL') . "usuario/{$usuario->id}/visita/{$video->id}");

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJson(['message' => 'Visita registrada exitosamente.']);
        $this->assertDatabaseHas('visitas', ['user_id' => $usuario->id, 'video_id' => $video->id]);
    }

    public function testNoPuedeRegistrarVisitaAntesDeUnMinuto()
    {
        $usuario = User::inRandomOrder()->first();
        $video = Video::inRandomOrder()->first();

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
