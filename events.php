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

// Get filter parameters
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : null;
$tag_id = isset($_GET['tag']) ? (int)$_GET['tag'] : null;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : null;
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : null;
$location = isset($_GET['location']) ? trim($_GET['location']) : '';
$min_price = isset($_GET['min_price']) ? (float)$_GET['min_price'] : null;
$max_price = isset($_GET['max_price']) ? (float)$_GET['max_price'] : null;
$event_type = isset($_GET['event_type']) ? $_GET['event_type'] : '';
$available_spots = isset($_GET['available_spots']) ? (int)$_GET['available_spots'] : null;

// Build the query with filters
$query = "
    SELECT DISTINCT e.*, u.username as manager_name, 
           COUNT(CASE WHEN r.status = 'attending' THEN 1 END) as rsvp_count,
           c.name as category_name,
           (e.capacity - COUNT(CASE WHEN r.status = 'attending' THEN 1 END)) as spots_available
    FROM events e 
    LEFT JOIN users u ON e.manager_id = u.id 
    LEFT JOIN rsvps r ON e.id = r.event_id 
    LEFT JOIN categories c ON e.category_id = c.id
    LEFT JOIN event_tags et ON e.id = et.event_id
    LEFT JOIN tags t ON et.tag_id = t.id
    WHERE 1=1
";

$params = [];

if ($category_id) {
    $query .= " AND e.category_id = ?";
    $params[] = $category_id;
}

if ($tag_id) {
    $query .= " AND et.tag_id = ?";
    $params[] = $tag_id;
}

if ($search_query) {
    $query .= " AND (e.title LIKE ? OR e.description LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

if ($start_date) {
    $query .= " AND e.date >= ?";
    $params[] = $start_date;
}

if ($end_date) {
    $query .= " AND e.date <= ?";
    $params[] = $end_date;
}

if ($location) {
    $query .= " AND e.location LIKE ?";
    $params[] = "%$location%";
}

if ($min_price !== null) {
    $query .= " AND e.price >= ?";
    $params[] = $min_price;
}

if ($max_price !== null) {
    $query .= " AND e.price <= ?";
    $params[] = $max_price;
}

if ($event_type) {
    $query .= " AND e.event_type = ?";
    $params[] = $event_type;
}

if ($available_spots !== null) {
    $query .= " HAVING spots_available >= ?";
    $params[] = $available_spots;
}

$query .= " GROUP BY e.id ORDER BY e.date ASC, e.time ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$events = $stmt->fetchAll();

// Get all categories and tags for the filter
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$tags = $pdo->query("SELECT * FROM tags ORDER BY name")->fetchAll();
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
                
                <!-- Advanced Search Form -->
                <div class="card mb-4 animate-fade-in">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <!-- Search Bar -->
                            <div class="col-12 mb-3">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" name="search" 
                                           placeholder="Search events..." value="<?php echo htmlspecialchars($search_query); ?>">
                                </div>
                            </div>
                            
                            <!-- Category and Tag Filters -->
                            <div class="col-md-4">
                                <label for="category" class="form-label">Category</label>
                                <select class="form-select" id="category" name="category">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" 
                                                <?php echo $category_id == $cat['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="tag" class="form-label">Tag</label>
                                <select class="form-select" id="tag" name="tag">
                                    <option value="">All Tags</option>
                                    <?php foreach ($tags as $t): ?>
                                        <option value="<?php echo $t['id']; ?>"
                                                <?php echo $tag_id == $t['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($t['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Date Range -->
                            <div class="col-md-4">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" 
                                       value="<?php echo $start_date; ?>">
                            </div>
                            
                            <div class="col-md-4">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" 
                                       value="<?php echo $end_date; ?>">
                            </div>
                            
                            <!-- Location -->
                            <div class="col-md-4">
                                <label for="location" class="form-label">Location</label>
                                <input type="text" class="form-control" id="location" name="location" 
                                       placeholder="Enter location" value="<?php echo htmlspecialchars($location); ?>">
                            </div>
                            
                            <!-- Price Range -->
                            <div class="col-md-4">
                                <label for="min_price" class="form-label">Min Price</label>
                                <input type="number" class="form-control" id="min_price" name="min_price" 
                                       min="0" step="0.01" value="<?php echo $min_price; ?>">
                            </div>
                            
                            <div class="col-md-4">
                                <label for="max_price" class="form-label">Max Price</label>
                                <input type="number" class="form-control" id="max_price" name="max_price" 
                                       min="0" step="0.01" value="<?php echo $max_price; ?>">
                            </div>
                            
                            <!-- Event Type -->
                            <div class="col-md-4">
                                <label for="event_type" class="form-label">Event Type</label>
                                <select class="form-select" id="event_type" name="event_type">
                                    <option value="">All Types</option>
                                    <option value="free" <?php echo $event_type === 'free' ? 'selected' : ''; ?>>Free</option>
                                    <option value="paid" <?php echo $event_type === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                    <option value="donation" <?php echo $event_type === 'donation' ? 'selected' : ''; ?>>Donation</option>
                                </select>
                            </div>
                            
                            <!-- Available Spots -->
                            <div class="col-md-4">
                                <label for="available_spots" class="form-label">Minimum Available Spots</label>
                                <input type="number" class="form-control" id="available_spots" name="available_spots" 
                                       min="0" value="<?php echo $available_spots; ?>">
                            </div>
                            
                            <!-- Filter Buttons -->
                            <div class="col-12 d-flex justify-content-end gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter me-2"></i>Apply Filters
                                </button>
                                <a href="events.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>Clear Filters
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
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
                    <div class="card h-100 animate-fade-in" style="cursor: pointer;" onclick="window.location.href='event_details.php?id=<?php echo $event['id']; ?>'">
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
                                
                                <div class="event-meta">
                                    <?php if ($event['category_name']): ?>
                                        <span class="badge bg-info me-2">
                                            <i class="fas fa-tag me-1"></i>
                                            <?php echo htmlspecialchars($event['category_name']); ?>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($event['price'] > 0): ?>
                                        <span class="badge bg-success me-2">
                                            <i class="fas fa-rupee-sign me-1"></i>
                                            ₹<?php echo number_format($event['price'], 2); ?>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php
                                    // Get tags for this event
                                    $tag_stmt = $pdo->prepare("
                                        SELECT t.name 
                                        FROM tags t 
                                        JOIN event_tags et ON t.id = et.tag_id 
                                        WHERE et.event_id = ?
                                    ");
                                    $tag_stmt->execute([$event['id']]);
                                    $event_tags = $tag_stmt->fetchAll();
                                    
                                    foreach ($event_tags as $tag):
                                    ?>
                                        <span class="badge bg-secondary me-2">
                                            <?php echo htmlspecialchars($tag['name']); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
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