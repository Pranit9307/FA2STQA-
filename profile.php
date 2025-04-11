<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth_check.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user data
$stmt = $pdo->prepare("
    SELECT 
        u.*,
        (SELECT COUNT(*) FROM events e WHERE e.manager_id = u.id) as events_created,
        (SELECT COUNT(DISTINCT r.event_id) FROM rsvps r WHERE r.user_id = u.id AND r.status = 'confirmed') as events_attended,
        (SELECT COUNT(*) FROM follows WHERE followed_id = u.id) as followers_count,
        (SELECT COUNT(*) FROM follows WHERE follower_id = u.id) as following_count
    FROM users u
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user's recent events (only for event managers)
if ($user['role'] === 'event_manager') {
    $stmt = $pdo->prepare("
        SELECT e.*, c.name as category_name
        FROM events e
        LEFT JOIN categories c ON e.category_id = c.id
        WHERE e.manager_id = ?
        ORDER BY e.date DESC
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $recent_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $recent_events = [];
}

// Get upcoming events user is attending
$stmt = $pdo->prepare("
    SELECT e.*, c.name as category_name
    FROM events e
    JOIN rsvps r ON e.id = r.event_id
    LEFT JOIN categories c ON e.category_id = c.id
    WHERE r.user_id = ? AND r.status = 'confirmed' AND e.date >= CURDATE()
    ORDER BY e.date ASC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$upcoming_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $bio = $_POST['bio'] ?? '';
    
    try {
        // Handle profile picture upload
        $profile_picture = $user['profile_picture']; // Keep existing picture by default
        
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $file_type = $_FILES['profile_picture']['type'];
            
            if (!in_array($file_type, $allowed_types)) {
                throw new Exception("Invalid file type. Only JPG, PNG, and GIF are allowed.");
            }
            
            $upload_dir = 'uploads/profile_pictures/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
            $new_filename = uniqid() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                // Delete old profile picture if it exists and is not the default
                if ($profile_picture && $profile_picture !== '/SDL_final/assets/images/default-avatar.png') {
                    @unlink($profile_picture);
                }
                $profile_picture = $upload_path;
            } else {
                throw new Exception("Failed to upload profile picture");
            }
        }
        
        // Update user profile
        $stmt = $pdo->prepare("
            UPDATE users 
            SET username = ?, email = ?, bio = ?, profile_picture = ?
            WHERE id = ?
        ");
        $stmt->execute([$username, $email, $bio, $profile_picture, $_SESSION['user_id']]);
        
        // Refresh user data
        $stmt = $pdo->prepare("
            SELECT 
                u.*,
                (SELECT COUNT(*) FROM events e WHERE e.manager_id = u.id) as events_created,
                (SELECT COUNT(DISTINCT r.event_id) FROM rsvps r WHERE r.user_id = u.id AND r.status = 'confirmed') as events_attended,
                (SELECT COUNT(*) FROM follows WHERE followed_id = u.id) as followers_count,
                (SELECT COUNT(*) FROM follows WHERE follower_id = u.id) as following_count
            FROM users u
            WHERE u.id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Store success message in session
        $_SESSION['success_message'] = "Profile updated successfully!";
        
        // Redirect to prevent form resubmission
        header("Location: profile.php?id=" . $_SESSION['user_id']);
        exit();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Display success message if it exists
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // Clear the message after displaying
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - EventHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/SDL_final/assets/css/style.css">
    <style>
        .profile-header {
            background: linear-gradient(135deg, #6B73FF 0%, #000DFF 100%);
            color: white;
            padding: 4rem 0;
            margin-bottom: 2rem;
            position: relative;
        }
        .profile-avatar {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            border: 5px solid white;
            object-fit: cover;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .profile-stats {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .stat-item {
            text-align: center;
            padding: 1.5rem;
            border-right: 1px solid #eee;
        }
        .stat-item:last-child {
            border-right: none;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #000DFF;
        }
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            text-transform: uppercase;
        }
        .profile-section {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .event-card {
            border: none;
            border-radius: 12px;
            transition: transform 0.2s;
            margin-bottom: 1rem;
            background: #f8f9fa;
        }
        .event-card:hover {
            transform: translateY(-5px);
        }
        .event-date {
            background: white;
            padding: 0.8rem;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            color: #000DFF;
        }
        .edit-profile-btn {
            background: white;
            color: #000DFF;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
        }
        .edit-profile-btn:hover {
            background: #000DFF;
            color: white;
        }
        .social-links {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        .social-link {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            transition: all 0.3s ease;
        }
        .social-link:hover {
            background: white;
            color: #000DFF;
            transform: translateY(-3px);
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="profile-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-2 text-center">
                    <img src="<?php echo isset($user['profile_picture']) && !empty($user['profile_picture']) ? htmlspecialchars($user['profile_picture']) : '/SDL_final/assets/images/default-avatar.png'; ?>" 
                         alt="Profile Picture" class="profile-avatar">
                </div>
                <div class="col-md-6">
                    <h1 class="mb-2"><?php echo htmlspecialchars($user['username']); ?></h1>
                    <p class="mb-0"><?php echo htmlspecialchars($user['email']); ?></p>
                    <p class="mt-2"><?php echo !empty($user['bio']) ? htmlspecialchars($user['bio']) : 'No bio added yet.'; ?></p>
                </div>
                <div class="col-md-4 text-end">
                    <button class="btn edit-profile-btn" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                        <i class="fas fa-edit me-2"></i> Edit Profile
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="row">
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-4">
                            <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" 
                                 alt="Profile Picture" 
                                 class="rounded-circle me-3" 
                                 style="width: 100px; height: 100px; object-fit: cover;">
                            <div>
                                <h4 class="mb-1"><?php echo htmlspecialchars($user['username']); ?></h4>
                                <p class="text-muted mb-2"><?php echo htmlspecialchars($user['email']); ?></p>
                                <p class="mb-0"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></p>
                            </div>
                        </div>
                        <div class="row text-center">
                            <?php if ($user['role'] === 'event_manager'): ?>
                            <div class="col">
                                <h5 class="mb-0"><?php echo $user['events_created']; ?></h5>
                                <small class="text-muted">Events Created</small>
                            </div>
                            <?php endif; ?>
                            <div class="col">
                                <h5 class="mb-0"><?php echo $user['events_attended']; ?></h5>
                                <small class="text-muted">Events Registered</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="profile-section">
                    <h4 class="mb-4">Account Details</h4>
                    <div class="mb-3">
                        <label class="text-muted">Email</label>
                        <p class="mb-0"><?php echo htmlspecialchars($user['email']); ?></p>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted">Role</label>
                        <p class="mb-0">
                            <span class="badge bg-primary"><?php echo htmlspecialchars($user['role']); ?></span>
                        </p>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted">Member Since</label>
                        <p class="mb-0"><?php echo date('F Y', strtotime($user['created_at'])); ?></p>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <?php if ($user['role'] === 'event_manager'): ?>
                    <div class="profile-section">
                        <h4 class="mb-4">Recent Events Created</h4>
                        <?php if (empty($recent_events)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-calendar-plus fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No events created yet.</p>
                                <a href="create_event.php" class="btn btn-primary">Create Your First Event</a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_events as $event): ?>
                                <div class="card event-card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h5 class="card-title mb-2"><?php echo htmlspecialchars($event['title']); ?></h5>
                                                <p class="card-text text-muted mb-0">
                                                    <i class="fas fa-tag me-2"></i><?php echo htmlspecialchars($event['category_name']); ?>
                                                </p>
                                            </div>
                                            <div class="event-date">
                                                <i class="fas fa-calendar-alt me-2"></i><?php echo date('M j, Y', strtotime($event['date'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="profile-section">
                    <h4 class="mb-4">Upcoming Events</h4>
                    <?php if (empty($upcoming_events)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-calendar-check fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No upcoming events.</p>
                            <a href="events.php" class="btn btn-primary">Browse Events</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($upcoming_events as $event): ?>
                            <div class="card event-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h5 class="card-title mb-2"><?php echo htmlspecialchars($event['title']); ?></h5>
                                            <p class="card-text text-muted mb-0">
                                                <i class="fas fa-tag me-2"></i><?php echo htmlspecialchars($event['category_name']); ?>
                                            </p>
                                        </div>
                                        <div class="event-date">
                                            <i class="fas fa-calendar-alt me-2"></i><?php echo date('M j, Y', strtotime($event['date'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <div class="modal fade" id="editProfileModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($success_message)): ?>
                        <div class="alert alert-success">
                            <?php echo htmlspecialchars($success_message); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?php echo htmlspecialchars($user['username']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="bio" class="form-label">Bio</label>
                            <textarea class="form-control" id="bio" name="bio" rows="3"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="profile_picture" class="form-label">Profile Picture</label>
                            <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/*">
                            <small class="text-muted">Max file size: 2MB. Allowed types: JPG, PNG, GIF</small>
                        </div>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/SDL_final/assets/js/main.js"></script>
</body>
</html> 