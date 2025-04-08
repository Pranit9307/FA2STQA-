<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and is an event manager
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'event_manager') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $date = $_POST['date'];
    $time = $_POST['time'];
    $location = trim($_POST['location']);
    $capacity = (int)$_POST['capacity'];
    $manager_id = $_SESSION['user_id'];
    
    $errors = [];
    
    if (empty($title)) $errors[] = "Title is required";
    if (empty($description)) $errors[] = "Description is required";
    if (empty($date)) $errors[] = "Date is required";
    if (empty($time)) $errors[] = "Time is required";
    if (empty($location)) $errors[] = "Location is required";
    if ($capacity <= 0) $errors[] = "Capacity must be greater than 0";
    
    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO events (title, description, date, time, location, capacity, manager_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        if ($stmt->execute([$title, $description, $date, $time, $location, $capacity, $manager_id])) {
            // Send confirmation email to event manager
            $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
            $stmt->execute([$manager_id]);
            $manager_email = $stmt->fetchColumn();
            
            $to = $manager_email;
            $subject = "Event Created Successfully - EventHub";
            $message = "Dear Event Manager,\n\nYour event '$title' has been successfully created on EventHub.\n\nEvent Details:\nDate: $date\nTime: $time\nLocation: $location\nCapacity: $capacity\n\nBest regards,\nEventHub Team";
            $headers = "From: noreply@eventhub.com";
            
            mail($to, $subject, $message, $headers);
            
            $_SESSION['success'] = "Event created successfully!";
            header("Location: my_events.php");
            exit();
        } else {
            $errors[] = "Failed to create event. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Event - EventHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card animate__animated animate__fadeIn">
                    <div class="card-body p-5">
                        <h2 class="text-center mb-4">Create New Event</h2>
                        
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="title" class="form-label">Event Title</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="4" required></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="date" class="form-label">Date</label>
                                    <input type="date" class="form-control" id="date" name="date" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="time" class="form-label">Time</label>
                                    <input type="time" class="form-control" id="time" name="time" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="location" class="form-label">Location</label>
                                <input type="text" class="form-control" id="location" name="location" required>
                            </div>
                            
                            <div class="mb-4">
                                <label for="capacity" class="form-label">Capacity</label>
                                <input type="number" class="form-control" id="capacity" name="capacity" min="1" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">Create Event</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html> 