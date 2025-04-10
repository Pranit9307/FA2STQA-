<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and is an event manager
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'event_manager') {
    header("Location: login.php");
    exit();
}

// Get manager's events statistics
$stats_query = $pdo->prepare("
    SELECT 
        e.id,
        e.title,
        e.date,
        e.time,
        e.capacity,
        e.price,
        COUNT(DISTINCT r.id) as total_rsvps,
        COUNT(DISTINCT CASE WHEN r.status = 'confirmed' THEN r.id END) as confirmed_rsvps,
        COUNT(DISTINCT CASE WHEN r.status = 'cancelled' THEN r.id END) as cancelled_rsvps,
        (COUNT(DISTINCT CASE WHEN r.status = 'confirmed' THEN r.id END) * e.price) as revenue
    FROM events e
    LEFT JOIN rsvps r ON e.id = r.event_id
    WHERE e.manager_id = ? OR e.created_by = ?
    GROUP BY e.id, e.title, e.date, e.time, e.capacity, e.price
    ORDER BY e.date DESC
");
$stats_query->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$events_stats = $stats_query->fetchAll();

// Get popular time slots
$time_slots_query = $pdo->prepare("
    SELECT 
        HOUR(time) as hour,
        COUNT(*) as event_count,
        AVG(CASE WHEN r.status = 'confirmed' THEN 1 ELSE 0 END) as avg_attendance_rate
    FROM events e
    LEFT JOIN rsvps r ON e.id = r.event_id
    WHERE e.manager_id = ? OR e.created_by = ?
    GROUP BY HOUR(time)
    ORDER BY event_count DESC
");
$time_slots_query->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$time_slots = $time_slots_query->fetchAll();

// Get monthly revenue
$monthly_revenue_query = $pdo->prepare("
    SELECT 
        DATE_FORMAT(e.date, '%Y-%m') as month,
        SUM(CASE WHEN r.status = 'confirmed' THEN e.price ELSE 0 END) as revenue,
        COUNT(DISTINCT e.id) as event_count
    FROM events e
    LEFT JOIN rsvps r ON e.id = r.event_id
    WHERE (e.manager_id = ? OR e.created_by = ?) AND e.date >= DATE_SUB(CURRENT_DATE, INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(e.date, '%Y-%m')
    ORDER BY month ASC
");
$monthly_revenue_query->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$monthly_revenue = $monthly_revenue_query->fetchAll();

// Calculate overall statistics
$total_revenue = 0;
$total_attendance = 0;
$total_capacity = 0;

foreach ($events_stats as $stat) {
    $total_revenue += $stat['revenue'];
    $total_attendance += $stat['confirmed_rsvps'];
    $total_capacity += $stat['capacity'];
}

$overall_attendance_rate = $total_capacity > 0 ? ($total_attendance / $total_capacity) * 100 : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Analytics - EventHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .stats-card {
            transition: transform 0.2s;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            color: #000 !important;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .stats-card .card-title {
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.2);
            color: #000 !important;
        }
        .stats-card .card-text {
            font-size: 1.8rem;
            font-weight: bold;
            text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.2);
            color: #000 !important;
        }
        .bg-primary {
            background: linear-gradient(45deg, #0d6efd, #0a58ca) !important;
        }
        .bg-success {
            background: linear-gradient(45deg, #198754, #146c43) !important;
        }
        .bg-info {
            background: linear-gradient(45deg, #0dcaf0, #0aa2c0) !important;
        }
        .bg-warning {
            background: linear-gradient(45deg, #ffc107, #cc9a06) !important;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container my-5">
        <h2 class="mb-4">Event Analytics Dashboard</h2>
        
        <!-- Overall Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card bg-primary">
                    <div class="card-body">
                        <h5 class="card-title">Total Revenue</h5>
                        <h3 class="card-text">₹<?php echo number_format($total_revenue, 2); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card bg-success">
                    <div class="card-body">
                        <h5 class="card-title">Overall Attendance Rate</h5>
                        <h3 class="card-text"><?php echo number_format($overall_attendance_rate, 1); ?>%</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card bg-info">
                    <div class="card-body">
                        <h5 class="card-title">Total Events</h5>
                        <h3 class="card-text"><?php echo count($events_stats); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card bg-warning">
                    <div class="card-body">
                        <h5 class="card-title">Total Attendees</h5>
                        <h3 class="card-text"><?php echo $total_attendance; ?></h3>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Charts -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Revenue</h5>
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Popular Time Slots</h5>
                        <canvas id="timeSlotChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Events Table -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Event Performance</h5>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Event</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Attendance Rate</th>
                                <th>Revenue</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($events_stats as $stat): ?>
                                <tr>
                                    <td>
                                        <a href="event_details.php?id=<?php echo $stat['id']; ?>">
                                            <?php echo htmlspecialchars($stat['title']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($stat['date'])); ?></td>
                                    <td><?php echo date('g:i A', strtotime($stat['time'])); ?></td>
                                    <td>
                                        <?php 
                                        $attendance_rate = $stat['capacity'] > 0 ? 
                                            ($stat['confirmed_rsvps'] / $stat['capacity']) * 100 : 0;
                                        echo number_format($attendance_rate, 1) . '%';
                                        ?>
                                    </td>
                                    <td>₹<?php echo number_format($stat['revenue'], 2); ?></td>
                                    <td>
                                        <?php if (strtotime($stat['date']) < time()): ?>
                                            <span class="badge bg-secondary">Past</span>
                                        <?php else: ?>
                                            <span class="badge bg-primary">Upcoming</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>
    <script>
        // Monthly Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($monthly_revenue, 'month')); ?>,
                datasets: [{
                    label: 'Revenue',
                    data: <?php echo json_encode(array_column($monthly_revenue, 'revenue')); ?>,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.1)',
                    tension: 0.1,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Revenue Trend'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₹' + value;
                            }
                        }
                    }
                }
            }
        });

        // Time Slots Chart
        const timeSlotCtx = document.getElementById('timeSlotChart').getContext('2d');
        new Chart(timeSlotCtx, {
            type: 'bar',
            data: {
                labels: <?php 
                    $formatted_hours = array_map(function($slot) {
                        return date('ga', strtotime($slot['hour'] . ':00'));
                    }, $time_slots);
                    echo json_encode($formatted_hours);
                ?>,
                datasets: [{
                    label: 'Number of Events',
                    data: <?php echo json_encode(array_column($time_slots, 'event_count')); ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgb(54, 162, 235)',
                    borderWidth: 1
                }, {
                    label: 'Avg. Attendance Rate',
                    data: <?php echo json_encode(array_map(function($slot) {
                        return $slot['avg_attendance_rate'] * 100;
                    }, $time_slots)); ?>,
                    backgroundColor: 'rgba(255, 99, 132, 0.5)',
                    borderColor: 'rgb(255, 99, 132)',
                    borderWidth: 1,
                    yAxisID: 'percentage'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Events'
                        }
                    },
                    percentage: {
                        position: 'right',
                        beginAtZero: true,
                        max: 100,
                        title: {
                            display: true,
                            text: 'Attendance Rate (%)'
                        }
                    }
                }
            }
        });
    </script>
</body>
</html> 