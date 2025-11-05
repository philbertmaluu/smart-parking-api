<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Camera Diagnostic - Smart Parking</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f7fa;
            color: #333;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        .card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .status-item {
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #4CAF50;
        }
        .status-ok {
            background: #e8f5e8;
            border-left-color: #4CAF50;
        }
        .status-warning {
            background: #fff8e1;
            border-left-color: #ff9800;
        }
        .status-error {
            background: #ffebee;
            border-left-color: #f44336;
        }
        .btn {
            background: #2196F3;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            margin: 8px;
            text-decoration: none;
            display: inline-block;
            font-weight: 500;
            transition: background 0.2s;
        }
        .btn:hover {
            background: #1976D2;
        }
        .btn-success {
            background: #4CAF50;
        }
        .btn-success:hover {
            background: #45a049;
        }
        .btn-warning {
            background: #ff9800;
        }
        .btn-warning:hover {
            background: #e68900;
        }
        pre {
            background: #2d3748;
            color: #f7fafc;
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
            font-size: 14px;
            margin: 15px 0;
        }
        .info-box {
            background: #e3f2fd;
            border: 1px solid #90caf9;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .success-box {
            background: #e8f5e8;
            border: 1px solid #a5d6a7;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .warning-box {
            background: #fff8e1;
            border: 1px solid #ffcc02;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .url-box {
            font-family: monospace;
            background: #f8f9fa;
            padding: 12px;
            border: 2px solid #dee2e6;
            border-radius: 6px;
            margin: 10px 0;
            word-break: break-all;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
        }
        .port-open { color: #4CAF50; font-weight: bold; }
        .port-closed { color: #f44336; }
        .auth-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔧 Camera Diagnostic Tool</h1>
            <p>Comprehensive analysis of your RTSP camera at <strong>192.168.0.109</strong></p>
        </div>

        <div class="card">
            <h2>📊 Connection Status</h2>
            <div class="status-grid">
                <div class="status-item status-ok">
                    <h3>🌐 Network</h3>
                    <p>Camera is reachable via ping</p>
                </div>
                <div class="status-item status-ok">
                    <h3>🌍 HTTP (Port 80)</h3>
                    <p>Web interface is available</p>
                </div>
                <div class="status-item status-ok">
                    <h3>📹 RTSP (Port 554)</h3>
                    <p>RTSP server is responding</p>
                </div>
                <div class="status-item status-warning">
                    <h3>🔐 Authentication</h3>
                    <p>Digest authentication required</p>
                </div>
            </div>
        </div>

        <div class="card">
            <h2>🔐 Authentication Details</h2>
            <div class="auth-info">
                <strong>Discovery:</strong> The camera uses <strong>Digest Authentication</strong> with realm: <code>"@"</code>
                <br><br>
                This is different from basic authentication and requires proper credential handling.
            </div>

            <h3>Quick Access Options:</h3>
            <div style="margin: 20px 0;">
                <a href="http://192.168.0.109" target="_blank" class="btn btn-success">
                    🌍 Open Camera Web Interface
                </a>
                <button onclick="copyRTSPURL()" class="btn">
                    📋 Copy RTSP URL
                </button>
                <button onclick="downloadVLCPlaylist()" class="btn btn-warning">
                    📺 Download VLC Playlist
                </button>
            </div>
        </div>

        <div class="card">
            <h2>🎯 Direct Streaming Methods</h2>
            
            <div class="info-box">
                <h3>Method 1: Camera Web Interface</h3>
                <p>Most IP cameras provide a web interface with live streaming:</p>
                <ol>
                    <li>Click the "Open Camera Web Interface" button above</li>
                    <li>Enter the camera's username and password when prompted</li>
                    <li>Look for "Live View", "Video", or "Stream" sections</li>
                    <li>The web interface often provides multiple stream formats</li>
                </ol>
            </div>

            <div class="success-box">
                <h3>Method 2: VLC Media Player (Recommended)</h3>
                <p>VLC can handle RTSP authentication automatically:</p>
                <ol>
                    <li>Open VLC Media Player</li>
                    <li>Go to <strong>Media → Open Network Stream</strong></li>
                    <li>Enter one of these URLs:</li>
                </ol>
                <div class="url-box">rtsp://192.168.0.109:554/</div>
                <div class="url-box">rtsp://192.168.0.109:554/stream</div>
                <div class="url-box">rtsp://192.168.0.109:554/live</div>
                <p>VLC will prompt for credentials if needed.</p>
            </div>

            <div class="warning-box">
                <h3>Method 3: Browser-Based Streaming</h3>
                <p>For web viewing, the camera needs to provide HTTP-based streams:</p>
                <ul>
                    <li>Check the web interface for MJPEG or WebRTC streams</li>
                    <li>Look for URLs like: <code>/mjpeg</code>, <code>/video.mjpg</code>, <code>/stream</code></li>
                    <li>These can be embedded directly in web pages</li>
                </ul>
            </div>
        </div>

        <div class="card">
            <h2>🧪 Testing Commands</h2>
            <p>Use these commands to test the stream manually:</p>
            
            <h3>FFplay (if you have FFmpeg):</h3>
            <pre>ffplay rtsp://192.168.0.109:554/</pre>
            
            <h3>FFprobe (to get stream info):</h3>
            <pre>ffprobe rtsp://192.168.0.109:554/</pre>
            
            <h3>Curl (to test HTTP streams):</h3>
            <pre>curl -I http://192.168.0.109/mjpeg
curl -I http://192.168.0.109/video.mjpg
curl -I http://192.168.0.109/stream</pre>
        </div>

        <div class="card">
            <h2>📋 Technical Details</h2>
            <table>
                <tr>
                    <th>Parameter</th>
                    <th>Value</th>
                    <th>Status</th>
                </tr>
                <tr>
                    <td>Camera IP</td>
                    <td>192.168.0.109</td>
                    <td>✅ Reachable</td>
                </tr>
                <tr>
                    <td>RTSP Port</td>
                    <td>554</td>
                    <td class="port-open">✅ Open</td>
                </tr>
                <tr>
                    <td>HTTP Port</td>
                    <td>80</td>
                    <td class="port-open">✅ Open</td>
                </tr>
                <tr>
                    <td>Authentication</td>
                    <td>Digest (realm: "@")</td>
                    <td>⚠️ Credentials needed</td>
                </tr>
                <tr>
                    <td>RTSP Methods</td>
                    <td>OPTIONS, DESCRIBE, SETUP, PLAY, GET_PARAMETER, SET_PARAMETER, TEARDOWN</td>
                    <td>✅ Standard compliant</td>
                </tr>
            </table>
        </div>

        <div class="card">
            <h2>🚀 Next Steps</h2>
            <ol>
                <li><strong>Access Web Interface:</strong> Click "Open Camera Web Interface" to find login credentials</li>
                <li><strong>Try VLC:</strong> Download the playlist file and open it in VLC Media Player</li>
                <li><strong>Check Documentation:</strong> Look for camera manual or default credentials</li>
                <li><strong>Network Scan:</strong> Check for camera brand/model to find default credentials</li>
            </ol>

            <div class="info-box">
                <strong>Common Default Credentials:</strong>
                <ul>
                    <li>admin / admin</li>
                    <li>admin / password</li>
                    <li>admin / 123456</li>
                    <li>user / user</li>
                    <li>root / pass</li>
                    <li>admin / (empty password)</li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        function copyRTSPURL() {
            const url = 'rtsp://192.168.0.109:554/';
            navigator.clipboard.writeText(url).then(() => {
                alert('RTSP URL copied to clipboard!');
            }).catch(() => {
                prompt('Copy this RTSP URL:', url);
            });
        }

        function downloadVLCPlaylist() {
            const playlistContent = `#EXTM3U
#EXTINF:-1,Camera Stream (Default Path)
rtsp://192.168.0.109:554/
#EXTINF:-1,Camera Stream (Stream Path)
rtsp://192.168.0.109:554/stream
#EXTINF:-1,Camera Stream (Live Path)
rtsp://192.168.0.109:554/live
#EXTINF:-1,Camera Stream (Stream1 Path)
rtsp://192.168.0.109:554/stream1`;
            
            const blob = new Blob([playlistContent], { type: 'application/vnd.apple.mpegurl' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'camera_streams.m3u';
            a.click();
            URL.revokeObjectURL(url);
        }

        // Auto-refresh status every 30 seconds
        setInterval(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
