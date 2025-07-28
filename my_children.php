<?php
require_once 'config/config.php';
requireLogin();

// Ensure only parents can access this page
if ($_SESSION['role'] !== 'parent') {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$parent_id = $_SESSION['user_id'];

try {
    // Get parent's children with detailed information
    $stmt = $pdo->prepare("SELECT c.*, 
                          (SELECT COUNT(*) FROM missing_cases mc WHERE mc.child_id = c.id AND mc.status = 'active') as active_cases,
                          (SELECT COUNT(*) FROM alerts a WHERE a.child_id = c.id AND a.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as recent_alerts,
                          (SELECT lt.timestamp FROM location_tracking lt WHERE lt.child_id = c.id ORDER BY timestamp DESC LIMIT 1) as last_location_update,
                          (SELECT lt.latitude FROM location_tracking lt WHERE lt.child_id = c.id ORDER BY timestamp DESC LIMIT 1) as last_latitude,
                          (SELECT lt.longitude FROM location_tracking lt WHERE lt.child_id = c.id ORDER BY timestamp DESC LIMIT 1) as last_longitude,
                          (SELECT lt.inside_geofence FROM location_tracking lt WHERE lt.child_id = c.id ORDER BY timestamp DESC LIMIT 1) as inside_safe_zone
                          FROM children c 
                          JOIN parent_child pc ON c.id = pc.child_id 
                          WHERE pc.parent_id = ? AND c.status = 'active'
                          ORDER BY c.first_name, c.last_name");
    $stmt->execute([$parent_id]);
    $children = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = 'Failed to load children data.';
    $children = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Children - Child Tracking System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .children-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .child-detailed-card {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            border: 1px solid #e0e0e0;
        }
        
        .child-header-detailed {
            display: flex;
            align-items: center;
            gap: 2rem;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .child-photo-large {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #667eea;
        }
        
        .child-photo-placeholder-large {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            color: #666;
            border: 4px solid #667eea;
        }
        
        .child-main-info h2 {
            margin: 0 0 0.5rem 0;
            color: #333;
            font-size: 1.5rem;
        }
        
        .child-basic-details {
            color: #666;
            font-size: 1rem;
            line-height: 1.5;
        }
        
        .child-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .detail-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        
        .detail-section h3 {
            margin: 0 0 1rem 0;
            color: #333;
            font-size: 1.1rem;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
        .detail-label {
            font-weight: 600;
            color: #555;
        }
        
        .detail-value {
            color: #333;
        }
        
        .status-section {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        
        .status-badge-large {
            padding: 0.75rem 1rem;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .status-safe-large {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-warning-large {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .status-danger-large {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .actions-section {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .location-preview {
            background: #e3f2fd;
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
            border-left: 4px solid #2196f3;
        }
        
        .no-children-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        
        @media (max-width: 768px) {
            .child-header-detailed {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }
            
            .child-details-grid {
                grid-template-columns: 1fr;
            }
            
            .status-section {
                justify-content: center;
            }
            
            .actions-section {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="children-container">
            <div class="d-flex justify-between align-center mb-3">
                <h1>My Children</h1>
                <a href="parent_dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (empty($children)): ?>
                <div class="no-children-state">
                    <h2>No Children Registered</h2>
                    <p>You don't have any children registered in the tracking system yet.</p>
                    <p>Please contact the school administration to register your children and set up tracking devices.</p>
                    <div style="margin-top: 2rem;">
                        <a href="contact.php" class="btn btn-primary">Contact School Administration</a>
                        <a href="parent_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($children as $child): ?>
                <div class="child-detailed-card">
                    <!-- Child Header -->
                    <div class="child-header-detailed">
                        <?php if ($child['photo']): ?>
                            <img src="<?php echo htmlspecialchars($child['photo']); ?>" alt="Child Photo" class="child-photo-large">
                        <?php else: ?>
                            <div class="child-photo-placeholder-large">No Photo</div>
                        <?php endif; ?>
                        
                        <div class="child-main-info">
                            <h2><?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?></h2>
                            <div class="child-basic-details">
                                <strong>Student ID:</strong> <?php echo htmlspecialchars($child['student_id']); ?><br>
                                <strong>Grade:</strong> <?php echo htmlspecialchars($child['grade']); ?><br>
                                <strong>Age:</strong> <?php 
                                    $age = date_diff(date_create($child['date_of_birth']), date_create('today'))->y;
                                    echo $age . ' years old';
                                ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Status Badges -->
                    <div class="status-section">
                        <div class="status-badge-large <?php echo $child['device_id'] ? 'status-safe-large' : 'status-warning-large'; ?>">
                            <?php if ($child['device_id']): ?>
                                ✅ Device Connected
                            <?php else: ?>
                                ⚠️ No Tracking Device
                            <?php endif; ?>
                        </div>
                        
                        <div class="status-badge-large <?php echo $child['active_cases'] > 0 ? 'status-danger-large' : 'status-safe-large'; ?>">
                            <?php if ($child['active_cases'] > 0): ?>
                                🚨 <?php echo $child['active_cases']; ?> Active Case(s)
                            <?php else: ?>
                                ✅ No Active Cases
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($child['inside_safe_zone'] !== null): ?>
                        <div class="status-badge-large <?php echo $child['inside_safe_zone'] ? 'status-safe-large' : 'status-warning-large'; ?>">
                            <?php if ($child['inside_safe_zone']): ?>
                                🏫 Inside Safe Zone
                            <?php else: ?>
                                📍 Outside Safe Zone
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($child['recent_alerts'] > 0): ?>
                        <div class="status-badge-large status-warning-large">
                            🔔 <?php echo $child['recent_alerts']; ?> Alert(s) Today
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Detailed Information -->
                    <div class="child-details-grid">
                        <!-- Personal Information -->
                        <div class="detail-section">
                            <h3>📋 Personal Information</h3>
                            <div class="detail-item">
                                <span class="detail-label">Full Name:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Date of Birth:</span>
                                <span class="detail-value"><?php echo date('M j, Y', strtotime($child['date_of_birth'])); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Gender:</span>
                                <span class="detail-value"><?php echo ucfirst($child['gender']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Blood Type:</span>
                                <span class="detail-value"><?php echo $child['blood_type'] ?: 'Not specified'; ?></span>
                            </div>
                        </div>
                        
                        <!-- School Information -->
                        <div class="detail-section">
                            <h3>🏫 School Information</h3>
                            <div class="detail-item">
                                <span class="detail-label">Student ID:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($child['student_id']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Grade Level:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($child['grade']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Status:</span>
                                <span class="detail-value">
                                    <span class="badge badge-success"><?php echo ucfirst($child['status']); ?></span>
                                </span>
                            </div>
                        </div>
                        
                        <!-- Tracking Information -->
                        <div class="detail-section">
                            <h3>📱 Tracking Information</h3>
                            <div class="detail-item">
                                <span class="detail-label">Device Status:</span>
                                <span class="detail-value">
                                    <?php if ($child['device_id']): ?>
                                        <span class="badge badge-success">Connected</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Not Connected</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php if ($child['device_id']): ?>
                            <div class="detail-item">
                                <span class="detail-label">Device ID:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($child['device_id']); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="detail-item">
                                <span class="detail-label">Last Update:</span>
                                <span class="detail-value">
                                    <?php if ($child['last_location_update']): ?>
                                        <?php echo date('M j, Y g:i A', strtotime($child['last_location_update'])); ?>
                                    <?php else: ?>
                                        No location data
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                        
                        <!-- Emergency Information -->
                        <div class="detail-section">
                            <h3>🚨 Safety Information</h3>
                            <div class="detail-item">
                                <span class="detail-label">Active Cases:</span>
                                <span class="detail-value">
                                    <?php if ($child['active_cases'] > 0): ?>
                                        <span class="badge badge-danger"><?php echo $child['active_cases']; ?> Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">None</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Recent Alerts:</span>
                                <span class="detail-value">
                                    <?php if ($child['recent_alerts'] > 0): ?>
                                        <span class="badge badge-warning"><?php echo $child['recent_alerts']; ?> Today</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">None Today</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php if ($child['medical_conditions']): ?>
                            <div class="detail-item">
                                <span class="detail-label">Medical Notes:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($child['medical_conditions']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Location Preview -->
                    <?php if ($child['last_latitude'] && $child['last_longitude']): ?>
                    <div class="location-preview">
                        <h4>📍 Last Known Location</h4>
                        <p>
                            <strong>Coordinates:</strong> <?php echo $child['last_latitude']; ?>, <?php echo $child['last_longitude']; ?><br>
                            <strong>Updated:</strong> <?php echo date('M j, Y g:i A', strtotime($child['last_location_update'])); ?><br>
                            <strong>Safe Zone Status:</strong> 
                            <?php if ($child['inside_safe_zone']): ?>
                                <span class="badge badge-success">Inside Safe Zone</span>
                            <?php else: ?>
                                <span class="badge badge-warning">Outside Safe Zone</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Action Buttons -->
                    <div class="actions-section">
                        <a href="track_child.php?id=<?php echo $child['id']; ?>" class="btn btn-success">
                            📍 Track Real-time Location
                        </a>
                        <a href="child_profile.php?id=<?php echo $child['id']; ?>" class="btn btn-primary">
                            👤 View Full Profile
                        </a>
                        <?php if ($child['active_cases'] > 0): ?>
                            <a href="my_cases.php?child_id=<?php echo $child['id']; ?>" class="btn btn-danger">
                                🚨 View Active Cases
                            </a>
                        <?php endif; ?>
                        <?php if ($child['recent_alerts'] > 0): ?>
                            <a href="alerts.php?child_id=<?php echo $child['id']; ?>" class="btn btn-warning">
                                🔔 View Recent Alerts
                            </a>
                        <?php endif; ?>
                        <a href="location_history.php?id=<?php echo $child['id']; ?>" class="btn btn-info">
                            📊 Location History
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script>
        // Auto-refresh location data every 30 seconds
        setInterval(function() {
            // Update last location timestamps
            document.querySelectorAll('[data-child-id]').forEach(element => {
                const childId = element.dataset.childId;
                // In a real implementation, you would fetch updated data via AJAX
                console.log('Checking updates for child:', childId);
            });
        }, 30000);
        
        // Add smooth scrolling for better UX
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>
