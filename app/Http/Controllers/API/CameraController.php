<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\BaseController;
use App\Services\ZKTecoCameraService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Process;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CameraController extends BaseController
{
    private $rtspUrl = 'rtsp://192.168.0.109:554/stream';
    
    // Working credentials for this camera
    private $rtspUsername = 'admin';
    private $rtspPassword = 'Password123!';
    
    // URL encoding for special characters in password
    private function getUrlEncodedPassword() {
        return urlencode($this->rtspPassword);
    }
    
    /**
     * Get RTSP URL with authentication
     */
    private function getAuthenticatedRtspUrl()
    {
        $parsedUrl = parse_url($this->rtspUrl);
        $host = $parsedUrl['host'];
        $port = $parsedUrl['port'] ?? 554;
        $path = $parsedUrl['path'] ?? '/';
        
        return "rtsp://{$this->rtspUsername}:{$this->rtspPassword}@{$host}:{$port}{$path}";
    }
    
    /**
     * Display the camera stream page
     */
    public function index()
    {
        return view('camera-stream');
    }

    /**
     * Get camera snapshot with enhanced quality and error handling
     */
    public function snapshot(Request $request, $cameraId = 'camera1')
    {
        try {
            // Use unique temporary file with microseconds for uniqueness
            $tempFile = storage_path('app/temp/snapshot_' . $cameraId . '_' . microtime(true) . '.jpg');
            
            // Ensure temp directory exists
            if (!is_dir(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0755, true);
            }

            // Enhanced FFmpeg command for better quality snapshots
            $command = [
                'ffmpeg',
                '-y', // Overwrite output files
                '-rtsp_transport', 'tcp', // Use TCP for more reliable connection
                '-rtsp_flags', 'prefer_tcp', // Prefer TCP transport
                '-fflags', '+genpts', // Generate presentation timestamps
                '-avoid_negative_ts', 'make_zero', // Handle timing issues
                '-i', $this->getAuthenticatedRtspUrl(),
                '-vframes', '1', // Capture only 1 frame
                '-f', 'image2',
                '-q:v', '1', // Highest quality (1-31, lower is better)
                '-pix_fmt', 'yuvj420p', // Ensure proper pixel format
                '-vf', 'scale=trunc(iw/2)*2:trunc(ih/2)*2', // Ensure even dimensions
                $tempFile
            ];

            // Execute FFmpeg command with longer timeout for quality
            $result = Process::timeout(15)->run($command);
            
            if ($result->successful() && file_exists($tempFile) && filesize($tempFile) > 1000) {
                // Verify image is valid JPEG
                $imageInfo = @getimagesize($tempFile);
                if ($imageInfo && $imageInfo[2] === IMAGETYPE_JPEG) {
                    $response = response()->file($tempFile, [
                        'Content-Type' => 'image/jpeg',
                        'Cache-Control' => 'no-cache, no-store, must-revalidate',
                        'Pragma' => 'no-cache',
                        'Expires' => '0',
                        'X-Frame-Time' => microtime(true)
                    ]);

                    // Clean up temp file after sending
                    register_shutdown_function(function() use ($tempFile) {
                        if (file_exists($tempFile)) {
                            unlink($tempFile);
                        }
                    });

                    return $response;
                }
            }
            
            // Clean up failed temp file
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
            
            // Return a placeholder image if FFmpeg fails
            return $this->generatePlaceholderImage('Snapshot unavailable - Camera may be busy');
            
        } catch (\Exception $e) {
            return $this->generatePlaceholderImage('Error: ' . $e->getMessage());
        }
    }

    /**
     * Stream MJPEG from RTSP with enhanced quality and stability
     */
    public function mjpegStream(Request $request, $cameraId = 'camera1')
    {
        $response = new StreamedResponse();
        
        $response->headers->set('Content-Type', 'multipart/x-mixed-replace; boundary=mjpegframe');
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        $response->headers->set('Connection', 'close');

        $response->setCallback(function() use ($cameraId) {
            set_time_limit(0); // Remove PHP execution time limit
            ignore_user_abort(false); // Stop if client disconnects
            
            try {
                $frameCount = 0;
                $maxFrames = 1800; // 30 minutes at 1 fps
                $consecutiveFailures = 0;
                $maxConsecutiveFailures = 5;
                
                while ($frameCount < $maxFrames && !connection_aborted()) {
                    // Use unique filename with microsecond precision
                    $tempFile = storage_path('app/temp/mjpeg_' . $cameraId . '_' . microtime(true) . '.jpg');
                    
                    // Ensure temp directory exists
                    if (!is_dir(storage_path('app/temp'))) {
                        mkdir(storage_path('app/temp'), 0755, true);
                    }
                    
                    // Enhanced FFmpeg command for stable frame capture
                    $command = [
                        'ffmpeg',
                        '-y', // Overwrite output files
                        '-rtsp_transport', 'tcp', // TCP for reliability
                        '-rtsp_flags', 'prefer_tcp',
                        '-fflags', '+genpts', // Generate timestamps
                        '-avoid_negative_ts', 'make_zero',
                        '-analyzeduration', '1000000', // 1 second analysis
                        '-probesize', '5000000', // 5MB probe size
                        '-i', $this->getAuthenticatedRtspUrl(),
                        '-vframes', '1', // Single frame
                        '-f', 'image2',
                        '-q:v', '2', // Good quality
                        '-pix_fmt', 'yuvj420p', // Proper pixel format
                        '-vf', 'scale=trunc(iw/2)*2:trunc(ih/2)*2', // Even dimensions
                        $tempFile
                    ];
                    
                    $result = Process::timeout(8)->run($command);
                    
                    if ($result->successful() && file_exists($tempFile) && filesize($tempFile) > 1000) {
                        // Verify it's a valid JPEG
                        $imageInfo = @getimagesize($tempFile);
                        if ($imageInfo && $imageInfo[2] === IMAGETYPE_JPEG) {
                            $frameData = file_get_contents($tempFile);
                            
                            // Output frame in proper MJPEG format
                            echo "\r\n--mjpegframe\r\n";
                            echo "Content-Type: image/jpeg\r\n";
                            echo "Content-Length: " . strlen($frameData) . "\r\n\r\n";
                            echo $frameData;
                            
                            // Flush output immediately
                            if (ob_get_level()) {
                                ob_flush();
                            }
                            flush();
                            
                            $consecutiveFailures = 0; // Reset failure counter
                            $frameCount++;
                        } else {
                            $consecutiveFailures++;
                        }
                        
                        // Clean up temp file
                        unlink($tempFile);
                    } else {
                        $consecutiveFailures++;
                        
                        // Clean up failed temp file
                        if (file_exists($tempFile)) {
                            unlink($tempFile);
                        }
                    }
                    
                    // If too many consecutive failures, break
                    if ($consecutiveFailures >= $maxConsecutiveFailures) {
                        echo "\r\n--mjpegframe\r\n";
                        echo "Content-Type: text/plain\r\n\r\n";
                        echo "Stream interrupted - too many capture failures\r\n";
                        break;
                    }
                    
                    // Check if client disconnected
                    if (connection_aborted()) {
                        break;
                    }
                    
                    // Wait before next frame (adjust for desired FPS)
                    usleep(800000); // 0.8 seconds = ~1.25 FPS
                }
                
                // Send end boundary
                echo "\r\n--mjpegframe--\r\n";
                
            } catch (\Exception $e) {
                // Send error frame
                echo "\r\n--mjpegframe\r\n";
                echo "Content-Type: text/plain\r\n\r\n";
                echo "MJPEG Stream Error: " . $e->getMessage() . "\r\n";
                echo "\r\n--mjpegframe--\r\n";
            } finally {
                // Clean up any remaining temp files
                $tempPattern = storage_path('app/temp/mjpeg_' . $cameraId . '_*.jpg');
                foreach (glob($tempPattern) as $file) {
                    if (file_exists($file)) {
                        unlink($file);
                    }
                }
            }
        });

        return $response;
    }

    /**
     * Generate HLS stream from RTSP
     */
    public function hlsStream(Request $request, $cameraId = 'camera1')
    {
        try {
            $hlsDir = storage_path('app/public/hls/' . $cameraId);
            $playlistFile = $hlsDir . '/playlist.m3u8';

            // Ensure HLS directory exists
            if (!is_dir($hlsDir)) {
                mkdir($hlsDir, 0755, true);
            }

            // Check if HLS process is already running
            $pidFile = $hlsDir . '/ffmpeg.pid';
            if (file_exists($pidFile)) {
                $pid = file_get_contents($pidFile);
                if ($this->isProcessRunning($pid)) {
                    // Process is running, return existing playlist
                    if (file_exists($playlistFile)) {
                        return response()->file($playlistFile, [
                            'Content-Type' => 'application/x-mpegURL',
                            'Cache-Control' => 'no-cache'
                        ]);
                    }
                }
            }

            // Start new HLS process with optimized settings for fast startup
            $command = [
                'ffmpeg',
                '-y',
                '-rtsp_transport', 'tcp',
                '-i', $this->getAuthenticatedRtspUrl(),
                '-c:v', 'libx264',
                '-preset', 'veryfast', // Faster encoding
                '-tune', 'zerolatency',
                '-g', '30', // Keyframe every 30 frames
                '-sc_threshold', '0', // Disable scene change detection
                '-c:a', 'aac',
                '-b:a', '64k', // Lower audio bitrate
                '-f', 'hls',
                '-hls_time', '1', // 1-second segments (faster startup)
                '-hls_list_size', '2', // Keep only 2 segments
                '-hls_flags', 'delete_segments+independent_segments',
                '-hls_start_number_source', 'generic',
                '-hls_allow_cache', '0',
                $playlistFile
            ];

            // Start FFmpeg process in background
            $process = Process::start($command);
            file_put_contents($pidFile, $process->id());

            // Wait a moment for the playlist to be created
            $attempts = 0;
            while (!file_exists($playlistFile) && $attempts < 50) {
                usleep(100000); // 100ms
                $attempts++;
            }

            if (file_exists($playlistFile)) {
                return response()->file($playlistFile, [
                    'Content-Type' => 'application/x-mpegURL',
                    'Cache-Control' => 'no-cache'
                ]);
            } else {
                return response()->json(['error' => 'Failed to generate HLS stream'], 500);
            }

        } catch (\Exception $e) {
            return response()->json(['error' => 'HLS stream error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Serve HLS segments
     */
    public function hlsSegment(Request $request, $cameraId, $segment)
    {
        $segmentPath = storage_path('app/public/hls/' . $cameraId . '/' . $segment);
        
        if (file_exists($segmentPath)) {
            return response()->file($segmentPath, [
                'Content-Type' => 'video/MP2T',
                'Cache-Control' => 'no-cache'
            ]);
        }
        
        return response()->json(['error' => 'Segment not found'], 404);
    }

    /**
     * Stop HLS stream
     */
    public function stopHlsStream(Request $request, $cameraId = 'camera1')
    {
        $hlsDir = storage_path('app/public/hls/' . $cameraId);
        $pidFile = $hlsDir . '/ffmpeg.pid';

        if (file_exists($pidFile)) {
            $pid = file_get_contents($pidFile);
            
            // Kill the FFmpeg process
            if ($this->isProcessRunning($pid)) {
                Process::run(['kill', $pid]);
            }
            
            // Clean up
            unlink($pidFile);
            
            // Remove HLS files
            if (is_dir($hlsDir)) {
                $files = glob($hlsDir . '/*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }
        }

        return $this->sendResponse(null, 'HLS stream stopped');
    }

    /**
     * Test RTSP connection with multiple credential and path combinations
     */
    public function testConnection(Request $request)
    {
        // Get credentials from request or use defaults
        $username = $request->input('username', $this->rtspUsername);
        $password = $request->input('password', $this->rtspPassword);
        
        // Common RTSP paths for IP cameras
        $commonPaths = [
            '/',
            '/stream',
            '/stream1',
            '/stream0',
            '/live',
            '/live1',
            '/live.sdp',
            '/cam/realmonitor?channel=1&subtype=0',
            '/axis-media/media.amp',
            '/video.mjpg',
            '/mjpeg/1/video.mjpg',
            '/streaming/channels/101',
            '/streaming/channels/1/httppreview',
        ];
        
        // Common credential combinations
        $credentialCombos = [
            [$username, $password], // User provided
            ['admin', 'admin'],
            ['admin', 'password'],
            ['admin', '123456'],
            ['admin', ''],
            ['user', 'user'],
            ['root', 'pass'],
            ['', ''],
        ];
        
        $parsedUrl = parse_url($this->rtspUrl);
        $host = $parsedUrl['host'];
        $port = $parsedUrl['port'] ?? 554;
        
        foreach ($credentialCombos as [$testUser, $testPass]) {
            foreach ($commonPaths as $path) {
                $testUrl = "rtsp://";
                if ($testUser || $testPass) {
                    $testUrl .= "{$testUser}:{$testPass}@";
                }
                $testUrl .= "{$host}:{$port}{$path}";
                
                try {
                    $command = [
                        'ffprobe',
                        '-rtsp_transport', 'tcp',
                        '-v', 'error',
                        '-print_format', 'json',
                        '-show_format',
                        '-show_streams',
                        $testUrl
                    ];

                    $result = Process::timeout(5)->run($command);
                    
                    if ($result->successful() && !empty(trim($result->output()))) {
                        $streamInfo = json_decode($result->output(), true);
                        
                        // Update the working credentials
                        $this->rtspUsername = $testUser;
                        $this->rtspPassword = $testPass;
                        $this->rtspUrl = "rtsp://{$host}:{$port}{$path}";
                        
                        return $this->sendResponse([
                            'status' => 'connected',
                            'url' => $testUrl,
                            'working_credentials' => [
                                'username' => $testUser,
                                'password' => $testPass ? str_repeat('*', strlen($testPass)) : '(empty)',
                                'path' => $path
                            ],
                            'stream_info' => $streamInfo
                        ], 'RTSP connection successful');
                    }
                } catch (\Exception $e) {
                    // Continue to next combination
                    continue;
                }
            }
        }
        
        // If we get here, none worked
        return $this->sendError('RTSP connection failed', [
            'error' => 'No valid credential/path combination found',
            'tested_combinations' => count($credentialCombos) * count($commonPaths),
            'suggestions' => [
                'Check if camera requires different credentials',
                'Verify camera supports RTSP',
                'Try accessing camera web interface first',
                'Check camera documentation for correct RTSP path'
            ]
        ], 400);
    }

    /**
     * Generate placeholder image
     */
    private function generatePlaceholderImage($text)
    {
        // Create a simple placeholder image
        $width = 640;
        $height = 480;
        $image = imagecreate($width, $height);
        
        // Colors
        $bgColor = imagecolorallocate($image, 200, 200, 200);
        $textColor = imagecolorallocate($image, 100, 100, 100);
        
        // Add text
        $font = 5;
        $textWidth = imagefontwidth($font) * strlen($text);
        $textHeight = imagefontheight($font);
        $x = ($width - $textWidth) / 2;
        $y = ($height - $textHeight) / 2;
        
        imagestring($image, $font, $x, $y, $text, $textColor);
        
        // Output as JPEG
        ob_start();
        imagejpeg($image, null, 70);
        $imageData = ob_get_contents();
        ob_end_clean();
        
        imagedestroy($image);
        
        return response($imageData, 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'no-cache, no-store, must-revalidate'
        ]);
    }

    /**
     * Check if process is running
     */
    private function isProcessRunning($pid)
    {
        try {
            $result = Process::run(['ps', '-p', $pid]);
            return $result->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get camera status and info
     */
    public function getStatus(Request $request, $cameraId = 'camera1')
    {
        $hlsDir = storage_path('app/public/hls/' . $cameraId);
        $pidFile = $hlsDir . '/ffmpeg.pid';
        
        $status = [
            'camera_id' => $cameraId,
            'rtsp_url' => $this->rtspUrl,
            'hls_active' => false,
            'hls_playlist' => null,
            'last_snapshot' => null,
            'uptime' => null
        ];

        // Check HLS status
        if (file_exists($pidFile)) {
            $pid = file_get_contents($pidFile);
            if ($this->isProcessRunning($pid)) {
                $status['hls_active'] = true;
                $status['hls_playlist'] = '/api/toll-v1/stream/hls/' . $cameraId;
            }
        }

        // Check for recent snapshots
        $tempDir = storage_path('app/temp');
        if (is_dir($tempDir)) {
            $snapshots = glob($tempDir . '/snapshot_' . $cameraId . '_*.jpg');
            if (!empty($snapshots)) {
                $latest = max($snapshots);
                $status['last_snapshot'] = filemtime($latest);
            }
        }

        return $this->sendResponse($status, 'Camera status retrieved');
    }

    /**
     * Optimized stream endpoint with frame validation and quality control
     */
    public function optimizedStream(Request $request, $cameraId = 'camera1')
    {
        $response = new StreamedResponse();
        
        $response->headers->set('Content-Type', 'multipart/x-mixed-replace; boundary=optframe');
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        $response->headers->set('Connection', 'close');
        $response->headers->set('Access-Control-Allow-Origin', '*');

        $response->setCallback(function() use ($cameraId) {
            set_time_limit(0);
            ignore_user_abort(false);
            
            try {
                $frameCount = 0;
                $maxFrames = 3600; // 1 hour at 1 fps
                $consecutiveFailures = 0;
                $maxConsecutiveFailures = 3;
                $lastGoodFrame = null;
                $frameCache = [];
                
                while ($frameCount < $maxFrames && !connection_aborted()) {
                    $tempFile = storage_path('app/temp/opt_' . $cameraId . '_' . uniqid() . '.jpg');
                    
                    // Ensure temp directory exists
                    if (!is_dir(storage_path('app/temp'))) {
                        mkdir(storage_path('app/temp'), 0755, true);
                    }
                    
                    // Optimized FFmpeg command
                    $command = [
                        'ffmpeg',
                        '-y',
                        '-rtsp_transport', 'tcp',
                        '-rtsp_flags', 'prefer_tcp',
                        '-fflags', '+genpts+discardcorrupt',
                        '-flags', '+low_delay',
                        '-avoid_negative_ts', 'make_zero',
                        '-analyzeduration', '500000', // 0.5 seconds
                        '-probesize', '2000000', // 2MB
                        '-i', $this->getAuthenticatedRtspUrl(),
                        '-vframes', '1',
                        '-f', 'image2',
                        '-q:v', '3', // Balanced quality/speed
                        '-pix_fmt', 'yuvj420p',
                        '-vf', 'scale=trunc(iw/2)*2:trunc(ih/2)*2,eq=brightness=0.02:contrast=1.1', // Slight enhancement
                        $tempFile
                    ];
                    
                    $result = Process::timeout(6)->run($command);
                    
                    $frameValid = false;
                    
                    if ($result->successful() && file_exists($tempFile) && filesize($tempFile) > 2000) {
                        // Validate frame quality
                        $imageInfo = @getimagesize($tempFile);
                        if ($imageInfo && $imageInfo[2] === IMAGETYPE_JPEG && $imageInfo[0] > 100 && $imageInfo[1] > 100) {
                            // Check if image is not completely black/grey
                            $frameData = file_get_contents($tempFile);
                            
                            // Simple quality check - ensure frame has some variety
                            if (strlen($frameData) > 5000) {
                                // Additional check: ensure it's not a corrupted frame
                                $img = @imagecreatefromstring($frameData);
                                if ($img !== false) {
                                    // Get some pixels to check for variation
                                    $width = imagesx($img);
                                    $height = imagesy($img);
                                    
                                    $colors = [];
                                    for ($i = 0; $i < 10; $i++) {
                                        $x = rand(0, $width - 1);
                                        $y = rand(0, $height - 1);
                                        $colors[] = imagecolorat($img, $x, $y);
                                    }
                                    
                                    // Check if there's color variation (not all same color)
                                    $uniqueColors = array_unique($colors);
                                    if (count($uniqueColors) > 2) {
                                        $frameValid = true;
                                        $lastGoodFrame = $frameData;
                                        $consecutiveFailures = 0;
                                        
                                        // Cache recent good frames
                                        $frameCache[] = $frameData;
                                        if (count($frameCache) > 5) {
                                            array_shift($frameCache);
                                        }
                                    }
                                    
                                    imagedestroy($img);
                                }
                            }
                        }
                    }
                    
                    // Clean up temp file
                    if (file_exists($tempFile)) {
                        unlink($tempFile);
                    }
                    
                    if ($frameValid) {
                        // Send good frame
                        echo "\r\n--optframe\r\n";
                        echo "Content-Type: image/jpeg\r\n";
                        echo "Content-Length: " . strlen($frameData) . "\r\n";
                        echo "X-Frame-Quality: good\r\n\r\n";
                        echo $frameData;
                        
                        $frameCount++;
                    } else {
                        $consecutiveFailures++;
                        
                        // If we have a recent good frame, resend it
                        if ($lastGoodFrame && $consecutiveFailures <= 2) {
                            echo "\r\n--optframe\r\n";
                            echo "Content-Type: image/jpeg\r\n";
                            echo "Content-Length: " . strlen($lastGoodFrame) . "\r\n";
                            echo "X-Frame-Quality: cached\r\n\r\n";
                            echo $lastGoodFrame;
                        } else if ($consecutiveFailures >= $maxConsecutiveFailures) {
                            // Too many failures, send error frame
                            $errorFrame = $this->generateStreamErrorFrame('Temporary stream interruption');
                            echo "\r\n--optframe\r\n";
                            echo "Content-Type: image/jpeg\r\n";
                            echo "Content-Length: " . strlen($errorFrame) . "\r\n";
                            echo "X-Frame-Quality: error\r\n\r\n";
                            echo $errorFrame;
                        }
                    }
                    
                    // Flush output
                    if (ob_get_level()) {
                        ob_flush();
                    }
                    flush();
                    
                    // Check connection
                    if (connection_aborted()) {
                        break;
                    }
                    
                    // Frame rate control - ~1.5 FPS
                    usleep(650000); // 0.65 seconds
                }
                
                echo "\r\n--optframe--\r\n";
                
            } catch (\Exception $e) {
                $errorFrame = $this->generateStreamErrorFrame('Stream Error: ' . $e->getMessage());
                echo "\r\n--optframe\r\n";
                echo "Content-Type: image/jpeg\r\n";
                echo "Content-Length: " . strlen($errorFrame) . "\r\n\r\n";
                echo $errorFrame;
                echo "\r\n--optframe--\r\n";
            } finally {
                // Cleanup
                $pattern = storage_path('app/temp/opt_' . $cameraId . '_*.jpg');
                foreach (glob($pattern) as $file) {
                    if (file_exists($file)) {
                        unlink($file);
                    }
                }
            }
        });

        return $response;
    }

    /**
     * Generate error frame for stream interruptions
     */
    private function generateStreamErrorFrame($message)
    {
        $width = 640;
        $height = 480;
        $image = imagecreatetruecolor($width, $height);
        
        // Colors
        $bgColor = imagecolorallocate($image, 40, 40, 40);
        $textColor = imagecolorallocate($image, 255, 255, 255);
        $accentColor = imagecolorallocate($image, 255, 100, 100);
        
        // Fill background
        imagefill($image, 0, 0, $bgColor);
        
        // Add border
        imagerectangle($image, 0, 0, $width-1, $height-1, $accentColor);
        imagerectangle($image, 1, 1, $width-2, $height-2, $accentColor);
        
        // Add text
        $font = 5;
        $title = "Stream Reconnecting...";
        $titleWidth = imagefontwidth($font) * strlen($title);
        $titleX = ($width - $titleWidth) / 2;
        $titleY = $height / 2 - 30;
        
        imagestring($image, $font, $titleX, $titleY, $title, $textColor);
        
        // Add message
        $messageWidth = imagefontwidth($font) * strlen($message);
        $messageX = ($width - $messageWidth) / 2;
        $messageY = $titleY + 40;
        
        imagestring($image, $font, $messageX, $messageY, $message, $textColor);
        
        // Convert to JPEG
        ob_start();
        imagejpeg($image, null, 80);
        $imageData = ob_get_contents();
        ob_end_clean();
        
        imagedestroy($image);
        
        return $imageData;
    }

    /**
     * Proxy requests to camera web interface (handles CORS and authentication)
     */
    public function cameraProxy(Request $request)
    {
        $targetUrl = $request->input('url');
        
        if (!$targetUrl) {
            return $this->sendError('Missing URL parameter', [], 400);
        }

        try {
            // Parse the target URL to validate it's the camera
            $parsedUrl = parse_url($targetUrl);
            $allowedHost = '192.168.0.109';
            
            if (!isset($parsedUrl['host']) || $parsedUrl['host'] !== $allowedHost) {
                return $this->sendError('Invalid camera URL', ['allowed_host' => $allowedHost], 403);
            }

            // Make request to camera with authentication
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $targetUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST | CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, "{$this->rtspUsername}:{$this->rtspPassword}");
            curl_setopt($ch, CURLOPT_HEADER, true);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            
            if (curl_errno($ch)) {
                $error = curl_error($ch);
                curl_close($ch);
                return $this->sendError('Camera connection failed', ['error' => $error], 500);
            }
            
            curl_close($ch);
            
            // Split headers and body
            $headers = substr($response, 0, $headerSize);
            $body = substr($response, $headerSize);
            
            // Parse headers
            $headerLines = explode("\r\n", $headers);
            $responseHeaders = [];
            foreach ($headerLines as $line) {
                if (strpos($line, ':') !== false) {
                    list($key, $value) = explode(':', $line, 2);
                    $responseHeaders[trim($key)] = trim($value);
                }
            }
            
            // Create response with appropriate headers
            $contentType = $responseHeaders['Content-Type'] ?? 'text/html';
            
            return response($body, $httpCode)
                ->header('Content-Type', $contentType)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
                
        } catch (\Exception $e) {
            return $this->sendError('Proxy request failed', ['error' => $e->getMessage()], 500);
        }
    }

    // ==================== ZKTeco Camera Methods ====================

    /**
     * ZKTeco camera test connection
     */
    public function zktecoTestConnection(Request $request)
    {
        try {
            $cameraService = new ZKTecoCameraService(
                $request->input('ip'),
                $request->input('http_port'),
                $request->input('rtsp_port'),
                $request->input('username'),
                $request->input('password')
            );

            $result = $cameraService->testConnection();
            
            if ($result['success']) {
                return $this->sendResponse($result, 'ZKTeco camera connection successful');
            }

            return $this->sendError('ZKTeco camera connection failed', $result, 422);
            
        } catch (\Exception $e) {
            return $this->sendError('ZKTeco connection test error', [
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ZKTeco camera configuration
     */
    public function zktecoConfig()
    {
        try {
            $cameraService = new ZKTecoCameraService();
            $config = $cameraService->getConfig();
            
            return $this->sendResponse($config, 'ZKTeco camera configuration retrieved');
            
        } catch (\Exception $e) {
            return $this->sendError('Failed to get ZKTeco configuration', [
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ZKTeco camera snapshot
     */
    public function zktecoSnapshot(Request $request)
    {
        try {
            $cameraService = new ZKTecoCameraService(
                $request->input('ip'),
                $request->input('http_port'),
                $request->input('rtsp_port'),
                $request->input('username'),
                $request->input('password')
            );

            $result = $cameraService->getSnapshot();
            
            if ($result['success']) {
                return response($result['data'])
                    ->header('Content-Type', $result['content_type'])
                    ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                    ->header('Pragma', 'no-cache')
                    ->header('Expires', '0')
                    ->header('Access-Control-Allow-Origin', '*');
            }

            return $this->sendError('Failed to capture ZKTeco snapshot', $result, 422);
            
        } catch (\Exception $e) {
            return $this->sendError('ZKTeco snapshot error', [
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ZKTeco RTSP URL generator
     */
    public function zktecoRtspUrl(Request $request)
    {
        try {
            $streamType = $request->input('stream_type', 'main');
            
            $cameraService = new ZKTecoCameraService(
                $request->input('ip'),
                $request->input('http_port'),
                $request->input('rtsp_port'),
                $request->input('username'),
                $request->input('password')
            );

            $rtspUrl = $cameraService->getRtspUrl($streamType);
            $webUrl = $cameraService->getWebInterfaceUrl();
            
            return $this->sendResponse([
                'rtsp_url' => $rtspUrl,
                'web_interface_url' => $webUrl,
                'stream_type' => $streamType,
                'config' => $cameraService->getConfig()
            ], 'ZKTeco RTSP URLs generated');
            
        } catch (\Exception $e) {
            return $this->sendError('Failed to generate ZKTeco RTSP URL', [
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ZKTeco device information
     */
    public function zktecoDeviceInfo(Request $request)
    {
        try {
            $cameraService = new ZKTecoCameraService(
                $request->input('ip'),
                $request->input('http_port'),
                $request->input('rtsp_port'),
                $request->input('username'),
                $request->input('password')
            );

            $result = $cameraService->getDeviceInfo();
            
            if ($result['success']) {
                return $this->sendResponse($result, 'ZKTeco device information retrieved');
            }

            return $this->sendError('Failed to get ZKTeco device info', $result, 422);
            
        } catch (\Exception $e) {
            return $this->sendError('ZKTeco device info error', [
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ZKTeco credentials validation
     */
    public function zktecoValidateCredentials(Request $request)
    {
        $request->validate([
            'ip' => 'required|ip',
            'username' => 'required|string',
            'password' => 'required|string',
            'http_port' => 'sometimes|integer|min:1|max:65535',
            'rtsp_port' => 'sometimes|integer|min:1|max:65535',
        ]);

        try {
            $cameraService = new ZKTecoCameraService(
                $request->input('ip'),
                $request->input('http_port', 80),
                $request->input('rtsp_port', 554),
                $request->input('username'),
                $request->input('password')
            );

            $result = $cameraService->validateCredentials();
            
            if ($result['success']) {
                return $this->sendResponse($result, 'ZKTeco credentials validated successfully');
            }

            return $this->sendError('ZKTeco credentials validation failed', $result, 422);
            
        } catch (\Exception $e) {
            return $this->sendError('ZKTeco credentials validation error', [
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ZKTeco MJPEG stream proxy
     */
    public function zktecoMjpegStream(Request $request)
    {
        try {
            $cameraService = new ZKTecoCameraService(
                $request->input('ip'),
                $request->input('http_port'),
                $request->input('rtsp_port'),
                $request->input('username'),
                $request->input('password')
            );

            // Test connection first
            $connectionTest = $cameraService->testConnection();
            if (!$connectionTest['success']) {
                return $this->sendError('Cannot connect to ZKTeco camera', $connectionTest, 503);
            }

            $response = new StreamedResponse();
            $response->headers->set('Content-Type', 'multipart/x-mixed-replace; boundary=zktecoframe');
            $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
            $response->headers->set('Connection', 'close');
            $response->headers->set('Access-Control-Allow-Origin', '*');

            $response->setCallback(function() use ($cameraService) {
                set_time_limit(0);
                ignore_user_abort(false);
                
                $mjpegUrl = $cameraService->getHttpUrl('/cgi-bin/mjpeg');
                $authHeaders = $cameraService->getAuthHeaders();
                
                // Use cURL for streaming
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $mjpegUrl);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: ' . $authHeaders['Authorization'],
                    'User-Agent: ' . $authHeaders['User-Agent'],
                ]);
                curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) {
                    if (connection_aborted()) {
                        return -1;
                    }
                    echo $data;
                    ob_flush();
                    flush();
                    return strlen($data);
                });
                curl_setopt($ch, CURLOPT_TIMEOUT, 0);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                
                curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode !== 200) {
                    echo "data: Camera stream error (HTTP $httpCode)\n\n";
                }
            });

            return $response;
            
        } catch (\Exception $e) {
            return $this->sendError('ZKTeco MJPEG stream error', [
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
