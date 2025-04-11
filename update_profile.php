<?php
require_once 'config/database.php';
require_once 'includes/auth_check.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    
    // Validate username
    if (empty($username)) {
        $errors[] = "Username is required";
    } elseif (strlen($username) < 3) {
        $errors[] = "Username must be at least 3 characters long";
    } else {
        // Check if username is already taken
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $_SESSION['user_id']]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "Username is already taken";
        }
    }

    // Handle profile picture upload
    $profile_picture = null;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($_FILES['profile_picture']['type'], $allowed_types)) {
            $errors[] = "Invalid file type. Only JPG, PNG, and GIF are allowed.";
        } elseif ($_FILES['profile_picture']['size'] > $max_size) {
            $errors[] = "File size too large. Maximum size is 5MB.";
        } else {
            $upload_dir = 'uploads/profile_pictures/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $extension;
            $target_path = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_path)) {
                $profile_picture = $target_path;
            } else {
                $errors[] = "Failed to upload profile picture";
            }
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            $sql = "UPDATE users SET username = ?, bio = ?";
            $params = [$username, $bio];
            
            if ($profile_picture) {
                $sql .= ", profile_picture = ?";
                $params[] = $profile_picture;
            }
            
            $sql .= " WHERE id = ?";
            $params[] = $_SESSION['user_id'];
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $pdo->commit();
            $success = true;
            
            // Update session username
            $_SESSION['username'] = $username;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Redirect back to profile page with status
$_SESSION['profile_update_errors'] = $errors;
$_SESSION['profile_update_success'] = $success;
header('Location: profile.php');
exit(); 