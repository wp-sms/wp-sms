/**
 * WP-SMS Authentication Modal
 * 
 * Handles modal triggers and creates accessible modal overlays for authentication forms.
 */

(function() {
    'use strict';

    class WPSMSAuthModal {
            constructor() {
        console.log('WPSMS Auth Modal constructor called');
        
        this.modal = null;
        this.overlay = null;
        this.focusTrap = null;
        this.focusableElements = [];
        this.lastFocusedElement = null;
        
        this.init();
    }

        /**
         * Initialize modal functionality
         */
        init() {
            console.log('WPSMS Auth Modal init called');
            this.bindEvents();
            console.log('WPSMS Auth Modal events bound');
        }

        /**
         * Bind event listeners
         */
        bindEvents() {
                    // Listen for clicks on modal triggers
        document.addEventListener('click', (e) => {
            console.log('Click detected on:', e.target);
            console.log('Has data-wpsms-auth="open":', e.target.matches('[data-wpsms-auth="open"]'));
            
            if (e.target.matches('[data-wpsms-auth="open"]')) {
                console.log('Modal trigger clicked!');
                e.preventDefault();
                this.openModal(e.target);
            }
        });

            // Handle escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.modal) {
                    this.closeModal();
                }
            });
        }

        /**
         * Open authentication modal
         */
        openModal(trigger) {
            const mode = trigger.dataset.mode || 'auth';
            const methods = trigger.dataset.methods || 'password,otp,magic';
            const defaultTab = trigger.dataset.defaultTab || 'login';
            const redirect = trigger.dataset.redirect || '/';
            const enabledFields = this.getEnabledFields();

            // Store last focused element for restoration
            this.lastFocusedElement = document.activeElement;

            // Create modal overlay
            this.createModalOverlay();

            // Create modal content
            this.createModalContent(mode, methods, defaultTab, redirect, enabledFields);

            // Show modal
            this.showModal();

            // Initialize auth form
            this.initializeAuthForm();

            // Set up focus trap
            this.setupFocusTrap();

            // Dispatch event
            this.dispatchEvent('modal:open', { mode, methods, defaultTab, redirect });
        }

        /**
         * Create modal overlay
         */
        createModalOverlay() {
            // Create overlay
            this.overlay = document.createElement('div');
            this.overlay.className = 'wpsms-auth-modal-overlay';
            this.overlay.setAttribute('aria-hidden', 'true');
            this.overlay.setAttribute('role', 'presentation');

            // Create modal container
            this.modal = document.createElement('div');
            this.modal.className = 'wpsms-auth-modal';
            this.modal.setAttribute('role', 'dialog');
            this.modal.setAttribute('aria-modal', 'true');
            this.modal.setAttribute('aria-labelledby', 'wpsms-auth-modal-title');

            // Add to DOM
            this.overlay.appendChild(this.modal);
            document.body.appendChild(this.overlay);
        }

        /**
         * Create modal content
         */
        createModalContent(mode, methods, defaultTab, redirect, enabledFields) {
            const methodsArray = methods.split(',').map(m => m.trim());
            
            const props = {
                mode,
                redirect,
                methods: methodsArray,
                tabs: mode === 'auth',
                default_tab: defaultTab,
                fields: ['username', 'email', 'phone', 'password'],
                class: 'wpsms-auth-modal__form',
                restBase: (window.wpsmsAuthData && window.wpsmsAuthData.restUrl) || '/wp-json/wpsms/v1',
                nonces: {
                    auth: this.getNonce()
                },
                globals: {
                    enabledFields
                }
            };

            this.modal.innerHTML = `
                <div class="wpsms-auth-modal__header">
                    <h2 id="wpsms-auth-modal-title" class="wpsms-auth-modal__title">
                        ${this.getModalTitle(mode)}
                    </h2>
                    <button type="button" class="wpsms-auth-modal__close" aria-label="Close modal">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="wpsms-auth-modal__body">
                    <div class="wpsms-auth" data-props='${JSON.stringify(props)}'></div>
                </div>
            `;

            // Bind close button
            const closeBtn = this.modal.querySelector('.wpsms-auth-modal__close');
            closeBtn.addEventListener('click', () => this.closeModal());
        }

        /**
         * Get modal title based on mode
         */
        getModalTitle(mode) {
            const titles = {
                login: 'Sign In',
                register: 'Create Account',
                auth: 'Sign In or Create Account'
            };
            return titles[mode] || titles.auth;
        }

        /**
         * Get enabled fields from global settings
         */
        getEnabledFields() {
            // Default to all enabled - can be filtered by settings
            return {
                username: true,
                email: true,
                phone: true,
                password: true
            };
        }

            /**
     * Get nonce for authentication
     */
    getNonce() {
        // Try to get from localized data first
        if (window.wpsmsAuthData && window.wpsmsAuthData.nonce) {
            return window.wpsmsAuthData.nonce;
        }
        
        // Try to get from wpApiSettings as fallback
        if (window.wpApiSettings && window.wpApiSettings.nonce) {
            return window.wpApiSettings.nonce;
        }
        
        // Fallback to data attribute if available
        const nonceEl = document.querySelector('meta[name="wpsms-auth-nonce"]');
        if (nonceEl) {
            return nonceEl.getAttribute('content');
        }
        
        // Return empty string if no nonce available
        return '';
    }

        /**
         * Show modal
         */
        showModal() {
            // Add classes for animation
            this.overlay.classList.add('wpsms-auth-modal-overlay--visible');
            this.modal.classList.add('wpsms-auth-modal--visible');
            
            // Set aria-hidden to false
            this.overlay.setAttribute('aria-hidden', 'false');
            
            // Focus modal
            this.modal.focus();
        }

        /**
         * Close modal
         */
        closeModal() {
            if (!this.modal) return;

            // Remove focus trap
            this.removeFocusTrap();

            // Hide modal
            this.overlay.classList.remove('wpsms-auth-modal-overlay--visible');
            this.modal.classList.remove('wpsms-auth-modal--visible');

            // Set aria-hidden to true
            this.overlay.setAttribute('aria-hidden', 'true');

            // Remove from DOM after animation
            setTimeout(() => {
                if (this.overlay && this.overlay.parentNode) {
                    this.overlay.parentNode.removeChild(this.overlay);
                }
                this.overlay = null;
                this.modal = null;
            }, 300);

            // Restore focus
            if (this.lastFocusedElement && this.lastFocusedElement.focus) {
                this.lastFocusedElement.focus();
            }

            // Dispatch event
            this.dispatchEvent('modal:close');
        }

        /**
         * Initialize authentication form
         */
        initializeAuthForm() {
            if (!this.modal) return;

            const authContainer = this.modal.querySelector('.wpsms-auth');
            if (authContainer && window.WPSMSAuthForm) {
                new window.WPSMSAuthForm(authContainer);
            }
        }

        /**
         * Set up focus trap for accessibility
         */
        setupFocusTrap() {
            if (!this.modal) return;

            // Get all focusable elements
            this.focusableElements = this.modal.querySelectorAll(
                'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
            );

            if (this.focusableElements.length === 0) return;

            // Set up focus trap
            this.focusTrap = (e) => {
                if (e.key !== 'Tab') return;

                const firstElement = this.focusableElements[0];
                const lastElement = this.focusableElements[this.focusableElements.length - 1];

                if (e.shiftKey) {
                    if (document.activeElement === firstElement) {
                        e.preventDefault();
                        lastElement.focus();
                    }
                } else {
                    if (document.activeElement === lastElement) {
                        e.preventDefault();
                        firstElement.focus();
                    }
                }
            };

            this.modal.addEventListener('keydown', this.focusTrap);
        }

        /**
         * Remove focus trap
         */
        removeFocusTrap() {
            if (this.focusTrap && this.modal) {
                this.modal.removeEventListener('keydown', this.focusTrap);
                this.focusTrap = null;
            }
        }

        /**
         * Dispatch custom events
         */
        dispatchEvent(type, data = {}) {
            const event = new CustomEvent(`wpsms:auth:${type}`, {
                detail: { ...data, modal: this.modal },
                bubbles: true
            });
            document.dispatchEvent(event);
        }
    }

    // Initialize modal functionality
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM Content Loaded - Initializing WPSMS Auth Modal');
        
        // Only initialize if not already done
        if (!window.wpsmsAuthModal) {
            console.log('Creating new WPSMS Auth Modal instance');
            window.wpsmsAuthModal = new WPSMSAuthModal();
        } else {
            console.log('WPSMS Auth Modal already exists');
        }
    });

    // Make class available globally
    window.WPSMSAuthModal = WPSMSAuthModal;

})();
