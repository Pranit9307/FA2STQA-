<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Debug information
echo "<h2>Debug Information</h2>";

// 1. Check user's interests
$stmt = $pdo->prepare("SELECT category_id FROM user_interests WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_interests = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "<h3>1. User Interests</h3>";
echo "User ID: " . $_SESSION['user_id'] . "<br>";
echo "Selected Interests: " . implode(', ', $user_interests) . "<br><br>";

// 2. Check categories
$stmt = $pdo->query("SELECT * FROM categories");
$categories = $stmt->fetchAll();

echo "<h3>2. Categories</h3>";
echo "<table border='1'>";
echo "<tr><th>ID</th><th>Name</th></tr>";
foreach ($categories as $category) {
    echo "<tr>";
    echo "<td>" . $category['id'] . "</td>";
    echo "<td>" . $category['name'] . "</td>";
    echo "</tr>";
}
echo "</table><br>";

// 3. Check events
$stmt = $pdo->query("
    SELECT e.*, c.name as category_name 
    FROM events e 
    JOIN categories c ON e.category_id = c.id 
    WHERE e.date >= CURDATE()
    ORDER BY e.date ASC
");
$events = $stmt->fetchAll();

echo "<h3>3. Upcoming Events</h3>";
echo "<table border='1'>";
echo "<tr><th>ID</th><th>Title</th><th>Category</th><th>Date</th></tr>";
foreach ($events as $event) {
    echo "<tr>";
    echo "<td>" . $event['id'] . "</td>";
    echo "<td>" . $event['title'] . "</td>";
    echo "<td>" . $event['category_name'] . "</td>";
    echo "<td>" . $event['date'] . "</td>";
    echo "</tr>";
}
echo "</table><br>";

// 4. Test the recommendations query
if (!empty($user_interests)) {
    $placeholders = str_repeat('?,', count($user_interests) - 1) . '?';
    $query = "
        SELECT e.*, c.name as category_name 
        FROM events e 
        JOIN categories c ON e.category_id = c.id 
        WHERE e.date >= CURDATE() 
        AND e.category_id IN ($placeholders)
        ORDER BY e.date ASC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($user_interests);
    $recommendations = $stmt->fetchAll();
    
    echo "<h3>4. Recommendations Query Results</h3>";
    echo "Query: " . $query . "<br>";
    echo "Parameters: " . implode(', ', $user_interests) . "<br>";
    echo "Number of results: " . count($recommendations) . "<br>";
    
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Title</th><th>Category</th><th>Date</th></tr>";
    foreach ($recommendations as $event) {
        echo "<tr>";
        echo "<td>" . $event['id'] . "</td>";
        echo "<td>" . $event['title'] . "</td>";
        echo "<td>" . $event['category_name'] . "</td>";
        echo "<td>" . $event['date'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<h3>4. Recommendations Query Results</h3>";
    echo "No interests selected. Please select interests to get recommendations.";
}
?> 