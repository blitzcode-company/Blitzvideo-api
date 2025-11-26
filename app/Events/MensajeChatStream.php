<?php

namespace App\Events;
use App\Models\Mensaje;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;


class MensajeChatStream implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $mensaje;

    public function __construct(Mensaje $mensaje)
    {
        $this->mensaje = $mensaje;
        
    }

    public function broadcastOn()
    {
        return new PrivateChannel('stream.' . $this->mensaje->stream_id);
    }

    public function broadcastAs()
    {
        return 'chat-message';
    }

    public function broadcastWith()
    {
        \Log::info('🔊 Broadcasting evento chat-message', [
            'stream_id' => $this->mensaje->stream_id,
            'user' => $this->mensaje->user->name ?? null,
            'mensaje' => $this->mensaje->mensaje,
        ]);
    
        return [
            'id' => $this->mensaje->id,
            'user_id' => $this->mensaje->user_id,
            'user_name' => $this->mensaje->user->name,
            'user_photo' => $this->mensaje->user->foto ? $this->formatearFotoDelUsuario($this->mensaje->user->foto) : null,
            'message' => $this->mensaje->mensaje,
            'created_at' => $this->mensaje->created_at->toDateTimeString(),
        ];
    }

    private function formatearFotoDelUsuario($photo)
    {
        $host = str_replace('minio', env('BLITZVIDEO_HOST'), env('AWS_ENDPOINT')) . '/';
        $bucket = env('AWS_BUCKET') . '/';
        if (str_starts_with($photo, $host . $bucket) || filter_var($photo, FILTER_VALIDATE_URL)) {
            return $photo;
        }
        return $host . $bucket . $photo;
    }
}
