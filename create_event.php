<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and is an event manager
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'event_manager') {
    header("Location: login.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Handle image upload
        $image_path = null;
        if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($_FILES['event_image']['type'], $allowed_types)) {
                throw new Exception('Invalid file type. Only JPEG, PNG, and GIF images are allowed.');
            }
            
            if ($_FILES['event_image']['size'] > $max_size) {
                throw new Exception('File size too large. Maximum size is 5MB.');
            }
            
            // Create uploads directory if it doesn't exist
            if (!file_exists('uploads/events')) {
                mkdir('uploads/events', 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['event_image']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid() . '.' . $file_extension;
            $upload_path = 'uploads/events/' . $file_name;
            
            if (move_uploaded_file($_FILES['event_image']['tmp_name'], $upload_path)) {
                $image_path = $upload_path;
            } else {
                throw new Exception('Failed to upload image.');
            }
        }

        // Insert event into database
        $stmt = $pdo->prepare("
            INSERT INTO events (title, description, date, time, location, image_path, is_featured, created_by,
                              price, event_type, capacity, available_spots)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $_POST['title'],
            $_POST['description'],
            $_POST['date'],
            $_POST['time'],
            $_POST['location'],
            $image_path,
            isset($_POST['is_featured']) ? 1 : 0,
            $_SESSION['user_id'],
            $_POST['price'],
            $_POST['event_type'],
            $_POST['capacity'],
            $_POST['capacity']  // Initially, available_spots equals capacity
        ]);
        
        $event_id = $pdo->lastInsertId();
        
        // Handle category
        if (isset($_POST['category_id'])) {
            $stmt = $pdo->prepare("UPDATE events SET category_id = ? WHERE id = ?");
            $stmt->execute([$_POST['category_id'], $event_id]);
        }
        
        // Handle tags
        if (isset($_POST['tags']) && is_array($_POST['tags'])) {
            $stmt = $pdo->prepare("INSERT INTO event_tags (event_id, tag_id) VALUES (?, ?)");
            foreach ($_POST['tags'] as $tag_id) {
                $stmt->execute([$event_id, $tag_id]);
            }
        }
        
        $success = 'Event created successfully!';
        header("Location: event_details.php?id=" . $event_id);
        exit();
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Fetch categories and tags for the form
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$tags = $pdo->query("SELECT * FROM tags ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Event - EventHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card animate__animated animate__fadeIn">
                    <div class="card-body p-5">
                        <h2 class="text-center mb-4">Create New Event</h2>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="title" class="form-label">Event Title</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="4" required></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="date" class="form-label">Date</label>
                                    <input type="date" class="form-control" id="date" name="date" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="time" class="form-label">Time</label>
                                    <input type="time" class="form-control" id="time" name="time" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="location" class="form-label">Location</label>
                                <input type="text" class="form-control" id="location" name="location" required>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="price" class="form-label">Price</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₹</span>
                                        <input type="number" class="form-control" id="price" name="price" 
                                               min="0" step="0.01" value="0" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="event_type" class="form-label">Event Type</label>
                                    <select class="form-select" id="event_type" name="event_type" required>
                                        <option value="free">Free</option>
                                        <option value="paid">Paid</option>
                                        <option value="donation">Donation</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="capacity" class="form-label">Capacity</label>
                                    <input type="number" class="form-control" id="capacity" name="capacity" 
                                           min="1" value="100" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="event_image" class="form-label">Event Image</label>
                                <input type="file" class="form-control" id="event_image" name="event_image" accept="image/*">
                                <div class="form-text">Upload a JPEG, PNG, or GIF image (max 5MB)</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="category_id" class="form-label">Category</label>
                                <select class="form-select" id="category_id" name="category_id" required>
                                    <option value="">Select a category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>">
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Tags</label>
                                <div class="row">
                                    <?php foreach ($tags as $tag): ?>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="tags[]" 
                                                       value="<?php echo $tag['id']; ?>" id="tag<?php echo $tag['id']; ?>">
                                                <label class="form-check-label" for="tag<?php echo $tag['id']; ?>">
                                                    <?php echo htmlspecialchars($tag['name']); ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="is_featured" name="is_featured">
                                <label class="form-check-label" for="is_featured">Feature this event</label>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">Create Event</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html> 