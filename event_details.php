<?php
session_start();
require_once 'config/database.php';

// Check if send_mail.php exists and include it
$email_sending_enabled = false;
if (file_exists('send_mail.php')) {
    require_once 'send_mail.php';
    $email_sending_enabled = true;
}

// Check if event ID is provided and is valid
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid event ID";
    header("Location: events.php");
    exit();
}

$event_id = (int)$_GET['id'];

// Get event details with improved query
$stmt = $pdo->prepare("
    SELECT e.*, u.username as manager_name,
           COUNT(r.id) as rsvp_count,
           COALESCE(GROUP_CONCAT(
               CASE WHEN r.status = 'attending' 
               THEN u2.username 
               END
           ), '') as attending_users
    FROM events e
    LEFT JOIN users u ON e.manager_id = u.id
    LEFT JOIN rsvps r ON e.id = r.event_id
    LEFT JOIN users u2 ON r.user_id = u2.id
    WHERE e.id = ?
    GROUP BY e.id, e.title, e.description, e.date, e.time, e.location, e.capacity, e.manager_id, u.username
");
$stmt->execute([$event_id]);
$event = $stmt->fetch();

if (!$event) {
    $_SESSION['error'] = "Event not found";
    header("Location: events.php");
    exit();
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle RSVP submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Invalid form submission";
        header("Location: event_details.php?id=" . $event_id);
        exit();
    }

    $status = $_POST['rsvp_status'];
    
    // Validate RSVP status
    $valid_statuses = ['attending', 'maybe', 'not_attending'];
    if (!in_array($status, $valid_statuses)) {
        $_SESSION['error'] = "Invalid RSVP status";
        header("Location: event_details.php?id=" . $event_id);
        exit();
    }
    
    try {
        // Check if user already has an RSVP
        $check_stmt = $pdo->prepare("SELECT id FROM rsvps WHERE event_id = ? AND user_id = ?");
        $check_stmt->execute([$event_id, $_SESSION['user_id']]);
        $existing_rsvp = $check_stmt->fetch();
        
        if ($existing_rsvp) {
            // Update existing RSVP
            $update_stmt = $pdo->prepare("UPDATE rsvps SET status = ? WHERE id = ?");
            $update_stmt->execute([$status, $existing_rsvp['id']]);
        } else {
            // Create new RSVP
            $insert_stmt = $pdo->prepare("INSERT INTO rsvps (event_id, user_id, status) VALUES (?, ?, ?)");
            $insert_stmt->execute([$event_id, $_SESSION['user_id'], $status]);
        }
        
        // Send RSVP confirmation email if enabled
        if ($email_sending_enabled) {
            // Get user details for email
            $user_stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
            $user_stmt->execute([$_SESSION['user_id']]);
            $user = $user_stmt->fetch();
            
            if ($user) {
                // Format date and time for email
                $formatted_date = date('F j, Y', strtotime($event['date']));
                $formatted_time = date('g:i A', strtotime($event['time']));
                
                // Send RSVP confirmation email
                sendRsvpEmail(
                    $user['email'],
                    $user['username'],
                    $event['title'],
                    $formatted_date,
                    $formatted_time,
                    $event['location'],
                    $status
                );
            }
        }
        
        $_SESSION['success'] = "Your RSVP has been updated!";
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $_SESSION['error'] = "Failed to update RSVP. Please try again.";
    }
    
    header("Location: event_details.php?id=" . $event_id);
    exit();
}

// Get user's RSVP status if logged in
$user_rsvp = null;
if (isset($_SESSION['user_id'])) {
    $rsvp_stmt = $pdo->prepare("SELECT status FROM rsvps WHERE event_id = ? AND user_id = ?");
    $rsvp_stmt->execute([$event_id, $_SESSION['user_id']]);
    $user_rsvp = $rsvp_stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($event['title']); ?> - EventHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container my-5">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php 
                echo $_SESSION['success'];
                unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?php 
                echo $_SESSION['error'];
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>
        
        <div class="card animate__animated animate__fadeIn">
            <div class="card-body">
                <h2 class="card-title"><?php echo htmlspecialchars($event['title']); ?></h2>
                <p class="text-muted">Created by <?php echo htmlspecialchars($event['manager_name']); ?></p>
                
                <div class="row mt-4">
                    <div class="col-md-8">
                        <h4>Description</h4>
                        <p><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
                        
                        <h4 class="mt-4">Event Details</h4>
                        <ul class="list-unstyled">
                            <li><strong>Date:</strong> <?php echo date('F j, Y', strtotime($event['date'])); ?></li>
                            <li><strong>Time:</strong> <?php echo date('g:i A', strtotime($event['time'])); ?></li>
                            <li><strong>Location:</strong> <?php echo htmlspecialchars($event['location']); ?></li>
                            <li><strong>Capacity:</strong> <?php echo $event['rsvp_count']; ?>/<?php echo $event['capacity']; ?> spots filled</li>
                        </ul>
                    </div>
                    
                    <div class="col-md-4">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">RSVP</h5>
                                    <form method="POST">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="radio" name="rsvp_status" value="attending" 
                                                <?php echo ($user_rsvp && $user_rsvp['status'] === 'attending') ? 'checked' : ''; ?>>
                                            <label class="form-check-label">Attending</label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="radio" name="rsvp_status" value="maybe"
                                                <?php echo ($user_rsvp && $user_rsvp['status'] === 'maybe') ? 'checked' : ''; ?>>
                                            <label class="form-check-label">Maybe</label>
                                        </div>
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="radio" name="rsvp_status" value="not_attending"
                                                <?php echo ($user_rsvp && $user_rsvp['status'] === 'not_attending') ? 'checked' : ''; ?>>
                                            <label class="form-check-label">Not Attending</label>
                                        </div>
                                        <button type="submit" class="btn btn-primary w-100">Submit RSVP</button>
                                    </form>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                Please <a href="login.php">login</a> to RSVP for this event.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!empty($event['attending_users'])): ?>
                    <div class="mt-4">
                        <h4>Who's Attending</h4>
                        <p><?php echo htmlspecialchars($event['attending_users']); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html> 