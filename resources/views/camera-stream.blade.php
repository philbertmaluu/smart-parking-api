<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Smart Parking - Camera Stream</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
    
    <!-- Styles -->
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <style>
            body {
                font-family: 'Instrument Sans', sans-serif;
                margin: 0;
                padding: 20px;
                background: #f8f9fa;
            }
            .container {
                max-width: 1200px;
                margin: 0 auto;
            }
            .camera-container {
                background: white;
                border-radius: 12px;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
                padding: 24px;
                margin-bottom: 24px;
            }
            .video-wrapper {
                position: relative;
                background: #000;
                border-radius: 8px;
                overflow: hidden;
            }
            video, img {
                width: 100%;
                height: 400px;
                object-fit: cover;
            }
            .controls {
                margin-top: 16px;
                display: flex;
                gap: 12px;
                flex-wrap: wrap;
            }
            button {
                background: #3b82f6;
                color: white;
                border: none;
                padding: 8px 16px;
                border-radius: 6px;
                cursor: pointer;
                font-weight: 500;
            }
            button:hover {
                background: #2563eb;
            }
            button:disabled {
                background: #9ca3af;
                cursor: not-allowed;
            }
            .status {
                padding: 12px;
                border-radius: 6px;
                margin-top: 12px;
                font-weight: 500;
            }
            .status.success {
                background: #dcfce7;
                color: #166534;
                border: 1px solid #bbf7d0;
            }
            .status.error {
                background: #fef2f2;
                color: #dc2626;
                border: 1px solid #fecaca;
            }
            .status.info {
                background: #dbeafe;
                color: #1d4ed8;
                border: 1px solid #bfdbfe;
            }
            h1, h2 {
                color: #1f2937;
            }
            .method-description {
                color: #6b7280;
                margin-bottom: 16px;
                line-height: 1.5;
            }
            .rtsp-url {
                background: #f3f4f6;
                padding: 8px 12px;
                border-radius: 4px;
                font-family: monospace;
                font-size: 14px;
                margin: 8px 0;
            }
        </style>
    @endif
</head>
<body>
    <div class="container">
        <header style="margin-bottom: 32px;">
            <h1>Smart Parking Camera Stream</h1>
            <div class="rtsp-url">RTSP URL: rtsp://192.168.0.109:554/</div>
        </header>

        <!-- Method 1: HLS Stream (Requires FFmpeg conversion) -->
        <div class="camera-container">
            <h2>Method 1: HLS Stream (Best for Production)</h2>
            <div class="method-description">
                This method converts RTSP to HLS format for better browser compatibility. Requires FFmpeg server-side conversion.
            </div>
            <div class="video-wrapper">
                <video id="hlsVideo" controls>
                    <source src="/stream/camera1.m3u8" type="application/x-mpegURL">
                    Your browser does not support HLS video.
                </video>
            </div>
            <div class="controls">
                <button onclick="startHLSStream()">Start HLS Stream</button>
                <button onclick="stopHLSStream()">Stop Stream</button>
            </div>
            <div id="hlsStatus" class="status info" style="display: none;">
                HLS stream status will appear here
            </div>
        </div>

        <!-- Method 2: WebRTC Stream -->
        <div class="camera-container">
            <h2>Method 2: WebRTC Stream (Real-time)</h2>
            <div class="method-description">
                Ultra-low latency streaming using WebRTC. Requires a WebRTC gateway to convert RTSP.
            </div>
            <div class="video-wrapper">
                <video id="webrtcVideo" autoplay muted></video>
            </div>
            <div class="controls">
                <button onclick="startWebRTCStream()">Start WebRTC Stream</button>
                <button onclick="stopWebRTCStream()">Stop Stream</button>
            </div>
            <div id="webrtcStatus" class="status info" style="display: none;">
                WebRTC stream status will appear here
            </div>
        </div>

        <!-- Method 3: MJPEG Stream -->
        <div class="camera-container">
            <h2>Method 3: Motion JPEG Stream (Simple)</h2>
            <div class="method-description">
                Simple HTTP-based video stream. Works well for basic monitoring needs.
            </div>
            <div class="video-wrapper">
                <img id="mjpegStream" alt="Camera Stream" style="display: none;">
            </div>
            <div class="controls">
                <button onclick="startMJPEGStream()">Start MJPEG Stream</button>
                <button onclick="stopMJPEGStream()">Stop Stream</button>
                <button onclick="refreshMJPEGStream()">Refresh</button>
            </div>
            <div id="mjpegStatus" class="status info" style="display: none;">
                MJPEG stream status will appear here
            </div>
        </div>

        <!-- Method 4: Snapshot Viewer -->
        <div class="camera-container">
            <h2>Method 4: Live Snapshots (Fallback)</h2>
            <div class="method-description">
                Periodically refreshed camera snapshots. Good fallback option when streaming isn't available.
            </div>
            <div class="video-wrapper">
                <img id="snapshotImage" alt="Camera Snapshot" style="display: none;">
            </div>
            <div class="controls">
                <button onclick="startSnapshots()">Start Auto-Refresh</button>
                <button onclick="stopSnapshots()">Stop Auto-Refresh</button>
                <button onclick="takeSnapshot()">Take Snapshot</button>
                <select id="refreshInterval">
                    <option value="1000">1 second</option>
                    <option value="2000" selected>2 seconds</option>
                    <option value="5000">5 seconds</option>
                    <option value="10000">10 seconds</option>
                </select>
            </div>
            <div id="snapshotStatus" class="status info" style="display: none;">
                Snapshot status will appear here
            </div>
        </div>
    </div>

    <!-- Load HLS.js for HLS support -->
    <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>

    <script>
        // HLS Stream Functions
        let hlsInstance = null;

        function startHLSStream() {
            const video = document.getElementById('hlsVideo');
            const status = document.getElementById('hlsStatus');
            
            showStatus('hlsStatus', 'Starting HLS stream...', 'info');
            
            // First, start the HLS stream on the server
            fetch('/api/toll-v1/stream/hls/camera1', {
                method: 'GET'
            })
            .then(response => {
                if (response.ok) {
                    return response.json();
                } else {
                    throw new Error('Failed to start HLS stream');
                }
            })
            .then(data => {
                showStatus('hlsStatus', 'HLS stream starting on server...', 'info');
                
                // Wait a moment for the stream to initialize, then load the playlist
                setTimeout(() => {
                    if (Hls.isSupported()) {
                        hlsInstance = new Hls();
                        hlsInstance.loadSource('/api/toll-v1/stream/hls/camera1');
                        hlsInstance.attachMedia(video);
                        
                        hlsInstance.on(Hls.Events.MANIFEST_PARSED, function() {
                            showStatus('hlsStatus', 'HLS stream started successfully', 'success');
                            video.play();
                        });
                        
                        hlsInstance.on(Hls.Events.ERROR, function(event, data) {
                            showStatus('hlsStatus', 'HLS stream error: ' + data.details, 'error');
                        });
                    } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
                        video.src = '/api/toll-v1/stream/hls/camera1';
                        video.addEventListener('canplay', function() {
                            showStatus('hlsStatus', 'HLS stream started successfully', 'success');
                        });
                    } else {
                        showStatus('hlsStatus', 'HLS is not supported in this browser', 'error');
                    }
                }, 2000); // Wait 2 seconds for stream to initialize
            })
            .catch(error => {
                showStatus('hlsStatus', 'Error starting HLS stream: ' + error.message, 'error');
            });
        }

        function stopHLSStream() {
            const video = document.getElementById('hlsVideo');
            if (hlsInstance) {
                hlsInstance.destroy();
                hlsInstance = null;
            }
            video.pause();
            video.src = '';
            showStatus('hlsStatus', 'HLS stream stopped', 'info');
        }

        // WebRTC Stream Functions
        let webrtcConnection = null;

        function startWebRTCStream() {
            const video = document.getElementById('webrtcVideo');
            const status = document.getElementById('webrtcStatus');
            
            // This would typically connect to a WebRTC gateway
            // For demo purposes, we'll show how it would work
            
            webrtcConnection = new RTCPeerConnection({
                iceServers: [{ urls: 'stun:stun.l.google.com:19302' }]
            });
            
            // Add transceiver for receiving video
            webrtcConnection.addTransceiver('video', { direction: 'recvonly' });
            
            webrtcConnection.ontrack = function(event) {
                video.srcObject = event.streams[0];
                showStatus('webrtcStatus', 'WebRTC stream connected', 'success');
            };
            
            // This would typically negotiate with your WebRTC server
            showStatus('webrtcStatus', 'WebRTC connection initiated (requires WebRTC gateway)', 'info');
        }

        function stopWebRTCStream() {
            const video = document.getElementById('webrtcVideo');
            if (webrtcConnection) {
                webrtcConnection.close();
                webrtcConnection = null;
            }
            video.srcObject = null;
            showStatus('webrtcStatus', 'WebRTC stream stopped', 'info');
        }

        // MJPEG Stream Functions
        function startMJPEGStream() {
            const img = document.getElementById('mjpegStream');
            const status = document.getElementById('mjpegStatus');
            
            img.src = '/api/toll-v1/stream/mjpeg/camera1';
            img.style.display = 'block';
            
            img.onload = function() {
                showStatus('mjpegStatus', 'MJPEG stream started', 'success');
            };
            
            img.onerror = function() {
                showStatus('mjpegStatus', 'MJPEG stream failed to load', 'error');
            };
        }

        function stopMJPEGStream() {
            const img = document.getElementById('mjpegStream');
            img.style.display = 'none';
            img.src = '';
            showStatus('mjpegStatus', 'MJPEG stream stopped', 'info');
        }

        function refreshMJPEGStream() {
            const img = document.getElementById('mjpegStream');
            if (img.src) {
                img.src = img.src.split('?')[0] + '?t=' + Date.now();
            }
        }

        // Snapshot Functions
        let snapshotInterval = null;

        function startSnapshots() {
            const interval = document.getElementById('refreshInterval').value;
            const status = document.getElementById('snapshotStatus');
            
            stopSnapshots(); // Stop any existing interval
            
            takeSnapshot(); // Take initial snapshot
            snapshotInterval = setInterval(takeSnapshot, parseInt(interval));
            
            showStatus('snapshotStatus', `Auto-refresh started (every ${interval}ms)`, 'success');
        }

        function stopSnapshots() {
            if (snapshotInterval) {
                clearInterval(snapshotInterval);
                snapshotInterval = null;
            }
            showStatus('snapshotStatus', 'Auto-refresh stopped', 'info');
        }

        function takeSnapshot() {
            const img = document.getElementById('snapshotImage');
            const status = document.getElementById('snapshotStatus');
            
            // Add timestamp to force refresh
            img.src = '/api/toll-v1/stream/snapshot/camera1?t=' + Date.now();
            img.style.display = 'block';
            
            img.onload = function() {
                showStatus('snapshotStatus', 'Snapshot updated at ' + new Date().toLocaleTimeString(), 'success');
            };
            
            img.onerror = function() {
                showStatus('snapshotStatus', 'Failed to load snapshot', 'error');
            };
        }

        // Utility Functions
        function showStatus(elementId, message, type) {
            const element = document.getElementById(elementId);
            element.style.display = 'block';
            element.textContent = message;
            element.className = 'status ' + type;
        }

        // Test camera connection on page load
        window.addEventListener('load', function() {
            // Try to take an initial snapshot to test the connection
            takeSnapshot();
        });
    </script>
</body>
</html>
