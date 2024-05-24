<?php

namespace App\Http\Controllers;

use App\Models\Visita;
use App\Models\Video;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function visita($userId, $videoId)
    {
        Visita::create([
            'user_id' => $userId,
            'video_id' => $videoId,
        ]);
        return response()->json(['message' => 'Visita registrada con éxito']);
    }
}