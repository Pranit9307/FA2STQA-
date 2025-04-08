<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

/**
 * Simple email function that logs to error log instead of sending emails
 * This is a placeholder until PHPMailer is properly set up
 */
function sendWelcomeEmail($to, $name) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'yashsaid.17@gmail.com'; // Your Gmail address
        $mail->Password   = 'ltpq wsmf qbgq okjm';   // Your Gmail App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom('yashsaid.17@gmail.com', 'EventHub');
        $mail->addAddress($to, $name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Welcome to EventHub!';
        
        // Email body with improved styling
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #333;'>Welcome to EventHub!</h2>
                <p>Hi <strong>{$name}</strong>,</p>
                <p>Thank you for registering with EventHub! Your account has been successfully created.</p>
                <p>You can now:</p>
                <ul>
                    <li>Browse and join events</li>
                    <li>Create your own events (if you're an event manager)</li>
                    <li>Connect with other event enthusiasts</li>
                </ul>
                <p>Get started by logging in to your account:</p>
                <p style='text-align: center;'>
                    <a href='http://localhost/SDL_PROJ/login.php' 
                       style='background-color: #007bff; color: white; padding: 10px 20px; 
                              text-decoration: none; border-radius: 5px; display: inline-block;'>
                        Login to EventHub
                    </a>
                </p>
                <p>If you have any questions, feel free to contact our support team.</p>
                <p>Best regards,<br>The EventHub Team</p>
                <hr style='border: 1px solid #eee; margin: 20px 0;'>
                <p style='color: #666; font-size: 12px;'>
                    This is an automated message, please do not reply to this email.
                </p>
            </div>
        ";

        // Plain text version for non-HTML email clients
        $mail->AltBody = "
            Welcome to EventHub!
            
            Hi {$name},
            
            Thank you for registering with EventHub! Your account has been successfully created.
            
            You can now:
            - Browse and join events
            - Create your own events (if you're an event manager)
            - Connect with other event enthusiasts
            
            Get started by logging in to your account at: http://localhost/SDL_PROJ/login.php
            
            If you have any questions, feel free to contact our support team.
            
            Best regards,
            The EventHub Team
            
            This is an automated message, please do not reply to this email.
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Send RSVP confirmation email to user
 */
function sendRsvpEmail($to, $name, $event_title, $event_date, $event_time, $event_location, $rsvp_status) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'yashsaid.17@gmail.com';
        $mail->Password   = 'ltpq wsmf qbgq okjm';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom('yashsaid.17@gmail.com', 'EventHub');
        $mail->addAddress($to, $name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = "RSVP Confirmation: {$event_title}";
        
        // Format the status for display
        $status_display = ucfirst($rsvp_status);
        
        // Email body with improved styling
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #333;'>RSVP Confirmation</h2>
                <p>Hi <strong>{$name}</strong>,</p>
                <p>Your RSVP for <strong>{$event_title}</strong> has been confirmed as <strong>{$status_display}</strong>.</p>
                
                <div style='background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                    <h3 style='margin-top: 0; color: #333;'>Event Details</h3>
                    <p><strong>Event:</strong> {$event_title}</p>
                    <p><strong>Date:</strong> {$event_date}</p>
                    <p><strong>Time:</strong> {$event_time}</p>
                    <p><strong>Location:</strong> {$event_location}</p>
                    <p><strong>Your RSVP Status:</strong> {$status_display}</p>
                </div>
                
                <p>You can view or update your RSVP status at any time by visiting the event page:</p>
                <p style='text-align: center;'>
                    <a href='http://localhost/SDL_PROJ/events.php' 
                       style='background-color: #007bff; color: white; padding: 10px 20px; 
                              text-decoration: none; border-radius: 5px; display: inline-block;'>
                        View Events
                    </a>
                </p>
                
                <p>If you have any questions, feel free to contact our support team.</p>
                <p>Best regards,<br>The EventHub Team</p>
                <hr style='border: 1px solid #eee; margin: 20px 0;'>
                <p style='color: #666; font-size: 12px;'>
                    This is an automated message, please do not reply to this email.
                </p>
            </div>
        ";

        // Plain text version for non-HTML email clients
        $mail->AltBody = "
            RSVP Confirmation
            
            Hi {$name},
            
            Your RSVP for {$event_title} has been confirmed as {$status_display}.
            
            Event Details:
            - Event: {$event_title}
            - Date: {$event_date}
            - Time: {$event_time}
            - Location: {$event_location}
            - Your RSVP Status: {$status_display}
            
            You can view or update your RSVP status at any time by visiting the event page at:
            http://localhost/SDL_PROJ/events.php
            
            If you have any questions, feel free to contact our support team.
            
            Best regards,
            The EventHub Team
            
            This is an automated message, please do not reply to this email.
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("RSVP Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
