<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Playlist;
use App\Models\Video;
use App\Models\User;

class PlaylistController extends Controller
{
    public function CrearPlaylist(Request $request)
    {
        $validatedData = $request->validate([
            'nombre' => 'required|string|max:255',
            'acceso' => 'required|boolean',
            'user_id' => 'required|exists:users,id',
            'video_id' => 'nullable|exists:videos,id',
        ]);
    
        $playlist = Playlist::create([
            'nombre' => $validatedData['nombre'],
            'acceso' => $validatedData['acceso'],
            'user_id' => $validatedData['user_id'],
        ]);

        if (!empty($validatedData['video_id'])) {
            $playlist->videos()->attach($validatedData['video_id']);
        }
    
        return response()->json([
            'message' => 'Playlist creada exitosamente.',
        ], 201);
    }
    


    public function AgregarVideosAPlaylist(Request $request, $playlistId)
    {
        $datosValidados = $this->validarIdsDeVideos($request);

        $playlist = $this->buscarPlaylistOEliminar($playlistId);

        $nuevosIdsDeVideos = $this->obtenerIdsDeVideosNuevos($playlist, $datosValidados['video_ids']);

        if (empty($nuevosIdsDeVideos)) {
            return $this->respuestaConError('Todos los videos ya están en la playlist.');
        }

        $playlist->videos()->attach($nuevosIdsDeVideos);

        return $this->respuestaConExito('Videos agregados a la playlist exitosamente.', $playlist);
    }

   
    public function ListarPlaylistsDeUsuario($userId)
    {
        $user = User::findOrFail($userId);
        $playlists = $user->playlists()->with('videos')->get();
        return response()->json([
            'playlists' => $playlists,
        ], 200);
    }


    public function QuitarVideoDePlaylist(Request $request, $playlistId)
    {
        $validatedData = $request->validate([
            'video_id' => 'required|exists:videos,id'
        ]);

        $playlist = Playlist::findOrFail($playlistId);
        $playlist->videos()->detach($validatedData['video_id']);
        return response()->json([
            'message' => 'Video quitado de la playlist exitosamente.',
            'playlist' => $playlist->load('videos'),
        ], 200);
    }

    public function BorrarPlaylist($playlistId)
    {
        $playlist = Playlist::findOrFail($playlistId);
        $playlist->videos()->detach();
        $playlist->delete();

        return response()->json([
            'message' => 'Playlist borrada exitosamente.',
        ], 200);
    }


    public function ModificarPlaylist(Request $request, $playlistId)
    {
        $validatedData = $request->validate([
            'nombre' => 'required|string|max:255',
            'acceso' => 'required|boolean',
        ]);

        $playlist = Playlist::findOrFail($playlistId);

        $playlist->nombre = $validatedData['nombre'];
        $playlist->acceso = $validatedData['acceso'];
        $playlist->save();

        return response()->json([
            'message' => 'Playlist modificada exitosamente.',
            'playlist' => $playlist
        ], 200);
    }

    public function ObtenerPlaylistConVideos($playlistId)
    {
        $playlist = Playlist::with('videos')->findOrFail($playlistId);
    
        return response()->json([
            'playlist' => $playlist,
            'videos' => $playlist->videos,
        ], 200);
    }

    protected function validarIdsDeVideos(Request $request)
    {
        return $request->validate([
            'video_ids' => 'required|array',
            'video_ids.*' => 'exists:videos,id'
        ]);
    }

    protected function buscarPlaylistOEliminar($playlistId)
    {
        return Playlist::findOrFail($playlistId);
    }

    protected function obtenerIdsDeVideosNuevos(Playlist $playlist, array $videoIds)
    {
        $idsDeVideosExistentes = $playlist->videos->pluck('id')->toArray();
        return array_diff($videoIds, $idsDeVideosExistentes);
    }

    protected function respuestaConExito($mensaje, Playlist $playlist)
    {
        return response()->json([
            'mensaje' => $mensaje,
            'playlist' => $playlist->load('videos')
        ], 200);
    }

    protected function respuestaConError($mensaje)
    {
        return response()->json([
            'mensaje' => $mensaje
        ], 400);
    }


}
