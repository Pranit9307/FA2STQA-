<?php
require_once 'config/database.php';

try {
    $stmt = $pdo->query("DESCRIBE users");
    echo "<pre>";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
    echo "</pre>";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 