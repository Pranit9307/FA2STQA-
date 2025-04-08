<?php
require_once 'includes/auth_check.php';
if ($_SESSION['role'] !== 'attendant') {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendant Dashboard - EventHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include 'includes/navbar.php'; ?>
<div class="container my-5">
    <div class="card shadow animate__animated animate__fadeIn">
        <div class="card-body p-5">
            <h2 class="mb-4">Welcome, <?= htmlspecialchars($_SESSION['username']) ?>!</h2>
            <p class="lead">This is your Attendant Dashboard.</p>
            
            <div class="row mt-4">
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">Browse Events</h5>
                            <p class="card-text">Discover and join exciting events.</p>
                            <a href="events.php" class="btn btn-primary">View Events</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">My RSVPs</h5>
                            <p class="card-text">View and manage your event RSVPs.</p>
                            <a href="my_rsvps.php" class="btn btn-primary">View RSVPs</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">Profile</h5>
                            <p class="card-text">Update your profile information.</p>
                            <a href="profile.php" class="btn btn-primary">Edit Profile</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
</body>
</html>
