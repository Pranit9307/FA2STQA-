<?php
require_once 'config/database.php';

try {
    // Add profile_picture column
    $pdo->exec("ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) DEFAULT '/SDL_final/assets/images/default-avatar.png'");
    echo "Successfully added profile_picture column to users table.";
    
    // Create the uploads directory if it doesn't exist
    $upload_dir = 'uploads/profile_pictures';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
        echo "\nCreated uploads directory for profile pictures.";
    }
} catch (PDOException $e) {
    if ($e->getCode() == '42S21') {
        // Error code for duplicate column
        echo "Profile picture column already exists in users table.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
?> 