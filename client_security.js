/**
 * Client-Side Security Helper for Astraden
 * Provides CSRF token management, nonce handling, and DevTools protection
 */

class AstradenSecurity {
    constructor() {
        this.csrfToken = null;
        this.nonce = null;
        this.tokenExpiry = null;
        this.tokenRefreshInterval = null;
        
        // Initialize security
        this.init();
    }
    
    /**
     * Initialize security features
     */
    async init() {
        // Get initial tokens
        await this.refreshTokens();
        
        // Refresh tokens every 5 minutes
        this.tokenRefreshInterval = setInterval(() => {
            this.refreshTokens();
        }, 5 * 60 * 1000);
        
        // Setup DevTools protection
        this.setupDevToolsProtection();
        
        // Disable right-click
        this.disableRightClick();
        
        // Disable keyboard shortcuts
        this.disableKeyboardShortcuts();
    }
    
    /**
     * Get security tokens from server
     */
    async refreshTokens() {
        try {
            const response = await fetch('secure_api_base.php?get_tokens=1', {
                method: 'GET',
                credentials: 'same-origin'
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.success && data.tokens) {
                    this.csrfToken = data.tokens.csrf_token;
                    this.nonce = data.tokens.nonce;
                    this.tokenExpiry = Date.now() + (2 * 60 * 60 * 1000); // 2 hours
                }
            }
        } catch (error) {
            console.error('Failed to refresh security tokens:', error);
        }
    }
    
    /**
     * Get current CSRF token
     */
    getCSRFToken() {
        if (!this.csrfToken || Date.now() > this.tokenExpiry) {
            // Token expired, refresh
            this.refreshTokens();
        }
        return this.csrfToken;
    }
    
    /**
     * Get current nonce (generates new one for each request)
     */
    async getNonce() {
        // Nonces are single-use, so we need a new one for each request
        await this.refreshTokens();
        return this.nonce;
    }
    
    /**
     * Make secure API request
     */
    async secureRequest(url, options = {}) {
        // Get fresh nonce for this request
        const nonce = await this.getNonce();
        const csrfToken = this.getCSRFToken();
        
        // Prepare FormData or JSON
        let body;
        if (options.body instanceof FormData) {
            body = options.body;
            body.append('csrf_token', csrfToken);
            body.append('nonce', nonce);
        } else if (options.body) {
            const jsonBody = typeof options.body === 'string' ? JSON.parse(options.body) : options.body;
            jsonBody.csrf_token = csrfToken;
            jsonBody.nonce = nonce;
            body = JSON.stringify(jsonBody);
        } else {
            body = new FormData();
            body.append('csrf_token', csrfToken);
            body.append('nonce', nonce);
        }
        
        // Make request
        const response = await fetch(url, {
            ...options,
            method: options.method || 'POST',
            body: body,
            credentials: 'same-origin',
            headers: {
                ...options.headers,
                'X-CSRF-Token': csrfToken,
                'X-Nonce': nonce
            }
        });
        
        return response;
    }
    
    /**
     * Setup DevTools detection and protection
     */
    setupDevToolsProtection() {
        let devtools = {open: false, orientation: null};
        
        const detectDevTools = () => {
            const threshold = 160;
            const widthThreshold = window.outerWidth - window.innerWidth > threshold;
            const heightThreshold = window.outerHeight - window.innerHeight > threshold;
            
            if (widthThreshold || heightThreshold) {
                if (!devtools.open) {
                    devtools.open = true;
                    this.logSuspiciousActivity('devtools_opened');
                    
                    // Optionally redirect or show warning
                    // window.location.href = '/security-warning.php';
                }
            } else {
                devtools.open = false;
            }
        };
        
        // Check periodically
        setInterval(detectDevTools, 1000);
        
        // Also check on resize
        window.addEventListener('resize', detectDevTools);
        
        // Console detection (basic)
        const noop = () => {};
        const devtools = {
            open: false,
            orientation: null
        };
        
        setInterval(() => {
            if (window.outerHeight - window.innerHeight > 200 || 
                window.outerWidth - window.innerWidth > 200) {
                if (!devtools.open) {
                    devtools.open = true;
                    this.logSuspiciousActivity('devtools_detected');
                }
            } else {
                devtools.open = false;
            }
        }, 1000);
    }
    
    /**
     * Disable right-click context menu
     */
    disableRightClick() {
        document.addEventListener('contextmenu', (e) => {
            e.preventDefault();
            this.logSuspiciousActivity('right_click_attempt');
            return false;
        });
    }
    
    /**
     * Disable keyboard shortcuts that could be used for cheating
     */
    disableKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // F12 (DevTools)
            if (e.key === 'F12') {
                e.preventDefault();
                this.logSuspiciousActivity('f12_pressed');
                return false;
            }
            
            // Ctrl+Shift+I (DevTools)
            if (e.ctrlKey && e.shiftKey && e.key === 'I') {
                e.preventDefault();
                this.logSuspiciousActivity('devtools_shortcut');
                return false;
            }
            
            // Ctrl+Shift+J (Console)
            if (e.ctrlKey && e.shiftKey && e.key === 'J') {
                e.preventDefault();
                this.logSuspiciousActivity('console_shortcut');
                return false;
            }
            
            // Ctrl+Shift+C (Inspect)
            if (e.ctrlKey && e.shiftKey && e.key === 'C') {
                e.preventDefault();
                this.logSuspiciousActivity('inspect_shortcut');
                return false;
            }
            
            // Ctrl+U (View Source)
            if (e.ctrlKey && e.key === 'U') {
                e.preventDefault();
                this.logSuspiciousActivity('view_source_shortcut');
                return false;
            }
            
            // Ctrl+S (Save Page - could be used to inspect)
            if (e.ctrlKey && e.key === 'S' && e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA') {
                e.preventDefault();
                return false;
            }
        });
    }
    
    /**
     * Log suspicious activity to server
     */
    async logSuspiciousActivity(action) {
        try {
            await fetch('security_log.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: action,
                    timestamp: Date.now(),
                    user_agent: navigator.userAgent,
                    url: window.location.href
                }),
                credentials: 'same-origin'
            });
        } catch (error) {
            // Silently fail - don't alert attackers
            console.error('Failed to log security event');
        }
    }
    
    /**
     * Obfuscate score before sending (additional layer)
     * Note: This is NOT a security measure, just obfuscation
     * Real security is server-side validation
     */
    obfuscateScore(score) {
        // Simple XOR obfuscation (not secure, just obfuscation)
        const key = 0x5A5A5A5A;
        return (score ^ key).toString(36);
    }
    
    /**
     * Cleanup on page unload
     */
    destroy() {
        if (this.tokenRefreshInterval) {
            clearInterval(this.tokenRefreshInterval);
        }
    }
}

// Initialize global security instance
window.astradenSecurity = new AstradenSecurity();

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (window.astradenSecurity) {
        window.astradenSecurity.destroy();
    }
});

// Export for use in game files
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AstradenSecurity;
}

