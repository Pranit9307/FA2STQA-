<?php
require_once 'config/database.php';

try {
    // First check if the column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'bio'");
    if ($stmt->rowCount() == 0) {
        // Add bio column if it doesn't exist
        $pdo->exec("ALTER TABLE users ADD COLUMN bio TEXT NULL");
        echo "Successfully added bio column to users table.";
    } else {
        echo "Bio column already exists in users table.";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 