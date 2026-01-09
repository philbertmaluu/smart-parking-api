<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\CameraDetectionLog;

class NewVehicleDetection implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $detection;

    public function __construct(CameraDetectionLog $detection)
    {
        $this->detection = $detection->load('gate');
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('gate.' . $this->detection->gate_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'new-detection';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->detection->id,
            'numberplate' => $this->detection->numberplate,
            'detection_timestamp' => $this->detection->detection_timestamp->toDateTimeString(),
            'gate_id' => $this->detection->gate_id,
            'gate_name' => $this->detection->gate?->name ?? 'Unknown Gate',
            'global_confidence' => $this->detection->global_confidence,
        ];
    }
}