<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Smart Parking - Camera Stream (Simple)</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
    
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
            height: 400px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .video-player {
            width: 100%;
            height: 100%;
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
        .fallback-message {
            color: #6b7280;
            text-align: center;
            padding: 20px;
        }
        iframe {
            width: 100%;
            height: 400px;
            border: none;
            border-radius: 8px;
        }
        .instructions {
            background: #f0f9ff;
            border: 1px solid #0284c7;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
        }
        .instructions h3 {
            color: #0284c7;
            margin-top: 0;
        }
        .instructions ul {
            margin: 12px 0;
            padding-left: 20px;
        }
        .instructions li {
            margin: 8px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <header style="margin-bottom: 32px;">
            <h1>Smart Parking Camera Stream (Simple)</h1>
            <div class="rtsp-url">RTSP URL: rtsp://192.168.0.109:554/</div>
        </header>

        <div class="instructions">
            <h3>How to View Your RTSP Stream</h3>
            <p>Since RTSP isn't natively supported in web browsers, here are several options to view your stream:</p>
            <ul>
                <li><strong>VLC Media Player:</strong> Open VLC → Media → Open Network Stream → Enter: rtsp://192.168.0.109:554/</li>
                <li><strong>FFplay (if you have FFmpeg):</strong> Run: <code>ffplay rtsp://192.168.0.109:554/</code></li>
                <li><strong>Install FFmpeg for web streaming:</strong> Run <code>brew install ffmpeg</code> to enable web streaming features</li>
                <li><strong>QuickTime Player (macOS):</strong> File → Open Location → Enter the RTSP URL</li>
            </ul>
            <p>For production use, you'll typically convert RTSP to HLS, WebRTC, or MJPEG streams.</p>
        </div>

        <!-- Test Connection -->
        <div class="camera-container">
            <h2>Connection Test</h2>
            <div class="method-description">
                Test if the RTSP stream is accessible and responding.
            </div>
            <div class="controls">
                <button onclick="testRTSPConnection()">Test RTSP Connection</button>
                <button onclick="pingCamera()">Ping Camera IP</button>
            </div>
            <div id="connectionStatus" class="status info" style="display: none;">
                Connection status will appear here
            </div>
        </div>

        <!-- Browser-based options (require server-side conversion) -->
        <div class="camera-container">
            <h2>Browser Stream (Requires FFmpeg)</h2>
            <div class="method-description">
                These methods require FFmpeg to convert the RTSP stream to web-compatible formats.
                Install FFmpeg with: <code>brew install ffmpeg</code>
            </div>
            
            <div class="video-wrapper">
                <div class="fallback-message">
                    <p>Install FFmpeg to enable web-based streaming</p>
                    <p>Once installed, you can:</p>
                    <ul style="text-align: left; display: inline-block;">
                        <li>View live snapshots</li>
                        <li>Stream as MJPEG</li>
                        <li>Convert to HLS for smooth playback</li>
                    </ul>
                </div>
            </div>
            
            <div class="controls">
                <button onclick="checkFFmpeg()">Check FFmpeg Status</button>
                <button onclick="openVLCInstructions()">VLC Instructions</button>
            </div>
            <div id="ffmpegStatus" class="status info" style="display: none;">
                FFmpeg status will appear here
            </div>
        </div>

        <!-- Direct streaming options -->
        <div class="camera-container">
            <h2>Alternative Viewing Methods</h2>
            <div class="method-description">
                These are the most reliable ways to view your RTSP stream right now.
            </div>
            
            <div class="controls">
                <button onclick="openInVLC()">Open in VLC Player</button>
                <button onclick="openInQuickTime()">Open in QuickTime (macOS)</button>
                <button onclick="copyRTSPURL()">Copy RTSP URL</button>
                <button onclick="showStreamInfo()">Show Stream Information</button>
            </div>
            <div id="streamInfo" class="status info" style="display: none;">
                Stream information will appear here
            </div>
        </div>

        <!-- Installation Guide -->
        <div class="camera-container">
            <h2>Setup Guide</h2>
            <div class="method-description">
                To enable full web streaming capabilities in your Laravel application:
            </div>
            
            <div style="background: #f8f9fa; padding: 16px; border-radius: 8px; font-family: monospace; font-size: 14px;">
                <p><strong>1. Install FFmpeg:</strong></p>
                <pre style="margin: 8px 0; padding: 8px; background: #e5e7eb; border-radius: 4px;">brew install ffmpeg</pre>
                
                <p><strong>2. Test the stream:</strong></p>
                <pre style="margin: 8px 0; padding: 8px; background: #e5e7eb; border-radius: 4px;">ffplay rtsp://192.168.0.109:554/</pre>
                
                <p><strong>3. Convert to web-friendly format (example):</strong></p>
                <pre style="margin: 8px 0; padding: 8px; background: #e5e7eb; border-radius: 4px;">ffmpeg -i rtsp://192.168.0.109:554/ -f mjpeg -q:v 3 -r 10 http://localhost:8080/stream.mjpg</pre>
                
                <p><strong>4. Access your Laravel camera page:</strong></p>
                <pre style="margin: 8px 0; padding: 8px; background: #e5e7eb; border-radius: 4px;">http://localhost:8000/camera-stream</pre>
            </div>
        </div>
    </div>

    <script>
        const rtspUrl = 'rtsp://192.168.0.109:554/';
        const cameraIP = '192.168.0.109';

        function testRTSPConnection() {
            showStatus('connectionStatus', 'Testing RTSP connection...', 'info');
            
            // Since we can't directly test RTSP from browser, we'll test the endpoint
            fetch('/camera/test-connection')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showStatus('connectionStatus', 'RTSP connection successful!', 'success');
                    } else {
                        showStatus('connectionStatus', 'RTSP connection failed: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    showStatus('connectionStatus', 'Connection test error: ' + error.message, 'error');
                });
        }

        function pingCamera() {
            showStatus('connectionStatus', 'Pinging camera IP...', 'info');
            
            // This is a simple connectivity test
            const startTime = Date.now();
            fetch(`http://${cameraIP}`, { 
                mode: 'no-cors',
                timeout: 5000 
            })
            .then(() => {
                const responseTime = Date.now() - startTime;
                showStatus('connectionStatus', `Camera IP is reachable (${responseTime}ms response time)`, 'success');
            })
            .catch(() => {
                showStatus('connectionStatus', 'Cannot reach camera IP directly from browser (this is normal due to CORS)', 'info');
            });
        }

        function checkFFmpeg() {
            showStatus('ffmpegStatus', 'Checking FFmpeg installation...', 'info');
            
            fetch('/api/toll-v1/camera/test-connection')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showStatus('ffmpegStatus', 'FFmpeg is working! You can now use web streaming features.', 'success');
                    } else {
                        showStatus('ffmpegStatus', 'FFmpeg not available. Install with: brew install ffmpeg', 'error');
                    }
                })
                .catch(error => {
                    showStatus('ffmpegStatus', 'FFmpeg check failed. Please install FFmpeg to enable web streaming.', 'error');
                });
        }

        function openInVLC() {
            // Create a playlist file for VLC
            const playlistContent = `#EXTM3U
#EXTINF:-1,Camera Stream
${rtspUrl}`;
            
            const blob = new Blob([playlistContent], { type: 'application/vnd.apple.mpegurl' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'camera_stream.m3u';
            a.click();
            URL.revokeObjectURL(url);
            
            showStatus('streamInfo', 'Playlist file downloaded. Open it with VLC Media Player.', 'success');
        }

        function openInQuickTime() {
            // On macOS, this might work with a custom protocol handler
            try {
                window.location.href = rtspUrl;
                showStatus('streamInfo', 'Attempting to open in QuickTime Player...', 'info');
            } catch (error) {
                showStatus('streamInfo', 'Copy the RTSP URL manually: ' + rtspUrl, 'info');
            }
        }

        function copyRTSPURL() {
            navigator.clipboard.writeText(rtspUrl).then(() => {
                showStatus('streamInfo', 'RTSP URL copied to clipboard!', 'success');
            }).catch(() => {
                prompt('Copy this RTSP URL:', rtspUrl);
            });
        }

        function showStreamInfo() {
            const info = `
                RTSP URL: ${rtspUrl}
                Camera IP: ${cameraIP}
                Port: 554 (standard RTSP)
                Protocol: RTSP over TCP
                
                Compatible Players:
                • VLC Media Player
                • QuickTime Player (macOS)
                • FFplay (if FFmpeg installed)
                • Most IP camera apps
            `;
            showStatus('streamInfo', info, 'info');
        }

        function openVLCInstructions() {
            const instructions = `
                To view the stream in VLC:
                1. Open VLC Media Player
                2. Go to Media → Open Network Stream
                3. Enter: ${rtspUrl}
                4. Click Play
                
                Alternative: Download the playlist file using the "Open in VLC" button
            `;
            showStatus('ffmpegStatus', instructions, 'info');
        }

        // Utility Functions
        function showStatus(elementId, message, type) {
            const element = document.getElementById(elementId);
            element.style.display = 'block';
            element.textContent = message;
            element.className = 'status ' + type;
            
            // Preserve formatting for multiline messages
            if (message.includes('\n')) {
                element.style.whiteSpace = 'pre-line';
            }
        }

        // Auto-test connection on load
        window.addEventListener('load', function() {
            setTimeout(() => {
                testRTSPConnection();
            }, 1000);
        });
    </script>
</body>
</html>
