<?php
session_start();
require_once 'config/database.php';
require_once 'location_services.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$message = '';
$error = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get all events without coordinates
        $stmt = $pdo->query("SELECT id, location FROM events WHERE (latitude IS NULL OR longitude IS NULL) AND location IS NOT NULL");
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $updated = 0;
        $failed = 0;
        
        foreach ($events as $event) {
            $coordinates = getCoordinates($event['location']);
            
            if ($coordinates) {
                $updateStmt = $pdo->prepare("UPDATE events SET latitude = ?, longitude = ? WHERE id = ?");
                $updateStmt->execute([$coordinates['lat'], $coordinates['lng'], $event['id']]);
                $updated++;
            } else {
                $failed++;
            }
        }
        
        $message = "Updated $updated events with coordinates. Failed to geocode $failed events.";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get statistics
$totalEvents = $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
$eventsWithCoords = $pdo->query("SELECT COUNT(*) FROM events WHERE latitude IS NOT NULL AND longitude IS NOT NULL")->fetchColumn();
$eventsWithoutCoords = $pdo->query("SELECT COUNT(*) FROM events WHERE latitude IS NULL OR longitude IS NULL")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Event Coordinates - EventHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-map-marker-alt"></i> Update Event Coordinates</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-success"><?php echo $message; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">Total Events</h5>
                                        <p class="card-text display-4"><?php echo $totalEvents; ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-success text-white">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">With Coordinates</h5>
                                        <p class="card-text display-4"><?php echo $eventsWithCoords; ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-warning">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">Without Coordinates</h5>
                                        <p class="card-text display-4"><?php echo $eventsWithoutCoords; ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <form method="POST">
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-sync-alt"></i> Update All Event Coordinates
                                </button>
                            </div>
                            <p class="text-muted mt-3">
                                This will attempt to geocode all events that don't have coordinates yet.
                                The process uses OpenStreetMap's Nominatim service to convert addresses to coordinates.
                            </p>
                        </form>
                        
                        <?php if ($eventsWithoutCoords > 0): ?>
                            <hr>
                            <h5>Events Without Coordinates</h5>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Title</th>
                                            <th>Location</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $stmt = $pdo->query("SELECT id, title, location FROM events WHERE latitude IS NULL OR longitude IS NULL ORDER BY id DESC LIMIT 10");
                                        while ($event = $stmt->fetch(PDO::FETCH_ASSOC)):
                                        ?>
                                            <tr>
                                                <td><?php echo $event['id']; ?></td>
                                                <td><?php echo htmlspecialchars($event['title']); ?></td>
                                                <td><?php echo htmlspecialchars($event['location']); ?></td>
                                                <td>
                                                    <a href="event_details.php?id=<?php echo $event['id']; ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 