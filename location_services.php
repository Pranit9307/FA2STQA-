<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/database.php';

// Function to get coordinates from address using OpenStreetMap Nominatim
function getCoordinates($address) {
    $address = urlencode($address);
    $url = "https://nominatim.openstreetmap.org/search?format=json&q={$address}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'EventManagementSystem/1.0');
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if (!empty($data)) {
        return [
            'lat' => (float)$data[0]['lat'],
            'lng' => (float)$data[0]['lon']
        ];
    }
    
    return null;
}

// Function to get nearby events based on user's location
function getNearbyEvents($pdo, $lat, $lng, $radius = 10) {
    // First, check if there are any events with coordinates
    $checkSql = "SELECT COUNT(*) FROM events WHERE latitude IS NOT NULL AND longitude IS NOT NULL";
    $checkStmt = $pdo->query($checkSql);
    $hasCoordinates = $checkStmt->fetchColumn() > 0;
    
    if (!$hasCoordinates) {
        // If no events have coordinates, return all upcoming events
        $sql = "
            SELECT e.*, u.username as manager_name, c.name as category_name,
                   COUNT(DISTINCT r.id) as rsvp_count,
                   AVG(er.rating) as avg_rating,
                   999999 AS distance
            FROM events e
            LEFT JOIN users u ON e.created_by = u.id
            LEFT JOIN categories c ON e.category_id = c.id
            LEFT JOIN rsvps r ON e.id = r.event_id
            LEFT JOIN event_ratings er ON e.id = er.event_id
            WHERE e.date >= CURDATE()
            GROUP BY e.id
            ORDER BY e.date ASC
            LIMIT 20
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Debug log
        error_log("No events with coordinates found. Returning " . count($events) . " upcoming events.");
        return $events;
    }
    
    // Using the Haversine formula to calculate distances
    $sql = "
        SELECT e.*, u.username as manager_name, c.name as category_name,
               COUNT(DISTINCT r.id) as rsvp_count,
               AVG(er.rating) as avg_rating,
               (
                   6371 * acos(
                       cos(radians(?)) * cos(radians(latitude)) * 
                       cos(radians(longitude) - radians(?)) + 
                       sin(radians(?)) * sin(radians(latitude))
                   )
               ) AS distance
        FROM events e
        LEFT JOIN users u ON e.created_by = u.id
        LEFT JOIN categories c ON e.category_id = c.id
        LEFT JOIN rsvps r ON e.id = r.event_id
        LEFT JOIN event_ratings er ON e.id = er.event_id
        WHERE e.date >= CURDATE()
        AND e.latitude IS NOT NULL 
        AND e.longitude IS NOT NULL
        GROUP BY e.id
        HAVING distance <= ?
        ORDER BY distance
        LIMIT 20
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$lat, $lng, $lat, $radius]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug log
    error_log("Found " . count($events) . " events within " . $radius . " km of (" . $lat . ", " . $lng . ")");
    
    // Ensure all events have the required fields
    foreach ($events as &$event) {
        if (!isset($event['distance'])) {
            $event['distance'] = 999999;
        }
        if (!isset($event['manager_name'])) {
            $event['manager_name'] = 'Unknown';
        }
        if (!isset($event['category_name'])) {
            $event['category_name'] = 'Uncategorized';
        }
    }
    
    return $events;
}

// Function to get directions URL using OpenStreetMap
function getDirectionsUrl($destination) {
    return "https://www.openstreetmap.org/directions?from=&to=" . urlencode($destination);
}

// Handle AJAX request for nearby events
if (isset($_POST['get_nearby_events']) && isset($_POST['lat']) && isset($_POST['lng'])) {
    $lat = $_POST['lat'];
    $lng = $_POST['lng'];
    $radius = isset($_POST['radius']) ? (int)$_POST['radius'] : 10;
    
    $events = getNearbyEvents($pdo, $lat, $lng, $radius);
    
    // Add debug information
    $debug = [
        'count' => count($events),
        'user_location' => ['lat' => $lat, 'lng' => $lng, 'radius' => $radius],
        'events_with_coords' => $pdo->query("SELECT COUNT(*) FROM events WHERE latitude IS NOT NULL AND longitude IS NOT NULL")->fetchColumn()
    ];
    
    // Log the response for debugging
    error_log("Sending response with " . count($events) . " events");
    
    header('Content-Type: application/json');
    echo json_encode(['events' => $events, 'debug' => $debug]);
    exit();
}

// Handle AJAX request for coordinates
if (isset($_POST['get_coordinates']) && isset($_POST['address'])) {
    $coordinates = getCoordinates($_POST['address']);
    header('Content-Type: application/json');
    echo json_encode($coordinates);
    exit();
}

// Handle AJAX request to update coordinates for an event
if (isset($_POST['update_coordinates']) && isset($_POST['event_id']) && isset($_POST['lat']) && isset($_POST['lng'])) {
    $event_id = (int)$_POST['event_id'];
    $lat = (float)$_POST['lat'];
    $lng = (float)$_POST['lng'];
    
    try {
        $stmt = $pdo->prepare("UPDATE events SET latitude = ?, longitude = ? WHERE id = ?");
        $stmt->execute([$lat, $lng, $event_id]);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}
?> 