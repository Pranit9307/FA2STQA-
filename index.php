<?php
session_start();
require_once 'config/database.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand animate__animated animate__fadeIn" href="index.php">EventHub</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="events.php">Events</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">Profile</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">Logout</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="register.php">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="hero-section py-5 text-center">
        <div class="container">
            <h1 class="display-4 animate__animated animate__fadeInDown">Welcome to EventHub</h1>
            <p class="lead animate__animated animate__fadeInUp">Discover and manage events with ease</p>
            <?php if(!isset($_SESSION['user_id'])): ?>
                <div class="mt-4 animate__animated animate__fadeInUp">
                    <a href="register.php" class="btn btn-primary btn-lg me-3">Get Started</a>
                    <a href="login.php" class="btn btn-outline-primary btn-lg">Login</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Featured Events Section -->
    <div class="container my-5">
        <h2 class="text-center mb-4">Featured Events</h2>
        <div class="row">
            <?php
            $stmt = $pdo->query("SELECT * FROM events ORDER BY created_at DESC LIMIT 3");
            while($event = $stmt->fetch()) {
                ?>
                <div class="col-md-4 mb-4">
                    <div class="card h-100 animate__animated animate__fadeIn">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($event['title']); ?></h5>
                            <p class="card-text"><?php echo htmlspecialchars($event['description']); ?></p>
                            <p class="card-text">
                                <small class="text-muted">
                                    Date: <?php echo date('F j, Y', strtotime($event['date'])); ?><br>
                                    Time: <?php echo date('g:i A', strtotime($event['time'])); ?><br>
                                    Location: <?php echo htmlspecialchars($event['location']); ?>
                                </small>
                            </p>
                            <a href="event_details.php?id=<?php echo $event['id']; ?>" class="btn btn-primary">View Details</a>
                        </div>
                    </div>
                </div>
                <?php
            }
            ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html> 