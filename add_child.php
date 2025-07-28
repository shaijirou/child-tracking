<?php
require_once 'config/config.php';
requireLogin();

// Only admin can add children
if ($_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = sanitizeInput($_POST['student_id']);
    $first_name = sanitizeInput($_POST['first_name']);
    $last_name = sanitizeInput($_POST['last_name']);
    $date_of_birth = sanitizeInput($_POST['date_of_birth']);
    $grade = sanitizeInput($_POST['grade']);
    $device_id = sanitizeInput($_POST['device_id']);
    $emergency_contact = sanitizeInput($_POST['emergency_contact']);
    $medical_info = sanitizeInput($_POST['medical_info']);
    
    // Handle photo upload
    $photo_path = '';
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
        $upload_dir = 'uploads/children/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($file_extension, $allowed_extensions) && $_FILES['photo']['size'] <= MAX_FILE_SIZE) {
            $photo_name = uniqid() . '.' . $file_extension;
            $photo_path = $upload_dir . $photo_name;
            
            if (!move_uploaded_file($_FILES['photo']['tmp_name'], $photo_path)) {
                $error = 'Failed to upload photo.';
                $photo_path = '';
            }
        } else {
            $error = 'Invalid photo format or size too large.';
        }
    }
    
    if (empty($error)) {
        // Validation
        if (empty($student_id) || empty($first_name) || empty($last_name) || empty($date_of_birth) || empty($grade)) {
            $error = 'Please fill in all required fields.';
        } else {
            try {
                // Check if student ID already exists
                $stmt = $pdo->prepare("SELECT id FROM children WHERE student_id = ?");
                $stmt->execute([$student_id]);
                
                if ($stmt->fetch()) {
                    $error = 'Student ID already exists.';
                } else {
                    // Insert new child
                    $stmt = $pdo->prepare("INSERT INTO children (student_id, first_name, last_name, date_of_birth, grade, photo, device_id, emergency_contact, medical_info) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    if ($stmt->execute([$student_id, $first_name, $last_name, $date_of_birth, $grade, $photo_path, $device_id, $emergency_contact, $medical_info])) {
                        $success = 'Child added successfully!';
                        // Clear form
                        $_POST = [];
                    } else {
                        $error = 'Failed to add child. Please try again.';
                    }
                }
            } catch (PDOException $e) {
                $error = 'Database error. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Child - Child Tracking System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="main-content">
            <div class="d-flex justify-between align-center mb-3">
                <h1>Add New Child</h1>
                <a href="children.php" class="btn btn-secondary">Back to Children</a>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Child Information</h2>
                </div>
                
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="student_id" class="form-label">Student ID *</label>
                            <input type="text" id="student_id" name="student_id" class="form-control" value="<?php echo isset($_POST['student_id']) ? htmlspecialchars($_POST['student_id']) : ''; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="grade" class="form-label">Grade *</label>
                            <select id="grade" name="grade" class="form-control" required>
                                <option value="">Select Grade</option>
                                <option value="Pre-K" <?php echo (isset($_POST['grade']) && $_POST['grade'] === 'Pre-K') ? 'selected' : ''; ?>>Pre-K</option>
                                <option value="Kindergarten" <?php echo (isset($_POST['grade']) && $_POST['grade'] === 'Kindergarten') ? 'selected' : ''; ?>>Kindergarten</option>
                                <option value="1st Grade" <?php echo (isset($_POST['grade']) && $_POST['grade'] === '1st Grade') ? 'selected' : ''; ?>>1st Grade</option>
                                <option value="2nd Grade" <?php echo (isset($_POST['grade']) && $_POST['grade'] === '2nd Grade') ? 'selected' : ''; ?>>2nd Grade</option>
                                <option value="3rd Grade" <?php echo (isset($_POST['grade']) && $_POST['grade'] === '3rd Grade') ? 'selected' : ''; ?>>3rd Grade</option>
                                <option value="4th Grade" <?php echo (isset($_POST['grade']) && $_POST['grade'] === '4th Grade') ? 'selected' : ''; ?>>4th Grade</option>
                                <option value="5th Grade" <?php echo (isset($_POST['grade']) && $_POST['grade'] === '5th Grade') ? 'selected' : ''; ?>>5th Grade</option>
                                <option value="6th Grade" <?php echo (isset($_POST['grade']) && $_POST['grade'] === '6th Grade') ? 'selected' : ''; ?>>6th Grade</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name" class="form-label">First Name *</label>
                            <input type="text" id="first_name" name="first_name" class="form-control" value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name" class="form-label">Last Name *</label>
                            <input type="text" id="last_name" name="last_name" class="form-control" value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="date_of_birth" class="form-label">Date of Birth *</label>
                            <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" value="<?php echo isset($_POST['date_of_birth']) ? htmlspecialchars($_POST['date_of_birth']) : ''; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="emergency_contact" class="form-label">Emergency Contact</label>
                            <input type="tel" id="emergency_contact" name="emergency_contact" class="form-control" value="<?php echo isset($_POST['emergency_contact']) ? htmlspecialchars($_POST['emergency_contact']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="device_id" class="form-label">Device ID</label>
                        <input type="text" id="device_id" name="device_id" class="form-control" value="<?php echo isset($_POST['device_id']) ? htmlspecialchars($_POST['device_id']) : ''; ?>" placeholder="GPS device identifier">
                    </div>
                    
                    <div class="form-group">
                        <label for="photo" class="form-label">Photo</label>
                        <input type="file" id="photo" name="photo" class="form-control" accept="image/*">
                        <small>Max file size: 5MB. Supported formats: JPG, PNG, GIF</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="medical_info" class="form-label">Medical Information</label>
                        <textarea id="medical_info" name="medical_info" class="form-control" rows="3" placeholder="Any medical conditions, allergies, or special needs..."><?php echo isset($_POST['medical_info']) ? htmlspecialchars($_POST['medical_info']) : ''; ?></textarea>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Add Child</button>
                        <a href="children.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>
