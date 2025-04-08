<?php
session_start();
require_once 'config/database.php';

// Handle RSVP
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['event_id']) && isset($_SESSION['user_id'])) {
    $event_id = (int)$_POST['event_id'];
    $user_id = $_SESSION['user_id'];
    
    // Check if already RSVP'd
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM rsvps WHERE event_id = ? AND user_id = ?");
    $stmt->execute([$event_id, $user_id]);
    if ($stmt->fetchColumn() == 0) {
        // Check event capacity
        $stmt = $pdo->prepare("
            SELECT e.capacity, COUNT(r.id) as current_rsvps 
            FROM events e 
            LEFT JOIN rsvps r ON e.id = r.event_id 
            WHERE e.id = ?
            GROUP BY e.id
        ");
        $stmt->execute([$event_id]);
        $event = $stmt->fetch();
        
        if ($event['current_rsvps'] < $event['capacity']) {
            // Create RSVP
            $stmt = $pdo->prepare("INSERT INTO rsvps (event_id, user_id, status) VALUES (?, ?, 'confirmed')");
            if ($stmt->execute([$event_id, $user_id])) {
                // Send confirmation email
                $stmt = $pdo->prepare("
                    SELECT u.email, u.username, e.title, e.date, e.time, e.location 
                    FROM users u 
                    JOIN events e ON e.id = ? 
                    WHERE u.id = ?
                ");
                $stmt->execute([$event_id, $user_id]);
                $rsvp_info = $stmt->fetch();
                
                $to = $rsvp_info['email'];
                $subject = "RSVP Confirmation - EventHub";
                $message = "Dear {$rsvp_info['username']},\n\nYour RSVP for '{$rsvp_info['title']}' has been confirmed.\n\nEvent Details:\nDate: {$rsvp_info['date']}\nTime: {$rsvp_info['time']}\nLocation: {$rsvp_info['location']}\n\nBest regards,\nEventHub Team";
                $headers = "From: noreply@eventhub.com";
                
                mail($to, $subject, $message, $headers);
                
                $_SESSION['success'] = "RSVP successful! Check your email for confirmation.";
            }
        } else {
            $_SESSION['error'] = "Sorry, this event is already at full capacity.";
        }
    } else {
        $_SESSION['error'] = "You have already RSVP'd for this event.";
    }
    
    header("Location: events.php");
    exit();
}

// Get all events with RSVP count
$stmt = $pdo->query("
    SELECT e.*, u.username as manager_name, 
           COUNT(r.id) as rsvp_count 
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container my-5">
        <h2 class="text-center mb-4">Upcoming Events</h2>
        
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
        
        <div class="row">
            <?php foreach ($events as $event): ?>
                <div class="col-md-4 mb-4">
                    <div class="card h-100 animate__animated animate__fadeIn">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($event['title']); ?></h5>
                            <p class="card-text"><?php echo htmlspecialchars($event['description']); ?></p>
                            <p class="card-text">
                                <small class="text-muted">
                                    <strong>Date:</strong> <?php echo date('F j, Y', strtotime($event['date'])); ?><br>
                                    <strong>Time:</strong> <?php echo date('g:i A', strtotime($event['time'])); ?><br>
                                    <strong>Location:</strong> <?php echo htmlspecialchars($event['location']); ?><br>
                                    <strong>Organizer:</strong> <?php echo htmlspecialchars($event['manager_name']); ?><br>
                                    <strong>Capacity:</strong> <?php echo $event['rsvp_count']; ?>/<?php echo $event['capacity']; ?>
                                </small>
                            </p>
                            <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] == 'attendant'): ?>
                                <form method="POST" action="">
                                    <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                    <button type="submit" class="btn btn-primary w-100" <?php echo $event['rsvp_count'] >= $event['capacity'] ? 'disabled' : ''; ?>>
                                        <?php echo $event['rsvp_count'] >= $event['capacity'] ? 'Full' : 'RSVP'; ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html> 