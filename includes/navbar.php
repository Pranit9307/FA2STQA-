<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar navbar-expand-lg navbar-dark sticky-top">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <i class="fas fa-calendar-alt me-2"></i>
            EventHub
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'index.php' ? 'active' : ''; ?>" href="index.php">
                        <i class="fas fa-home me-1"></i> Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'events.php' ? 'active' : ''; ?>" href="events.php">
                        <i class="fas fa-calendar-week me-1"></i> Events
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="nearby_events.php">
                        <i class="fas fa-map-marker-alt"></i> Nearby Events
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="user_interests.php">
                        <i class="fas fa-heart"></i> Interests & Recommendations
                    </a>
                </li>
                <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] == 'event_manager'): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'create_event.php' ? 'active' : ''; ?>" href="create_event.php">
                        <i class="fas fa-plus-circle me-1"></i> Create Event
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'event_analytics.php' ? 'active' : ''; ?>" href="event_analytics.php">
                        <i class="fas fa-chart-bar me-1"></i> Analytics
                    </a>
                </li>
                <?php endif; ?>
            </ul>
            
            <ul class="navbar-nav">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" 
                           data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle me-1"></i>
                            <?php echo htmlspecialchars($_SESSION['username']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li>
                                <a class="dropdown-item" href="profile.php">
                                    <i class="fas fa-user me-2"></i> Profile
                                </a>
                            </li>
                            <?php if ($_SESSION['role'] === 'event_manager'): ?>
                            <li>
                                <a class="dropdown-item" href="my_events.php">
                                    <i class="fas fa-calendar-check me-2"></i> My Events
                                </a>
                            </li>
                            <?php endif; ?>
                            <li>
                                <a class="dropdown-item" href="my_rsvps.php">
                                    <i class="fas fa-ticket-alt me-2"></i> My RSVPs
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i> Logout
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'login.php' ? 'active' : ''; ?>" href="login.php">
                            <i class="fas fa-sign-in-alt me-1"></i> Login
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'register.php' ? 'active' : ''; ?>" href="register.php">
                            <i class="fas fa-user-plus me-1"></i> Register
                        </a>
                    </li>
                <?php endif; ?>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Admin Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="update_event_coordinates.php">
                            <i class="fas fa-map-marker-alt"></i> Update Coordinates
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="check_events.php">
                            <i class="fas fa-database"></i> Check Events
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav> 