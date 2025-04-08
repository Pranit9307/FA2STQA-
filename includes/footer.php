<?php
$current_year = date('Y');
?>
<footer class="footer mt-5 py-5">
    <div class="container">
        <div class="row">
            <div class="col-lg-6 mb-4 mb-lg-0">
                <h5 class="footer-title mb-4">EventHub</h5>
                <p class="footer-text">Your one-stop platform for discovering and managing events. Connect with event enthusiasts and create memorable experiences.</p>
                <div class="social-links mt-4">
                    <a href="#" class="social-link"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-4 mb-md-0">
                <h6 class="footer-heading mb-4">Quick Links</h6>
                <ul class="footer-links list-unstyled">
                    <li><a href="index.php">Home</a></li>
                    <li><a href="events.php">Events</a></li>
                    <li><a href="create_event.php">Create Event</a></li>
                    <li><a href="profile.php">Profile</a></li>
                </ul>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <h6 class="footer-heading mb-4">Support</h6>
                <ul class="footer-links list-unstyled">
                    <li><a href="faq.php">FAQ</a></li>
                    <li><a href="contact.php">Contact Us</a></li>
                    <li><a href="privacy.php">Privacy Policy</a></li>
                    <li><a href="terms.php">Terms of Service</a></li>
                </ul>
            </div>
        </div>
        
        <hr class="footer-divider">
        
        <div class="row">
            <div class="col-md-6 text-center text-md-start">
                <p class="footer-copyright mb-0">&copy; <?php echo $current_year; ?> EventHub. All rights reserved.</p>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <p class="footer-copyright mb-0">Made with <i class="fas fa-heart text-danger"></i> for event enthusiasts</p>
            </div>
        </div>
    </div>
</footer> 