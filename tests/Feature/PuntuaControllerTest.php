<?php
namespace Tests\Unit;

use App\Models\PLaylist;
use App\Models\Puntua;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Http\Response;
use Tests\TestCase;

class PuntuaControllerTest extends TestCase
{
    use WithoutMiddleware;

    public function testPuedePuntuarVideo()
    {
        $usuario  = User::first();
        $video    = Video::first();
        $response = $this->postJson(env('BLITZVIDEO_BASE_URL') . "videos/{$video->id}/puntuacion", [
            'user_id' => $usuario->id,
            'valora'  => 5,
        ]);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['message' => 'Puntuación agregada o actualizada exitosamente.']);
        $this->assertDatabaseHas('puntua', ['user_id' => $usuario->id, 'video_id' => $video->id, 'valora' => 5]);
    }

    public function testObtenerPuntuacionExistente()
    {
        $videoId           = 1;
        $userId            = 1;
        $valoraInicial     = 4;
        $valoraActualizado = 5;

        Puntua::create([
            'user_id'  => $userId,
            'video_id' => $videoId,
            'valora'   => $valoraInicial,
        ]);

        Puntua::where('user_id', $userId)
            ->where('video_id', $videoId)
            ->update(['valora' => $valoraActualizado]);

        $response = $this->get(env('BLITZVIDEO_BASE_URL') . "videos/{$videoId}/puntuacion/{$userId}");

        $response->assertStatus(Response::HTTP_OK)
            ->assertJson(['valora' => $valoraActualizado, 'message' => 'Puntuación actual obtenida exitosamente.']);

        $this->assertDatabaseHas('puntua', ['user_id' => $userId, 'video_id' => $videoId, 'valora' => $valoraActualizado]);
    }
    public function testObtenerPuntuacionNoExistente()
    {
        $videoId = 1;
        $userId  = 1;

        Puntua::where('user_id', $userId)
            ->where('video_id', $videoId)
            ->forceDelete();

        $response = $this->get(env('BLITZVIDEO_BASE_URL') . "videos/{$videoId}/puntuacion/{$userId}");
        $response->assertStatus(Response::HTTP_NOT_FOUND);
        $response->assertJson(['message' => 'El usuario no ha puntuado este video.']);
    }

    public function testPuedeActualizarPuntuacionExistente()
    {
        $usuario             = User::first();
        $video               = Video::first();
        $playlistDeFavoritos = Playlist::where('nombre', 'Favoritos')->where('user_id', $usuario->id)->first();

        if ($playlistDeFavoritos) {
            $playlistDeFavoritos->videos()->detach($video->id);
        }
        Puntua::create([
            'user_id'  => $usuario->id,
            'video_id' => $video->id,
            'valora'   => 4,
        ]);
        $response = $this->postJson(env('BLITZVIDEO_BASE_URL') . "videos/{$video->id}/puntuacion", [
            'user_id' => $usuario->id,
            'valora'  => 5,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['message' => 'Puntuación agregada o actualizada exitosamente.']);
        $this->assertDatabaseHas('puntua', ['user_id' => $usuario->id, 'video_id' => $video->id, 'valora' => 5]);
        $this->assertDatabaseHas('video_lista', ['playlist_id' => $playlistDeFavoritos->id, 'video_id' => $video->id]);
    }

    public function testPuedeEliminarPuntuacion()
    {
        $usuario = User::first();
        $video   = Video::first();
        $puntua  = Puntua::create([
            'user_id'  => $usuario->id,
            'video_id' => $video->id,
            'valora'   => 4,
        ]);
        $response = $this->deleteJson(env('BLITZVIDEO_BASE_URL') . "videos/{$video->id}/puntuacion", [
            'user_id' => $usuario->id,
        ]);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['message' => 'Puntuación eliminada exitosamente.']);

        $this->assertSoftDeleted('puntua', ['user_id' => $usuario->id, 'video_id' => $video->id]);
    }

    public function testDevuelveErrorSiIntentaEliminarPuntuacionInexistente()
    {
        $response = $this->deleteJson(env('BLITZVIDEO_BASE_URL') . "videos/999/puntuacion", [
            'user_id' => 999,
        ]);
        $response->assertStatus(Response::HTTP_NOT_FOUND);
        $response->assertJson(['message' => 'Puntuación no encontrada.']);
    }

    public function testListarPuntuacionesDevuelvePuntuaciones()
    {
        $videoId = 1;
        Puntua::create([
            'user_id'  => 1,
            'video_id' => $videoId,
            'valora'   => 4,
        ]);
        Puntua::create([
            'user_id'  => 2,
            'video_id' => $videoId,
            'valora'   => 5,
        ]);
        $response = $this->get(env('BLITZVIDEO_BASE_URL') . "videos/{$videoId}/puntuaciones");
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['video_id' => $videoId, 'valora' => 4]);
        $response->assertJsonFragment(['video_id' => $videoId, 'valora' => 5]);
    }

    public function testListarPuntuacionesDevuelveMensajeSiNoHayPuntuaciones()
    {
        $videoId = 999;
        Puntua::where('video_id', $videoId)->delete();
        $response = $this->get(env('BLITZVIDEO_BASE_URL') . "videos/{$videoId}/puntuaciones");
        $response->assertStatus(Response::HTTP_NOT_FOUND);
        $response->assertJson(['message' => 'No hay puntuaciones para este video.']);
    }

    public function testListarPuntuacionesPorUsuarioDevuelvePuntuaciones()
    {
        $userId = 1;
        Puntua::create([
            'user_id'  => $userId,
            'video_id' => 1,
            'valora'   => 4,
        ]);
        Puntua::create([
            'user_id'  => $userId,
            'video_id' => 2,
            'valora'   => 5,
        ]);
        $response = $this->get(env('BLITZVIDEO_BASE_URL') . "usuarios/{$userId}/puntuaciones");
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['user_id' => $userId, 'valora' => 4]);
        $response->assertJsonFragment(['user_id' => $userId, 'valora' => 5]);
    }

    public function testListarPuntuacionesPorUsuarioDevuelveMensajeSiNoHayPuntuaciones()
    {
        $userId = 999;
        Puntua::where('user_id', $userId)->delete();
        $response = $this->get(env('BLITZVIDEO_BASE_URL') . "usuarios/{$userId}/puntuaciones");
        $response->assertStatus(Response::HTTP_NOT_FOUND);
        $response->assertJson(['message' => 'No hay puntuaciones para este usuario.']);
    }

}
