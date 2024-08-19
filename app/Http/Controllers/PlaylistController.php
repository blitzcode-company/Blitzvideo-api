<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Playlist;
use App\Models\Video;
use App\Models\User;

class PlaylistController extends Controller
{
    public function CrearPlaylist(Request $request, $id)
    {
        $validatedData = $request->validate([
            'nombre' => 'required|string|max:255',
            'acceso' => 'required|boolean',
            'user_id' => 'required|exists:users,id',
        ]);
    
        $playlist = Playlist::create([
            'nombre' => $validatedData['nombre'],
            'acceso' => $validatedData['acceso'],
            'user_id' => $id,
        ]);
    
        return response()->json([
            'message' => 'Playlist creada exitosamente.',
        ], 201);
    }
    

    public function AgregarVideoAPlaylist(Request $request, $playlistId)
    {
        $validatedData = $request->validate([
            'video_id' => 'required|exists:videos,id'
        ]);

        $playlist = Playlist::findOrFail($playlistId);
        $playlist->videos()->attach($validatedData['video_id']);
        return response()->json([
            'message' => 'Video agregado a la playlist exitosamente.',
            'playlist' => $playlist->load('videos'),
        ], 200);
    }

    public function ListarPlaylistsDeUsuario($userId)
    {
        $user = User::findOrFail($userId);
        $playlists = $user->playlists()->with('videos')->get();
        return response()->json([
            'playlists' => $playlists,
        ], 200);
    }
}
