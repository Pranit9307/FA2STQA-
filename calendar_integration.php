<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/database.php';

// Function to generate Google Calendar URL
function getGoogleCalendarUrl($event) {
    $start_date = date('Ymd\THis', strtotime($event['date'] . ' ' . $event['time']));
    $end_date = date('Ymd\THis', strtotime($event['date'] . ' ' . $event['time'] . ' +2 hours')); // Default 2-hour duration
    
    $params = array(
        'action' => 'TEMPLATE',
        'text' => urlencode($event['title']),
        'dates' => $start_date . '/' . $end_date,
        'details' => urlencode($event['description']),
        'location' => urlencode($event['location']),
        'sf' => 'true',
        'output' => 'xml'
    );
    
    return 'https://calendar.google.com/calendar/render?' . http_build_query($params);
}

// Function to generate Outlook Calendar URL
function getOutlookCalendarUrl($event) {
    $start_date = date('Y-m-d\TH:i:s', strtotime($event['date'] . ' ' . $event['time']));
    $end_date = date('Y-m-d\TH:i:s', strtotime($event['date'] . ' ' . $event['time'] . ' +2 hours')); // Default 2-hour duration
    
    $params = array(
        'subject' => urlencode($event['title']),
        'startdt' => $start_date,
        'enddt' => $end_date,
        'body' => urlencode($event['description']),
        'location' => urlencode($event['location'])
    );
    
    return 'https://outlook.live.com/calendar/0/deeplink/compose?' . http_build_query($params);
}

// Function to generate iCal file content
function generateICalContent($event) {
    $start_date = date('Ymd\THis', strtotime($event['date'] . ' ' . $event['time']));
    $end_date = date('Ymd\THis', strtotime($event['date'] . ' ' . $event['time'] . ' +2 hours')); // Default 2-hour duration
    
    $ical = "BEGIN:VCALENDAR\r\n";
    $ical .= "VERSION:2.0\r\n";
    $ical .= "PRODID:-//EventHub//Calendar//EN\r\n";
    $ical .= "CALSCALE:GREGORIAN\r\n";
    $ical .= "BEGIN:VEVENT\r\n";
    $ical .= "DTSTART:" . $start_date . "\r\n";
    $ical .= "DTEND:" . $end_date . "\r\n";
    $ical .= "SUMMARY:" . $event['title'] . "\r\n";
    $ical .= "DESCRIPTION:" . $event['description'] . "\r\n";
    $ical .= "LOCATION:" . $event['location'] . "\r\n";
    $ical .= "END:VEVENT\r\n";
    $ical .= "END:VCALENDAR\r\n";
    
    return $ical;
}

// Function to send calendar invites to attendees
function sendCalendarInvites($pdo, $event_id) {
    // Get event details
    $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();
    
    if (!$event) {
        return false;
    }
    
    // Get all confirmed attendees
    $stmt = $pdo->prepare("
        SELECT u.email, u.username 
        FROM rsvps r 
        JOIN users u ON r.user_id = u.id 
        WHERE r.event_id = ? AND r.status = 'confirmed'
    ");
    $stmt->execute([$event_id]);
    $attendees = $stmt->fetchAll();
    
    // Generate iCal content
    $ical_content = generateICalContent($event);
    
    // Send emails to attendees
    foreach ($attendees as $attendee) {
        $to = $attendee['email'];
        $subject = "Calendar Invite: " . $event['title'];
        
        // Email headers
        $headers = array(
            'MIME-Version: 1.0',
            'Content-type: text/calendar; charset=UTF-8; method=REQUEST',
            'From: EventHub <noreply@eventhub.com>',
            'Reply-To: noreply@eventhub.com',
            'X-Mailer: PHP/' . phpversion()
        );
        
        // Email body
        $message = "Dear " . $attendee['username'] . ",\n\n";
        $message .= "You are invited to attend the following event:\n\n";
        $message .= "Event: " . $event['title'] . "\n";
        $message .= "Date: " . date('F j, Y', strtotime($event['date'])) . "\n";
        $message .= "Time: " . date('g:i A', strtotime($event['time'])) . "\n";
        $message .= "Location: " . $event['location'] . "\n\n";
        $message .= "Please find the calendar invite attached.\n\n";
        $message .= "Best regards,\nEventHub Team";
        
        // Send email with calendar attachment
        $boundary = md5(time());
        $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
        
        $body = "--" . $boundary . "\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $body .= $message . "\r\n\r\n";
        
        $body .= "--" . $boundary . "\r\n";
        $body .= "Content-Type: text/calendar; charset=UTF-8; method=REQUEST\r\n";
        $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $body .= $ical_content . "\r\n\r\n";
        
        $body .= "--" . $boundary . "--";
        
        mail($to, $subject, $body, implode("\r\n", $headers));
    }
    
    return true;
}

// Handle calendar invite requests
if (isset($_POST['send_invites']) && isset($_POST['event_id'])) {
    $event_id = $_POST['event_id'];
    $success = sendCalendarInvites($pdo, $event_id);
    echo json_encode(['success' => $success]);
    exit();
}
?> 