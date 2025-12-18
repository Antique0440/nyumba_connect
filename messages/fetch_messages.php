<?php
/**
 * Fetch Messages API
 * Returns new messages for real-time polling and updates read status
 */

define('NYUMBA_CONNECT', true);
require_once __DIR__ . '/../includes/config.php';

// Require authentication
require_auth();

// Only handle GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Check if this is an AJAX request
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// If not an AJAX request, redirect to messages page
if (!$isAjax) {
    $mentorshipId = intval($_GET['mentorship_id'] ?? 0);
    if ($mentorshipId > 0) {
        header('Location: ' . SITE_URL . '/messages/thread.php?mentorship_id=' . $mentorshipId);
    } else {
        header('Location: ' . SITE_URL . '/messages/inbox.php');
    }
    exit;
}

$currentUserId = getCurrentUserId();
$mentorshipId = intval($_GET['mentorship_id'] ?? 0);
$lastMessageId = intval($_GET['last_message_id'] ?? 0);

// Validate inputs
if (empty($mentorshipId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid mentorship ID']);
    exit;
}

// Verify user has access to this mentorship
if (!verify_mentorship_access($mentorshipId, $currentUserId)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied - mentorship not found or inactive']);
    exit;
}

try {
    $db = getDB();

    // Get new messages since last message ID
    $messages = $db->fetchAll(
        "SELECT m.*, u.name as sender_name
         FROM messages m
         JOIN accounts u ON m.sender_id = u.account_id
         WHERE m.mentorship_id = ? AND m.message_id > ?
         ORDER BY m.sent_at ASC",
        [$mentorshipId, $lastMessageId]
    );

    // Mark new messages as read for current user
    if (!empty($messages)) {
        $newMessageIds = array_column($messages, 'message_id');
        $placeholders = str_repeat('?,', count($newMessageIds) - 1) . '?';

        $db->execute(
            "UPDATE messages SET is_read = TRUE 
             WHERE mentorship_id = ? AND receiver_id = ? AND message_id IN ($placeholders)",
            array_merge([$mentorshipId, $currentUserId], $newMessageIds)
        );
    }

    // Get unread count for current user across all mentorships
    $unreadCount = $db->fetchOne(
        "SELECT COUNT(*) as count FROM messages m
         JOIN mentorships ms ON m.mentorship_id = ms.mentorship_id
         WHERE m.receiver_id = ? AND m.is_read = FALSE AND ms.active = TRUE",
        [$currentUserId]
    );

    // Format messages for response
    $formattedMessages = [];
    foreach ($messages as $message) {
        $formattedMessages[] = [
            'message_id' => intval($message['message_id']),
            'sender_id' => intval($message['sender_id']),
            'receiver_id' => intval($message['receiver_id']),
            'message_text' => sanitizeInput($message['message_text']),
            'sent_at' => $message['sent_at'],
            'is_read' => (bool) $message['is_read'],
            'sender_name' => sanitizeInput($message['sender_name']),
            'is_own_message' => intval($message['sender_id']) === intval($currentUserId)
        ];
    }

    echo json_encode([
        'success' => true,
        'messages' => $formattedMessages,
        'total_unread' => intval($unreadCount['count'] ?? 0),
        'has_new_messages' => !empty($messages)
    ]);

} catch (Exception $e) {
    error_log("Error fetching messages: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to fetch messages']);
}
?>