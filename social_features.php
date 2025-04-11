<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Handle follow/unfollow
if (isset($_POST['action']) && isset($_POST['user_id'])) {
    $follower_id = $_SESSION['user_id'];
    $followed_id = $_POST['user_id'];
    
    if ($_POST['action'] === 'follow') {
        $stmt = $pdo->prepare("INSERT INTO follows (follower_id, followed_id) VALUES (?, ?)");
        $stmt->execute([$follower_id, $followed_id]);
    } else if ($_POST['action'] === 'unfollow') {
        $stmt = $pdo->prepare("DELETE FROM follows WHERE follower_id = ? AND followed_id = ?");
        $stmt->execute([$follower_id, $followed_id]);
    }
    exit();
}

// Handle event rating and comment
if (isset($_POST['rate_event']) && isset($_POST['event_id'])) {
    $event_id = $_POST['event_id'];
    $user_id = $_SESSION['user_id'];
    $rating = $_POST['rating'];
    $comment = $_POST['comment'] ?? '';
    
    $stmt = $pdo->prepare("INSERT INTO event_ratings (event_id, user_id, rating, comment) 
                          VALUES (?, ?, ?, ?) 
                          ON DUPLICATE KEY UPDATE rating = ?, comment = ?");
    $stmt->execute([$event_id, $user_id, $rating, $comment, $rating, $comment]);
    exit();
}

// Handle user interests
if (isset($_POST['update_interests'])) {
    $user_id = $_SESSION['user_id'];
    $interests = $_POST['interests'] ?? [];
    
    // Delete existing interests
    $stmt = $pdo->prepare("DELETE FROM user_interests WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    // Add new interests
    if (!empty($interests)) {
        $stmt = $pdo->prepare("INSERT INTO user_interests (user_id, category_id) VALUES (?, ?)");
        foreach ($interests as $category_id) {
            $stmt->execute([$user_id, $category_id]);
        }
    }
    exit();
}

// Get event recommendations based on user interests
function getEventRecommendations($pdo, $user_id) {
    // First, get user's interests
    $stmt = $pdo->prepare("SELECT category_id FROM user_interests WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user_interests = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Debug log
    error_log("User interests: " . print_r($user_interests, true));

    // If no interests selected, return empty array
    if (empty($user_interests)) {
        return [];
    }

    // Build the query with placeholders for interests
    $placeholders = str_repeat('?,', count($user_interests) - 1) . '?';
    $query = "
        SELECT e.*, c.name as category_name,
               COUNT(DISTINCT r.id) as rsvp_count,
               COALESCE(AVG(er.rating), 0) as avg_rating
        FROM events e
        JOIN categories c ON e.category_id = c.id
        LEFT JOIN rsvps r ON e.id = r.event_id
        LEFT JOIN event_ratings er ON e.id = er.event_id
        WHERE e.date >= CURDATE()
        AND e.category_id IN ($placeholders)
        GROUP BY e.id
        ORDER BY e.date ASC, avg_rating DESC
        LIMIT 10
    ";

    // Debug log
    error_log("Query: " . $query);
    error_log("Parameters: " . print_r($user_interests, true));

    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($user_interests);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Debug log
        error_log("Number of results: " . count($results));
        error_log("Results: " . print_r($results, true));
        
        return $results;
    } catch (PDOException $e) {
        error_log("Error in getEventRecommendations: " . $e->getMessage());
        return [];
    }
}

// Get social sharing links
function getSocialSharingLinks($event) {
    $event_url = urlencode("http://$_SERVER[HTTP_HOST]/event_details.php?id=" . $event['id']);
    $event_title = urlencode($event['title']);
    
    return [
        'facebook' => "https://www.facebook.com/sharer/sharer.php?u=" . $event_url,
        'twitter' => "https://twitter.com/intent/tweet?url=" . $event_url . "&text=" . $event_title,
        'linkedin' => "https://www.linkedin.com/shareArticle?mini=true&url=" . $event_url . "&title=" . $event_title,
        'whatsapp' => "https://api.whatsapp.com/send?text=" . $event_title . "%20" . $event_url
    ];
}
?> 