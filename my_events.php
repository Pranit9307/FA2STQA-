<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and is an event manager
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'event_manager') {
    header("Location: login.php");
    exit();
}

// Get events created by the manager
$stmt = $pdo->prepare("
    SELECT e.*, COUNT(r.id) as rsvp_count 
    FROM events e 
    LEFT JOIN rsvps r ON e.id = r.event_id 
    WHERE e.manager_id = ? 
    GROUP BY e.id 
    ORDER BY e.date ASC, e.time ASC
");
$stmt->execute([$_SESSION['user_id']]);
$events = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Events - EventHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container my-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>My Events</h2>
            <a href="create_event.php" class="btn btn-primary">Create New Event</a>
        </div>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php 
                echo $_SESSION['success'];
                unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (empty($events)): ?>
            <div class="alert alert-info">
                You haven't created any events yet. <a href="create_event.php">Create your first event</a>
            </div>
        <?php else: ?>
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
                                        <strong>RSVPs:</strong> <?php echo $event['rsvp_count']; ?>/<?php echo $event['capacity']; ?>
                                    </small>
                                </p>
                                <a href="event_details.php?id=<?php echo $event['id']; ?>" class="btn btn-primary">View Details</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html> 