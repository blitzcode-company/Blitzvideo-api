<?php

namespace Tests\Unit;

use App\Models\Playlist;
use App\Models\Video;
use App\Models\User;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Http\Response;
use Tests\TestCase;

class PlaylistControllerTest extends TestCase
{
    use WithoutMiddleware;

    public function testPuedeCrearPlaylist()
    {
        $user = User::first();

        $response = $this->postJson(env('BLITZVIDEO_BASE_URL') . 'playlists', [
            'nombre' => 'Nueva Playlist',
            'acceso' => true,
            'user_id' => $user->id,
        ]);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJson(['message' => 'Playlist creada exitosamente.']);
        $this->assertDatabaseHas('playlists', [
            'nombre' => 'Nueva Playlist',
            'user_id' => $user->id,
        ]);
    }

    public function testPuedeAgregarVariosVideosAPlaylist()
    {
        $playlist = Playlist::create([
            'nombre' => 'Playlist de Prueba',
            'acceso' => true,
            'user_id' => User::first()->id,
        ]);

        $videos = Video::take(3)->pluck('id')->toArray();

        $response = $this->postJson(env('BLITZVIDEO_BASE_URL') . "playlists/{$playlist->id}/videos", [
            'video_ids' => $videos,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['mensaje' => 'Videos agregados a la playlist exitosamente.']);
        foreach ($videos as $videoId) {
            $this->assertDatabaseHas('video_lista', [
                'playlist_id' => $playlist->id,
                'video_id' => $videoId,
            ]);
        }
    }

    public function testNoSePuedeAgregarVideosYaExistentesEnPlaylist()
    {
        $playlist = Playlist::create([
            'nombre' => 'Playlist Existente',
            'acceso' => true,
            'user_id' => User::first()->id,
        ]);

        $video = Video::first();
        $playlist->videos()->attach($video->id);

        $response = $this->postJson(env('BLITZVIDEO_BASE_URL') . "playlists/{$playlist->id}/videos", [
            'video_ids' => [$video->id],
        ]);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
        $response->assertJson(['mensaje' => 'Todos los videos ya están en la playlist.']);
    }

    public function testPuedeQuitarVideoDePlaylist()
    {
        $playlist = Playlist::create([
            'nombre' => 'Playlist de Eliminación',
            'acceso' => true,
            'user_id' => User::first()->id,
        ]);

        $video = Video::first();
        $playlist->videos()->attach($video->id);

        $response = $this->deleteJson(env('BLITZVIDEO_BASE_URL') . "playlists/{$playlist->id}/videos", [
            'video_id' => $video->id,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['message' => 'Video quitado de la playlist exitosamente.']);
        $this->assertDatabaseMissing('video_lista', [
            'playlist_id' => $playlist->id,
            'video_id' => $video->id,
        ]);
    }

    public function testPuedeBorrarPlaylist()
    {
        $playlist = Playlist::create([
            'nombre' => 'Playlist de Prueba para Borrar',
            'acceso' => true,
            'user_id' => User::first()->id,
        ]);

        $response = $this->deleteJson(env('BLITZVIDEO_BASE_URL') . "playlists/{$playlist->id}");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['message' => 'Playlist borrada exitosamente.']);
        $this->assertDatabaseHas('playlists', [
            'id' => $playlist->id,
            'deleted_at' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    public function testPuedeModificarPlaylist()
    {
    $playlist = Playlist::create([
        'nombre' => 'Nombre Original',
        'acceso' => true,
        'user_id' => User::first()->id,
    ]);

    $datosModificados = [
        'nombre' => 'Nombre de playlist modificado',  
        'acceso' => false,
    ];

    $response = $this->putJson(env('BLITZVIDEO_BASE_URL') . "playlists/{$playlist->id}", $datosModificados);

    $response->assertStatus(Response::HTTP_OK);
    $response->assertJson(['message' => 'Playlist modificada exitosamente.']);

    $this->assertDatabaseHas('playlists', [
        'id' => $playlist->id,
        'nombre' => 'Nombre de playlist modificado', 
        'acceso' => false, 
    ]);
    }
}
