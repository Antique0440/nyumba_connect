/**
 * Main JavaScript for Nyumba Connect Platform
 * Contains shared functionality and utilities
 */

document.addEventListener('DOMContentLoaded', function () {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initialize popovers
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    alerts.forEach(function (alert) {
        setTimeout(function () {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });

    // Confirm delete actions
    const deleteButtons = document.querySelectorAll('[data-confirm-delete]');
    deleteButtons.forEach(function (button) {
        button.addEventListener('click', function (e) {
            const message = this.getAttribute('data-confirm-delete') || 'Are you sure you want to delete this item?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });

    // Character counters for textareas
    const textareas = document.querySelectorAll('textarea[maxlength]');
    textareas.forEach(function (textarea) {
        const maxLength = textarea.getAttribute('maxlength');
        const counter = document.createElement('div');
        counter.className = 'form-text text-end';
        counter.innerHTML = `<span class="char-count">0</span>/${maxLength} characters`;

        textarea.parentNode.appendChild(counter);

        const charCountSpan = counter.querySelector('.char-count');

        textarea.addEventListener('input', function () {
            const currentLength = this.value.length;
            charCountSpan.textContent = currentLength;

            if (currentLength > maxLength * 0.9) {
                charCountSpan.classList.add('text-warning');
            } else {
                charCountSpan.classList.remove('text-warning');
            }

            if (currentLength >= maxLength) {
                charCountSpan.classList.add('text-danger');
            } else {
                charCountSpan.classList.remove('text-danger');
            }
        });

        // Trigger initial count
        textarea.dispatchEvent(new Event('input'));
    });

    // Auto-resize textareas
    const autoResizeTextareas = document.querySelectorAll('textarea.auto-resize');
    autoResizeTextareas.forEach(function (textarea) {
        textarea.addEventListener('input', function () {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });

        // Initial resize
        textarea.style.height = textarea.scrollHeight + 'px';
    });

    // Enhanced form validation
    const forms = document.querySelectorAll('.needs-validation');
    forms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();

                // Focus on first invalid field
                const firstInvalid = form.querySelector(':invalid');
                if (firstInvalid) {
                    firstInvalid.focus();
                }

                // Show custom validation messages
                showValidationErrors(form);
            }
            form.classList.add('was-validated');
        });

        // Real-time validation
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(function (input) {
            input.addEventListener('blur', function () {
                validateField(this);
            });

            input.addEventListener('input', function () {
                if (this.classList.contains('is-invalid')) {
                    validateField(this);
                }
            });
        });
    });

    // Custom validation functions
    function validateField(field) {
        const isValid = field.checkValidity();
        field.classList.toggle('is-valid', isValid);
        field.classList.toggle('is-invalid', !isValid);

        // Custom validation messages
        const feedback = field.parentNode.querySelector('.invalid-feedback');
        if (feedback && !isValid) {
            if (field.validity.valueMissing) {
                feedback.textContent = `${getFieldLabel(field)} is required.`;
            } else if (field.validity.typeMismatch) {
                feedback.textContent = `Please enter a valid ${field.type}.`;
            } else if (field.validity.patternMismatch) {
                feedback.textContent = field.getAttribute('data-pattern-message') || 'Invalid format.';
            } else if (field.validity.tooShort) {
                feedback.textContent = `Minimum ${field.minLength} characters required.`;
            } else if (field.validity.tooLong) {
                feedback.textContent = `Maximum ${field.maxLength} characters allowed.`;
            }
        }
    }

    function showValidationErrors(form) {
        const invalidFields = form.querySelectorAll(':invalid');
        invalidFields.forEach(validateField);
    }

    function getFieldLabel(field) {
        const label = form.querySelector(`label[for="${field.id}"]`);
        return label ? label.textContent.replace('*', '').trim() : field.name || 'Field';
    }

    // File upload preview
    const fileInputs = document.querySelectorAll('input[type="file"][data-preview]');
    fileInputs.forEach(function (input) {
        input.addEventListener('change', function (e) {
            const file = e.target.files[0];
            const previewId = this.getAttribute('data-preview');
            const preview = document.getElementById(previewId);

            if (file && preview) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    if (file.type.startsWith('image/')) {
                        preview.innerHTML = `<img src="${e.target.result}" class="img-thumbnail" style="max-width: 200px;">`;
                    } else {
                        preview.innerHTML = `<div class="alert alert-info">File selected: ${file.name}</div>`;
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    });

    // Loading states for buttons
    const loadingButtons = document.querySelectorAll('[data-loading-text]');
    loadingButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const loadingText = this.getAttribute('data-loading-text');
            const originalText = this.innerHTML;

            this.innerHTML = loadingText;
            this.disabled = true;

            // Re-enable after form submission or timeout
            setTimeout(() => {
                this.innerHTML = originalText;
                this.disabled = false;
            }, 3000);
        });
    });

    // Smooth scrolling for anchor links
    const anchorLinks = document.querySelectorAll('a[href^="#"]');
    anchorLinks.forEach(function (link) {
        link.addEventListener('click', function (e) {
            const targetId = this.getAttribute('href').substring(1);
            const targetElement = document.getElementById(targetId);

            if (targetElement) {
                e.preventDefault();
                targetElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Copy to clipboard functionality
    const copyButtons = document.querySelectorAll('[data-copy]');
    copyButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const textToCopy = this.getAttribute('data-copy');

            navigator.clipboard.writeText(textToCopy).then(function () {
                // Show success feedback
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="bi bi-check"></i> Copied!';
                button.classList.add('btn-success');

                setTimeout(() => {
                    button.innerHTML = originalText;
                    button.classList.remove('btn-success');
                }, 2000);
            }).catch(function () {
                alert('Failed to copy to clipboard');
            });
        });
    });

    // Enhanced search functionality
    const searchInputs = document.querySelectorAll('[data-search-target]');
    searchInputs.forEach(function (input) {
        const targetSelector = input.getAttribute('data-search-target');
        const searchTargets = document.querySelectorAll(targetSelector);

        input.addEventListener('input', NyumbaConnect.debounce(function () {
            const searchTerm = this.value.toLowerCase();

            searchTargets.forEach(function (target) {
                const text = target.textContent.toLowerCase();
                const matches = text.includes(searchTerm);
                target.style.display = matches ? '' : 'none';

                // Add highlight to matching text
                if (matches && searchTerm) {
                    highlightSearchTerm(target, searchTerm);
                } else {
                    removeHighlight(target);
                }
            });
        }, 300));
    });

    // Password strength indicator
    const passwordInputs = document.querySelectorAll('input[type="password"][data-strength]');
    passwordInputs.forEach(function (input) {
        const strengthIndicator = createPasswordStrengthIndicator(input);

        input.addEventListener('input', function () {
            const strength = calculatePasswordStrength(this.value);
            updatePasswordStrengthIndicator(strengthIndicator, strength);
        });
    });

    // Form progress indicator
    const progressForms = document.querySelectorAll('form[data-progress]');
    progressForms.forEach(function (form) {
        const progressBar = createProgressBar(form);
        const requiredFields = form.querySelectorAll('[required]');

        requiredFields.forEach(function (field) {
            field.addEventListener('input', function () {
                updateFormProgress(form, progressBar, requiredFields);
            });
        });
    });

    // Start real-time features if user is logged in
    if (document.body.classList.contains('logged-in')) {
        NyumbaConnect.startRealTime();
    }
});

// Helper functions for enhanced functionality
function highlightSearchTerm(element, term) {
    const originalText = element.getAttribute('data-original-text') || element.innerHTML;
    if (!element.getAttribute('data-original-text')) {
        element.setAttribute('data-original-text', originalText);
    }

    const regex = new RegExp(`(${term})`, 'gi');
    const highlightedText = originalText.replace(regex, '<mark>$1</mark>');
    element.innerHTML = highlightedText;
}

function removeHighlight(element) {
    const originalText = element.getAttribute('data-original-text');
    if (originalText) {
        element.innerHTML = originalText;
    }
}

function createPasswordStrengthIndicator(input) {
    const indicator = document.createElement('div');
    indicator.className = 'password-strength-indicator mt-2';
    indicator.innerHTML = `
        <div class="progress" style="height: 5px;">
            <div class="progress-bar" role="progressbar" style="width: 0%"></div>
        </div>
        <small class="form-text text-muted">Password strength: <span class="strength-text">Weak</span></small>
    `;

    input.parentNode.appendChild(indicator);
    return indicator;
}

function calculatePasswordStrength(password) {
    let score = 0;

    if (password.length >= 8) score += 25;
    if (password.length >= 12) score += 25;
    if (/[a-z]/.test(password)) score += 10;
    if (/[A-Z]/.test(password)) score += 10;
    if (/[0-9]/.test(password)) score += 10;
    if (/[^A-Za-z0-9]/.test(password)) score += 20;

    return Math.min(score, 100);
}

function updatePasswordStrengthIndicator(indicator, strength) {
    const progressBar = indicator.querySelector('.progress-bar');
    const strengthText = indicator.querySelector('.strength-text');

    progressBar.style.width = strength + '%';

    if (strength < 30) {
        progressBar.className = 'progress-bar bg-danger';
        strengthText.textContent = 'Weak';
    } else if (strength < 60) {
        progressBar.className = 'progress-bar bg-warning';
        strengthText.textContent = 'Fair';
    } else if (strength < 80) {
        progressBar.className = 'progress-bar bg-info';
        strengthText.textContent = 'Good';
    } else {
        progressBar.className = 'progress-bar bg-success';
        strengthText.textContent = 'Strong';
    }
}

function createProgressBar(form) {
    const progressBar = document.createElement('div');
    progressBar.className = 'form-progress mb-3';
    progressBar.innerHTML = `
        <div class="d-flex justify-content-between align-items-center mb-2">
            <small class="text-muted">Form completion</small>
            <small class="text-muted"><span class="progress-percentage">0</span>%</small>
        </div>
        <div class="progress" style="height: 5px;">
            <div class="progress-bar bg-primary" role="progressbar" style="width: 0%"></div>
        </div>
    `;

    form.insertBefore(progressBar, form.firstChild);
    return progressBar;
}

function updateFormProgress(form, progressBar, requiredFields) {
    const filledFields = Array.from(requiredFields).filter(field => {
        return field.value.trim() !== '' && field.checkValidity();
    });

    const percentage = Math.round((filledFields.length / requiredFields.length) * 100);

    const progressBarElement = progressBar.querySelector('.progress-bar');
    const percentageElement = progressBar.querySelector('.progress-percentage');

    progressBarElement.style.width = percentage + '%';
    percentageElement.textContent = percentage;
}

// AJAX functionality
function makeAjaxRequest(url, options = {}) {
    const defaults = {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    };

    const config = { ...defaults, ...options };

    return fetch(url, config)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .catch(error => {
            console.error('AJAX request failed:', error);
            throw error;
        });
}

// Real-time notifications
let notificationInterval;

function startNotificationPolling() {
    if (notificationInterval) {
        clearInterval(notificationInterval);
    }

    notificationInterval = setInterval(() => {
        checkForNotifications();
    }, 30000); // Check every 30 seconds
}

function checkForNotifications() {
    makeAjaxRequest('api/notifications.php')
        .then(data => {
            if (data.notifications && data.notifications.length > 0) {
                updateNotificationBadge(data.notifications.length);
                showNotificationToast(data.notifications[0]);
            }
        })
        .catch(error => {
            console.error('Failed to check notifications:', error);
        });
}

function updateNotificationBadge(count) {
    const badge = document.querySelector('.notification-badge');
    if (badge) {
        badge.textContent = count;
        badge.style.display = count > 0 ? 'inline' : 'none';
    }
}

function showNotificationToast(notification) {
    const toastContainer = document.getElementById('toast-container') || createToastContainer();

    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <div class="toast-header">
            <strong class="me-auto">${notification.title}</strong>
            <small class="text-muted">now</small>
            <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-body">
            ${notification.message}
        </div>
    `;

    toastContainer.appendChild(toast);
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();

    // Remove toast after it's hidden
    toast.addEventListener('hidden.bs.toast', () => {
        toast.remove();
    });
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toast-container';
    container.className = 'toast-container position-fixed top-0 end-0 p-3';
    container.style.zIndex = '1055';
    document.body.appendChild(container);
    return container;
}

// Auto-save functionality for forms
function enableAutoSave(formSelector, saveUrl, interval = 30000) {
    const form = document.querySelector(formSelector);
    if (!form) return;

    let autoSaveTimeout;
    let hasChanges = false;

    const inputs = form.querySelectorAll('input, textarea, select');
    inputs.forEach(input => {
        input.addEventListener('input', () => {
            hasChanges = true;
            clearTimeout(autoSaveTimeout);
            autoSaveTimeout = setTimeout(() => {
                if (hasChanges) {
                    autoSaveForm(form, saveUrl);
                }
            }, interval);
        });
    });
}

function autoSaveForm(form, saveUrl) {
    const formData = new FormData(form);

    makeAjaxRequest(saveUrl, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(data => {
            if (data.success) {
                showAutoSaveIndicator('saved');
                hasChanges = false;
            }
        })
        .catch(error => {
            showAutoSaveIndicator('error');
            console.error('Auto-save failed:', error);
        });
}

function showAutoSaveIndicator(status) {
    let indicator = document.getElementById('autosave-indicator');
    if (!indicator) {
        indicator = document.createElement('div');
        indicator.id = 'autosave-indicator';
        indicator.className = 'position-fixed bottom-0 end-0 m-3 p-2 rounded';
        document.body.appendChild(indicator);
    }

    if (status === 'saved') {
        indicator.className = 'position-fixed bottom-0 end-0 m-3 p-2 rounded bg-success text-white';
        indicator.textContent = 'Changes saved';
    } else if (status === 'error') {
        indicator.className = 'position-fixed bottom-0 end-0 m-3 p-2 rounded bg-danger text-white';
        indicator.textContent = 'Save failed';
    }

    indicator.style.display = 'block';
    setTimeout(() => {
        indicator.style.display = 'none';
    }, 3000);
}

// Utility functions
window.NyumbaConnect = {
    // Show loading spinner
    showLoading: function (element) {
        if (typeof element === 'string') {
            element = document.getElementById(element);
        }
        if (element) {
            element.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';
        }
    },

    // Hide loading spinner
    hideLoading: function (element) {
        if (typeof element === 'string') {
            element = document.getElementById(element);
        }
        if (element) {
            element.innerHTML = '';
        }
    },

    // Format file size
    formatFileSize: function (bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    },

    // Validate email
    validateEmail: function (email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    },

    // Debounce function
    debounce: function (func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    // AJAX helper
    ajax: makeAjaxRequest,

    // Start real-time features
    startRealTime: function () {
        startNotificationPolling();
    },

    // Enable auto-save for forms
    enableAutoSave: enableAutoSave,

    // Show toast notification
    showToast: function (title, message, type = 'info') {
        showNotificationToast({ title, message, type });
    }
};