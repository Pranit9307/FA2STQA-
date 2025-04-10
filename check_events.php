<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Get table structure
$columns = [];
$stmt = $pdo->query("DESCRIBE events");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $columns[] = $row;
}

// Get event count
$totalEvents = $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
$eventsWithCoords = $pdo->query("SELECT COUNT(*) FROM events WHERE latitude IS NOT NULL AND longitude IS NOT NULL")->fetchColumn();

// Get sample events
$sampleEvents = $pdo->query("SELECT id, title, location, latitude, longitude, date FROM events LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check Events - EventHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-database"></i> Events Database Check</h4>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">Total Events</h5>
                                        <p class="card-text display-4"><?php echo $totalEvents; ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card bg-success text-white">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">Events With Coordinates</h5>
                                        <p class="card-text display-4"><?php echo $eventsWithCoords; ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <h5>Table Structure</h5>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Field</th>
                                        <th>Type</th>
                                        <th>Null</th>
                                        <th>Key</th>
                                        <th>Default</th>
                                        <th>Extra</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($columns as $column): ?>
                                        <tr>
                                            <td><?php echo $column['Field']; ?></td>
                                            <td><?php echo $column['Type']; ?></td>
                                            <td><?php echo $column['Null']; ?></td>
                                            <td><?php echo $column['Key']; ?></td>
                                            <td><?php echo $column['Default']; ?></td>
                                            <td><?php echo $column['Extra']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <h5 class="mt-4">Sample Events</h5>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Title</th>
                                        <th>Location</th>
                                        <th>Latitude</th>
                                        <th>Longitude</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sampleEvents as $event): ?>
                                        <tr>
                                            <td><?php echo $event['id']; ?></td>
                                            <td><?php echo htmlspecialchars($event['title']); ?></td>
                                            <td><?php echo htmlspecialchars($event['location']); ?></td>
                                            <td><?php echo $event['latitude']; ?></td>
                                            <td><?php echo $event['longitude']; ?></td>
                                            <td><?php echo $event['date']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-4">
                            <a href="update_event_coordinates.php" class="btn btn-primary">
                                <i class="fas fa-map-marker-alt"></i> Update Event Coordinates
                            </a>
                            <a href="nearby_events.php" class="btn btn-info">
                                <i class="fas fa-map-marked-alt"></i> Test Nearby Events
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 