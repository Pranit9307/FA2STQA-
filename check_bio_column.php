<?php
require_once 'config/database.php';

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'bio'");
    if ($stmt->rowCount() > 0) {
        echo "Bio column exists in users table.";
    } else {
        echo "Bio column does not exist in users table.";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 