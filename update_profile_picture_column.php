<?php
require_once 'config/database.php';

try {
    $sql = "ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) DEFAULT '/SDL_final/assets/images/default-avatar.png'";
    $pdo->exec($sql);
    echo "Successfully added profile_picture column to users table.";
} catch (PDOException $e) {
    if ($e->getCode() == '42S21') {
        echo "Column profile_picture already exists.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
?> 