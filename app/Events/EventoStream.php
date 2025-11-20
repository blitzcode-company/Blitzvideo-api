<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EventoStream
{
    use Dispatchable, InteractsWithSockets, SerializesModels;


    public $streamId;
    public $payload;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($streamId, array $payload)
    {
        $this->streamId = $streamId;
        $this->payload = $payload;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
      public function broadcastOn()
    {
        return new PrivateChannel('stream.' . $this->streamId);
    }

    public function broadcastAs()
    {
        return 'stream-event';
    }

    public function broadcastWith()
    {
        return $this->payload;
    }
}
