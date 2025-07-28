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
$child_filter = isset($_GET['child_id']) ? (int)$_GET['child_id'] : 0;

try {
    // Get parent's children for filter dropdown
    $stmt = $pdo->prepare("SELECT c.id, c.first_name, c.last_name 
                          FROM children c 
                          JOIN parent_child pc ON c.id = pc.child_id 
                          WHERE pc.parent_id = ? AND c.status = 'active'
                          ORDER BY c.first_name, c.last_name");
    $stmt->execute([$parent_id]);
    $children = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get cases for parent's children
    $query = "SELECT mc.*, c.first_name, c.last_name, c.student_id, c.photo,
              (SELECT COUNT(*) FROM alerts a WHERE a.case_id = mc.id) as alert_count
              FROM missing_cases mc 
              JOIN children c ON mc.child_id = c.id 
              JOIN parent_child pc ON c.id = pc.child_id 
              WHERE pc.parent_id = ?";
    
    $params = [$parent_id];
    
    if ($child_filter > 0) {
        $query .= " AND c.id = ?";
        $params[] = $child_filter;
    }
    
    $query .= " ORDER BY mc.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = 'Failed to load cases data.';
    $cases = [];
    $children = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Cases - Child Tracking System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .cases-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .filter-section {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .case-card {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            border-left: 5px solid #dc3545;
        }
        
        .case-card.resolved {
            border-left-color: #28a745;
        }
        
        .case-card.closed {
            border-left-color: #6c757d;
        }
        
        .case-header {
            display: flex;
            justify-content: between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
            gap: 2rem;
        }
        
        .case-main-info {
            flex: 1;
        }
        
        .case-child-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .case-child-photo {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #ddd;
        }
        
        .case-child-photo-placeholder {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: #666;
        }
        
        .case-badges {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        
        .case-details {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        
        .case-timeline {
            border-left: 3px solid #dee2e6;
            padding-left: 1rem;
            margin-left: 1rem;
        }
        
        .timeline-item {
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }
        
        .timeline-item:last-child {
            border-bottom: none;
        }
        
        .timeline-date {
            font-size: 0.85rem;
            color: #666;
            font-weight: 600;
        }
        
        .timeline-content {
            margin-top: 0.5rem;
            color: #333;
        }
        
        .case-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card-cases {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number-cases {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .stat-label-cases {
            color: #666;
            font-size: 0.9rem;
        }
        
        .no-cases-message {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        @media (max-width: 768px) {
            .case-header {
                flex-direction: column;
                gap: 1rem;
            }
            
            .case-actions {
                justify-content: center;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="cases-container">
            <div class="d-flex justify-between align-center mb-3">
                <h1>My Cases</h1>
                <a href="parent_dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card-cases">
                    <div class="stat-number-cases" style="color: #dc3545;">
                        <?php echo count(array_filter($cases, function($case) { return $case['status'] === 'active'; })); ?>
                    </div>
                    <div class="stat-label-cases">Active Cases</div>
                </div>
                
                <div class="stat-card-cases">
                    <div class="stat-number-cases" style="color: #28a745;">
                        <?php echo count(array_filter($cases, function($case) { return $case['status'] === 'resolved'; })); ?>
                    </div>
                    <div class="stat-label-cases">Resolved Cases</div>
                </div>
                
                <div class="stat-card-cases">
                    <div class="stat-number-cases" style="color: #ffc107;">
                        <?php echo count(array_filter($cases, function($case) { return $case['priority'] === 'critical'; })); ?>
                    </div>
                    <div class="stat-label-cases">Critical Priority</div>
                </div>
                
                <div class="stat-card-cases">
                    <div class="stat-number-cases" style="color: #17a2b8;">
                        <?php echo count($cases); ?>
                    </div>
                    <div class="stat-label-cases">Total Cases</div>
                </div>
            </div>
            
            <!-- Filter Section -->
            <?php if (!empty($children)): ?>
            <div class="filter-section">
                <form method="GET" action="">
                    <div class="d-flex gap-2 align-center">
                        <label for="child_filter">Filter by Child:</label>
                        <select name="child_id" id="child_filter" class="form-control" style="width: auto;">
                            <option value="0">All Children</option>
                            <?php foreach ($children as $child): ?>
                                <option value="<?php echo $child['id']; ?>" <?php echo $child_filter == $child['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <?php if ($child_filter > 0): ?>
                            <a href="my_cases.php" class="btn btn-secondary">Clear Filter</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            <?php endif; ?>
            
            <?php if (empty($cases)): ?>
                <div class="no-cases-message">
                    <h2>No Cases Found</h2>
                    <?php if ($child_filter > 0): ?>
                        <p>No cases found for the selected child.</p>
                        <a href="my_cases.php" class="btn btn-primary">View All Cases</a>
                    <?php else: ?>
                        <p>Great news! There are no missing child cases for your children.</p>
                        <p>This means all your children are safe and accounted for.</p>
                    <?php endif; ?>
                    <a href="parent_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                </div>
            <?php else: ?>
                <?php foreach ($cases as $case): ?>
                <div class="case-card <?php echo $case['status']; ?>">
                    <div class="case-header">
                        <div class="case-main-info">
                            <!-- Child Information -->
                            <div class="case-child-info">
                                <?php if ($case['photo']): ?>
                                    <img src="<?php echo htmlspecialchars($case['photo']); ?>" alt="Child Photo" class="case-child-photo">
                                <?php else: ?>
                                    <div class="case-child-photo-placeholder">No Photo</div>
                                <?php endif; ?>
                                <div>
                                    <h3 style="margin: 0;"><?php echo htmlspecialchars($case['first_name'] . ' ' . $case['last_name']); ?></h3>
                                    <small>Student ID: <?php echo htmlspecialchars($case['student_id']); ?></small>
                                </div>
                            </div>
                            
                            <!-- Case Badges -->
                            <div class="case-badges">
                                <span class="badge badge-primary">Case #<?php echo htmlspecialchars($case['case_number']); ?></span>
                                <span class="badge badge-<?php echo $case['status'] === 'active' ? 'danger' : ($case['status'] === 'resolved' ? 'success' : 'secondary'); ?>">
                                    <?php echo ucfirst($case['status']); ?>
                                </span>
                                <span class="badge badge-<?php echo $case['priority'] === 'critical' ? 'danger' : ($case['priority'] === 'high' ? 'warning' : 'info'); ?>">
                                    <?php echo ucfirst($case['priority']); ?> Priority
                                </span>
                                <?php if ($case['alert_count'] > 0): ?>
                                    <span class="badge badge-warning"><?php echo $case['alert_count']; ?> Alert(s)</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div style="text-align: right; color: #666; font-size: 0.9rem;">
                            <div><strong>Reported:</strong></div>
                            <div><?php echo date('M j, Y', strtotime($case['created_at'])); ?></div>
                            <div><?php echo date('g:i A', strtotime($case['created_at'])); ?></div>
                        </div>
                    </div>
                    
                    <!-- Case Details -->
                    <div class="case-details">
                        <h4>Case Details</h4>
                        <div style="margin-bottom: 1rem;">
                            <strong>Last Seen Location:</strong> <?php echo htmlspecialchars($case['last_seen_location']); ?>
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <strong>Last Seen Time:</strong> <?php echo date('M j, Y g:i A', strtotime($case['last_seen_time'])); ?>
                        </div>
                        <?php if ($case['description']): ?>
                        <div style="margin-bottom: 1rem;">
                            <strong>Description:</strong><br>
                            <?php echo nl2br(htmlspecialchars($case['description'])); ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($case['circumstances']): ?>
                        <div style="margin-bottom: 1rem;">
                            <strong>Circumstances:</strong><br>
                            <?php echo nl2br(htmlspecialchars($case['circumstances'])); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Case Timeline -->
                    <div>
                        <h4>Case Timeline</h4>
                        <div class="case-timeline">
                            <div class="timeline-item">
                                <div class="timeline-date"><?php echo date('M j, Y g:i A', strtotime($case['created_at'])); ?></div>
                                <div class="timeline-content">Case reported and investigation started</div>
                            </div>
                            
                            <?php if ($case['status'] === 'resolved' && $case['resolved_at']): ?>
                            <div class="timeline-item">
                                <div class="timeline-date"><?php echo date('M j, Y g:i A', strtotime($case['resolved_at'])); ?></div>
                                <div class="timeline-content">
                                    <strong>Case Resolved</strong>
                                    <?php if ($case['resolution_notes']): ?>
                                        <br><?php echo nl2br(htmlspecialchars($case['resolution_notes'])); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Case Actions -->
                    <div class="case-actions">
                        <a href="case_details.php?case=<?php echo urlencode($case['case_number']); ?>" class="btn btn-primary">
                            📋 View Full Details
                        </a>
                        <a href="track_child.php?id=<?php echo $case['child_id']; ?>" class="btn btn-success">
                            📍 Track Child
                        </a>
                        <?php if ($case['alert_count'] > 0): ?>
                            <a href="alerts.php?case_id=<?php echo $case['id']; ?>" class="btn btn-warning">
                                🔔 View Alerts (<?php echo $case['alert_count']; ?>)
                            </a>
                        <?php endif; ?>
                        <?php if ($case['status'] === 'active'): ?>
                            <a href="emergency_contacts.php" class="btn btn-danger">
                                📞 Emergency Contacts
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script>
        // Auto-refresh active cases every 60 seconds
        setInterval(function() {
            const activeCases = document.querySelectorAll('.case-card:not(.resolved):not(.closed)');
            if (activeCases.length > 0) {
                // In a real implementation, you would fetch updated case data via AJAX
                console.log('Checking for case updates...');
            }
        }, 60000);
        
        // Highlight urgent cases
        document.addEventListener('DOMContentLoaded', function() {
            const criticalCases = document.querySelectorAll('.case-card');
            criticalCases.forEach(caseCard => {
                const priorityBadge = caseCard.querySelector('.badge');
                if (priorityBadge && priorityBadge.textContent.includes('Critical')) {
                    caseCard.style.boxShadow = '0 2px 15px rgba(220, 53, 69, 0.3)';
                    caseCard.style.borderLeftWidth = '8px';
                }
            });
        });
        
        // Add notification for active cases
        const activeCasesCount = <?php echo count(array_filter($cases, function($case) { return $case['status'] === 'active'; })); ?>;
        if (activeCasesCount > 0) {
            console.log(`You have ${activeCasesCount} active case(s) that require attention.`);
        }
    </script>
</body>
</html>
