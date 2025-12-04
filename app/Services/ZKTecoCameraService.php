<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * ZKTeco Camera Service
 * Handles authentication and streaming for ZKTeco IP cameras
 */
class ZKTecoCameraService
{
    private string $ip;
    private int $httpPort;
    private int $rtspPort;
    private string $username;
    private string $password;
    
    // ZKTeco specific endpoints (Updated from actual camera settings)
    private array $endpoints = [
        'web_interface' => '/',
        'main_stream' => '/ch01',
        'sub_stream' => '/ch01_sub',
        'third_stream' => '/ch01_third',
        'mjpeg' => '/cgi-bin/mjpeg',
        'snapshot' => '/cgi-bin/snapshot.cgi',
        'status' => '/cgi-bin/magicBox.cgi?action=getSystemInfo',
        'device_info' => '/cgi-bin/magicBox.cgi?action=getDeviceInfo',
    ];

    public function __construct(
        ?string $ip = null,
        ?int $httpPort = null,
        ?int $rtspPort = null,
        ?string $username = null,
        ?string $password = null
    ) {
        $this->ip = $ip ?: env('ZKTECO_IP', env('CAMERA_IP', '192.168.0.109'));
        $this->httpPort = $httpPort ?: (int) env('ZKTECO_HTTP_PORT', env('CAMERA_HTTP_PORT', 80));
        $this->rtspPort = $rtspPort ?: (int) env('ZKTECO_RTSP_PORT', env('CAMERA_RTSP_PORT', 554));
        $this->username = $username ?: env('ZKTECO_USERNAME', env('CAMERA_USERNAME', ''));
        $this->password = $password ?: env('ZKTECO_PASSWORD', env('CAMERA_PASSWORD', 'Password123!'));
    }

    /**
     * Get camera configuration
     */
    public function getConfig(): array
    {
        return [
            'ip' => $this->ip,
            'http_port' => $this->httpPort,
            'rtsp_port' => $this->rtspPort,
            'username' => $this->username,
            // Don't expose password in config
            'endpoints' => $this->endpoints,
        ];
    }

    /**
     * Test camera connection
     */
    public function testConnection(): array
    {
        try {
            $url = $this->getHttpUrl('/');
            
            $response = Http::timeout(10)
                ->withBasicAuth($this->username, $this->password)
                ->withHeaders([
                    'User-Agent' => 'SmartParkingApp/1.0',
                ])
                ->get($url);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Camera connection successful',
                    'status_code' => $response->status(),
                    'response_time' => $response->handlerStats()['total_time'] ?? null,
                ];
            }

            return [
                'success' => false,
                'message' => 'Camera responded with error: ' . $response->status(),
                'status_code' => $response->status(),
            ];

        } catch (Exception $e) {
            Log::error('ZKTeco camera connection test failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get camera device information
     */
    public function getDeviceInfo(): array
    {
        try {
            $url = $this->getHttpUrl($this->endpoints['device_info']);
            
            $response = Http::timeout(10)
                ->withBasicAuth($this->username, $this->password)
                ->get($url);

            if ($response->successful()) {
                $content = $response->body();
                
                // Parse device info from response
                return [
                    'success' => true,
                    'data' => $this->parseDeviceInfo($content),
                    'raw_response' => $content,
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to get device info: ' . $response->status(),
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Device info request failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get camera snapshot
     */
    public function getSnapshot(): array
    {
        try {
            $url = $this->getHttpUrl($this->endpoints['snapshot']);
            
            $response = Http::timeout(15)
                ->withBasicAuth($this->username, $this->password)
                ->get($url);

            if ($response->successful()) {
                $contentType = $response->header('Content-Type');
                
                if (str_contains($contentType, 'image/')) {
                    return [
                        'success' => true,
                        'data' => $response->body(),
                        'content_type' => $contentType,
                        'size' => strlen($response->body()),
                    ];
                }

                return [
                    'success' => false,
                    'message' => 'Unexpected content type: ' . $contentType,
                ];
            }

            return [
                'success' => false,
                'message' => 'Snapshot request failed: ' . $response->status(),
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Snapshot capture failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Generate RTSP URL
     */
    public function getRtspUrl(string $streamType = 'main'): string
    {
        $endpoint = $streamType === 'main' 
            ? $this->endpoints['main_stream'] 
            : $this->endpoints['sub_stream'];
        
        $encodedPassword = urlencode($this->password);
        
        return "rtsp://{$this->username}:{$encodedPassword}@{$this->ip}:{$this->rtspPort}{$endpoint}";
    }

    /**
     * Get HTTP URL for camera endpoints
     */
    public function getHttpUrl(string $endpoint = '/'): string
    {
        return "http://{$this->ip}:{$this->httpPort}{$endpoint}";
    }

    /**
     * Get web interface URL
     */
    public function getWebInterfaceUrl(): string
    {
        return $this->getHttpUrl($this->endpoints['web_interface']);
    }

    /**
     * Stream MJPEG from camera
     */
    public function streamMjpeg(): \Generator
    {
        $url = $this->getHttpUrl($this->endpoints['mjpeg']);
        
        // Use cURL for streaming
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) {
            yield $data;
            return strlen($data);
        });
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        curl_exec($ch);
        curl_close($ch);
    }

    /**
     * Validate camera credentials
     */
    public function validateCredentials(): array
    {
        // Test basic HTTP authentication
        $httpTest = $this->testConnection();
        
        if (!$httpTest['success']) {
            return $httpTest;
        }

        // Additional validation - try to get device info
        $deviceInfo = $this->getDeviceInfo();
        
        return [
            'success' => $deviceInfo['success'],
            'message' => $deviceInfo['success'] 
                ? 'Credentials validated successfully'
                : 'Authentication failed: ' . ($deviceInfo['message'] ?? 'Unknown error'),
            'http_status' => $httpTest,
            'device_info' => $deviceInfo,
        ];
    }

    /**
     * Parse device info response (ZKTeco format)
     */
    private function parseDeviceInfo(string $content): array
    {
        $info = [];
        
        // ZKTeco cameras often return info in key=value format
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line, 2);
                $info[trim($key)] = trim($value);
            }
        }
        
        return $info;
    }

    /**
     * Get authentication headers for HTTP requests
     */
    public function getAuthHeaders(): array
    {
        return [
            'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password),
            'User-Agent' => 'SmartParkingApp/1.0',
        ];
    }

    /**
     * Update camera credentials
     */
    public function updateCredentials(string $ip, string $username, string $password, ?int $httpPort = null, ?int $rtspPort = null): void
    {
        $this->ip = $ip;
        $this->username = $username;
        $this->password = $password;
        
        if ($httpPort !== null) {
            $this->httpPort = $httpPort;
        }
        
        if ($rtspPort !== null) {
            $this->rtspPort = $rtspPort;
        }
    }
}