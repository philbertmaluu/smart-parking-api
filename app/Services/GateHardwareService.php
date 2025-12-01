<?php

namespace App\Services;

use App\Models\Gate;
use App\Models\GateDevice;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * GateHardwareService
 *
 * This service is responsible for taking the logical gate actions that are
 * already cached by GateControlService (open/close/deny) and forwarding them
 * to the physical boom-gate devices configured in the database.
 *
 * It is intentionally **decoupled** from the core gate control logic so that
 * existing behavior is not changed – it only *consumes* the cached actions.
 */
class GateHardwareService
{
    /**
     * Process cached gate action for a specific gate and forward it to hardware.
     *
     * This will:
     * - Read the last gate action from cache (including emergency overrides)
     * - Compare against the last processed action (to avoid duplicates)
     * - If new, send the corresponding command to the configured boom_gate device
     *
     * @param int $gateId
     * @return array{success: bool, message: string, action?: string|null}
     */
    public function processCachedActionForGateId(int $gateId): array
    {
        $gate = Gate::with('devices')->find($gateId);

        if (!$gate) {
            return [
                'success' => false,
                'message' => "Gate {$gateId} not found",
            ];
        }

        // Prefer emergency action if present
        $emergencyKey = "gate_emergency_{$gateId}";
        $controlKey = "gate_control_{$gateId}";

        $emergencyAction = Cache::get($emergencyKey);
        $normalAction = Cache::get($controlKey);

        $actionPayload = $emergencyAction ?? $normalAction;

        if (!$actionPayload || !is_array($actionPayload) || empty($actionPayload['action'])) {
            return [
                'success' => true,
                'message' => "No cached action for gate {$gateId}",
                'action' => null,
            ];
        }

        $action = $actionPayload['action'];
        $timestamp = $actionPayload['timestamp'] ?? now();

        // Prevent sending the same action repeatedly:
        $lastProcessedKey = "gate_hardware_last_processed_{$gateId}";
        $lastProcessed = Cache::get($lastProcessedKey);

        $currentId = "{$action}:{$timestamp}";

        if ($lastProcessed === $currentId) {
            return [
                'success' => true,
                'message' => "Action {$currentId} already processed for gate {$gateId}",
                'action' => $action,
            ];
        }

        $sendResult = $this->sendActionToGate($gate, $action);

        if ($sendResult['success']) {
            Cache::put($lastProcessedKey, $currentId, 60);
        }

        return $sendResult + ['action' => $action];
    }

    /**
     * Send a logical action (open/close/deny) to the configured boom gate device.
     *
     * @param Gate $gate
     * @param string $action
     * @return array{success: bool, message: string}
     */
    public function sendActionToGate(Gate $gate, string $action): array
    {
        // Find active boom_gate device for this gate
        /** @var GateDevice|null $device */
        $device = $gate->devices()
            ->where('device_type', 'boom_gate')
            ->where('status', 'active')
            ->first();

        if (!$device) {
            Log::warning('GateHardwareService: No active boom_gate device configured for gate', [
                'gate_id' => $gate->id,
                'gate_name' => $gate->name,
            ]);

            return [
                'success' => false,
                'message' => 'No active boom gate device configured for this gate',
            ];
        }

        $scheme = $device->use_https ? 'https' : 'http';
        $host = "{$device->ip_address}:{$device->http_port}";

        // Allow the exact paths to be configured via env, with sensible defaults.
        // These should match whatever API your physical controller exposes.
        $openPath = config('gate_hardware.open_path', env('BOOM_GATE_OPEN_PATH', '/open'));
        $closePath = config('gate_hardware.close_path', env('BOOM_GATE_CLOSE_PATH', '/close'));
        $denyPath = config('gate_hardware.deny_path', env('BOOM_GATE_DENY_PATH', '/deny'));

        switch ($action) {
            case 'open':
                $path = $openPath;
                break;
            case 'close':
                $path = $closePath;
                break;
            case 'deny':
            default:
                // "deny" usually means keep closed – if you have a dedicated endpoint you can configure it
                $path = $denyPath;
                break;
        }

        $url = "{$scheme}://{$host}{$path}";

        try {
            Log::info('GateHardwareService: Sending action to boom gate', [
                'gate_id' => $gate->id,
                'gate_name' => $gate->name,
                'device_id' => $device->id,
                'device_name' => $device->name,
                'action' => $action,
                'url' => $url,
            ]);

            $timeout = max(1, (int) $device->connection_timeout);

            $response = Http::timeout($timeout)
                ->withBasicAuth($device->username, $device->password)
                ->post($url, [
                    'gate_id' => $gate->id,
                    'action' => $action,
                ]);

            if (!$response->successful()) {
                Log::error('GateHardwareService: Boom gate controller returned error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                $device->update([
                    'is_online' => false,
                    'last_ping_at' => now(),
                ]);

                return [
                    'success' => false,
                    'message' => "Boom gate controller error: {$response->status()}",
                ];
            }

            $device->update([
                'is_online' => true,
                'last_connected_at' => now(),
                'last_ping_at' => now(),
            ]);

            return [
                'success' => true,
                'message' => 'Gate action sent to boom gate successfully',
            ];
        } catch (\Exception $e) {
            Log::error('GateHardwareService: Exception sending action to boom gate', [
                'gate_id' => $gate->id,
                'gate_name' => $gate->name,
                'device_id' => $device->id,
                'device_name' => $device->name,
                'action' => $action,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            $device->update([
                'is_online' => false,
                'last_ping_at' => now(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send action to boom gate: ' . $e->getMessage(),
            ];
        }
    }
}


