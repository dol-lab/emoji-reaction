/**
 * Handle success and error messages for emoji reaction chart operations
 */
(function() {
    'use strict';

    // Check for messages in URL parameters
    function checkForMessages() {
        const urlParams = new URLSearchParams(window.location.search);

        if (urlParams.get('emoji_chart_added') === '1') {
            showMessage('Emoji reaction chart has been added to this post!', 'success');
            // Clean up URL
            removeUrlParameter('emoji_chart_added');
        }

        if (urlParams.get('emoji_chart_exists') === '1') {
            showMessage('An emoji reaction chart already exists on this post.', 'info');
            // Clean up URL
            removeUrlParameter('emoji_chart_exists');
        }
    }

    // Show message to user
    function showMessage(text, type) {
        const messageDiv = document.createElement('div');
        messageDiv.className = 'emoji-chart-message emoji-chart-message-' + type;
        messageDiv.innerHTML = '<p>' + text + '</p>';
        messageDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 4px;
            z-index: 9999;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            font-size: 14px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 350px;
            transition: opacity 0.3s ease;
        `;

        if (type === 'success') {
            messageDiv.style.backgroundColor = '#d4edda';
            messageDiv.style.color = '#155724';
            messageDiv.style.border = '1px solid #c3e6cb';
        } else if (type === 'info') {
            messageDiv.style.backgroundColor = '#d1ecf1';
            messageDiv.style.color = '#0c5460';
            messageDiv.style.border = '1px solid #bee5eb';
        }

        document.body.appendChild(messageDiv);

        // Auto-remove after 5 seconds
        setTimeout(function() {
            messageDiv.style.opacity = '0';
            setTimeout(function() {
                if (messageDiv.parentNode) {
                    messageDiv.parentNode.removeChild(messageDiv);
                }
            }, 300);
        }, 5000);
    }

    // Remove URL parameter and update browser history
    function removeUrlParameter(param) {
        const url = new URL(window.location);
        url.searchParams.delete(param);
        window.history.replaceState({}, document.title, url.pathname + url.search);
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', checkForMessages);
    } else {
        checkForMessages();
    }
})();
