<?php

namespace App\Http\Controllers;
use App\Events\MensajeChatStream;
use App\Models\Mensaje;
use App\Models\Stream;
use Illuminate\Http\Request;



class ChatStreamController extends Controller
{
    public function mandarMensaje(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'message' => 'required|string|max:500',
            'stream_id' => 'required|exists:streams,id',
        ]);
    
        $stream = Stream::findOrFail($request->input('stream_id'));
    
        if (!$stream->activo) {
            return response()->json(['error' => 'No se puede enviar mensajes a un stream inactivo'], 400);
        }
    
        $mensaje = Mensaje::create([
            'user_id' => $request->input('user_id'), 
            'stream_id' => $stream->id,
            'mensaje' => $request->input('message'),
            'bloqueado' => false,
        ]);
    
        $mensaje->load('user'); 
        
        event(new MensajeChatStream($mensaje));
        return response()->json(['status' => 'Mensaje enviado', 'mensaje' => $mensaje]);
    }
    
    public function obtenerMensajes($streamId)
    {
        $stream = Stream::findOrFail($streamId);
        $mensajes = Mensaje::where('stream_id', $streamId)
            ->where('bloqueado', false)
            ->with(['user' => function ($query) {
                $query->select('id', 'name', 'foto');
            }])
            ->orderBy('created_at', 'asc')
            ->get();
    
        $mensajes->each(function ($mensaje) {
            if ($mensaje->user->foto) {
                $mensaje->user->foto = $this->formatearFotoUsuario($mensaje->user->foto);
            }
        });
    
        return response()->json($mensajes);
    }
    
    private function formatearFotoUsuario($foto)
    {
        $host = str_replace('minio', env('BLITZVIDEO_HOST'), env('AWS_ENDPOINT')) . '/';
        $bucket = env('AWS_BUCKET') . '/';
    
        if (str_starts_with($foto, $host . $bucket) || filter_var($foto, FILTER_VALIDATE_URL)) {
            return $foto;
        }
    
        return $host . $bucket . $foto;
    }
}
