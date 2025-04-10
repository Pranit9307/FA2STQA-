<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get all categories
$stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
$categories = $stmt->fetchAll();

// Get user's current interests
$stmt = $pdo->prepare("SELECT category_id FROM user_interests WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_interests = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get recommended events
require_once 'social_features.php';
$recommendations = getEventRecommendations($pdo, $_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Interests & Recommendations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Your Interests</h5>
                        <form id="interestsForm">
                            <?php foreach ($categories as $category): ?>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" 
                                       name="interests[]" value="<?php echo $category['id']; ?>"
                                       id="category<?php echo $category['id']; ?>"
                                       <?php echo in_array($category['id'], $user_interests) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="category<?php echo $category['id']; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                            <button type="submit" class="btn btn-primary mt-3">Save Interests</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Recommended Events</h5>
                        <?php if (empty($recommendations)): ?>
                        <p class="text-muted">No recommendations available. Please select your interests to get personalized recommendations.</p>
                        <?php else: ?>
                        <div class="row">
                            <?php foreach ($recommendations as $event): ?>
                            <div class="col-md-6 mb-4">
                                <div class="card h-100">
                                    <?php if ($event['image_path']): ?>
                                    <img src="<?php echo htmlspecialchars($event['image_path']); ?>" 
                                         class="card-img-top" alt="<?php echo htmlspecialchars($event['title']); ?>"
                                         style="height: 200px; object-fit: cover;">
                                    <?php endif; ?>
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($event['title']); ?></h5>
                                        <p class="card-text">
                                            <small class="text-muted">
                                                <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($event['date'])); ?><br>
                                                <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($event['location']); ?><br>
                                                <i class="fas fa-tag"></i> <?php echo htmlspecialchars($event['category_name']); ?>
                                            </small>
                                        </p>
                                        <?php if ($event['avg_rating']): ?>
                                        <div class="text-warning mb-2">
                                            <?php
                                            $rating = round($event['avg_rating']);
                                            for ($i = 0; $i < 5; $i++) {
                                                echo $i < $rating ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                                            }
                                            ?>
                                            <small class="text-muted">(<?php echo number_format($event['avg_rating'], 1); ?>)</small>
                                        </div>
                                        <?php endif; ?>
                                        <a href="event_details.php?id=<?php echo $event['id']; ?>" class="btn btn-primary">View Details</a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.getElementById('interestsForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('update_interests', '1');
        
        fetch('social_features.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(() => {
            location.reload();
        })
        .catch(error => console.error('Error:', error));
    });
    </script>
</body>
</html> 