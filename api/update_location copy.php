<?php
require_once '../config/config.php';

header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required_fields = ['device_id', 'latitude', 'longitude'];
foreach ($required_fields as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit();
    }
}

$device_id = sanitizeInput($input['device_id']);
$latitude = (float)$input['latitude'];
$longitude = (float)$input['longitude'];
$accuracy = isset($input['accuracy']) ? (float)$input['accuracy'] : null;
$battery_level = isset($input['battery_level']) ? (int)$input['battery_level'] : null;

try {
    // Find child by device ID
    $stmt = $pdo->prepare("SELECT id FROM children WHERE device_id = ? AND status = 'active'");
    $stmt->execute([$device_id]);
    $child = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$child) {
        http_response_code(404);
        echo json_encode(['error' => 'Device not found']);
        exit();
    }
    
    $child_id = $child['id'];
    
    // Check if location is within geofence (simplified - assumes one main geofence)
    $stmt = $pdo->query("SELECT * FROM geofences WHERE status = 'active' ORDER BY id LIMIT 1");
    $geofence = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $inside_geofence = true;
    if ($geofence) {
        // Calculate distance from geofence center
        $distance = calculateDistance($latitude, $longitude, $geofence['center_lat'], $geofence['center_lng']);
        $inside_geofence = $distance <= $geofence['radius'];
        
        // Check if child just left the geofence
        $stmt = $pdo->prepare("SELECT inside_geofence FROM location_tracking WHERE child_id = ? ORDER BY timestamp DESC LIMIT 1");
        $stmt->execute([$child_id]);
        $last_location = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($last_location && $last_location['inside_geofence'] && !$inside_geofence) {
            // Child just left the safe zone - create alert
            $stmt = $pdo->prepare("SELECT first_name, last_name FROM children WHERE id = ?");
            $stmt->execute([$child_id]);
            $child_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $alert_message = "GEOFENCE ALERT: " . $child_info['first_name'] . " " . $child_info['last_name'] . " has left the safe zone.";
            
            // Get parents and teachers
            $stmt = $pdo->prepare("SELECT u.id, u.phone FROM users u 
                                  JOIN parent_child pc ON u.id = pc.parent_id 
                                  WHERE pc.child_id = ? AND u.status = 'active'
                                  UNION
                                  SELECT u.id, u.phone FROM users u 
                                  JOIN teacher_child tc ON u.id = tc.teacher_id 
                                  WHERE tc.child_id = ? AND u.status = 'active'");
            $stmt->execute([$child_id, $child_id]);
            $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $recipient_ids = array_column($recipients, 'id');
            
            // Insert alert
            $stmt = $pdo->prepare("INSERT INTO alerts (child_id, alert_type, message, severity, sent_to) VALUES (?, 'geofence_exit', ?, 'warning', ?)");
            $stmt->execute([$child_id, $alert_message, json_encode($recipient_ids)]);
            
            // Send SMS alerts
            foreach ($recipients as $recipient) {
                if (!empty($recipient['phone'])) {
                    $stmt = $pdo->prepare("INSERT INTO sms_logs (phone_number, message, status) VALUES (?, ?, 'sent')");
                    $stmt->execute([$recipient['phone'], $alert_message]);
                }
            }
        }
    }
    
    // Insert location record
    $stmt = $pdo->prepare("INSERT INTO location_tracking (child_id, latitude, longitude, accuracy, battery_level, inside_geofence) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$child_id, $latitude, $longitude, $accuracy, $battery_level, $inside_geofence]);
    
    // Check for low battery
    if ($battery_level && $battery_level < 20) {
        // Create low battery alert
        $stmt = $pdo->prepare("SELECT first_name, last_name FROM children WHERE id = ?");
        $stmt->execute([$child_id]);
        $child_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $alert_message = "LOW BATTERY ALERT: " . $child_info['first_name'] . " " . $child_info['last_name'] . "'s device battery is at " . $battery_level . "%.";
        
        $stmt = $pdo->prepare("INSERT INTO alerts (child_id, alert_type, message, severity, sent_to) VALUES (?, 'low_battery', ?, 'warning', '[]')");
        $stmt->execute([$child_id, $alert_message]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Location updated successfully',
        'inside_geofence' => $inside_geofence
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}

// Function to calculate distance between two points
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371000; // Earth radius in meters
    
    $lat1_rad = deg2rad($lat1);
    $lon1_rad = deg2rad($lon1);
    $lat2_rad = deg2rad($lat2);
    $lon2_rad = deg2rad($lon2);
    
    $delta_lat = $lat2_rad - $lat1_rad;
    $delta_lon = $lon2_rad - $lon1_rad;
    
    $a = sin($delta_lat / 2) * sin($delta_lat / 2) + cos($lat1_rad) * cos($lat2_rad) * sin($delta_lon / 2) * sin($delta_lon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    
    return $earth_radius * $c;
}
?>
