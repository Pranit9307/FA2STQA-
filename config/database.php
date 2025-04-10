<?php
$host = 'localhost';
$dbname = 'event_management_system';
$username = 'root';
$password = 'Pranit@123';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create follows table
    $sql = "CREATE TABLE IF NOT EXISTS follows (
        id INT AUTO_INCREMENT PRIMARY KEY,
        follower_id INT NOT NULL,
        followed_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (followed_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_follow (follower_id, followed_id)
    )";
    $pdo->exec($sql);

    // Create event_ratings table
    $sql = "CREATE TABLE IF NOT EXISTS event_ratings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_id INT NOT NULL,
        user_id INT NOT NULL,
        rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
        comment TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_rating (event_id, user_id)
    )";
    $pdo->exec($sql);

    // Create user_interests table
    $sql = "CREATE TABLE IF NOT EXISTS user_interests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        category_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
        UNIQUE KEY unique_interest (user_id, category_id)
    )";
    $pdo->exec($sql);

    // Add latitude and longitude columns to events table if they don't exist
    $sql = "SHOW COLUMNS FROM events LIKE 'latitude'";
    $result = $pdo->query($sql);
    if ($result->rowCount() == 0) {
        $sql = "ALTER TABLE events ADD COLUMN latitude DECIMAL(10, 8) NULL, ADD COLUMN longitude DECIMAL(11, 8) NULL";
        $pdo->exec($sql);
    }
} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
?> 