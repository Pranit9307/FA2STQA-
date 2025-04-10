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
    $stmt = $pdo->prepare("
        SELECT DISTINCT e.*, u.username as manager_name, c.name as category_name,
               COUNT(DISTINCT r.id) as rsvp_count,
               AVG(er.rating) as avg_rating
        FROM events e
        JOIN users u ON e.manager_id = u.id
        JOIN categories c ON e.category_id = c.id
        LEFT JOIN rsvps r ON e.id = r.event_id
        LEFT JOIN event_ratings er ON e.id = er.event_id
        WHERE e.category_id IN (
            SELECT category_id 
            FROM user_interests 
            WHERE user_id = ?
        )
        AND e.date >= CURDATE()
        GROUP BY e.id
        ORDER BY avg_rating DESC, rsvp_count DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
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