<?php
require_once 'config/database.php';

try {
    // Add bio column if it doesn't exist
    $sql = "ALTER TABLE users ADD COLUMN IF NOT EXISTS bio TEXT";
    $pdo->exec($sql);
    echo "Added bio column successfully.<br>";

    // Add profile_picture column if it doesn't exist
    $sql = "ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_picture VARCHAR(255) DEFAULT '/SDL_final/assets/images/default-avatar.png'";
    $pdo->exec($sql);
    echo "Added profile_picture column successfully.<br>";

    echo "All columns added successfully!";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 