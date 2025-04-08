<?php
$host = 'localhost';
$username = 'root';
$password = '';

try {
    // Connect to MySQL without selecting a database
    $pdo = new PDO("mysql:host=$host;unix_socket=/opt/lampp/var/mysql/mysql.sock", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS event_management_system");
    echo "Database 'event_management_system' created successfully!<br>";
    
    // Select the database
    $pdo->exec("USE event_management_system");
    
    // Create users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT PRIMARY KEY AUTO_INCREMENT,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('attendant', 'event_manager') NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Table 'users' created successfully!<br>";
    
    // Create events table
    $pdo->exec("CREATE TABLE IF NOT EXISTS events (
        id INT PRIMARY KEY AUTO_INCREMENT,
        title VARCHAR(100) NOT NULL,
        description TEXT,
        date DATE NOT NULL,
        time TIME NOT NULL,
        location VARCHAR(255) NOT NULL,
        capacity INT NOT NULL,
        manager_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (manager_id) REFERENCES users(id)
    )");
    echo "Table 'events' created successfully!<br>";
    
    // Drop existing rsvps table if it exists
    $pdo->exec("DROP TABLE IF EXISTS rsvps");
    echo "Dropped existing 'rsvps' table<br>";
    
    // Create rsvps table with new enum values
    $pdo->exec("CREATE TABLE rsvps (
        id INT PRIMARY KEY AUTO_INCREMENT,
        event_id INT,
        user_id INT,
        status ENUM('attending', 'maybe', 'not_attending') DEFAULT 'maybe',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (event_id) REFERENCES events(id),
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");
    echo "Table 'rsvps' created successfully with new status values!<br>";
    
    echo "<br>All tables have been created successfully!<br>";
    echo "You can now <a href='index.php'>go to the homepage</a> and start using the system.";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 