<?php // app/Events/ImageUpdated.php
namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ImageUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public $imageData) {}

    public function broadcastOn()
    {
        // This is the "Channel" name your Expo app will listen to
        return new Channel('carousel-channel');
    }

    public function broadcastAs()
    {
        // This is the "Event" name
        return 'image.updated';
    }
}
