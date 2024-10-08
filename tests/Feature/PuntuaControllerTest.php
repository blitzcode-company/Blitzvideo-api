<?php

namespace Tests\Unit;

use App\Models\Puntua;
use App\Models\User;
use App\Models\Video;
use App\Models\PLaylist;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Http\Response;
use Tests\TestCase;

class PuntuaControllerTest extends TestCase
{
    use WithoutMiddleware;

    public function testPuedePuntuarVideo()
    {
        $usuario = User::first();
        $video = Video::first();
        $response = $this->postJson(env('BLITZVIDEO_BASE_URL') . "videos/{$video->id}/puntuacion", [
            'user_id' => $usuario->id,
            'valora' => 5,
        ]);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['message' => 'Puntuación agregada o actualizada exitosamente.']);
        $this->assertDatabaseHas('puntua', ['user_id' => $usuario->id, 'video_id' => $video->id, 'valora' => 5]);
    }


    public function testObtenerPuntuacionExistente()
    {
        $videoId = 1;
        $userId = 1;
        $valoraInicial = 4;
        $valoraActualizado = 5;
    
        Puntua::create([
            'user_id' => $userId,
            'video_id' => $videoId,
            'valora' => $valoraInicial,
        ]);
    
        Puntua::where('user_id', $userId)
              ->where('video_id', $videoId)
              ->update(['valora' => $valoraActualizado]);
    
        $response = $this->get(env('BLITZVIDEO_BASE_URL') . "videos/{$videoId}/puntuacion/{$userId}");
    
        $response->assertStatus(Response::HTTP_OK)
                 ->assertJson(['valora' => $valoraActualizado, 'message' => 'Puntuación actual obtenida exitosamente.' ]);
    
        $this->assertDatabaseHas('puntua', ['user_id' => $userId,'video_id' => $videoId,'valora' => $valoraActualizado]);
    }
    public function testObtenerPuntuacionNoExistente() {
        $videoId = 1;
        $userId = 1;

        Puntua::where('user_id', $userId)
            ->where('video_id', $videoId)
            ->forceDelete(); 

        $response = $this->get(env('BLITZVIDEO_BASE_URL') . "videos/{$videoId}/puntuacion/{$userId}");
        $response->assertStatus(Response::HTTP_NOT_FOUND);
        $response->assertJson(['message' => 'El usuario no ha puntuado este video.']);
    }

    public function testPuedeActualizarPuntuacionExistente()
{
    $usuario = User::first();
    $video = Video::first();
    
    $playlistDeFavoritos = Playlist::where('nombre', 'Favoritos')->where('user_id', $usuario->id)->first();

    if ($playlistDeFavoritos) {
        $playlistDeFavoritos->videos()->detach($video->id);
    }

    Puntua::create([
        'user_id' => $usuario->id,
        'video_id' => $video->id,
        'valora' => 4,
    ]);
    
    $response = $this->postJson(env('BLITZVIDEO_BASE_URL') . "videos/{$video->id}/puntuacion", [
        'user_id' => $usuario->id,
        'valora' => 5,
    ]);

    $response->assertStatus(Response::HTTP_OK);

    $response->assertJson(['message' => 'Puntuación agregada o actualizada exitosamente.']);

    $this->assertDatabaseHas('puntua', ['user_id' => $usuario->id, 'video_id' => $video->id, 'valora' => 5]);

    $this->assertDatabaseHas('video_lista', ['playlist_id' => $playlistDeFavoritos->id, 'video_id' => $video->id]);
}

    public function testPuedeEliminarPuntuacion(){
        $usuario = User::first();
        $video = Video::first();

        $puntua = Puntua::create([
            'user_id' => $usuario->id,
            'video_id' => $video->id,
            'valora' => 4,
        ]);

        $response = $this->deleteJson(env('BLITZVIDEO_BASE_URL') . "videos/{$video->id}/puntuacion", [
            'user_id' => $usuario->id,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['message' => 'Puntuación eliminada exitosamente.']);

        $this->assertSoftDeleted('puntua', ['user_id' => $usuario->id,'video_id' => $video->id,]);
    }

    public function testDevuelveErrorSiIntentaEliminarPuntuacionInexistente(){
        $response = $this->deleteJson(env('BLITZVIDEO_BASE_URL') . "videos/999/puntuacion", [
            'user_id' => 999,
        ]);
        $response->assertStatus(Response::HTTP_NOT_FOUND);
        $response->assertJson(['message' => 'Puntuación no encontrada.']);
    }

  

}
