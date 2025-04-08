<?php
session_start();
require_once 'config/database.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EventHub - Your Event Management Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <!-- Hero Section -->
    <div class="hero-section py-5 text-center">
        <div class="container">
            <h1 class="display-4 animate-fade-in">Welcome to EventHub</h1>
            <p class="lead animate-fade-in">Discover and manage events with ease</p>
            <?php if(!isset($_SESSION['user_id'])): ?>
                <div class="mt-4 animate-fade-in">
                    <a href="register.php" class="btn btn-primary btn-lg me-3">
                        <i class="fas fa-rocket me-2"></i>Get Started
                    </a>
                    <a href="login.php" class="btn btn-outline-primary btn-lg">
                        <i class="fas fa-sign-in-alt me-2"></i>Login
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Features Section -->
    <div class="features-section py-5">
        <div class="container">
            <h2 class="text-center mb-5 animate-fade-in">Why Choose EventHub?</h2>
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="feature-card card h-100 animate-fade-in">
                        <div class="card-body text-center">
                            <i class="fas fa-calendar-check feature-icon"></i>
                            <h3 class="card-title">Easy Event Management</h3>
                            <p class="card-text">Create and manage your events with our intuitive platform.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="feature-card card h-100 animate-fade-in">
                        <div class="card-body text-center">
                            <i class="fas fa-users feature-icon"></i>
                            <h3 class="card-title">Connect with People</h3>
                            <p class="card-text">Join events and connect with like-minded individuals.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="feature-card card h-100 animate-fade-in">
                        <div class="card-body text-center">
                            <i class="fas fa-bell feature-icon"></i>
                            <h3 class="card-title">Stay Updated</h3>
                            <p class="card-text">Get notifications about upcoming events and updates.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Featured Events Section -->
    <div class="container my-5">
        <h2 class="text-center mb-4 animate-fade-in">Featured Events</h2>
        <div class="row">
            <?php
            $stmt = $pdo->query("SELECT * FROM events ORDER BY created_at DESC LIMIT 3");
            while($event = $stmt->fetch()) {
                ?>
                <div class="col-md-4 mb-4">
                    <div class="card h-100 animate-fade-in">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="card-title mb-0"><?php echo htmlspecialchars($event['title']); ?></h5>
                                <span class="status-badge status-pending">Upcoming</span>
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
                            <p class="card-text mb-4"><?php echo htmlspecialchars($event['description']); ?></p>
                            <a href="event_details.php?id=<?php echo $event['id']; ?>" class="btn btn-primary w-100">
                                <i class="fas fa-info-circle me-2"></i>View Details
                            </a>
                        </div>
                    </div>
                </div>
                <?php
            }
            ?>
        </div>
    </div>

    <!-- Call to Action Section -->
    <?php if(!isset($_SESSION['user_id'])): ?>
    <div class="cta-section py-5 text-center">
        <div class="container">
            <h2 class="mb-4 animate-fade-in">Ready to Get Started?</h2>
            <p class="lead mb-4 animate-fade-in">Join our community of event enthusiasts today!</p>
            <a href="register.php" class="btn btn-primary btn-lg animate-fade-in">
                <i class="fas fa-user-plus me-2"></i>Create Your Account
            </a>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <?php include 'includes/footer.php'; ?>
</body>
</html> 