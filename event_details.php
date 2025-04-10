<?php
session_start();
require_once 'config/database.php';

// Check if send_mail.php exists and include it
$email_sending_enabled = false;
if (file_exists('send_mail.php')) {
    require_once 'send_mail.php';
    $email_sending_enabled = true;
}

// Check if event ID is provided and is valid
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid event ID";
    header("Location: events.php");
    exit();
}

$event_id = (int)$_GET['id'];

// Get event details with improved query
$stmt = $pdo->prepare("
    SELECT e.*, u.username as manager_name,
           COUNT(r.id) as rsvp_count,
           c.name as category_name,
           COALESCE(GROUP_CONCAT(
               CASE WHEN r.status = 'attending' 
               THEN u2.username 
               END
           ), '') as attending_users
    FROM events e
    LEFT JOIN users u ON e.manager_id = u.id
    LEFT JOIN rsvps r ON e.id = r.event_id
    LEFT JOIN users u2 ON r.user_id = u2.id
    LEFT JOIN categories c ON e.category_id = c.id
    WHERE e.id = ?
    GROUP BY e.id, e.title, e.description, e.date, e.time, e.location, e.capacity, e.image_path, e.manager_id, u.username, c.name
");
$stmt->execute([$event_id]);
$event = $stmt->fetch();

if (!$event) {
    $_SESSION['error'] = "Event not found";
    header("Location: events.php");
    exit();
}

// Get event tags
$tag_stmt = $pdo->prepare("
    SELECT t.name 
    FROM tags t 
    JOIN event_tags et ON t.id = et.tag_id 
    WHERE et.event_id = ?
");
$tag_stmt->execute([$event_id]);
$event_tags = $tag_stmt->fetchAll();

// Get event ratings and comments
$stmt = $pdo->prepare("
    SELECT er.*, u.username 
    FROM event_ratings er 
    JOIN users u ON er.user_id = u.id 
    WHERE er.event_id = ? 
    ORDER BY er.created_at DESC
");
$stmt->execute([$event_id]);
$ratings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate average rating
$avg_rating = 0;
if (!empty($ratings)) {
    $avg_rating = array_sum(array_column($ratings, 'rating')) / count($ratings);
}

// Get social sharing links
require_once 'social_features.php';
$sharing_links = getSocialSharingLinks($event);

// Get calendar integration URLs
require_once 'calendar_integration.php';
$google_calendar_url = getGoogleCalendarUrl($event);
$outlook_calendar_url = getOutlookCalendarUrl($event);

// Get directions URL
require_once 'location_services.php';
$directions_url = getDirectionsUrl($event['location']);

// Get coordinates for the event location
$coordinates = null;
if (!empty($event['latitude']) && !empty($event['longitude'])) {
    $coordinates = [
        'lat' => $event['latitude'],
        'lng' => $event['longitude']
    ];
} else {
    // Try to get coordinates from address
    $coordinates = getCoordinates($event['location']);
    if ($coordinates) {
        // Update event with coordinates
        $stmt = $pdo->prepare("UPDATE events SET latitude = ?, longitude = ? WHERE id = ?");
        $stmt->execute([$coordinates['lat'], $coordinates['lng'], $event_id]);
    }
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle RSVP submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Invalid form submission";
        header("Location: event_details.php?id=" . $event_id);
        exit();
    }

    $status = $_POST['rsvp_status'];
    
    // Validate RSVP status
    $valid_statuses = ['confirmed', 'pending', 'cancelled'];
    if (!in_array($status, $valid_statuses)) {
        $_SESSION['error'] = "Invalid RSVP status";
        header("Location: event_details.php?id=" . $event_id);
        exit();
    }
    
    try {
        // Check if user already has an RSVP
        $check_stmt = $pdo->prepare("SELECT id FROM rsvps WHERE event_id = ? AND user_id = ?");
        $check_stmt->execute([$event_id, $_SESSION['user_id']]);
        $existing_rsvp = $check_stmt->fetch();
        
        if ($existing_rsvp) {
            // Update existing RSVP
            $update_stmt = $pdo->prepare("UPDATE rsvps SET status = ? WHERE id = ?");
            $update_stmt->execute([$status, $existing_rsvp['id']]);
        } else {
            // Create new RSVP
            $insert_stmt = $pdo->prepare("INSERT INTO rsvps (event_id, user_id, status) VALUES (?, ?, ?)");
            $insert_stmt->execute([$event_id, $_SESSION['user_id'], $status]);
        }
        
        // Send RSVP confirmation email if enabled
        if ($email_sending_enabled) {
            // Get user details for email
            $user_stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
            $user_stmt->execute([$_SESSION['user_id']]);
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
                    $status
                );
            }
        }
        
        $_SESSION['success'] = "Your RSVP has been updated!";
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $_SESSION['error'] = "Failed to update RSVP. Please try again.";
    }
    
    header("Location: event_details.php?id=" . $event_id);
    exit();
}

// Get user's RSVP status if logged in
$user_rsvp = null;
if (isset($_SESSION['user_id'])) {
    $rsvp_stmt = $pdo->prepare("SELECT status FROM rsvps WHERE event_id = ? AND user_id = ?");
    $rsvp_stmt->execute([$event_id, $_SESSION['user_id']]);
    $user_rsvp = $rsvp_stmt->fetch();
}

// Check if user is the event manager
$is_manager = isset($_SESSION['user_id']) && ($event['manager_id'] == $_SESSION['user_id'] || $event['created_by'] == $_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($event['title']); ?> - EventHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
    .rating {
        display: flex;
        flex-direction: row-reverse;
        justify-content: flex-end;
    }

    .rating input {
        display: none;
    }

    .rating label {
        cursor: pointer;
        font-size: 1.5rem;
        color: #ddd;
        padding: 0 0.1em;
    }

    .rating input:checked ~ label,
    .rating label:hover,
    .rating label:hover ~ label {
        color: #ffc107;
    }

    .rating-item:last-child {
        border-bottom: none !important;
    }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container my-5">
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
        
        <div class="card animate__animated animate__fadeIn">
            <div class="card-body">
                <h2 class="card-title"><?php echo htmlspecialchars($event['title']); ?></h2>
                <p class="text-muted">Created by <?php echo htmlspecialchars($event['manager_name']); ?></p>
                
                <?php if ($event['image_path']): ?>
                    <div class="event-image mb-4">
                        <img src="<?php echo htmlspecialchars($event['image_path']); ?>" 
                             alt="<?php echo htmlspecialchars($event['title']); ?>" 
                             class="img-fluid rounded">
                    </div>
                <?php endif; ?>
                
                <div class="row mt-4">
                    <div class="col-md-8">
                        <h4>Description</h4>
                        <p><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
                        
                        <h4 class="mt-4">Event Details</h4>
                        <ul class="list-unstyled">
                            <li><strong>Date:</strong> <?php echo date('F j, Y', strtotime($event['date'])); ?></li>
                            <li><strong>Time:</strong> <?php echo date('g:i A', strtotime($event['time'])); ?></li>
                            <li><strong>Location:</strong> <?php echo htmlspecialchars($event['location']); ?></li>
                            <li><strong>Capacity:</strong> <?php echo $event['rsvp_count']; ?>/<?php echo $event['capacity']; ?> spots filled</li>
                            <li>
                                <strong>Price:</strong>
                                <?php if ($event['price'] > 0): ?>
                                    ₹<?php echo number_format($event['price'], 2); ?>
                                    <span class="badge bg-<?php echo $event['event_type'] === 'donation' ? 'warning' : 'success'; ?> ms-2">
                                        <?php echo ucfirst($event['event_type']); ?>
                                    </span>
                                <?php else: ?>
                                    Free
                                <?php endif; ?>
                            </li>
                            <?php if ($event['category_name']): ?>
                                <li>
                                    <strong>Category:</strong>
                                    <span class="badge bg-info">
                                        <?php echo htmlspecialchars($event['category_name']); ?>
                                    </span>
                                </li>
                            <?php endif; ?>
                            <?php if (!empty($event_tags)): ?>
                                <li>
                                    <strong>Tags:</strong>
                                    <?php foreach ($event_tags as $tag): ?>
                                        <span class="badge bg-secondary me-1">
                                            <?php echo htmlspecialchars($tag['name']); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    
                    <div class="col-md-4">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">RSVP</h5>
                                    <form method="POST">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="radio" name="rsvp_status" value="confirmed" 
                                                <?php echo ($user_rsvp && $user_rsvp['status'] === 'confirmed') ? 'checked' : ''; ?>>
                                            <label class="form-check-label">Confirmed</label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="radio" name="rsvp_status" value="pending"
                                                <?php echo ($user_rsvp && $user_rsvp['status'] === 'pending') ? 'checked' : ''; ?>>
                                            <label class="form-check-label">Pending</label>
                                        </div>
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="radio" name="rsvp_status" value="cancelled"
                                                <?php echo ($user_rsvp && $user_rsvp['status'] === 'cancelled') ? 'checked' : ''; ?>>
                                            <label class="form-check-label">Cancelled</label>
                                        </div>
                                        <button type="submit" class="btn btn-primary w-100">Submit RSVP</button>
                                    </form>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                Please <a href="login.php">login</a> to RSVP for this event.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!empty($event['attending_users'])): ?>
                    <div class="mt-4">
                        <h4>Who's Attending</h4>
                        <p><?php echo htmlspecialchars($event['attending_users']); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Share Event</h5>
                        <div class="d-flex gap-2">
                            <a href="<?php echo $sharing_links['facebook']; ?>" target="_blank" class="btn btn-primary">
                                <i class="fab fa-facebook"></i> Facebook
                            </a>
                            <a href="<?php echo $sharing_links['twitter']; ?>" target="_blank" class="btn btn-info">
                                <i class="fab fa-twitter"></i> Twitter
                            </a>
                            <a href="<?php echo $sharing_links['linkedin']; ?>" target="_blank" class="btn btn-secondary">
                                <i class="fab fa-linkedin"></i> LinkedIn
                            </a>
                            <a href="<?php echo $sharing_links['whatsapp']; ?>" target="_blank" class="btn btn-success">
                                <i class="fab fa-whatsapp"></i> WhatsApp
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Event Rating</h5>
                        <div class="d-flex align-items-center mb-3">
                            <div class="me-3">
                                <h2 class="mb-0"><?php echo number_format($avg_rating, 1); ?></h2>
                                <div class="text-warning">
                                    <?php
                                    $full_stars = floor($avg_rating);
                                    $half_star = $avg_rating - $full_stars >= 0.5;
                                    for ($i = 0; $i < 5; $i++) {
                                        if ($i < $full_stars) {
                                            echo '<i class="fas fa-star"></i>';
                                        } elseif ($i == $full_stars && $half_star) {
                                            echo '<i class="fas fa-star-half-alt"></i>';
                                        } else {
                                            echo '<i class="far fa-star"></i>';
                                        }
                                    }
                                    ?>
                                </div>
                                <small class="text-muted"><?php echo count($ratings); ?> ratings</small>
                            </div>
                        </div>
                        
                        <?php 
                        if (isset($_SESSION['user_id']) && !$is_manager): 
                        ?>
                        <form id="ratingForm" class="mb-4">
                            <input type="hidden" name="event_id" value="<?php echo $event_id; ?>">
                            <div class="mb-3">
                                <label class="form-label">Your Rating</label>
                                <div class="rating">
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <input type="radio" name="rating" value="<?php echo $i; ?>" id="star<?php echo $i; ?>">
                                    <label for="star<?php echo $i; ?>"><i class="fas fa-star"></i></label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="comment" class="form-label">Your Comment</label>
                                <textarea class="form-control" id="comment" name="comment" rows="3"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Submit Rating</button>
                        </form>
                        <?php endif; ?>
                        
                        <div class="ratings-list">
                            <?php foreach ($ratings as $rating): ?>
                            <div class="rating-item border-bottom py-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <strong><?php echo htmlspecialchars($rating['username']); ?></strong>
                                        <div class="text-warning">
                                            <?php for ($i = 0; $i < 5; $i++): ?>
                                            <i class="fas fa-star<?php echo $i < $rating['rating'] ? '' : '-o'; ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo date('M d, Y', strtotime($rating['created_at'])); ?>
                                    </small>
                                </div>
                                <?php if (!empty($rating['comment'])): ?>
                                <p class="mb-0"><?php echo htmlspecialchars($rating['comment']); ?></p>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-body">
                <h5 class="card-title">Add to Calendar</h5>
                <div class="d-flex gap-2">
                    <a href="<?php echo $google_calendar_url; ?>" target="_blank" class="btn btn-outline-primary">
                        <i class="fab fa-google"></i> Google Calendar
                    </a>
                    <a href="<?php echo $outlook_calendar_url; ?>" target="_blank" class="btn btn-outline-secondary">
                        <i class="fab fa-microsoft"></i> Outlook
                    </a>
                    <?php if ($is_manager): ?>
                    <button id="sendInvitesBtn" class="btn btn-outline-success">
                        <i class="fas fa-envelope"></i> Send Calendar Invites
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-body">
                <h5 class="card-title">Location</h5>
                <?php if ($coordinates): ?>
                <div id="map" style="height: 400px; width: 100%; border-radius: 8px; margin-bottom: 20px;"></div>
                <div class="d-flex gap-2">
                    <a href="<?php echo $directions_url; ?>" target="_blank" class="btn btn-primary">
                        <i class="fas fa-directions"></i> Get Directions
                    </a>
                    <button id="copyAddress" class="btn btn-outline-secondary">
                        <i class="fas fa-copy"></i> Copy Address
                    </button>
                    <?php if ($is_manager): ?>
                    <button id="updateCoordinates" class="btn btn-outline-info">
                        <i class="fas fa-sync-alt"></i> Update Coordinates
                    </button>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <p class="text-muted">Location map not available</p>
                <a href="<?php echo $directions_url; ?>" target="_blank" class="btn btn-primary">
                    <i class="fas fa-directions"></i> Get Directions
                </a>
                <?php if ($is_manager): ?>
                <button id="updateCoordinates" class="btn btn-outline-info">
                    <i class="fas fa-sync-alt"></i> Update Coordinates
                </button>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
    document.getElementById('ratingForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('rate_event', '1');
        
        fetch('social_features.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(() => {
            location.reload();
        })
        .catch(error => console.error('Error:', error));
    });

    document.getElementById('sendInvitesBtn')?.addEventListener('click', function() {
        if (confirm('Send calendar invites to all confirmed attendees?')) {
            const formData = new FormData();
            formData.append('send_invites', '1');
            formData.append('event_id', '<?php echo $event_id; ?>');
            
            fetch('calendar_integration.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Calendar invites have been sent to all confirmed attendees.');
                } else {
                    alert('Failed to send calendar invites. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while sending calendar invites.');
            });
        }
    });

    // Update coordinates button
    document.getElementById('updateCoordinates')?.addEventListener('click', function() {
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
        
        fetch('location_services.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'get_coordinates=1&address=' + encodeURIComponent('<?php echo addslashes($event['location']); ?>')
        })
        .then(response => response.json())
        .then(data => {
            if (data && data.lat && data.lng) {
                // Update coordinates in database
                fetch('location_services.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'update_coordinates=1&event_id=<?php echo $event_id; ?>&lat=' + data.lat + '&lng=' + data.lng
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        alert('Coordinates updated successfully!');
                        location.reload();
                    } else {
                        alert('Failed to update coordinates: ' + result.message);
                        this.innerHTML = '<i class="fas fa-sync-alt"></i> Update Coordinates';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while updating coordinates.');
                    this.innerHTML = '<i class="fas fa-sync-alt"></i> Update Coordinates';
                });
            } else {
                alert('Could not find coordinates for this address.');
                this.innerHTML = '<i class="fas fa-sync-alt"></i> Update Coordinates';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while geocoding the address.');
            this.innerHTML = '<i class="fas fa-sync-alt"></i> Update Coordinates';
        });
    });
    </script>

    <?php if ($coordinates): ?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
    function initMap() {
        try {
            const eventLocation = [<?php echo $coordinates['lat']; ?>, <?php echo $coordinates['lng']; ?>];
            
            const map = L.map('map').setView(eventLocation, 15);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);
            
            const marker = L.marker(eventLocation).addTo(map);
            
            marker.bindPopup(`
                <div>
                    <h5><?php echo addslashes($event['title']); ?></h5>
                    <p><?php echo addslashes($event['location']); ?></p>
                </div>
            `);
            
            // Try to get user's location
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        const userLocation = [
                            position.coords.latitude,
                            position.coords.longitude
                        ];
                        
                        const userMarker = L.marker(userLocation, {
                            icon: L.divIcon({
                                className: 'user-location-marker',
                                html: '<div style="background-color: #4285F4; width: 12px; height: 12px; border-radius: 50%; border: 2px solid white;"></div>',
                                iconSize: [12, 12]
                            })
                        }).addTo(map);
                        
                        // Draw route between user and event
                        fetch(`https://router.project-osrm.org/route/v1/driving/${userLocation[1]},${userLocation[0]};${eventLocation[1]},${eventLocation[0]}?overview=full&geometries=geojson`)
                            .then(response => response.json())
                            .then(data => {
                                if (data.routes && data.routes[0]) {
                                    L.geoJSON(data.routes[0].geometry, {
                                        style: {
                                            color: '#4285F4',
                                            weight: 4,
                                            opacity: 0.7
                                        }
                                    }).addTo(map);
                                    
                                    // Fit map to show both markers and route
                                    const bounds = L.latLngBounds([userLocation, eventLocation]);
                                    map.fitBounds(bounds, { padding: [50, 50] });
                                }
                            })
                            .catch(error => console.error('Error getting route:', error));
                    },
                    (error) => {
                        console.error('Error getting location:', error);
                    }
                );
            }
        } catch (error) {
            console.error('Error initializing map:', error);
            document.getElementById('map').innerHTML = `
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    Unable to load the map. Please check your internet connection and try again.
                </div>
            `;
        }
    }

    // Initialize map when page loads
    window.onload = initMap;
    </script>
    <?php endif; ?>
</body>
</html> 