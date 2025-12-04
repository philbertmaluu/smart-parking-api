<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Smart Parking - Live Camera Stream</title>

    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            color: #2d3748;
            margin: 0 0 10px 0;
            font-size: 2.5em;
        }

        .header p {
            color: #718096;
            font-size: 1.1em;
        }

        .main-content {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 30px;
            align-items: start;
        }

        .video-section {
            background: #000;
            border-radius: 12px;
            overflow: hidden;
            position: relative;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        .video-container {
            background: #000;
            min-height: 400px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            position: relative;
        }

        .video-container video,
        .video-container img {
            width: 100%;
            height: auto;
            display: block;
        }

        .placeholder {
            text-align: center;
            color: #a0aec0;
        }

        .placeholder h3 {
            margin: 0 0 10px 0;
            font-size: 1.5em;
        }

        .video-overlay {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            display: none;
        }

        .video-overlay.show {
            display: block;
        }

        .controls-section {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 25px;
        }

        .control-group {
            margin-bottom: 25px;
        }

        .control-group h3 {
            margin: 0 0 15px 0;
            color: #2d3748;
            font-size: 1.2em;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 8px;
        }

        .btn {
            background: #4299e1;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            margin: 5px 5px 5px 0;
            transition: all 0.2s;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn:hover {
            background: #3182ce;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(66, 153, 225, 0.3);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn-success {
            background: #48bb78;
        }

        .btn-success:hover {
            background: #38a169;
            box-shadow: 0 4px 12px rgba(72, 187, 120, 0.3);
        }

        .btn-danger {
            background: #f56565;
        }

        .btn-danger:hover {
            background: #e53e3e;
            box-shadow: 0 4px 12px rgba(245, 101, 101, 0.3);
        }

        .btn-warning {
            background: #ed8936;
        }

        .btn-warning:hover {
            background: #dd6b20;
        }

        .btn-secondary {
            background: #718096;
        }

        .btn-secondary:hover {
            background: #4a5568;
        }

        .btn:disabled {
            background: #e2e8f0;
            color: #a0aec0;
            cursor: not-allowed;
            transform: none;
        }

        .btn:disabled:hover {
            background: #e2e8f0;
            transform: none;
            box-shadow: none;
        }

        .status {
            padding: 12px 16px;
            border-radius: 8px;
            margin: 15px 0;
            font-weight: 500;
            font-size: 14px;
        }

        .status-success {
            background: #c6f6d5;
            color: #22543d;
            border: 1px solid #9ae6b4;
        }

        .status-error {
            background: #fed7d7;
            color: #742a2a;
            border: 1px solid #feb2b2;
        }

        .status-info {
            background: #bee3f8;
            color: #2a4365;
            border: 1px solid #90cdf4;
        }

        .status-warning {
            background: #faf089;
            color: #744210;
            border: 1px solid #f6e05e;
        }

        .stream-method {
            display: grid;
            grid-template-columns: 1fr auto;
            align-items: center;
            gap: 10px;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            margin: 8px 0;
            transition: all 0.2s;
        }

        .stream-method:hover {
            border-color: #cbd5e0;
        }

        .stream-method.active {
            border-color: #48bb78;
            background: #f0fff4;
        }

        .stream-info {
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
            font-size: 13px;
        }

        .stream-info .url {
            font-family: monospace;
            background: #edf2f7;
            padding: 8px;
            border-radius: 4px;
            margin: 5px 0;
            word-break: break-all;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin: 15px 0;
        }

        .stat-item {
            background: #edf2f7;
            padding: 10px;
            border-radius: 6px;
            text-align: center;
        }

        .stat-value {
            font-size: 1.5em;
            font-weight: bold;
            color: #2d3748;
        }

        .stat-label {
            font-size: 0.8em;
            color: #718096;
            margin-top: 4px;
        }

        @media (max-width: 768px) {
            .main-content {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .container {
                padding: 20px;
            }

            .header h1 {
                font-size: 2em;
            }
        }

        .loading-spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #4299e1;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-right: 10px;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>🎥 Live Camera Stream</h1>
            <p>Real-time RTSP camera monitoring system</p>
        </div>

        <div class="main-content">
            <div class="video-section">
                <div class="video-container" id="videoContainer">
                    <div class="placeholder">
                        <h3>📹 Camera Feed</h3>
                        <p>Click "Start Live Stream" to begin monitoring</p>
                    </div>
                </div>
                <div class="video-overlay" id="streamOverlay">
                    <span id="streamMethod">Live Stream</span> • <span id="streamStatus">Ready</span>
                </div>
            </div>

            <div class="controls-section">
                <div class="control-group">
                    <h3>🚀 Quick Actions</h3>
                    <button class="btn btn-success" id="startBtn" onclick="startLiveStream()">
                        <span class="btn-icon">▶️</span> Start Live Stream
                    </button>
                    <button class="btn btn-danger" id="stopBtn" onclick="stopStream()" disabled>
                        <span class="btn-icon">⏹️</span> Stop Stream
                    </button>
                    <button class="btn btn-warning" onclick="captureSnapshot()">
                        <span class="btn-icon">📸</span> Take Snapshot
                    </button>
                </div>

                <div class="control-group">
                    <h3>🔧 Stream Methods</h3>
                    <div class="stream-method" id="method-mjpeg">
                        <div>
                            <strong>MJPEG Stream</strong>
                            <div style="font-size: 12px; color: #666;">Real-time video (Recommended)</div>
                        </div>
                        <button class="btn btn-success" onclick="startMJPEGStream()">
                            <span class="btn-icon">🎬</span> Start
                        </button>
                    </div>

                    <div class="stream-method" id="method-snapshot">
                        <div>
                            <strong>Live Snapshots</strong>
                            <div style="font-size: 12px; color: #666;">1-2 second updates</div>
                        </div>
                        <button class="btn btn-secondary" onclick="startSnapshotStream()">
                            <span class="btn-icon">📷</span> Start
                        </button>
                    </div>

                    <div class="stream-method" id="method-hls">
                        <div>
                            <strong>HLS Stream</strong>
                            <div style="font-size: 12px; color: #666;">High quality (slower startup)</div>
                        </div>
                        <button class="btn" onclick="startHLSStream()">
                            <span class="btn-icon">🎯</span> Start
                        </button>
                    </div>
                </div>

                <div class="control-group">
                    <h3>📊 Stream Stats</h3>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-value" id="fps">0</div>
                            <div class="stat-label">FPS</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value" id="uptime">00:00</div>
                            <div class="stat-label">Uptime</div>
                        </div>
                    </div>
                </div>

                <div class="control-group">
                    <h3>🔍 Diagnostics</h3>
                    <button class="btn" onclick="testConnection()">
                        <span class="btn-icon">🔍</span> Test Connection
                    </button>
                    <button class="btn btn-secondary" onclick="openInVLC()">
                        <span class="btn-icon">🎥</span> Open in VLC
                    </button>
                    <button class="btn btn-secondary" onclick="copyStreamURL()">
                        <span class="btn-icon">📋</span> Copy URL
                    </button>
                </div>

                <div class="stream-info">
                    <strong>Camera Information:</strong>
                    <div class="url" id="cameraInfo">
                        IP: 192.168.0.109:554<br>
                        Protocol: RTSP<br>
                        Authentication: Digest
                    </div>
                </div>

                <div id="status"></div>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let streamActive = false;
        let currentStreamType = '';
        let streamStartTime = null;
        let refreshInterval = null;
        let fpsCounter = 0;
        let fpsInterval = null;

        // Working camera credentials (from our previous testing)
        const cameraCredentials = {
            username: 'admin',
            password: 'Password123!',
            ip: '192.168.0.109',
            port: '554',
            path: '/stream'
        };

        // UI Helper Functions
        function showStatus(message, type) {
            const statusDiv = document.getElementById('status');
            statusDiv.innerHTML = `<div class="status status-${type}">${message}</div>`;
        }

        function updateStreamOverlay(method, status) {
            const overlay = document.getElementById('streamOverlay');
            const methodSpan = document.getElementById('streamMethod');
            const statusSpan = document.getElementById('streamStatus');

            methodSpan.textContent = method;
            statusSpan.textContent = status;
            overlay.classList.add('show');
        }

        function hideStreamOverlay() {
            document.getElementById('streamOverlay').classList.remove('show');
        }

        function updateStreamStats() {
            const fpsElement = document.getElementById('fps');
            const uptimeElement = document.getElementById('uptime');

            if (streamActive && streamStartTime) {
                const elapsed = Math.floor((Date.now() - streamStartTime) / 1000);
                const minutes = Math.floor(elapsed / 60);
                const seconds = elapsed % 60;
                uptimeElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

                // Update FPS (approximate)
                if (currentStreamType === 'mjpeg') {
                    fpsElement.textContent = '~1';
                } else if (currentStreamType === 'optimized') {
                    fpsElement.textContent = '~1.5';
                } else if (currentStreamType === 'snapshot') {
                    fpsElement.textContent = '0.5';
                } else {
                    fpsElement.textContent = '0';
                }
            } else {
                uptimeElement.textContent = '00:00';
                fpsElement.textContent = '0';
            }
        }

        function setActiveStreamMethod(methodId) {
            // Remove active class from all methods
            document.querySelectorAll('.stream-method').forEach(method => {
                method.classList.remove('active');
            });

            // Add active class to current method
            if (methodId) {
                document.getElementById(methodId).classList.add('active');
            }
        }

        function updateButtonStates(streaming) {
            const startBtn = document.getElementById('startBtn');
            const stopBtn = document.getElementById('stopBtn');

            startBtn.disabled = streaming;
            stopBtn.disabled = !streaming;
        }

        // Core Stream Functions
        function startLiveStream() {
            // Default to MJPEG as it works best
            startMJPEGStream();
        }

        function startMJPEGStream() {
            if (streamActive) {
                showStatus('⚠️ Please stop the current stream first', 'warning');
                return;
            }

            showStatus('<div class="loading-spinner"></div>Starting optimized stream...', 'info');
            
            const container = document.getElementById('videoContainer');
            const streamImg = document.createElement('img');
            
            streamImg.onload = function() {
                streamActive = true;
                currentStreamType = 'optimized';
                streamStartTime = Date.now();
                
                updateStreamOverlay('Optimized Stream', 'Live');
                updateButtonStates(true);
                setActiveStreamMethod('method-mjpeg');
                showStatus('✅ Optimized stream started successfully!', 'success');
                
                // Start stats updates
                if (fpsInterval) clearInterval(fpsInterval);
                fpsInterval = setInterval(updateStreamStats, 1000);
            };
            
            streamImg.onerror = function() {
                showStatus('❌ Optimized stream failed. Trying MJPEG fallback...', 'error');
                // Fallback to regular MJPEG
                streamImg.src = '/api/toll-v1/stream/mjpeg/camera1?' + Date.now();
            };
            
            // Use optimized stream endpoint
            streamImg.src = '/api/toll-v1/stream/optimized/camera1?' + Date.now();
            streamImg.style.width = '100%';
            streamImg.style.height = 'auto';
            
            container.innerHTML = '';
            container.appendChild(streamImg);
        }

        function startSnapshotStream() {
            if (streamActive && currentStreamType !== 'snapshot') {
                showStatus('⚠️ Please stop the current stream first', 'warning');
                return;
            }

            showStatus('<div class="loading-spinner"></div>Starting live snapshots...', 'info');

            // Test first snapshot
            const testImg = document.createElement('img');
            testImg.onload = function() {
                streamActive = true;
                currentStreamType = 'snapshot';
                streamStartTime = Date.now();

                updateStreamOverlay('Live Snapshots', 'Active');
                updateButtonStates(true);
                setActiveStreamMethod('method-snapshot');
                showStatus('✅ Live snapshots started!', 'success');

                const container = document.getElementById('videoContainer');
                container.innerHTML = '';
                container.appendChild(testImg);

                // Start refresh interval
                if (refreshInterval) clearInterval(refreshInterval);
                refreshInterval = setInterval(() => {
                    if (streamActive && currentStreamType === 'snapshot') {
                        const img = document.createElement('img');
                        img.src = `/stream/snapshot/camera1?t=${Date.now()}`;
                        img.style.width = '100%';
                        img.style.height = 'auto';

                        img.onload = function() {
                            const container = document.getElementById('videoContainer');
                            if (container.firstChild) {
                                container.replaceChild(img, container.firstChild);
                            }
                        };
                    }
                }, 1500); // 1.5 second refresh

                // Start stats updates
                if (fpsInterval) clearInterval(fpsInterval);
                fpsInterval = setInterval(updateStreamStats, 1000);
            };

            testImg.onerror = function() {
                showStatus('❌ Failed to capture snapshots. Check connection.', 'error');
            };

            testImg.src = `/stream/snapshot/camera1?t=${Date.now()}`;
        }

        function startHLSStream() {
            if (streamActive) {
                showStatus('⚠️ Please stop the current stream first', 'warning');
                return;
            }

            showStatus('<div class="loading-spinner"></div>Starting HLS stream (this may take 10-30 seconds)...', 'info');

            fetch('/api/toll-v1/stream/hls/camera1')
                .then(response => {
                    if (response.ok) {
                        return response.blob();
                    }
                    throw new Error('HLS stream initialization failed');
                })
                .then(blob => {
                    // HLS stream is ready, set up video player
                    const container = document.getElementById('videoContainer');
                    const video = document.createElement('video');

                    video.controls = true;
                    video.autoplay = true;
                    video.muted = true;
                    video.style.width = '100%';
                    video.style.height = 'auto';

                    // Load HLS.js if available
                    if (window.Hls && Hls.isSupported()) {
                        const hls = new Hls();
                        hls.loadSource('/api/toll-v1/stream/hls/camera1');
                        hls.attachMedia(video);

                        hls.on(Hls.Events.MEDIA_ATTACHED, function() {
                            console.log('HLS media attached');
                        });
                    } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
                        video.src = '/api/toll-v1/stream/hls/camera1';
                    } else {
                        throw new Error('HLS not supported in this browser');
                    }

                    container.innerHTML = '';
                    container.appendChild(video);

                    streamActive = true;
                    currentStreamType = 'hls';
                    streamStartTime = Date.now();

                    updateStreamOverlay('HLS Stream', 'Live');
                    updateButtonStates(true);
                    setActiveStreamMethod('method-hls');
                    showStatus('✅ HLS stream started!', 'success');

                    // Start stats updates
                    if (fpsInterval) clearInterval(fpsInterval);
                    fpsInterval = setInterval(updateStreamStats, 1000);
                })
                .catch(error => {
                    showStatus('❌ HLS stream failed. Trying MJPEG fallback...', 'error');
                    startMJPEGStream();
                });
        }

        function captureSnapshot() {
            showStatus('<div class="loading-spinner"></div>Capturing snapshot...', 'info');

            const img = document.createElement('img');
            img.onload = function() {
                showStatus('📸 Snapshot captured successfully!', 'success');

                // If no stream is active, show the snapshot
                if (!streamActive) {
                    const container = document.getElementById('videoContainer');
                    container.innerHTML = '';
                    img.style.width = '100%';
                    img.style.height = 'auto';
                    container.appendChild(img);

                    updateStreamOverlay('Snapshot', 'Captured');
                    setTimeout(() => hideStreamOverlay(), 3000);
                }
            };

            img.onerror = function() {
                showStatus('❌ Failed to capture snapshot', 'error');
            };

            img.src = `/stream/snapshot/camera1?t=${Date.now()}`;
        }

        function stopStream() {
            if (!streamActive) {
                showStatus('ℹ️ No active stream to stop', 'info');
                return;
            }

            // Clear intervals
            if (refreshInterval) {
                clearInterval(refreshInterval);
                refreshInterval = null;
            }
            if (fpsInterval) {
                clearInterval(fpsInterval);
                fpsInterval = null;
            }

            // Stop HLS stream on server if needed
            if (currentStreamType === 'hls') {
                fetch('/api/toll-v1/stream/hls/camera1/stop', {
                        method: 'POST'
                    })
                    .catch(error => console.log('HLS stop error:', error));
            }

            // Reset UI
            const container = document.getElementById('videoContainer');
            container.innerHTML = `
                <div class="placeholder">
                    <h3>📹 Camera Feed</h3>
                    <p>Click "Start Live Stream" to begin monitoring</p>
                </div>
            `;

            streamActive = false;
            currentStreamType = '';
            streamStartTime = null;

            hideStreamOverlay();
            updateButtonStates(false);
            setActiveStreamMethod(null);
            updateStreamStats();
            showStatus('⏹️ Stream stopped', 'info');
        }

        // Diagnostic Functions
        function testConnection() {
            showStatus('<div class="loading-spinner"></div>Testing camera connection...', 'info');

            fetch('/camera/test-connection', {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showStatus('✅ Camera connection successful!', 'success');
                    } else {
                        showStatus(`❌ Connection test failed: ${data.message}`, 'error');
                    }
                })
                .catch(error => {
                    showStatus('❌ Connection test error', 'error');
                });
        }

        function openInVLC() {
            const rtspUrl = `rtsp://${cameraCredentials.username}:${encodeURIComponent(cameraCredentials.password)}@${cameraCredentials.ip}:${cameraCredentials.port}${cameraCredentials.path}`;

            const playlistContent = `#EXTM3U
#EXTINF:-1,Smart Parking Camera
${rtspUrl}`;

            const blob = new Blob([playlistContent], {
                type: 'application/vnd.apple.mpegurl'
            });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');

            a.href = url;
            a.download = 'smart_parking_camera.m3u';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);

            showStatus('📁 VLC playlist downloaded! Open it with VLC Media Player.', 'success');
        }

        function copyStreamURL() {
            const rtspUrl = `rtsp://${cameraCredentials.username}:${encodeURIComponent(cameraCredentials.password)}@${cameraCredentials.ip}:${cameraCredentials.port}${cameraCredentials.path}`;

            navigator.clipboard.writeText(rtspUrl)
                .then(() => {
                    showStatus('📋 RTSP URL copied to clipboard!', 'success');
                })
                .catch(() => {
                    // Fallback for older browsers
                    const textArea = document.createElement('textarea');
                    textArea.value = rtspUrl;
                    document.body.appendChild(textArea);
                    textArea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textArea);
                    showStatus('📋 RTSP URL copied to clipboard!', 'success');
                });
        }

        // Initialize the application
        function init() {
            updateStreamStats();
            showStatus('🔄 Initializing camera connection...', 'info');

            // Auto-test connection after a short delay
            setTimeout(() => {
                testConnection();
            }, 1000);

            // Set up periodic stats updates
            setInterval(updateStreamStats, 1000);
        }

        // Start the application when page loads
        document.addEventListener('DOMContentLoaded', init);
    </script>
</body>

</html>