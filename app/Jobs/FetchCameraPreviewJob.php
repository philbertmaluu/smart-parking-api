<?php

namespace App\Jobs;

use App\Models\GateDevice;
use App\Services\ZKTecoCameraService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FetchCameraPreviewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $deviceId;

    public function __construct(int $deviceId)
    {
        $this->deviceId = $deviceId;
        $this->onQueue(config('queue.default', 'default'));
    }

    public function handle()
    {
        $device = GateDevice::find($this->deviceId);
        if (!$device) return;

        // Only attempt snapshots for devices that support snapshots and have IP
        if (!$device->supports_snapshot || !$device->ip_address) {
            return;
        }

        $service = new ZKTecoCameraService(
            $device->ip_address,
            $device->http_port,
            $device->rtsp_port,
            $device->username,
            $device->password
        );

        $result = $service->getSnapshot();

        if ($result['success'] && !empty($result['data'])) {
            // Save to public storage so frontend can access via storage URL if needed
            $filename = 'camera_previews/' . Str::slug($device->name ?: $device->device_id ?: 'camera') . '_' . $device->id . '_' . time() . '.jpg';
            Storage::disk('public')->put($filename, $result['data']);

            // Delete previous preview file if exists (best-effort)
            if ($device->last_preview_path && Storage::disk('public')->exists($device->last_preview_path)) {
                try { Storage::disk('public')->delete($device->last_preview_path); } catch (\Exception $e) {}
            }

            $device->last_preview_path = $filename;
            $device->last_preview_at = now();
            $device->is_online = true;
            $device->save();
        } else {
            // Mark device offline if snapshot failed
            $device->is_online = false;
            $device->last_preview_at = now();
            $device->save();
        }
    }
}
