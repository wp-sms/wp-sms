/**
 * WP-SMS Authentication Form
 * 
 * Handles the main authentication UI including login, register, OTP, and magic link flows.
 */

(function() {
    'use strict';

    class WPSMSAuthForm {
        constructor(container) {
            this.container = container;
            this.props = this.parseProps();
            this.currentMethod = 'password';
            this.currentTab = this.props.default_tab || 'login';
            this.otpFlowId = null;
            this.magicFlowId = null;
            
            this.init();
        }

        /**
         * Parse data-props from container
         */
        parseProps() {
            try {
                const props = this.container.getAttribute('data-props');
                return props ? JSON.parse(props) : {};
            } catch (e) {
                console.error('Failed to parse auth props:', e);
                return {};
            }
        }

        /**
         * Initialize the form
         */
        init() {
            this.render();
            this.bindEvents();
            this.dispatchEvent('view');
        }

        /**
         * Render the authentication form
         */
        render() {
            const { tabs, methods, mode } = this.props;
            
            let html = '<div class="wpsms-auth-form">';
            
            // Render tabs if enabled
            if (tabs && mode === 'auth') {
                html += this.renderTabs();
            }
            
            // Render method switcher
            if (methods.length > 1) {
                html += this.renderMethodSwitcher();
            }
            
            // Render forms
            html += this.renderForms();
            
            // Render status area
            html += '<div class="wpsms-auth-form__status" aria-live="polite"></div>';
            
            html += '</div>';
            
            this.container.innerHTML = html;
        }

        /**
         * Render tabs for combined auth form
         */
        renderTabs() {
            const { currentTab } = this;
            return `
                <div class="wpsms-auth-form__tabs">
                    <button type="button" class="wpsms-auth-form__tab ${currentTab === 'login' ? 'active' : ''}" 
                            data-tab="login">
                        ${this.i18n('Sign In')}
                    </button>
                    <button type="button" class="wpsms-auth-form__tab ${currentTab === 'register' ? 'active' : ''}" 
                            data-tab="register">
                        ${this.i18n('Create Account')}
                    </button>
                </div>
            `;
        }

        /**
         * Render method switcher
         */
        renderMethodSwitcher() {
            const { methods, globals } = this.props;
            const availableMethods = methods.filter(method => {
                if (method === 'password') return true;
                if (method === 'otp') return globals.enabledFields.phone || globals.enabledFields.email;
                if (method === 'magic') return globals.enabledFields.email;
                return false;
            });

            if (availableMethods.length <= 1) return '';

            return `
                <div class="wpsms-auth-form__methods">
                    ${availableMethods.map(method => `
                        <button type="button" class="wpsms-auth-form__method ${method === this.currentMethod ? 'active' : ''}" 
                                data-method="${method}">
                            ${this.getMethodLabel(method)}
                        </button>
                    `).join('')}
                </div>
            `;
        }

        /**
         * Render all forms
         */
        renderForms() {
            const { currentMethod, currentTab } = this;
            let html = '<div class="wpsms-auth-form__forms">';
            
            // Password form
            if (this.props.methods.includes('password')) {
                html += this.renderPasswordForm();
            }
            
            // OTP form
            if (this.props.methods.includes('otp')) {
                html += this.renderOtpForm();
            }
            
            // Magic link form
            if (this.props.methods.includes('magic')) {
                html += this.renderMagicForm();
            }
            
            html += '</div>';
            return html;
        }

        /**
         * Render password-based authentication form
         */
        renderPasswordForm() {
            const { currentMethod, currentTab, props } = this;
            const isVisible = currentMethod === 'password';
            const { enabledFields } = props.globals;
            
            let html = `
                <form class="wpsms-auth-form__form wpsms-auth-form__form--password ${isVisible ? 'active' : ''}" 
                      data-method="password" style="display: ${isVisible ? 'block' : 'none'}">
            `;
            
            // Identifier field (username/email/phone)
            if (enabledFields.username || enabledFields.email || enabledFields.phone) {
                html += `
                    <div class="wpsms-auth-form__field">
                        <label for="identifier" class="wpsms-auth-form__label">
                            ${this.getIdentifierLabel()}
                        </label>
                        <input type="text" id="identifier" name="identifier" class="wpsms-auth-form__input" 
                               required autocomplete="username">
                    </div>
                `;
            }
            
            // Password field
            if (enabledFields.password) {
                html += `
                    <div class="wpsms-auth-form__field">
                        <label for="password" class="wpsms-auth-form__label">
                            ${this.i18n('Password')}
                        </label>
                        <input type="password" id="password" name="password" class="wpsms-auth-form__input" 
                               required autocomplete="current-password">
                    </div>
                `;
            }
            
            // Additional fields for registration
            if (currentTab === 'register') {
                if (enabledFields.username) {
                    html += `
                        <div class="wpsms-auth-form__field">
                            <label for="reg-username" class="wpsms-auth-form__label">
                                ${this.i18n('Username')}
                            </label>
                            <input type="text" id="reg-username" name="username" class="wpsms-auth-form__input" 
                                   autocomplete="username">
                        </div>
                    `;
                }
                
                if (enabledFields.email) {
                    html += `
                        <div class="wpsms-auth-form__field">
                            <label for="reg-email" class="wpsms-auth-form__label">
                                ${this.i18n('Email')}
                            </label>
                            <input type="email" id="reg-email" name="email" class="wpsms-auth-form__input" 
                                   required autocomplete="email">
                        </div>
                    `;
                }
                
                if (enabledFields.phone) {
                    html += `
                        <div class="wpsms-auth-form__field">
                            <label for="reg-phone" class="wpsms-auth-form__label">
                                ${this.i18n('Phone Number')}
                            </label>
                            <input type="tel" id="reg-phone" name="phone" class="wpsms-auth-form__input" 
                                   autocomplete="tel">
                        </div>
                    `;
                }
            }
            
            html += `
                <div class="wpsms-auth-form__actions">
                    <button type="submit" class="wpsms-auth-form__submit">
                        ${currentTab === 'login' ? this.i18n('Sign In') : this.i18n('Create Account')}
                    </button>
                </div>
            </form>
            `;
            
            return html;
        }

        /**
         * Render OTP form
         */
        renderOtpForm() {
            const { currentMethod, props } = this;
            const isVisible = currentMethod === 'otp';
            const { enabledFields } = props.globals;
            
            let html = `
                <form class="wpsms-auth-form__form wpsms-auth-form__form--otp ${isVisible ? 'active' : ''}" 
                      data-method="otp" style="display: ${isVisible ? 'block' : ''}">
            `;
            
            // Identifier field
            if (enabledFields.phone || enabledFields.email) {
                html += `
                    <div class="wpsms-auth-form__field">
                        <label for="otp-identifier" class="wpsms-auth-form__label">
                            ${enabledFields.phone && enabledFields.email ? this.i18n('Phone or Email') : 
                              enabledFields.phone ? this.i18n('Phone Number') : this.i18n('Email')}
                        </label>
                        <input type="text" id="otp-identifier" name="otp-identifier" class="wpsms-auth-form__input" 
                               required>
                    </div>
                `;
            }
            
            // OTP input (initially hidden)
            html += `
                <div class="wpsms-auth-form__otp-section" style="display: none;">
                    <div class="wpsms-auth-form__field">
                        <label class="wpsms-auth-form__label">${this.i18n('Enter 6-digit code')}</label>
                        <div class="wpsms-auth-form__otp-inputs">
                            ${Array(6).fill(0).map((_, i) => `
                                <input type="text" class="wpsms-auth-form__otp-input" maxlength="1" 
                                       data-index="${i}" inputmode="numeric" pattern="[0-9]">
                            `).join('')}
                        </div>
                    </div>
                    <div class="wpsms-auth-form__resend">
                        <button type="button" class="wpsms-auth-form__resend-btn" disabled>
                            ${this.i18n('Resend Code')} (<span class="countdown">60</span>s)
                        </button>
                    </div>
                </div>
            `;
            
            html += `
                <div class="wpsms-auth-form__actions">
                    <button type="submit" class="wpsms-auth-form__submit wpsms-auth-form__submit--send">
                        ${this.i18n('Send Code')}
                    </button>
                    <button type="submit" class="wpsms-auth-form__submit wpsms-auth-form__submit--verify" style="display: none;">
                        ${this.i18n('Verify Code')}
                    </button>
                </div>
            </form>
            `;
            
            return html;
        }

        /**
         * Render magic link form
         */
        renderMagicForm() {
            const { currentMethod, props } = this;
            const isVisible = currentMethod === 'magic';
            const { enabledFields } = props.globals;
            
            if (!enabledFields.email) return '';
            
            let html = `
                <form class="wpsms-auth-form__form wpsms-auth-form__form--magic ${isVisible ? 'active' : ''}" 
                      data-method="magic" style="display: ${isVisible ? 'block' : 'none'}">
                    <div class="wpsms-auth-form__field">
                        <label for="magic-email" class="wpsms-auth-form__label">
                            ${this.i18n('Email Address')}
                        </label>
                        <input type="email" id="magic-email" name="magic-email" class="wpsms-auth-form__input" 
                               required autocomplete="email">
                    </div>
                    
                    <div class="wpsms-auth-form__magic-status" style="display: none;">
                        <p class="wpsms-auth-form__message">
                            ${this.i18n('Check your email for a magic link to sign in.')}
                        </p>
                    </div>
                    
                    <div class="wpsms-auth-form__actions">
                        <button type="submit" class="wpsms-auth-form__submit">
                            ${this.i18n('Send Magic Link')}
                        </button>
                    </div>
                </form>
            `;
            
            return html;
        }

        /**
         * Bind event listeners
         */
        bindEvents() {
            // Tab switching
            this.container.addEventListener('click', (e) => {
                if (e.target.matches('[data-tab]')) {
                    this.switchTab(e.target.dataset.tab);
                }
                
                if (e.target.matches('[data-method]')) {
                    this.switchMethod(e.target.dataset.method);
                }
            });
            
            // Form submissions
            this.container.addEventListener('submit', (e) => {
                e.preventDefault();
                this.handleSubmit(e.target);
            });
            
            // OTP input handling
            this.container.addEventListener('input', (e) => {
                if (e.target.matches('.wpsms-auth-form__otp-input')) {
                    this.handleOtpInput(e.target);
                }
            });
            
            // Resend button
            this.container.addEventListener('click', (e) => {
                if (e.target.matches('.wpsms-auth-form__resend-btn')) {
                    this.handleResend();
                }
            });
        }

        /**
         * Switch between login/register tabs
         */
        switchTab(tab) {
            this.currentTab = tab;
            
            // Update tab buttons
            this.container.querySelectorAll('[data-tab]').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.tab === tab);
            });
            
            // Update form submit button text
            const submitBtn = this.container.querySelector('.wpsms-auth-form__submit');
            if (submitBtn) {
                submitBtn.textContent = tab === 'login' ? this.i18n('Sign In') : this.i18n('Create Account');
            }
        }

        /**
         * Switch between authentication methods
         */
        switchMethod(method) {
            this.currentMethod = method;
            
            // Update method buttons
            this.container.querySelectorAll('[data-method]').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.method === method);
            });
            
            // Show/hide forms
            this.container.querySelectorAll('[data-method]').forEach(form => {
                form.style.display = form.dataset.method === method ? 'block' : 'none';
            });
        }

        /**
         * Handle form submission
         */
        async handleSubmit(form) {
            const method = form.dataset.method;
            const submitBtn = form.querySelector('.wpsms-auth-form__submit');
            const originalText = submitBtn.textContent;
            
            // Disable submit button
            submitBtn.disabled = true;
            submitBtn.textContent = this.i18n('Processing...');
            
            try {
                let response;
                
                switch (method) {
                    case 'password':
                        response = await this.handlePasswordAuth(form);
                        break;
                    case 'otp':
                        response = await this.handleOtpAuth(form);
                        break;
                    case 'magic':
                        response = await this.handleMagicAuth(form);
                        break;
                    default:
                        throw new Error('Unknown authentication method');
                }
                
                if (response.ok) {
                    this.handleSuccess(response);
                } else {
                    this.handleError(response);
                }
                
            } catch (error) {
                console.error('Authentication error:', error);
                this.handleError({
                    ok: false,
                    message: error.message || this.i18n('An unexpected error occurred.'),
                    code: 'error'
                });
            } finally {
                // Re-enable submit button
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        }

        /**
         * Handle password-based authentication
         */
        async handlePasswordAuth(form) {
            const formData = new FormData(form);
            const data = {
                identifier: formData.get('identifier'),
                password: formData.get('password'),
                nonce: this.props.nonces.auth
            };
            
            // Add registration fields if needed
            if (this.currentTab === 'register') {
                if (formData.get('username')) data.username = formData.get('username');
                if (formData.get('email')) data.email = formData.get('email');
                if (formData.get('phone')) data.phone = formData.get('phone');
            }
            
            const endpoint = this.currentTab === 'login' ? 'auth/login' : 'auth/register';
            
            return await this.apiCall(endpoint, data);
        }

        /**
         * Handle OTP authentication
         */
        async handleOtpAuth(form) {
            const formData = new FormData(form);
            const identifier = formData.get('otp-identifier');
            
            // Check if we're in send or verify mode
            const otpSection = form.querySelector('.wpsms-auth-form__otp-section');
            const isVerifyMode = otpSection.style.display !== 'none';
            
            if (!isVerifyMode) {
                // Send OTP
                const response = await this.apiCall('otp/send', {
                    identifier,
                    nonce: this.props.nonces.auth
                });
                
                if (response.ok) {
                    this.otpFlowId = response.flow_id;
                    this.showOtpInput();
                    this.startResendCountdown();
                }
                
                return response;
            } else {
                // Verify OTP
                const otpCode = Array.from(form.querySelectorAll('.wpsms-auth-form__otp-input'))
                    .map(input => input.value)
                    .join('');
                
                return await this.apiCall('otp/verify', {
                    flow_id: this.otpFlowId,
                    code: otpCode,
                    nonce: this.props.nonces.auth
                });
            }
        }

        /**
         * Handle magic link authentication
         */
        async handleMagicAuth(form) {
            const formData = new FormData(form);
            const email = formData.get('magic-email');
            
            const response = await this.apiCall('magic/init', {
                identifier: email,
                nonce: this.props.nonces.auth
            });
            
            if (response.ok) {
                this.magicFlowId = response.flow_id;
                this.showMagicStatus();
            }
            
            return response;
        }

        /**
         * Show OTP input section
         */
        showOtpInput() {
            const form = this.container.querySelector('[data-method="otp"]');
            const otpSection = form.querySelector('.wpsms-auth-form__otp-section');
            const sendBtn = form.querySelector('.wpsms-auth-form__submit--send');
            const verifyBtn = form.querySelector('.wpsms-auth-form__submit--verify');
            
            otpSection.style.display = 'block';
            sendBtn.style.display = 'none';
            verifyBtn.style.display = 'block';
            
            // Focus first OTP input
            const firstInput = otpSection.querySelector('.wpsms-auth-form__otp-input');
            if (firstInput) firstInput.focus();
        }

        /**
         * Show magic link status
         */
        showMagicStatus() {
            const form = this.container.querySelector('[data-method="magic"]');
            const statusSection = form.querySelector('.wpsms-auth-form__magic-status');
            const submitBtn = form.querySelector('.wpsms-auth-form__submit');
            
            statusSection.style.display = 'block';
            submitBtn.style.display = 'none';
        }

        /**
         * Start resend countdown
         */
        startResendCountdown() {
            const resendBtn = this.container.querySelector('.wpsms-auth-form__resend-btn');
            const countdown = resendBtn.querySelector('.countdown');
            let seconds = 60;
            
            resendBtn.disabled = true;
            
            const timer = setInterval(() => {
                seconds--;
                countdown.textContent = seconds;
                
                if (seconds <= 0) {
                    clearInterval(timer);
                    resendBtn.disabled = false;
                    countdown.textContent = '60';
                }
            }, 1000);
        }

        /**
         * Handle OTP input changes
         */
        handleOtpInput(input) {
            const index = parseInt(input.dataset.index);
            const value = input.value;
            
            // Auto-advance to next input
            if (value && index < 5) {
                const nextInput = this.container.querySelector(`[data-index="${index + 1}"]`);
                if (nextInput) nextInput.focus();
            }
            
            // Auto-advance to previous input on backspace
            if (!value && index > 0) {
                const prevInput = this.container.querySelector(`[data-index="${index - 1}"]`);
                if (prevInput) prevInput.focus();
            }
        }

        /**
         * Handle resend request
         */
        async handleResend() {
            const form = this.container.querySelector('[data-method="otp"]');
            const identifier = form.querySelector('#otp-identifier').value;
            
            if (!identifier) {
                this.showStatus(this.i18n('Please enter your phone number or email first.'), 'error');
                return;
            }
            
            try {
                const response = await this.apiCall('otp/send', {
                    identifier,
                    nonce: this.props.nonces.auth
                });
                
                if (response.ok) {
                    this.showStatus(this.i18n('Code resent successfully.'), 'success');
                    this.startResendCountdown();
                } else {
                    this.showStatus(response.message, 'error');
                }
            } catch (error) {
                this.showStatus(this.i18n('Failed to resend code.'), 'error');
            }
        }

        /**
         * Make API call
         */
        async apiCall(endpoint, data) {
            const url = `${this.props.restBase}/${endpoint}`;
            
            // Get nonce from multiple sources
            let nonce = this.props.nonces?.auth;
            if (!nonce && window.wpsmsAuthData && window.wpsmsAuthData.nonce) {
                nonce = window.wpsmsAuthData.nonce;
            } else if (!nonce && window.wpApiSettings && window.wpApiSettings.nonce) {
                nonce = window.wpApiSettings.nonce;
            }
            
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce || ''
                },
                body: JSON.stringify(data)
            });
            
            return await response.json();
        }

        /**
         * Handle successful authentication
         */
        handleSuccess(response) {
            this.dispatchEvent('success', response);
            this.showStatus(response.message || this.i18n('Authentication successful!'), 'success');
            
            // Redirect after a short delay
            setTimeout(() => {
                window.location.href = this.props.redirect || '/';
            }, 1500);
        }

        /**
         * Handle authentication error
         */
        handleError(response) {
            this.dispatchEvent('error', response);
            this.showStatus(response.message || this.i18n('Authentication failed.'), 'error');
            
            // Show field-specific errors if available
            if (response.errors) {
                this.showFieldErrors(response.errors);
            }
        }

        /**
         * Show status message
         */
        showStatus(message, type) {
            const statusEl = this.container.querySelector('.wpsms-auth-form__status');
            if (statusEl) {
                statusEl.innerHTML = `<div class="wpsms-auth-form__message wpsms-auth-form__message--${type}">${message}</div>`;
                statusEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }

        /**
         * Show field-specific errors
         */
        showFieldErrors(errors) {
            Object.entries(errors).forEach(([field, error]) => {
                const input = this.container.querySelector(`[name="${field}"]`);
                if (input) {
                    input.classList.add('error');
                    input.setAttribute('aria-describedby', `error-${field}`);
                    
                    // Add error message below input
                    const errorEl = document.createElement('div');
                    errorEl.id = `error-${field}`;
                    errorEl.className = 'wpsms-auth-form__field-error';
                    errorEl.textContent = error;
                    input.parentNode.appendChild(errorEl);
                }
            });
        }

        /**
         * Dispatch custom events
         */
        dispatchEvent(type, data = {}) {
            const event = new CustomEvent(`wpsms:auth:${type}`, {
                detail: { ...data, container: this.container },
                bubbles: true
            });
            this.container.dispatchEvent(event);
        }

        /**
         * Get method label
         */
        getMethodLabel(method) {
            const labels = {
                password: this.i18n('Password'),
                otp: this.i18n('SMS/Email Code'),
                magic: this.i18n('Magic Link')
            };
            return labels[method] || method;
        }

        /**
         * Get identifier label based on enabled fields
         */
        getIdentifierLabel() {
            const { enabledFields } = this.props.globals;
            
            if (enabledFields.username && enabledFields.email && enabledFields.phone) {
                return this.i18n('Username, Email, or Phone');
            } else if (enabledFields.username && enabledFields.email) {
                return this.i18n('Username or Email');
            } else if (enabledFields.username && enabledFields.phone) {
                return this.i18n('Username or Phone');
            } else if (enabledFields.email && enabledFields.phone) {
                return this.i18n('Email or Phone');
            } else if (enabledFields.username) {
                return this.i18n('Username');
            } else if (enabledFields.email) {
                return this.i18n('Email');
            } else if (enabledFields.phone) {
                return this.i18n('Phone Number');
            }
            
            return this.i18n('Identifier');
        }

        /**
         * Internationalization helper
         */
        i18n(text) {
            // In a real implementation, this would use wp.i18n.__()
            return text;
        }
    }

    // Initialize all auth forms on DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        const containers = document.querySelectorAll('.wpsms-auth');
        containers.forEach(container => {
            // Check if already initialized
            if (!container.hasAttribute('data-wpsms-auth-initialized')) {
                container.setAttribute('data-wpsms-auth-initialized', 'true');
                new WPSMSAuthForm(container);
            }
        });
    });

    // Make class available globally for modal usage
    window.WPSMSAuthForm = WPSMSAuthForm;

})();
