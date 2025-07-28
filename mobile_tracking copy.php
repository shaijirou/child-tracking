<?php
require_once 'config/config.php';

$error = '';
$success = '';
$child = null; // Initialize the variable
$device_id = isset($_GET['device_id']) ? sanitizeInput($_GET['device_id']) : '';

// Verify device exists
if ($device_id) {
    try {
        $stmt = $pdo->prepare("SELECT c.id, c.first_name, c.last_name, c.student_id FROM children c WHERE c.device_id = ? AND c.status = 'active'");
        $stmt->execute([$device_id]);
        $child = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$child) {
            $error = 'Device not found in system. Please check your IMEI number.';
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mobile GPS Tracker</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .tracker-container {
            max-width: 400px;
            margin: 0 auto;
            padding: 20px;
        }
        .status-indicator {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 10px;
        }
        .status-active { background-color: #28a745; }
        .status-inactive { background-color: #dc3545; }
        .status-warning { background-color: #ffc107; }
        .location-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        .big-button {
            width: 100%;
            padding: 15px;
            font-size: 18px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="tracker-container">
        <div class="card">
            <div class="card-header text-center">
                <h1>📱 Mobile GPS Tracker</h1>
                <?php if ($child): ?>
                    <h2><?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?></h2>
                    <p>Student ID: <?php echo htmlspecialchars($child['student_id']); ?></p>
                <?php endif; ?>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (!$device_id): ?>
                <div class="alert alert-warning">
                    <strong>Setup Required:</strong><br>
                    Add your device ID to the URL:<br>
                    <code>mobile_tracker.php?device_id=YOUR_IMEI</code>
                </div>
                
                <div class="form-group">
                    <label for="manual_device_id" class="form-label">Enter Your Device IMEI:</label>
                    <input type="text" id="manual_device_id" class="form-control" placeholder="Enter IMEI number">
                    <button onclick="setDeviceId()" class="btn btn-primary mt-2">Set Device ID</button>
                </div>
            <?php elseif ($child): ?>
                
                <div class="location-info">
                    <div class="d-flex align-center mb-2">
                        <span id="status-indicator" class="status-indicator status-inactive"></span>
                        <strong id="status-text">GPS Status: Inactive</strong>
                    </div>
                    
                    <div id="location-display">
                        <p><strong>Latitude:</strong> <span id="current-lat">Not available</span></p>
                        <p><strong>Longitude:</strong> <span id="current-lng">Not available</span></p>
                        <p><strong>Accuracy:</strong> <span id="current-accuracy">Not available</span></p>
                        <p><strong>Last Update:</strong> <span id="last-update">Never</span></p>
                    </div>
                </div>
                
                <button id="start-tracking" onclick="startTracking()" class="btn btn-success big-button">
                    🎯 Start Location Tracking
                </button>
                
                <button id="stop-tracking" onclick="stopTracking()" class="btn btn-danger big-button" style="display: none;">
                    ⏹️ Stop Tracking
                </button>
                
                <button onclick="sendLocationOnce()" class="btn btn-primary big-button">
                    📍 Send Location Once
                </button>
                
                <div class="mt-3">
                    <h4>Tracking Settings:</h4>
                    <div class="form-group">
                        <label for="update-interval">Update Interval (seconds):</label>
                        <select id="update-interval" class="form-control">
                            <option value="30">30 seconds</option>
                            <option value="60" selected>1 minute</option>
                            <option value="300">5 minutes</option>
                            <option value="600">10 minutes</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="high-accuracy" checked> High Accuracy GPS
                        </label>
                    </div>
                </div>
                
                <div id="log-container" class="mt-3">
                    <h4>Activity Log:</h4>
                    <div id="activity-log" style="max-height: 200px; overflow-y: auto; background: #f8f9fa; padding: 10px; border-radius: 4px; font-size: 12px;">
                        <p>Ready to start tracking...</p>
                    </div>
                </div>
                
            <?php endif; ?>
        </div>
    </div>

    <script>
        let trackingInterval = null;
        let watchId = null;
        const deviceId = '<?php echo $device_id; ?>';
        
        function log(message) {
            const logContainer = document.getElementById('activity-log');
            const timestamp = new Date().toLocaleTimeString();
            logContainer.innerHTML += `<p>[${timestamp}] ${message}</p>`;
            logContainer.scrollTop = logContainer.scrollHeight;
        }
        
        function updateStatus(status, text) {
            const indicator = document.getElementById('status-indicator');
            const statusText = document.getElementById('status-text');
            
            indicator.className = `status-indicator status-${status}`;
            statusText.textContent = text;
        }
        
        function updateLocationDisplay(position) {
            document.getElementById('current-lat').textContent = position.coords.latitude.toFixed(6);
            document.getElementById('current-lng').textContent = position.coords.longitude.toFixed(6);
            document.getElementById('current-accuracy').textContent = position.coords.accuracy.toFixed(1) + 'm';
            document.getElementById('last-update').textContent = new Date().toLocaleString();
        }
        
        function sendLocationToServer(position) {
            const locationData = {
                device_id: deviceId,
                latitude: position.coords.latitude,
                longitude: position.coords.longitude,
                accuracy: position.coords.accuracy,
                battery_level: getBatteryLevel()
            };
            
            log(`Sending location: ${position.coords.latitude.toFixed(6)}, ${position.coords.longitude.toFixed(6)}`);
            
            fetch('api/update_location.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(locationData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    log('✅ Location sent successfully');
                    updateStatus('active', 'GPS Status: Active & Sending');
                } else {
                    log('❌ Failed to send location: ' + (data.error || 'Unknown error'));
                    updateStatus('warning', 'GPS Status: Error Sending');
                }
            })
            .catch(error => {
                log('❌ Network error: ' + error.message);
                updateStatus('warning', 'GPS Status: Network Error');
            });
        }
        
        function getBatteryLevel() {
            // Try to get battery level (may not work on all browsers)
            if ('getBattery' in navigator) {
                navigator.getBattery().then(function(battery) {
                    return Math.round(battery.level * 100);
                });
            }
            return null;
        }
        
        function startTracking() {
            if (!navigator.geolocation) {
                alert('Geolocation is not supported by this browser.');
                return;
            }
            
            const options = {
                enableHighAccuracy: document.getElementById('high-accuracy').checked,
                timeout: 10000,
                maximumAge: 0
            };
            
            log('🎯 Starting GPS tracking...');
            updateStatus('warning', 'GPS Status: Starting...');
            
            // Start watching position
            watchId = navigator.geolocation.watchPosition(
                function(position) {
                    updateLocationDisplay(position);
                    sendLocationToServer(position);
                },
                function(error) {
                    log('❌ GPS Error: ' + error.message);
                    updateStatus('inactive', 'GPS Status: Error - ' + error.message);
                },
                options
            );
            
            // Set up interval for regular updates
            const interval = parseInt(document.getElementById('update-interval').value) * 1000;
            trackingInterval = setInterval(() => {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        updateLocationDisplay(position);
                        sendLocationToServer(position);
                    },
                    function(error) {
                        log('❌ GPS Error: ' + error.message);
                    },
                    options
                );
            }, interval);
            
            // Update UI
            document.getElementById('start-tracking').style.display = 'none';
            document.getElementById('stop-tracking').style.display = 'block';
            
            log('✅ Tracking started with ' + (interval/1000) + 's intervals');
        }
        
        function stopTracking() {
            if (trackingInterval) {
                clearInterval(trackingInterval);
                trackingInterval = null;
            }
            
            if (watchId) {
                navigator.geolocation.clearWatch(watchId);
                watchId = null;
            }
            
            updateStatus('inactive', 'GPS Status: Stopped');
            
            // Update UI
            document.getElementById('start-tracking').style.display = 'block';
            document.getElementById('stop-tracking').style.display = 'none';
            
            log('⏹️ Tracking stopped');
        }
        
        function sendLocationOnce() {
            if (!navigator.geolocation) {
                alert('Geolocation is not supported by this browser.');
                return;
            }
            
            log('📍 Getting current location...');
            updateStatus('warning', 'GPS Status: Getting Location...');
            
            const options = {
                enableHighAccuracy: document.getElementById('high-accuracy').checked,
                timeout: 10000,
                maximumAge: 0
            };
            
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    updateLocationDisplay(position);
                    sendLocationToServer(position);
                },
                function(error) {
                    log('❌ GPS Error: ' + error.message);
                    updateStatus('inactive', 'GPS Status: Error - ' + error.message);
                    alert('Failed to get location: ' + error.message);
                },
                options
            );
        }
        
        function setDeviceId() {
            const deviceId = document.getElementById('manual_device_id').value.trim();
            if (deviceId) {
                window.location.href = 'mobile_tracker.php?device_id=' + encodeURIComponent(deviceId);
            } else {
                alert('Please enter a device ID');
            }
        }
        
        // Auto-start tracking when page loads (optional)
        document.addEventListener('DOMContentLoaded', function() {
            // Uncomment the line below to auto-start tracking
            // setTimeout(startTracking, 2000);
        });
        
        // Handle page visibility changes to pause/resume tracking
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                log('📱 App moved to background');
            } else {
                log('📱 App moved to foreground');
            }
        });
    </script>
</body>
</html>
