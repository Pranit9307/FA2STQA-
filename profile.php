<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get user's RSVPs if they're an attendant
if ($user['role'] == 'attendant') {
    $stmt = $pdo->prepare("
        SELECT e.*, r.status as rsvp_status 
        FROM events e 
        JOIN rsvps r ON e.id = r.event_id 
        WHERE r.user_id = ? 
        ORDER BY e.date ASC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $rsvps = $stmt->fetchAll();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    
    $errors = [];
    
    // Validate current password
    if (!empty($current_password)) {
        if (!password_verify($current_password, $user['password'])) {
            $errors[] = "Current password is incorrect";
        }
    }
    
    // Check if username or email already exists
    if ($username !== $user['username'] || $email !== $user['email']) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $stmt->execute([$username, $email, $_SESSION['user_id']]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Username or email already exists";
        }
    }
    
    if (empty($errors)) {
        if (!empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, password = ? WHERE id = ?");
            $stmt->execute([$username, $email, $hashed_password, $_SESSION['user_id']]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
            $stmt->execute([$username, $email, $_SESSION['user_id']]);
        }
        
        $_SESSION['success'] = "Profile updated successfully!";
        header("Location: profile.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - EventHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container my-5">
        <div class="row">
            <div class="col-md-4">
                <div class="card animate__animated animate__fadeIn">
                    <div class="card-body">
                        <h3 class="card-title text-center mb-4">Profile Information</h3>
                        
                        <?php if (isset($_SESSION['success'])): ?>
                            <div class="alert alert-success">
                                <?php 
                                echo $_SESSION['success'];
                                unset($_SESSION['success']);
                                ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password">
                                <small class="text-muted">Required only if changing password</small>
                            </div>
                            
                            <div class="mb-4">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password">
                                <small class="text-muted">Leave blank to keep current password</small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">Update Profile</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <?php if ($user['role'] == 'attendant'): ?>
                    <div class="card animate__animated animate__fadeIn">
                        <div class="card-body">
                            <h3 class="card-title mb-4">My RSVPs</h3>
                            
                            <?php if (empty($rsvps)): ?>
                                <p class="text-muted">You haven't RSVP'd to any events yet.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Event</th>
                                                <th>Date</th>
                                                <th>Time</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($rsvps as $rsvp): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($rsvp['title']); ?></td>
                                                    <td><?php echo date('F j, Y', strtotime($rsvp['date'])); ?></td>
                                                    <td><?php echo date('g:i A', strtotime($rsvp['time'])); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            echo ($rsvp['rsvp_status'] === 'attending') ? 'success' : 
                                                                (($rsvp['rsvp_status'] === 'maybe') ? 'warning' : 'danger'); 
                                                        ?>">
                                                            <?php echo ucfirst($rsvp['rsvp_status']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html> 