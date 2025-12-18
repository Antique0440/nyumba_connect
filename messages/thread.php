<?php
/**
 * Message Thread - Display conversation between mentorship partners
 * Shows message history and provides interface for sending new messages
 */

define('NYUMBA_CONNECT', true);
require_once __DIR__ . '/../includes/config.php';

// Require authentication
require_auth();

$currentUserId = getCurrentUserId();
$mentorshipId = intval($_GET['mentorship_id'] ?? 0);

// Validate mentorship ID
if (empty($mentorshipId)) {
    redirectWithMessage(SITE_URL . '/messages/inbox.php', 'error', 'Invalid mentorship ID');
}

// Verify user has access to this mentorship
if (!verify_mentorship_access($mentorshipId, $currentUserId)) {
    redirectWithMessage(SITE_URL . '/messages/inbox.php', 'error', 'You do not have permission to access this conversation');
}

try {
    $db = getDB();

    // Get mentorship details and partner information
    $mentorship = $db->fetchOne(
        "SELECT m.*, 
                s.name as student_name, s.account_id as student_id,
                a.name as alumni_name, a.account_id as alumni_id
         FROM mentorships m
         JOIN accounts s ON m.student_id = s.account_id
         JOIN accounts a ON m.alumni_id = a.account_id
         WHERE m.mentorship_id = ? AND m.active = TRUE",
        [$mentorshipId]
    );

    if (!$mentorship) {
        redirectWithMessage(SITE_URL . '/messages/inbox.php', 'error', 'Mentorship not found or inactive');
    }

    // Determine partner information
    $partnerId = ($mentorship['student_id'] == $currentUserId) ? $mentorship['alumni_id'] : $mentorship['student_id'];
    $partnerName = ($mentorship['student_id'] == $currentUserId) ? $mentorship['alumni_name'] : $mentorship['student_name'];

    // Get messages for this mentorship
    $messages = $db->fetchAll(
        "SELECT m.*, u.name as sender_name
         FROM messages m
         JOIN accounts u ON m.sender_id = u.account_id
         WHERE m.mentorship_id = ?
         ORDER BY m.sent_at ASC",
        [$mentorshipId]
    );

    // Mark messages as read for current user
    $db->execute(
        "UPDATE messages SET is_read = TRUE 
         WHERE mentorship_id = ? AND receiver_id = ? AND is_read = FALSE",
        [$mentorshipId, $currentUserId]
    );

} catch (Exception $e) {
    error_log("Error loading message thread: " . $e->getMessage());
    redirectWithMessage(SITE_URL . '/messages/inbox.php', 'error', 'Error loading conversation');
}

$pageTitle = 'Messages with ' . $partnerName;

include __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2>
                    <i class="bi bi-chat-dots me-2"></i>
                    Conversation with <?php echo sanitizeInput($partnerName); ?>
                </h2>
                <p class="text-muted mb-0">
                    <i class="bi bi-calendar me-1"></i>
                    Mentorship started: <?php echo formatDate($mentorship['started_at']); ?>
                </p>
            </div>
            <a href="<?php echo SITE_URL; ?>/messages/inbox.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Back to Messages
            </a>
        </div>

        <!-- Messages Container -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="bi bi-person-circle me-1"></i>
                        <?php echo sanitizeInput($partnerName); ?>
                    </h6>
                    <small>
                        <?php echo count($messages); ?> message<?php echo count($messages) !== 1 ? 's' : ''; ?>
                    </small>
                </div>
            </div>

            <!-- Messages Display -->
            <div class="card-body p-0">
                <div id="messages-container" class="messages-container"
                    style="height: 450px; overflow-y: auto; padding: 1rem;">
                    <?php if (empty($messages)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-chat-text display-4 text-muted"></i>
                            <h5 class="mt-3 text-muted">No messages yet</h5>
                            <p class="text-muted">Start the conversation by sending a message below!</p>
                        </div>
                    <?php else: ?>
                        <div class="p-3">
                            <?php foreach ($messages as $message): ?>
                                <?php $isOwnMessage = $message['sender_id'] == $currentUserId; ?>
                                <div
                                    class="message-item mb-3 d-flex <?php echo $isOwnMessage ? 'justify-content-end' : 'justify-content-start'; ?>">
                                    <div class="message-bubble <?php echo $isOwnMessage ? 'sent' : 'received'; ?>">
                                        <?php if (!$isOwnMessage): ?>
                                            <div class="message-sender mb-1">
                                                <small class="fw-bold text-primary">
                                                    <?php echo sanitizeInput($message['sender_name']); ?>
                                                </small>
                                            </div>
                                        <?php endif; ?>

                                        <div class="message-text">
                                            <?php echo nl2br(sanitizeInput($message['message_text'])); ?>
                                        </div>

                                        <div class="message-meta mt-2">
                                            <small class="<?php echo $isOwnMessage ? 'text-white-50' : 'text-muted'; ?>">
                                                <?php echo formatDateTime($message['sent_at'], 'M j, g:i A'); ?>
                                                <?php if ($isOwnMessage): ?>
                                                    <?php if ($message['is_read']): ?>
                                                        <i class="bi bi-check2-all ms-1" title="Read"></i>
                                                    <?php else: ?>
                                                        <i class="bi bi-check2 ms-1" title="Sent"></i>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Message Input -->
            <div class="card-footer bg-white border-top">
                <form id="message-form" class="d-flex gap-2 align-items-end">
                    <?php echo getCSRFTokenField(); ?>
                    <input type="hidden" name="mentorship_id" value="<?php echo $mentorshipId; ?>">
                    <div class="flex-grow-1">
                        <textarea id="message-input" name="message_text" class="form-control border-0 shadow-sm"
                            placeholder="Type your message..." rows="1" maxlength="2000" required
                            style="resize: none; border-radius: 1.5rem; padding: 0.75rem 1rem;"></textarea>
                        <div class="form-text px-2">
                            <span id="char-count">0</span>/2000 characters
                        </div>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary rounded-circle p-2" id="send-btn"
                            style="width: 45px; height: 45px;" title="Send message">
                            <i class="bi bi-send"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Message Guidelines -->
        <div class="row mt-4">
            <div class="col-md-8 mx-auto">
                <div class="alert alert-info">
                    <h6><i class="bi bi-info-circle me-1"></i>Message Guidelines</h6>
                    <ul class="mb-0 small">
                        <li>Keep conversations professional and respectful</li>
                        <li>Messages are private between you and your mentorship partner</li>
                        <li>Maximum message length is 2000 characters</li>
                        <li>Messages are automatically marked as read when viewed</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const messageForm = document.getElementById('message-form');
        const messageInput = document.getElementById('message-input');
        const sendBtn = document.getElementById('send-btn');
        const charCount = document.getElementById('char-count');
        const messagesContainer = document.getElementById('messages-container');

        // Real-time messaging variables
        let lastMessageId = <?php echo !empty($messages) ? max(array_column($messages, 'message_id')) : 0; ?>;
        let pollingInterval;
        let isPolling = false;

        // Character counter
        messageInput.addEventListener('input', function () {
            const count = this.value.length;
            charCount.textContent = count;

            if (count > 2000) {
                charCount.classList.add('text-danger');
            } else {
                charCount.classList.remove('text-danger');
            }
        });

        // Auto-resize textarea
        messageInput.addEventListener('input', function () {
            this.style.height = 'auto';
            const newHeight = Math.min(this.scrollHeight, 120);
            this.style.height = newHeight + 'px';

            // Update rows based on content
            const lineHeight = 24; // Approximate line height
            const rows = Math.max(1, Math.min(5, Math.ceil(newHeight / lineHeight)));
            this.rows = rows;
        });

        // Handle keyboard shortcuts
        messageInput.addEventListener('keydown', function (e) {
            // Send message with Ctrl+Enter or Cmd+Enter
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                messageForm.dispatchEvent(new Event('submit'));
            }
        });

        // Handle form submission
        messageForm.addEventListener('submit', function (e) {
            e.preventDefault();

            const messageText = messageInput.value.trim();
            if (!messageText) {
                alert('Please enter a message');
                return;
            }

            if (messageText.length > 2000) {
                alert('Message is too long (maximum 2000 characters)');
                return;
            }

            // Disable form during submission
            sendBtn.disabled = true;
            sendBtn.innerHTML = '<i class="bi bi-hourglass-split"></i>';
            sendBtn.classList.add('btn-secondary');
            sendBtn.classList.remove('btn-primary');

            // Send message via AJAX
            const formData = new FormData(this);

            fetch('<?php echo SITE_URL; ?>/messages/send_message.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Add message to display
                        addMessageToDisplay(data.message, true);

                        // Update last message ID
                        lastMessageId = Math.max(lastMessageId, data.message.message_id);

                        // Clear form
                        messageInput.value = '';
                        messageInput.style.height = 'auto';
                        messageInput.rows = 1;
                        charCount.textContent = '0';

                        // Scroll to bottom
                        scrollToBottom();
                    } else {
                        alert('Error: ' + (data.error || 'Failed to send message'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while sending the message');
                })
                .finally(() => {
                    // Re-enable form
                    sendBtn.disabled = false;
                    sendBtn.innerHTML = '<i class="bi bi-send"></i>';
                    sendBtn.classList.remove('btn-secondary');
                    sendBtn.classList.add('btn-primary');
                    messageInput.focus();
                });
        });

        // Real-time message polling
        function startPolling() {
            if (isPolling) return;

            isPolling = true;
            pollingInterval = setInterval(fetchNewMessages, 3000); // Poll every 3 seconds
        }

        function stopPolling() {
            if (pollingInterval) {
                clearInterval(pollingInterval);
                pollingInterval = null;
            }
            isPolling = false;
        }

        function fetchNewMessages() {
            fetch(`<?php echo SITE_URL; ?>/messages/fetch_messages.php?mentorship_id=<?php echo $mentorshipId; ?>&last_message_id=${lastMessageId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.has_new_messages) {
                        data.messages.forEach(message => {
                            // Display all new messages with correct ownership
                            addMessageToDisplay(message, message.is_own_message);
                            lastMessageId = Math.max(lastMessageId, message.message_id);
                        });

                        // Scroll to bottom if new messages
                        if (data.messages.length > 0) {
                            scrollToBottom();

                            // Show notification if page is not visible
                            if (document.hidden) {
                                showNotification('New message received');
                            }
                        }

                        // Update unread count in title
                        updateUnreadCount(data.total_unread);
                    }
                })
                .catch(error => {
                    console.error('Polling error:', error);
                });
        }

        // Function to add message to display
        function addMessageToDisplay(message, isOwnMessage) {
            const messagesDiv = messagesContainer.querySelector('.p-3') || createMessagesDiv();

            const messageDiv = document.createElement('div');
            messageDiv.className = `message-item mb-3 d-flex ${isOwnMessage ? 'justify-content-end' : 'justify-content-start'}`;

            const bubbleClass = isOwnMessage ? 'sent' : 'received';
            const textClass = isOwnMessage ? 'text-white-50' : 'text-muted';

            const senderInfo = !isOwnMessage ? `
                <div class="message-sender mb-1">
                    <small class="fw-bold text-primary">${message.sender_name}</small>
                </div>
            ` : '';

            messageDiv.innerHTML = `
                <div class="message-bubble ${bubbleClass}">
                    ${senderInfo}
                    <div class="message-text">
                        ${message.message_text.replace(/\n/g, '<br>')}
                    </div>
                    <div class="message-meta mt-2">
                        <small class="${textClass}">
                            ${formatDateTime(message.sent_at)}
                            ${isOwnMessage ? (message.is_read ? '<i class="bi bi-check2-all ms-1" title="Read"></i>' : '<i class="bi bi-check2 ms-1" title="Sent"></i>') : ''}
                        </small>
                    </div>
                </div>
            `;

            messagesDiv.appendChild(messageDiv);

            // Add fade-in animation for new messages
            if (!isOwnMessage) {
                messageDiv.style.opacity = '0';
                messageDiv.style.transform = 'translateY(10px)';
                setTimeout(() => {
                    messageDiv.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                    messageDiv.style.opacity = '1';
                    messageDiv.style.transform = 'translateY(0)';
                }, 100);
            }
        }

        // Function to create messages div if it doesn't exist
        function createMessagesDiv() {
            const emptyState = messagesContainer.querySelector('.text-center');
            if (emptyState) {
                emptyState.remove();
            }

            const messagesDiv = document.createElement('div');
            messagesDiv.className = 'p-3';
            messagesContainer.appendChild(messagesDiv);
            return messagesDiv;
        }

        // Function to scroll to bottom
        function scrollToBottom() {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        // Function to format datetime
        function formatDateTime(datetime) {
            const date = new Date(datetime);
            return date.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit'
            });
        }

        // Function to show browser notification
        function showNotification(message) {
            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification('Nyumba Connect', {
                    body: message,
                    icon: '<?php echo SITE_URL; ?>/assets/images/favicon.ico'
                });
            }
        }

        // Function to update unread count in page title
        function updateUnreadCount(count) {
            const baseTitle = 'Messages with <?php echo addslashes($partnerName); ?>';
            document.title = count > 0 ? `(${count}) ${baseTitle}` : baseTitle;
        }

        // Request notification permission
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }

        // Page visibility handling
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                // Page is hidden, continue polling but reduce frequency
                stopPolling();
                pollingInterval = setInterval(fetchNewMessages, 10000); // Poll every 10 seconds when hidden
            } else {
                // Page is visible, resume normal polling
                stopPolling();
                startPolling();

                // Fetch messages immediately when page becomes visible
                fetchNewMessages();
            }
        });

        // Handle page unload
        window.addEventListener('beforeunload', function () {
            stopPolling();
        });

        // Scroll to bottom on page load
        scrollToBottom();

        // Focus on message input
        messageInput.focus();

        // Handle Enter key (Shift+Enter for new line, Enter to send)
        messageInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                messageForm.dispatchEvent(new Event('submit'));
            }
        });

        // Start real-time polling
        startPolling();

        // Show typing indicator (future enhancement)
        let typingTimer;
        messageInput.addEventListener('input', function () {
            clearTimeout(typingTimer);
            // Could implement typing indicator here
            typingTimer = setTimeout(() => {
                // Stop showing typing indicator
            }, 1000);
        });
    });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>