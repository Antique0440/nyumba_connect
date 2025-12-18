<?php
/**
 * Send Message Handler
 * Processes message sending between mentorship partners
 */

define('NYUMBA_CONNECT', true);
require_once __DIR__ . '/../includes/config.php';

// Require authentication
require_auth();

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Check CSRF token
if (!checkCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid security token']);
    exit;
}

// Rate limiting for message sending
if (!check_rate_limit('send_message', 10, 60)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Too many messages sent. Please wait before sending another.']);
    exit;
}

$currentUserId = getCurrentUserId();
$mentorshipId = intval($_POST['mentorship_id'] ?? 0);
$messageText = trim($_POST['message_text'] ?? '');

// Validate inputs
if (empty($mentorshipId)) {
    echo json_encode(['success' => false, 'error' => 'Invalid mentorship ID']);
    exit;
}

if (empty($messageText)) {
    echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
    exit;
}

if (strlen($messageText) > 2000) {
    echo json_encode(['success' => false, 'error' => 'Message is too long (maximum 2000 characters)']);
    exit;
}

// Verify user has access to this mentorship
if (!verify_mentorship_access($mentorshipId, $currentUserId)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'You do not have permission to send messages in this mentorship']);
    exit;
}

try {
    $db = getDB();

    // Get mentorship details to determine receiver
    $mentorship = $db->fetchOne(
        "SELECT student_id, alumni_id FROM mentorships WHERE mentorship_id = ? AND active = TRUE",
        [$mentorshipId]
    );

    if (!$mentorship) {
        echo json_encode(['success' => false, 'error' => 'Mentorship not found or inactive']);
        exit;
    }

    // Determine receiver ID
    $receiverId = ($mentorship['student_id'] == $currentUserId) ? $mentorship['alumni_id'] : $mentorship['student_id'];

    // Sanitize message text for database storage (remove dangerous content but preserve formatting)
    $cleanMessage = sanitizeForDatabase($messageText);

    // Insert message
    $stmt = $db->execute(
        "INSERT INTO messages (sender_id, receiver_id, mentorship_id, message_text, sent_at, is_read) 
         VALUES (?, ?, ?, ?, NOW(), FALSE)",
        [$currentUserId, $receiverId, $mentorshipId, $cleanMessage]
    );

    if ($stmt) {
        $messageId = $db->lastInsertId();

        // Get sender name
        $senderInfo = $db->fetchOne("SELECT name FROM accounts WHERE account_id = ?", [$currentUserId]);
        $senderName = $senderInfo ? $senderInfo['name'] : 'Unknown';

        // Log activity
        logActivity($currentUserId, 'Message Sent', "Mentorship ID: $mentorshipId");

        // Return success with message data (sanitize for display)
        echo json_encode([
            'success' => true,
            'message_id' => $messageId,
            'message' => [
                'message_id' => $messageId,
                'sender_id' => $currentUserId,
                'receiver_id' => $receiverId,
                'message_text' => sanitizeInput($cleanMessage),
                'sent_at' => date('Y-m-d H:i:s'),
                'is_read' => false,
                'sender_name' => $senderName
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to send message']);
    }

} catch (Exception $e) {
    error_log("Error sending message: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An error occurred while sending the message']);
}
?>