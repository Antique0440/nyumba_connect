/**
 * Messaging JavaScript Functions
 * Shared functionality for real-time messaging features
 */

class MessagingSystem {
    constructor() {
        this.pollingInterval = null;
        this.isPolling = false;
        this.lastMessageId = 0;
        this.mentorshipId = null;
        this.currentUserId = null;
        this.unreadCount = 0;
    }

    // Initialize messaging system
    init(mentorshipId, currentUserId, lastMessageId = 0) {
        this.mentorshipId = mentorshipId;
        this.currentUserId = currentUserId;
        this.lastMessageId = lastMessageId;

        this.setupEventListeners();
        this.requestNotificationPermission();
        this.startPolling();
    }

    // Setup event listeners
    setupEventListeners() {
        // Page visibility handling
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.reducePollingFrequency();
            } else {
                this.resumeNormalPolling();
                this.fetchNewMessages();
            }
        });

        // Handle page unload
        window.addEventListener('beforeunload', () => {
            this.stopPolling();
        });
    }

    // Start message polling
    startPolling() {
        if (this.isPolling) return;

        this.isPolling = true;
        this.pollingInterval = setInterval(() => {
            this.fetchNewMessages();
        }, 3000); // Poll every 3 seconds
    }

    // Stop message polling
    stopPolling() {
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
            this.pollingInterval = null;
        }
        this.isPolling = false;
    }

    // Reduce polling frequency when page is hidden
    reducePollingFrequency() {
        this.stopPolling();
        this.pollingInterval = setInterval(() => {
            this.fetchNewMessages();
        }, 10000); // Poll every 10 seconds when hidden
    }

    // Resume normal polling frequency
    resumeNormalPolling() {
        this.stopPolling();
        this.startPolling();
    }

    // Fetch new messages from server
    async fetchNewMessages() {
        if (!this.mentorshipId) return;

        try {
            const response = await fetch(
                `${window.SITE_URL}/messages/fetch_messages.php?mentorship_id=${this.mentorshipId}&last_message_id=${this.lastMessageId}`
            );

            const data = await response.json();

            if (data.success && data.has_new_messages) {
                this.handleNewMessages(data.messages);
                this.updateUnreadCount(data.total_unread);
            }
        } catch (error) {
            console.error('Error fetching messages:', error);
        }
    }

    // Handle new messages received
    handleNewMessages(messages) {
        messages.forEach(message => {
            if (message.sender_id !== this.currentUserId) {
                this.displayNewMessage(message);
                this.lastMessageId = Math.max(this.lastMessageId, message.message_id);

                // Show notification if page is hidden
                if (document.hidden) {
                    this.showNotification(`New message from ${message.sender_name}`);
                }
            }
        });

        if (messages.length > 0) {
            this.scrollToBottom();
        }
    }

    // Display new message in UI
    displayNewMessage(message) {
        // This method should be overridden by specific implementations
        console.log('New message received:', message);
    }

    // Send message to server
    async sendMessage(messageText, csrfToken) {
        if (!this.mentorshipId || !messageText.trim()) {
            throw new Error('Invalid message data');
        }

        const formData = new FormData();
        formData.append('mentorship_id', this.mentorshipId);
        formData.append('message_text', messageText);
        formData.append('csrf_token', csrfToken);

        const response = await fetch(`${window.SITE_URL}/messages/send_message.php`, {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Failed to send message');
        }

        return data;
    }

    // Show browser notification
    showNotification(message) {
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification('Nyumba Connect', {
                body: message,
                icon: `${window.SITE_URL}/assets/images/favicon.ico`,
                tag: 'nyumba-message' // Prevent duplicate notifications
            });
        }
    }

    // Request notification permission
    requestNotificationPermission() {
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }
    }

    // Update unread count in page title
    updateUnreadCount(count) {
        this.unreadCount = count;

        // Update page title
        const baseTitle = document.title.replace(/^\(\d+\)\s*/, '');
        document.title = count > 0 ? `(${count}) ${baseTitle}` : baseTitle;

        // Update favicon badge (if supported)
        this.updateFaviconBadge(count);
    }

    // Update favicon with unread count badge
    updateFaviconBadge(count) {
        // This is a simple implementation - could be enhanced with canvas drawing
        const link = document.querySelector("link[rel*='icon']") || document.createElement('link');
        link.type = 'image/x-icon';
        link.rel = 'shortcut icon';

        if (count > 0) {
            // Could implement favicon badge drawing here
            link.href = `${window.SITE_URL}/assets/images/favicon-unread.ico`;
        } else {
            link.href = `${window.SITE_URL}/assets/images/favicon.ico`;
        }

        document.getElementsByTagName('head')[0].appendChild(link);
    }

    // Scroll messages container to bottom
    scrollToBottom() {
        const container = document.getElementById('messages-container');
        if (container) {
            container.scrollTop = container.scrollHeight;
        }
    }

    // Format datetime for display
    formatDateTime(datetime) {
        const date = new Date(datetime);
        return date.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit'
        });
    }

    // Validate message text
    validateMessage(text) {
        const errors = [];

        if (!text || !text.trim()) {
            errors.push('Message cannot be empty');
        }

        if (text.length > 2000) {
            errors.push('Message is too long (maximum 2000 characters)');
        }

        return errors;
    }

    // Clean up resources
    destroy() {
        this.stopPolling();
        this.mentorshipId = null;
        this.currentUserId = null;
        this.lastMessageId = 0;
    }
}

// Utility functions for message formatting
const MessageUtils = {
    // Escape HTML to prevent XSS
    escapeHtml: function (text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    // Convert newlines to <br> tags
    nl2br: function (text) {
        return text.replace(/\n/g, '<br>');
    },

    // Truncate text with ellipsis
    truncate: function (text, length = 100) {
        if (text.length <= length) return text;
        return text.substring(0, length) + '...';
    },

    // Format message for display
    formatMessage: function (text) {
        return this.nl2br(this.escapeHtml(text));
    }
};

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { MessagingSystem, MessageUtils };
} else {
    window.MessagingSystem = MessagingSystem;
    window.MessageUtils = MessageUtils;
}