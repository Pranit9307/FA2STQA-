<?php
require_once 'config/database.php';

try {
    // Check if image_path column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM events LIKE 'image_path'");
    $columnExists = $stmt->rowCount() > 0;
    
    if (!$columnExists) {
        // Add image_path column
        $pdo->exec("ALTER TABLE events ADD COLUMN image_path VARCHAR(255) DEFAULT NULL AFTER location");
        echo "Successfully added image_path column to events table.<br>";
    } else {
        echo "image_path column already exists in events table.<br>";
    }
    
    // Check if is_featured column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM events LIKE 'is_featured'");
    $isFeaturedExists = $stmt->rowCount() > 0;
    
    if (!$isFeaturedExists) {
        // Add is_featured column
        $pdo->exec("ALTER TABLE events ADD COLUMN is_featured BOOLEAN DEFAULT FALSE");
        echo "Successfully added is_featured column to events table.<br>";
    } else {
        echo "is_featured column already exists in events table.<br>";
    }
    
    // Check if created_by column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM events LIKE 'created_by'");
    $columnExists = $stmt->rowCount() > 0;
    
    if (!$columnExists) {
        // Add created_by column
        $pdo->exec("ALTER TABLE events ADD COLUMN created_by INT AFTER is_featured");
        echo "Successfully added created_by column to events table.<br>";
        
        // Add foreign key for created_by
        $pdo->exec("ALTER TABLE events ADD CONSTRAINT fk_events_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL");
        echo "Successfully added foreign key for created_by column.<br>";
    } else {
        echo "created_by column already exists in events table.<br>";
    }
    
    // Check capacity column and set default value
    $stmt = $pdo->query("SHOW COLUMNS FROM events LIKE 'capacity'");
    $capacityColumn = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($capacityColumn) {
        // Check if it has a default value
        if ($capacityColumn['Default'] === null) {
            // Add default value to capacity column
            $pdo->exec("ALTER TABLE events MODIFY capacity INT NOT NULL DEFAULT 100");
            echo "Successfully added default value to capacity column.<br>";
        }
    } else {
        // Add capacity column if it doesn't exist
        $pdo->exec("ALTER TABLE events ADD COLUMN capacity INT NOT NULL DEFAULT 100");
        echo "Successfully added capacity column with default value.<br>";
    }
    
    // Check if price column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM events LIKE 'price'");
    $columnExists = $stmt->rowCount() > 0;
    
    if (!$columnExists) {
        // Add price column
        $pdo->exec("ALTER TABLE events ADD COLUMN price DECIMAL(10,2) DEFAULT 0.00 AFTER capacity");
        echo "Successfully added price column to events table.<br>";
    } else {
        echo "price column already exists in events table.<br>";
    }
    
    // Check if available_spots column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM events LIKE 'available_spots'");
    $columnExists = $stmt->rowCount() > 0;
    
    if (!$columnExists) {
        // Add available_spots column
        $pdo->exec("ALTER TABLE events ADD COLUMN available_spots INT DEFAULT NULL AFTER price");
        echo "Successfully added available_spots column to events table.<br>";
        
        // Update existing events to set available_spots equal to capacity
        $pdo->exec("UPDATE events SET available_spots = capacity WHERE available_spots IS NULL");
        echo "Successfully updated existing events with available_spots.<br>";
    } else {
        echo "available_spots column already exists in events table.<br>";
    }
    
    // Check if event_type column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM events LIKE 'event_type'");
    $columnExists = $stmt->rowCount() > 0;
    
    if (!$columnExists) {
        // Add event_type column
        $pdo->exec("ALTER TABLE events ADD COLUMN event_type ENUM('free', 'paid', 'donation') DEFAULT 'free' AFTER price");
        echo "Successfully added event_type column to events table.<br>";
    } else {
        echo "event_type column already exists in events table.<br>";
    }
    
    echo "<br>Database check completed successfully!";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
?> 