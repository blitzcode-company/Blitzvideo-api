<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;


class StreamStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $streamId,
        public int $canalId,
        public string $estado 
    ) {}

    public function broadcastOn(): array
    {
        return [
        new Channel("stream.{$this->streamId}"),  
        new PrivateChannel("canal.{$this->canalId}"), 
    ];
    }

    public function broadcastAs(): string
    {
        return 'stream.status.changed';
    }
}
