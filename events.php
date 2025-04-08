<?php
session_start();
require_once 'config/database.php';

// Check if send_mail.php exists and include it
$email_sending_enabled = false;
if (file_exists('send_mail.php')) {
    require_once 'send_mail.php';
    $email_sending_enabled = true;
}

// Handle RSVP
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['event_id']) && isset($_SESSION['user_id'])) {
    $event_id = (int)$_POST['event_id'];
    $user_id = $_SESSION['user_id'];
    
    // Check if already RSVP'd
    $stmt = $pdo->prepare("SELECT id, status FROM rsvps WHERE event_id = ? AND user_id = ?");
    $stmt->execute([$event_id, $user_id]);
    $existing_rsvp = $stmt->fetch();
    
    // Check event capacity
    $stmt = $pdo->prepare("
        SELECT e.*, u.username as manager_name
        FROM events e 
        LEFT JOIN users u ON e.manager_id = u.id
        WHERE e.id = ?
    ");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();
    
    if (!$event) {
        $_SESSION['error'] = "Event not found.";
        header("Location: events.php");
        exit();
    }
    
    // Get current RSVP count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM rsvps WHERE event_id = ? AND status = 'confirmed'");
    $stmt->execute([$event_id]);
    $current_rsvps = $stmt->fetchColumn();
    
    if ($current_rsvps >= $event['capacity'] && !$existing_rsvp) {
        $_SESSION['error'] = "Sorry, this event is already at full capacity.";
        header("Location: events.php");
        exit();
    }
    
    try {
        if ($existing_rsvp) {
            // Update existing RSVP
            $stmt = $pdo->prepare("UPDATE rsvps SET status = 'confirmed' WHERE id = ?");
            $stmt->execute([$existing_rsvp['id']]);
        } else {
            // Create new RSVP
            $stmt = $pdo->prepare("INSERT INTO rsvps (event_id, user_id, status) VALUES (?, ?, 'confirmed')");
            $stmt->execute([$event_id, $user_id]);
        }
        
        // Send RSVP confirmation email if enabled
        if ($email_sending_enabled) {
            // Get user details for email
            $user_stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
            $user_stmt->execute([$user_id]);
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
                    'attending'
                );
            }
        }
        
        $_SESSION['success'] = "RSVP successful! Check your email for confirmation.";
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $_SESSION['error'] = "Failed to process RSVP. Please try again.";
    }
    
    header("Location: events.php");
    exit();
}

// Get all events with RSVP count
$stmt = $pdo->query("
    SELECT e.*, u.username as manager_name, 
           COUNT(CASE WHEN r.status = 'attending' THEN 1 END) as rsvp_count 
    FROM events e 
    LEFT JOIN users u ON e.manager_id = u.id 
    LEFT JOIN rsvps r ON e.id = r.event_id 
    GROUP BY e.id 
    ORDER BY e.date ASC, e.time ASC
");
$events = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events - EventHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container my-5">
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="text-center mb-4 animate-fade-in">Upcoming Events</h2>
                
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success animate-fade-in">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php 
                        echo $_SESSION['success'];
                        unset($_SESSION['success']);
                        ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger animate-fade-in">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php 
                        echo $_SESSION['error'];
                        unset($_SESSION['error']);
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="row">
            <?php foreach ($events as $event): ?>
                <div class="col-md-4 mb-4">
                    <div class="card h-100 animate-fade-in">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="card-title mb-0"><?php echo htmlspecialchars($event['title']); ?></h5>
                                <span class="status-badge status-<?php echo strtolower($event['status'] ?? 'pending'); ?>">
                                    <?php echo ucfirst($event['status'] ?? 'Pending'); ?>
                                </span>
                            </div>
                            
                            <p class="card-text text-muted mb-3">
                                <i class="fas fa-calendar-alt me-2"></i>
                                <?php echo date('F j, Y', strtotime($event['date'])); ?>
                            </p>
                            
                            <p class="card-text text-muted mb-3">
                                <i class="fas fa-clock me-2"></i>
                                <?php echo date('g:i A', strtotime($event['time'])); ?>
                            </p>
                            
                            <p class="card-text text-muted mb-3">
                                <i class="fas fa-map-marker-alt me-2"></i>
                                <?php echo htmlspecialchars($event['location']); ?>
                            </p>
                            
                            <p class="card-text mb-4">
                                <?php echo htmlspecialchars($event['description']); ?>
                            </p>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="text-muted">
                                    <i class="fas fa-users me-2"></i>
                                    <?php echo $event['rsvp_count']; ?>/<?php echo $event['capacity']; ?> spots
                                </div>
                                
                                <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] == 'attendant'): ?>
                                    <form method="POST" action="" class="mb-0">
                                        <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                        <button type="submit" class="btn btn-primary" 
                                            <?php echo $event['rsvp_count'] >= $event['capacity'] ? 'disabled' : ''; ?>>
                                            <i class="fas fa-check-circle me-2"></i>
                                            <?php echo $event['rsvp_count'] >= $event['capacity'] ? 'Full' : 'RSVP'; ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <?php include 'includes/footer.php'; ?>
</body>
</html> 