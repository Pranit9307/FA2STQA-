<?php
require_once 'config/database.php';

try {
    // Check if profile_picture column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) DEFAULT '/SDL_final/assets/images/default-avatar.png'");
        echo "Added profile_picture column successfully.<br>";
    } else {
        echo "profile_picture column already exists.<br>";
    }

    // Check if bio column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'bio'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN bio TEXT");
        echo "Added bio column successfully.<br>";
    } else {
        echo "bio column already exists.<br>";
    }

    echo "All columns are now properly set up!";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 