<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\BaseController;
use App\Http\Requests\GateDeviceRequest;
use App\Repositories\GateDeviceRepository;
use Illuminate\Http\Request;

class GateDeviceController extends BaseController
{
    protected $gateDeviceRepository;

    public function __construct(GateDeviceRepository $gateDeviceRepository)
    {
        $this->gateDeviceRepository = $gateDeviceRepository;
    }

    /**
     * Display a listing of gate devices
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search', '');
            $gateId = $request->get('gate_id');
            $deviceType = $request->get('device_type');

            if ($search) {
                $devices = $this->gateDeviceRepository->searchGateDevices($search, $perPage);
            } elseif ($gateId) {
                $devices = $this->gateDeviceRepository->getGateDevicesByGate($gateId);
                return $this->sendResponse($devices, 'Gate devices retrieved successfully');
            } elseif ($deviceType) {
                $devices = $this->gateDeviceRepository->getGateDevicesByType($deviceType);
                return $this->sendResponse($devices, 'Gate devices retrieved successfully');
            } else {
                $devices = $this->gateDeviceRepository->getAllGateDevicesPaginated($perPage);
            }

            return $this->sendResponse($devices, 'Gate devices retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving gate devices', $e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created gate device
     */
    public function store(GateDeviceRequest $request)
    {
        try {
            $data = $request->validated();
            
            // Check if encryption key is available before proceeding
            if (empty(config('app.key'))) {
                return $this->sendError(
                    'Application encryption key is not set. Please run: php artisan key:generate',
                    'Encryption key missing',
                    500
                );
            }
            
            // Password encryption is handled by the model's Attribute cast
            $device = $this->gateDeviceRepository->createGateDevice($data);

            return $this->sendResponse($device->load(['gate.station']), 'Gate device created successfully', 201);
        } catch (\RuntimeException $e) {
            return $this->sendError('Error creating gate device', $e->getMessage(), 500);
        } catch (\Exception $e) {
            return $this->sendError('Error creating gate device', $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified gate device
     */
    public function show($id)
    {
        try {
            $device = $this->gateDeviceRepository->getGateDeviceByIdWithRelations($id);

            if (!$device) {
                return $this->sendError('Gate device not found', [], 404);
            }

            // Password decryption is handled by the model's Attribute cast

            return $this->sendResponse($device, 'Gate device retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving gate device', $e->getMessage(), 500);
        }
    }

    /**
     * Update the specified gate device
     */
    public function update(GateDeviceRequest $request, $id)
    {
        try {
            $data = $request->validated();
            
            // Remove password from update if not provided (to keep existing password)
            if (empty($data['password'])) {
                unset($data['password']);
            }
            // Password encryption is handled by the model's Attribute cast

            $device = $this->gateDeviceRepository->updateGateDevice($id, $data);

            if (!$device) {
                return $this->sendError('Gate device not found', [], 404);
            }

            return $this->sendResponse($device, 'Gate device updated successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error updating gate device', $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified gate device
     */
    public function destroy($id)
    {
        try {
            $deleted = $this->gateDeviceRepository->deleteGateDevice($id);

            if (!$deleted) {
                return $this->sendError('Gate device not found', [], 404);
            }

            return $this->sendResponse([], 'Gate device deleted successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error deleting gate device', $e->getMessage(), 500);
        }
    }

    /**
     * Get gate devices by gate
     */
    public function getByGate($gateId)
    {
        try {
            $devices = $this->gateDeviceRepository->getGateDevicesByGate($gateId);
            return $this->sendResponse($devices, 'Gate devices retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving gate devices', $e->getMessage(), 500);
        }
    }

    /**
     * Get gate devices by type
     */
    public function getByType($type)
    {
        try {
            $devices = $this->gateDeviceRepository->getGateDevicesByType($type);
            return $this->sendResponse($devices, 'Gate devices retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving gate devices', $e->getMessage(), 500);
        }
    }

    /**
     * Get active gate devices
     */
    public function getActiveList()
    {
        try {
            $devices = $this->gateDeviceRepository->getActiveGateDevices();
            return $this->sendResponse($devices, 'Active gate devices retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving active gate devices', $e->getMessage(), 500);
        }
    }

    /**
     * Test connection to a gate device
     */
    public function testConnection($id)
    {
        try {
            $device = $this->gateDeviceRepository->getGateDeviceByIdWithRelations($id);

            if (!$device) {
                return $this->sendError('Gate device not found', [], 404);
            }

            // Password decryption is handled by the model's Attribute cast
            $password = $device->password;

            // Simple connection test (ping the IP)
            $result = @fsockopen($device->ip_address, $device->http_port, $errno, $errstr, 2);
            
            if ($result) {
                fclose($result);
                $device->update([
                    'is_online' => true,
                    'last_connected_at' => now(),
                    'last_ping_at' => now(),
                ]);
                
                return $this->sendResponse([
                    'connected' => true,
                    'message' => 'Connection successful',
                    'device' => $device->fresh(['gate.station']),
                ], 'Connection test successful');
            } else {
                $device->update([
                    'is_online' => false,
                    'last_ping_at' => now(),
                ]);
                
                return $this->sendResponse([
                    'connected' => false,
                    'message' => "Connection failed: {$errstr}",
                    'device' => $device->fresh(['gate.station']),
                ], 'Connection test failed', 200);
            }
        } catch (\Exception $e) {
            return $this->sendError('Error testing connection', $e->getMessage(), 500);
        }
    }
}
