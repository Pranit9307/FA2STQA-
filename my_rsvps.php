<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user's RSVPs
$stmt = $pdo->prepare("
    SELECT e.*, r.status as rsvp_status, u.username as manager_name
    FROM rsvps r
    JOIN events e ON r.event_id = e.id
    JOIN users u ON e.manager_id = u.id
    WHERE r.user_id = ?
    ORDER BY e.date ASC, e.time ASC
");
$stmt->execute([$_SESSION['user_id']]);
$rsvps = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My RSVPs - EventHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container my-5">
        <h2 class="mb-4">My RSVPs</h2>
        
        <?php if (empty($rsvps)): ?>
            <div class="alert alert-info">
                You haven't RSVP'd to any events yet. <a href="events.php">Browse events</a> to get started!
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($rsvps as $rsvp): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100 animate__animated animate__fadeIn">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($rsvp['title']); ?></h5>
                                <p class="card-text"><?php echo htmlspecialchars($rsvp['description']); ?></p>
                                <p class="card-text">
                                    <small class="text-muted">
                                        <strong>Date:</strong> <?php echo date('F j, Y', strtotime($rsvp['date'])); ?><br>
                                        <strong>Time:</strong> <?php echo date('g:i A', strtotime($rsvp['time'])); ?><br>
                                        <strong>Location:</strong> <?php echo htmlspecialchars($rsvp['location']); ?><br>
                                        <strong>Organizer:</strong> <?php echo htmlspecialchars($rsvp['manager_name']); ?><br>
                                        <strong>Your RSVP:</strong> 
                                        <span class="badge <?php 
                                            echo ($rsvp['rsvp_status'] === 'attending') ? 'bg-success' : 
                                                (($rsvp['rsvp_status'] === 'maybe') ? 'bg-warning' : 'bg-danger'); 
                                        ?>">
                                            <?php echo ucfirst($rsvp['rsvp_status']); ?>
                                        </span>
                                    </small>
                                </p>
                                <a href="event_details.php?id=<?php echo $rsvp['id']; ?>" class="btn btn-primary">View Event</a>
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